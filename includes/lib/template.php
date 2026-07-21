<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Markdown rendering
// ---------------------------------------------------------------------------

function render_markdown(string $markdown, array $context = []): string
{
    $markdown = filter_content($markdown, $context);
    $parsedown = get_markdown_parser();

    $html = $parsedown->text($markdown);
    $html = apply_filter('on_render_markdown', $html, $context);
    $html = add_lazy_loading_to_images($html);

    return restore_private_use_emoji($html);
}

function restore_private_use_emoji(string $html): string
{
    if (!preg_match('/[\x{F000}-\x{F8FF}]/u', $html)) {
        return $html;
    }

    return preg_replace_callback('/[\x{F000}-\x{F8FF}]/u', static function (array $match): string {
        $codepoint = mb_ord($match[0], 'UTF-8');
        return mb_chr($codepoint + 0x10000, 'UTF-8');
    }, $html) ?? $html;
}

function add_lazy_loading_to_images(string $html): string
{
    return preg_replace_callback('/<img\s+([^>]+)>/i', function (array $matches): string {
        $attributes = $matches[1];
        if (preg_match('/\bloading\s*=/i', $attributes)) {
            return $matches[0];
        }
        return '<img loading="lazy" ' . $attributes . '>';
    }, $html) ?? $html;
}

function get_markdown_parser(): object
{
    static $parsedown = null;
    if ($parsedown !== null) {
        return $parsedown;
    }

    require_once PUREBLOG_BASE_PATH . '/lib/Parsedown.php';
    if (is_file(PUREBLOG_BASE_PATH . '/lib/ParsedownExtra.php')) {
        require_once PUREBLOG_BASE_PATH . '/lib/ParsedownExtra.php';
        if (is_file(PUREBLOG_BASE_PATH . '/lib/ParsedownPureblog.php')) {
            require_once PUREBLOG_BASE_PATH . '/lib/ParsedownPureblog.php';
        }
    }

    if (class_exists('ParsedownPureblog')) {
        $parsedown = new ParsedownPureblog();
    } elseif (class_exists('ParsedownExtra')) {
        $parsedown = new ParsedownExtra();
    } else {
        $parsedown = new Parsedown();
    }
    $parsedown->setSafeMode(false);

    return $parsedown;
}

// ---------------------------------------------------------------------------
// Liquid / data-loop templating
// ---------------------------------------------------------------------------

function load_yaml_list(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return [];
    }

    $items   = [];
    $current = null;

    // Block scalar state
    $blockKey    = null;
    $blockLines  = [];
    $blockIndent = null;
    $blockFold   = false;

    $flushBlock = function () use (&$current, &$blockKey, &$blockLines, &$blockIndent, &$blockFold): void {
        if ($blockKey === null || $current === null) {
            return;
        }
        // Remove trailing empty lines
        while (count($blockLines) > 0 && end($blockLines) === '') {
            array_pop($blockLines);
        }
        if ($blockFold) {
            $result = '';
            foreach ($blockLines as $bl) {
                if ($bl === '') {
                    $result = rtrim($result) . "\n\n";
                } else {
                    $result .= $bl . ' ';
                }
            }
            $current[$blockKey] = rtrim($result);
        } else {
            $current[$blockKey] = implode("\n", $blockLines);
        }
        $blockKey    = null;
        $blockLines  = [];
        $blockIndent = null;
        $blockFold   = false;
    };

    $parseLine = function (string $line) use (&$items, &$current, &$blockKey, &$blockLines, &$blockIndent, &$blockFold, $flushBlock): void {
        if ($line === '' || str_starts_with(ltrim($line), '#')) {
            return;
        }

        if (preg_match('/^\s*-\s*(.*)$/', $line, $matches)) {
            if ($current !== null) {
                $items[] = $current;
            }
            $current = [];
            $rest    = trim($matches[1]);
            if ($rest !== '' && strpos($rest, ':') !== false) {
                [$key, $value] = array_map('trim', explode(':', $rest, 2));
                $value = trim($value, "\"'");
                if ($value === '|' || $value === '>') {
                    $blockKey  = $key;
                    $blockFold = ($value === '>');
                } else {
                    $current[$key] = $value;
                }
            }
            return;
        }

        if ($current === null || strpos(ltrim($line), ':') === false) {
            return;
        }

        [$key, $value] = array_map('trim', explode(':', trim($line), 2));
        $value = trim($value, "\"'");
        if ($value === '|' || $value === '>') {
            $blockKey  = $key;
            $blockFold = ($value === '>');
        } else {
            $current[$key] = $value;
        }
    };

    foreach ($lines as $line) {
        $line = rtrim($line);

        if ($blockKey !== null) {
            // Blank lines are preserved within a block scalar
            if ($line === '') {
                $blockLines[] = '';
                continue;
            }

            $lineIndent = strlen($line) - strlen(ltrim($line));

            // First non-empty line sets the indentation level
            if ($blockIndent === null) {
                $blockIndent = $lineIndent;
            }

            if ($lineIndent >= $blockIndent) {
                $blockLines[] = substr($line, $blockIndent);
                continue;
            }

            // Less indented — end of block, process the triggering line normally
            $flushBlock();
        }

        $parseLine($line);
    }

    $flushBlock();

    if ($current !== null) {
        $items[] = $current;
    }

    return $items;
}

function render_markdown_fragment(string $markdown): string
{
    $parsedown = get_markdown_parser();
    return $parsedown->text($markdown);
}

function render_liquid_template_items(string $template, array $items): string
{
    $rendered = '';
    foreach ($items as $item) {
        $chunk = preg_replace_callback('/\{\%\s*if\s+site\.feed\s*\%\}(.*?)\{\%\s*endif\s*\%\}/s', function ($m) use ($item) {
            return !empty($item['feed']) ? $m[1] : '';
        }, $template);

        $chunk = preg_replace_callback('/\{\{\s*site\.([a-zA-Z0-9_]+)\s*\|\s*markdownify\s*\}\}/', function ($m) use ($item) {
            $value = $item[$m[1]] ?? '';
            return render_markdown_fragment($value);
        }, $chunk);

        $chunk = preg_replace_callback('/\{\{\s*site\.([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($item) {
            $value = $item[$m[1]] ?? '';
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $chunk);

        $rendered .= $chunk;
    }

    return $rendered;
}

function render_any_data_loops(string $markdown): string
{
    $pattern = '/\{\%\s*for\s+site\s+in\s+site\.data\.([a-zA-Z0-9_-]+)\s*\%\}(.*?)\{\%\s*endfor\s*\%\}/s';

    return preg_replace_callback($pattern, function (array $matches): string {
        $dataName = $matches[1] ?? '';
        $template = $matches[2] ?? '';
        if ($dataName === '') {
            return '';
        }

        $dataFile = PUREBLOG_DATA_PATH . '/' . $dataName . '.yml';
        $items = load_yaml_list($dataFile);
        if (!$items) {
            return '';
        }

        return render_liquid_template_items($template, $items);
    }, $markdown) ?? $markdown;
}

function protect_fenced_code_blocks(string $markdown, array &$blocks): string
{
    $blocks = [];
    $index = 0;
    $patterns = [
        '/```[\s\S]*?```/',
        '/~~~[\s\S]*?~~~/',
    ];

    foreach ($patterns as $pattern) {
        $markdown = preg_replace_callback($pattern, function (array $matches) use (&$blocks, &$index): string {
            $token = '__PUREBLOG_CODE_BLOCK_' . $index . '__';
            $blocks[$token] = $matches[0];
            $index++;
            return $token;
        }, $markdown) ?? $markdown;
    }

    return $markdown;
}

function restore_fenced_code_blocks(string $markdown, array $blocks): string
{
    if (!$blocks) {
        return $markdown;
    }

    return strtr($markdown, $blocks);
}

function protect_inline_code_spans(string $markdown, array &$spans): string
{
    $spans = [];
    $index = 0;

    return preg_replace_callback('/`[^`\n]*`/', function (array $matches) use (&$spans, &$index): string {
        $token = '__PUREBLOG_INLINE_CODE_' . $index . '__';
        $spans[$token] = $matches[0];
        $index++;
        return $token;
    }, $markdown) ?? $markdown;
}

function restore_inline_code_spans(string $markdown, array $spans): string
{
    if (!$spans) {
        return $markdown;
    }

    return strtr($markdown, $spans);
}

function render_global_shortcodes(string $markdown, array $context = []): string
{
    static $siteEmail = null;
    if ($siteEmail === null) {
        $config = load_config();
        $siteEmail = trim((string) ($config['site_email'] ?? ''));
    }

    $postTitle = trim((string) ($context['post_title'] ?? ''));
    $pageTitle = trim((string) ($context['page_title'] ?? ''));
    $contentTitle = trim((string) ($context['content_title'] ?? ($postTitle !== '' ? $postTitle : $pageTitle)));

    $shortcodes = [
        'site_email' => $siteEmail,
        'site.email' => $siteEmail,
        'post_title' => $postTitle,
        'page_title' => $pageTitle,
        'content_title' => $contentTitle,
    ];

    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function (array $matches) use ($shortcodes): string {
        $key = $matches[1];
        if (!array_key_exists($key, $shortcodes)) {
            return $matches[0];
        }

        return (string) $shortcodes[$key];
    }, $markdown) ?? $markdown;
}

function filter_content(string $markdown, array $context = []): string
{
    // Do not process loop syntax inside fenced code examples.
    $codeBlocks = [];
    $markdown = protect_fenced_code_blocks($markdown, $codeBlocks);
    $inlineCodeSpans = [];
    $markdown = protect_inline_code_spans($markdown, $inlineCodeSpans);

    $markdown = render_global_shortcodes($markdown, $context);
    $markdown = render_any_data_loops($markdown);
    $markdown = apply_filter('on_filter_content', $markdown);

    $markdown = restore_inline_code_spans($markdown, $inlineCodeSpans);

    return restore_fenced_code_blocks($markdown, $codeBlocks);
}

function get_excerpt(string $markdown, int $length = 200): string
{
    $parts = explode('<!--more-->', $markdown, 2);
    $excerpt = $parts[0];
    if (mb_strlen($excerpt) > $length * 6) {
        $excerpt = mb_substr($excerpt, 0, $length * 6);
    }
    $excerpt = preg_replace('/```.*?```/s', ' ', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/`[^`]*`/', ' ', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/!\[[^\]]*\]\([^)]+\)/', ' ', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/\[([^\]]*)\]\([^)]+\)/', '$1', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/^[ \t]*[-*>]+[ \t]*/m', ' ', $excerpt) ?? $excerpt; // list markers / blockquotes
    $excerpt = preg_replace('/^[ \t]*#{1,6}[ \t]+/m', ' ', $excerpt) ?? $excerpt;  // ATX headings
    $excerpt = preg_replace('/^[-*_]{2,}[ \t]*$/m', ' ', $excerpt) ?? $excerpt;    // setext headers / HR
    $excerpt = preg_replace('/[*_~]+/', '', $excerpt) ?? $excerpt;                  // inline emphasis
    $excerpt = strip_tags($excerpt);
    $excerpt = preg_replace('/\s+/', ' ', $excerpt) ?? $excerpt;
    $excerpt = trim($excerpt);

    if (mb_strlen($excerpt) > $length) {
        return rtrim(mb_substr($excerpt, 0, $length)) . '...';
    }

    return $excerpt;
}

// ---------------------------------------------------------------------------
// Tag and layout helpers
// ---------------------------------------------------------------------------

function normalize_tag(string $tag): string
{
    return slugify($tag);
}

function render_tag_links(array $tags): string
{
    $tags = array_values(array_filter(array_map('trim', $tags)));
    if (!$tags) {
        return '';
    }

    $links = [];
    foreach ($tags as $tag) {
        $slug = normalize_tag($tag);
        $links[] = '<a href="' . base_path() . '/tag/' . e(rawurlencode($slug)) . '">' . e($tag) . '</a>';
    }

    return implode(', ', $links);
}

function render_layout_partial(string $name, array $context = []): string
{
    $partialPath = resolve_template_file($name, PUREBLOG_BASE_PATH . '/content/includes', PUREBLOG_BASE_PATH . '/includes');
    if ($partialPath === null) {
        return '';
    }

    extract($context, EXTR_SKIP);

    ob_start();
    include $partialPath;
    $output = (string) ob_get_clean();
    $output = render_global_shortcodes($output, $context);
    return render_any_data_loops($output);
}

function find_include(string $name): ?string
{
    return resolve_template_file($name, PUREBLOG_BASE_PATH . '/content/includes', PUREBLOG_BASE_PATH . '/includes');
}

function resolve_template_file(string $name, string $userDir, string $coreDir): ?string
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', $name) ?? '';
    if ($safeName !== $name) {
        return null;
    }

    $userPath = rtrim($userDir, '/') . '/' . $safeName . '.php';
    if (is_file($userPath)) {
        return $userPath;
    }

    $corePath = rtrim($coreDir, '/') . '/' . $safeName . '.php';
    if (is_file($corePath)) {
        return $corePath;
    }

    return null;
}

function get_contextual_inject(array $config, string $region, array $context = []): string
{
    $postKey = $region . '_inject_post';
    $pageKey = $region . '_inject_page';
    $isPostView = isset($context['post']) && is_array($context['post']);

    if ($isPostView) {
        return (string) ($config[$postKey] ?? '');
    }

    // Fallback: page inject applies to page views and all other front-end views.
    return (string) ($config[$pageKey] ?? '');
}

function render_footer_layout(array $config, array $context = []): void
{
    $footerPath = find_include('footer') ?? (PUREBLOG_BASE_PATH . '/includes/footer.php');

    extract($context, EXTR_SKIP);
    ob_start();
    include $footerPath;
    $output = (string) ob_get_clean();

    $footerInject = trim(get_contextual_inject($config, 'footer', $context));
    if ($footerInject !== '') {
        $needle = '</footer>';
        $pos = strripos($output, $needle);
        if ($pos !== false) {
            $output = substr($output, 0, $pos) . $footerInject . "\n" . substr($output, $pos);
        } else {
            $output .= "\n" . $footerInject . "\n";
        }
    }

    echo $output;
}

function render_masthead_layout(array $config, array $context = []): void
{
    $mastheadPath = find_include('masthead') ?? (PUREBLOG_BASE_PATH . '/includes/masthead.php');

    $siteTagline = trim((string) ($config['site_tagline'] ?? ''));
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $bp = base_path();
    if ($bp !== '' && str_starts_with($uriPath, $bp)) {
        $uriPath = substr($uriPath, strlen($bp));
    }
    $currentPath = trim($uriPath, '/');
    $navPages = get_all_pages(false);
    $navPages = array_values(array_filter($navPages, fn($page) => ($page['include_in_nav'] ?? true)));
    $customNavItems = array_values(array_filter(parse_custom_nav($config['custom_nav'] ?? ''), function (array $item): bool {
        $url = $item['url'] ?? '';
        if ($url === '' || $url[0] === '/') {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }));

    extract($context, EXTR_SKIP);
    include $mastheadPath;
}

// ---------------------------------------------------------------------------
// Navigation and routing
// ---------------------------------------------------------------------------

function parse_custom_nav(string $raw): array
{
    $items = [];
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '|')) {
            continue;
        }
        [$label, $url] = array_map('trim', explode('|', $line, 2));
        if ($label === '' || $url === '') {
            continue;
        }
        $items[] = ['label' => $label, 'url' => $url];
    }
    return $items;
}

/**
 * @return list<array{path:string,target:string}>
 */
function parse_custom_routes(string $raw): array
{
    $items = [];
    $seen = [];
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '|')) {
            continue;
        }

        [$path, $target] = array_map('trim', explode('|', $line, 2));
        if ($path === '' || $target === '') {
            continue;
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        if (
            ($path !== '/' && !preg_match('#^/[a-zA-Z0-9/_-]+$#', $path))
            || str_contains($path, '//')
            || str_contains($path, '..')
        ) {
            continue;
        }

        if (isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;

        $items[] = [
            'path' => $path,
            'target' => $target,
        ];
    }

    return $items;
}

function resolve_custom_route_template(string $target): ?string
{
    $target = str_replace('\\', '/', trim($target));
    if ($target === '') {
        return null;
    }

    $targetPath = $target;
    if (!str_starts_with($targetPath, '/')) {
        $targetPath = '/content/includes/' . ltrim($targetPath, '/');
    }

    if (!str_starts_with($targetPath, '/content/includes/')) {
        return null;
    }

    if (!str_ends_with(strtolower($targetPath), '.php')) {
        return null;
    }

    $fullPath = PUREBLOG_BASE_PATH . $targetPath;
    $resolvedPath = realpath($fullPath);
    $allowedRoot = realpath(PUREBLOG_BASE_PATH . '/content/includes');

    if ($resolvedPath === false || $allowedRoot === false) {
        return null;
    }

    if (!str_starts_with($resolvedPath, $allowedRoot . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return is_file($resolvedPath) ? $resolvedPath : null;
}

// ---------------------------------------------------------------------------
// Search and tag indexes
// ---------------------------------------------------------------------------

function filter_posts_by_query(array $posts, string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return $posts;
    }

    // Split into individual words so multi-word queries match posts containing all terms
    $words = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
    if (empty($words)) {
        return $posts;
    }

    return array_values(array_filter($posts, function (array $post) use ($words): bool {
        $raw = implode(' ', [
            (string) ($post['title'] ?? ''),
            (string) ($post['description'] ?? ''),
            (string) ($post['excerpt'] ?? ''),
            implode(' ', $post['tags'] ?? []),
        ]);
        // Strip emoji and other non-letter/digit/punctuation characters so they
        // don't prevent matches (e.g. a title like "📚 Flybot" still matches "flybot")
        $haystack = mb_strtolower((string) preg_replace('/[^\p{L}\p{N}\p{P}\s]/u', ' ', $raw));
        foreach ($words as $word) {
            if (mb_strpos($haystack, $word) === false) {
                return false;
            }
        }
        return true;
    }));
}

function build_search_index(): bool
{
    $config = load_config();
    $excerptLength = (int) ($config['search_excerpt_length'] ?? 2500);
    $posts = get_all_posts(false, true);
    get_all_posts_meta(false, true);
    $index = array_map(function (array $post) use ($excerptLength): array {
        $content = (string) ($post['content'] ?? '');
        $excerpt = $excerptLength === 0 ? $content : get_excerpt($content, $excerptLength);
        return [
            'title' => (string) ($post['title'] ?? ''),
            'slug' => (string) ($post['slug'] ?? ''),
            'date' => (string) ($post['date'] ?? ''),
            'tags' => $post['tags'] ?? [],
            'description' => (string) ($post['description'] ?? ''),
            'excerpt' => $excerpt,
        ];
    }, $posts);

    $json = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents(PUREBLOG_SEARCH_INDEX_PATH, $json, LOCK_EX) !== false;
}

function build_tag_index(): bool
{
    $posts = get_all_posts(false, true);
    $index = [];
    foreach ($posts as $post) {
        $slug = (string) ($post['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        foreach ($post['tags'] ?? [] as $tag) {
            $tag = trim((string) $tag);
            $tagSlug = normalize_tag($tag);
            if ($tagSlug === '') {
                continue;
            }
            if (!isset($index[$tagSlug])) {
                $index[$tagSlug] = ['name' => $tag, 'posts' => []];
            }
            $index[$tagSlug]['posts'][] = $slug;
        }
    }

    $json = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents(PUREBLOG_TAG_INDEX_PATH, $json, LOCK_EX) !== false;
}

function load_tag_index(): ?array
{
    if (!is_file(PUREBLOG_TAG_INDEX_PATH)) {
        return null;
    }

    $raw = file_get_contents(PUREBLOG_TAG_INDEX_PATH);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function load_search_index(): ?array
{
    if (!is_file(PUREBLOG_SEARCH_INDEX_PATH)) {
        return null;
    }

    $raw = file_get_contents(PUREBLOG_SEARCH_INDEX_PATH);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

// ---------------------------------------------------------------------------
// Pagination and layout rendering
// ---------------------------------------------------------------------------

function paginate_posts(array $posts, int $perPage, int $currentPage): array
{
    $perPage = max(1, $perPage);
    $currentPage = max(1, $currentPage);
    $totalPosts = count($posts);
    $totalPages = $totalPosts > 0 ? (int) ceil($totalPosts / $perPage) : 1;
    $offset = ($currentPage - 1) * $perPage;
    $pagedPosts = array_slice($posts, $offset, $perPage);

    return [
        'posts' => $pagedPosts,
        'totalPosts' => $totalPosts,
        'totalPages' => $totalPages,
        'currentPage' => $currentPage,
    ];
}

function get_layouts(): array
{
    $dir = PUREBLOG_BASE_PATH . '/content/layouts';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.php') ?: [];
    $layouts = [];
    foreach ($files as $file) {
        $name = basename($file, '.php');
        $jsonFile = $dir . '/' . $name . '.json';
        $fields = [];
        $label = $name;
        if (is_file($jsonFile)) {
            $json = @file_get_contents($jsonFile);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $label = trim((string) ($decoded['label'] ?? $name));
                    if ($label === '') {
                        $label = $name;
                    }
                    $fields = is_array($decoded['fields'] ?? null) ? $decoded['fields'] : [];
                }
            }
        }
        $layouts[] = ['name' => $name, 'label' => $label, 'fields' => $fields];
    }
    return $layouts;
}

function layout_context(?array $post = null, ?array $config = null, ?array $adjacentPosts = null): array
{
    static $ctx = ['post' => [], 'config' => [], 'adjacentPosts' => []];
    if ($post !== null) {
        $ctx = ['post' => $post, 'config' => $config ?? [], 'adjacentPosts' => $adjacentPosts ?? []];
    }
    return $ctx;
}

function render_post_navigation(): string
{
    $ctx = layout_context();
    return render_layout_partial('post-meta', [
        'post'          => $ctx['post'],
        'config'        => $ctx['config'],
        'previous_post' => $ctx['adjacentPosts']['previous'] ?? null,
        'next_post'     => $ctx['adjacentPosts']['next'] ?? null,
    ]);
}

function render_layout_file(string $file, array $post, array $config, array $adjacentPosts): void
{
    layout_context($post, $config, $adjacentPosts);
    include $file;
}
