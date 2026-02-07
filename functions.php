<?php

declare(strict_types=1);

const PUREBLOG_BASE_PATH = __DIR__;
const PUREBLOG_CONFIG_PATH = PUREBLOG_BASE_PATH . '/config/config.php';
const PUREBLOG_POSTS_PATH = PUREBLOG_BASE_PATH . '/content/posts';
const PUREBLOG_PAGES_PATH = PUREBLOG_BASE_PATH . '/content/pages';
const PUREBLOG_SEARCH_INDEX_PATH = PUREBLOG_BASE_PATH . '/content/search-index.json';
const PUREBLOG_TAG_INDEX_PATH = PUREBLOG_BASE_PATH . '/content/tag-index.json';
const PUREBLOG_HOOKS_PATH = PUREBLOG_BASE_PATH . '/config/hooks.php';

function default_config(): array
{
    return [
        'site_title' => 'My Blog',
        'site_tagline' => '',
        'site_description' => '',
        'site_email' => '',
        'custom_nav' => '',
        'posts_per_page' => 20,
        'homepage_slug' => '',
        'blog_page_slug' => '',
        'hide_homepage_title' => true,
        'hide_blog_page_title' => true,
        'base_url' => '',
        'admin_username' => '',
        'admin_password_hash' => '',
        'theme' => [
            'color_mode' => 'auto',
            'font_stack' => 'sans',
            'admin_font_stack' => 'mono',
            'admin_color_mode' => 'auto',
            'background_color' => '#FAFAFA',
            'text_color' => '#212121',
            'accent_color' => '#0D47A1',
            'border_color' => '#898EA4',
            'accent_bg_color' => '#F5F7FF',
            'background_color_dark' => '#212121',
            'text_color_dark' => '#DCDCDC',
            'accent_color_dark' => '#FFB300',
            'border_color_dark' => '#555',
            'accent_bg_color_dark' => '#2B2B2B',
            'post_list_layout' => 'excerpt',
        ],
        'assets' => [
            'favicon' => '/assets/images/favicon.png',
            'og_image' => '/assets/images/og-image.png',
        ],
    ];
}

function load_config(): array
{
    if (!file_exists(PUREBLOG_CONFIG_PATH)) {
        return default_config();
    }

    $config = require PUREBLOG_CONFIG_PATH;
    if (!is_array($config)) {
        return default_config();
    }

    return array_replace_recursive(default_config(), $config);
}

function load_hooks(): void
{
    if (is_file(PUREBLOG_HOOKS_PATH)) {
        require_once PUREBLOG_HOOKS_PATH;
    }
}

function call_hook(string $name, array $args = []): void
{
    load_hooks();
    if (function_exists($name)) {
        $name(...$args);
    }
}

function save_config(array $config): bool
{
    $data = "<?php\nreturn " . var_export($config, true) . ";\n";
    $tmpPath = PUREBLOG_CONFIG_PATH . '.tmp';

    if (file_put_contents($tmpPath, $data) === false) {
        return false;
    }

    return rename($tmpPath, PUREBLOG_CONFIG_PATH);
}

function is_installed(): bool
{
    if (!file_exists(PUREBLOG_CONFIG_PATH)) {
        return false;
    }

    $config = load_config();
    return !empty($config['admin_password_hash']);
}

function require_setup_redirect(): void
{
    if (!is_installed()) {
        header('Location: /setup.php');
        exit;
    }
}

function start_admin_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function csrf_token(): string
{
    start_admin_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function verify_csrf(): void
{
    start_admin_session();
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if ($token === '' || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['is_admin']);
}

function require_admin_login(): void
{
    if (!is_admin_logged_in()) {
        header('Location: /admin/index.php');
        exit;
    }
}

function get_base_url(): string
{
    if (PHP_SAPI === 'cli-server') {
        return 'http://localhost:8000';
    }

    $config = load_config();
    $configuredBase = trim((string) ($config['base_url'] ?? ''));
    if ($configuredBase !== '') {
        $parsed = parse_url($configuredBase);
        if (is_array($parsed) && !empty($parsed['scheme']) && !empty($parsed['host'])) {
            return rtrim($configuredBase, '/');
        }
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $host = strtolower($host);
    if (!preg_match('/^[a-z0-9.-]+(:\d+)?$/', $host)) {
        $host = 'localhost';
    }
    $path = rtrim(str_replace('/setup.php', '', $_SERVER['SCRIPT_NAME'] ?? ''), '/');

    return $scheme . '://' . $host . $path;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function font_stack_css(string $fontStack): string
{
    return match ($fontStack) {
        'serif' => 'Georgia, Times, "Times New Roman", serif',
        'mono' => 'ui-monospace, "Cascadia Code", "Source Code Pro", Menlo, Consolas, "DejaVu Sans Mono", monospace',
        default => '-apple-system, BlinkMacSystemFont, "Avenir Next", Avenir, "Nimbus Sans L", Roboto, "Noto Sans", "Segoe UI", Arial, Helvetica, "Helvetica Neue", sans-serif',
    };
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\s-]/', '', $value) ?? '';
    $value = preg_replace('/\s+/', '-', $value) ?? '';
    $value = preg_replace('/-+/', '-', $value) ?? '';

    return trim($value, '-');
}

function parse_post_file(string $filepath): array
{
    $raw = file_get_contents($filepath);
    if ($raw === false) {
        return ['front_matter' => [], 'content' => ''];
    }

    $raw = str_replace("\r\n", "\n", $raw);
    $frontMatter = [];
    $content = $raw;

    if (str_starts_with($raw, "---\n")) {
        $parts = explode("\n---\n", $raw, 2);
        if (count($parts) === 2) {
            $frontMatterText = trim($parts[0], "-\n");
            $content = $parts[1];
            $lines = explode("\n", $frontMatterText);
            $listKey = null;
            foreach ($lines as $line) {
                $line = rtrim($line);
                if ($line === '') {
                    continue;
                }

                if ($listKey !== null) {
                    if (preg_match('/^\s*-\s*(.+)$/', $line, $matches)) {
                        $item = trim($matches[1], " \t\"'");
                        if ($item !== '') {
                            $frontMatter[$listKey][] = $item;
                        }
                        continue;
                    }
                    $listKey = null;
                }

                if (strpos($line, ':') === false) {
                    continue;
                }

                [$key, $value] = array_map('trim', explode(':', $line, 2));
                if ($key === '') {
                    continue;
                }

                if ($value === '') {
                    if (in_array($key, ['tags', 'categories'], true)) {
                        $listKey = $key;
                        $frontMatter[$key] = $frontMatter[$key] ?? [];
                        continue;
                    }
                    $frontMatter[$key] = '';
                    continue;
                }

                if ($key === 'date') {
                    $value = trim($value, "\"'");
                    $normalized = normalize_date_value($value);
                    $frontMatter[$key] = $normalized ?? $value;
                } elseif ($key === 'tags' || $key === 'categories') {
                    $value = trim($value, "[] ");
                    $tags = $value === '' ? [] : array_map('trim', explode(',', $value));
                    $frontMatter[$key] = array_filter($tags, fn($tag) => $tag !== '');
                } elseif ($key === 'description') {
                    $frontMatter[$key] = $value;
                } elseif ($key === 'include_in_nav') {
                    $frontMatter[$key] = in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
                } else {
                    $frontMatter[$key] = $value;
                }
            }

            if (!empty($frontMatter['categories'])) {
                $categoryTags = is_array($frontMatter['categories']) ? $frontMatter['categories'] : [];
                $existingTags = $frontMatter['tags'] ?? [];
                $merged = array_values(array_unique(array_merge($existingTags, $categoryTags)));
                $frontMatter['tags'] = $merged;
            }
        }
    }

    return [
        'front_matter' => $frontMatter,
        'content' => ltrim($content),
    ];
}

function normalize_date_value(string $value): ?string
{
    if ($value === '') {
        return null;
    }

    $formats = [
        'Y-m-d H:i',
        'Y-m-d H:i:s',
        'Y-m-d\\TH:i:s.u\\Z',
        'Y-m-d\\TH:i:s\\Z',
        'Y-m-d\\TH:i:s.uP',
        'Y-m-d\\TH:i:sP',
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return date('Y-m-d H:i', $timestamp);
    }

    return null;
}

function get_all_posts(bool $includeDrafts = false, bool $bustCache = false): array
{
    static $cache = null;
    if ($bustCache) {
        $cache = null;
    }
    if ($cache === null) {
        if (!is_dir(PUREBLOG_POSTS_PATH)) {
            $cache = [];
        } else {
            $files = glob(PUREBLOG_POSTS_PATH . '/*.md') ?: [];
            $posts = [];

            foreach ($files as $file) {
                $parsed = parse_post_file($file);
                $front = $parsed['front_matter'];
                $status = $front['status'] ?? 'draft';

                $dateString = $front['date'] ?? '';
                $timestamp = $dateString ? strtotime($dateString) : 0;

                $posts[] = [
                    'title' => $front['title'] ?? 'Untitled',
                    'slug' => $front['slug'] ?? '',
                    'date' => $dateString,
                    'timestamp' => $timestamp,
                    'status' => $status,
                    'tags' => $front['tags'] ?? [],
                    'description' => $front['description'] ?? '',
                    'content' => $parsed['content'],
                    'path' => $file,
                ];
            }

            usort($posts, fn($a, $b) => ($b['timestamp'] <=> $a['timestamp']));
            $cache = $posts;
        }
    }

    if ($includeDrafts) {
        return $cache;
    }

    return array_values(array_filter($cache, fn($post) => ($post['status'] ?? 'draft') === 'published'));
}

function get_post_by_slug(string $slug, bool $includeDrafts = false): ?array
{
    $posts = get_all_posts($includeDrafts);
    foreach ($posts as $post) {
        if ($post['slug'] === $slug) {
            return $post;
        }
    }

    return null;
}

function get_all_pages(bool $includeDrafts = false, bool $bustCache = false): array
{
    static $cache = null;
    if ($bustCache) {
        $cache = null;
    }
    if ($cache === null) {
        if (!is_dir(PUREBLOG_PAGES_PATH)) {
            $cache = [];
        } else {
            $files = glob(PUREBLOG_PAGES_PATH . '/*.md') ?: [];
            $pages = [];

            foreach ($files as $file) {
                $parsed = parse_post_file($file);
                $front = $parsed['front_matter'];
                $status = $front['status'] ?? 'draft';

                $pages[] = [
                    'title' => $front['title'] ?? 'Untitled',
                    'slug' => $front['slug'] ?? '',
                    'status' => $status,
                    'description' => $front['description'] ?? '',
                    'include_in_nav' => $front['include_in_nav'] ?? true,
                    'content' => $parsed['content'],
                    'path' => $file,
                ];
            }

            usort($pages, fn($a, $b) => ($a['title'] <=> $b['title']));
            $cache = $pages;
        }
    }

    if ($includeDrafts) {
        return $cache;
    }

    return array_values(array_filter($cache, fn($page) => ($page['status'] ?? 'draft') === 'published'));
}

function get_page_by_slug(string $slug, bool $includeDrafts = false): ?array
{
    $pages = get_all_pages($includeDrafts);
    foreach ($pages as $page) {
        if ($page['slug'] === $slug) {
            return $page;
        }
    }

    return null;
}

function save_page(array &$page, ?string $originalSlug = null, ?string $originalStatus = null, ?string &$error = null): bool
{
    $error = null;
    $title = trim($page['title'] ?? '');
    $slug = trim($page['slug'] ?? '');
    $status = trim($page['status'] ?? 'draft');
    $description = trim($page['description'] ?? '');
    $includeInNav = (bool) ($page['include_in_nav'] ?? true);
    $content = $page['content'] ?? '';

    if ($slug === '') {
        $slug = slugify($title);
    }

    if ($slug !== '') {
        $baseSlug = $slug;
        $suffix = 2;
        while (slug_in_use($slug, 'page', $originalSlug)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
    }
    $page['slug'] = $slug;

    $filename = $slug . '.md';
    $path = PUREBLOG_PAGES_PATH . '/' . $filename;

    $frontMatter = [
        'title' => $title,
        'slug' => $slug,
        'status' => $status,
        'description' => $description,
        'include_in_nav' => $includeInNav ? 'true' : 'false',
    ];

    $frontLines = ["---"];
    foreach ($frontMatter as $key => $value) {
        $frontLines[] = $key . ': ' . $value;
    }
    $frontLines[] = "---";

    $body = implode("\n", $frontLines) . "\n\n" . ltrim($content) . "\n";

    if (!is_dir(PUREBLOG_PAGES_PATH)) {
        mkdir(PUREBLOG_PAGES_PATH, 0755, true);
    }

    $existingPath = null;
    if ($originalSlug !== null && $originalSlug !== $slug) {
        $existingPath = PUREBLOG_PAGES_PATH . '/' . $originalSlug . '.md';
        if (!is_file($existingPath)) {
            $existingPath = null;
        }
    }

    if (file_put_contents($path, $body) === false) {
        $error = 'Unable to write page file.';
        return false;
    }

    if ($existingPath && $existingPath !== $path) {
        if (!unlink($existingPath)) {
            $error = 'Page saved, but could not remove the old file. Check permissions.';
            return false;
        }
    }

    if ($status === 'published') {
        call_hook('on_page_updated', [$slug]);
        if ($originalStatus !== 'published') {
            call_hook('on_page_published', [$slug]);
        }
    }

    call_hook('on_page_deleted', [$slug]);
    return true;
}

function delete_page_by_slug(string $slug): bool
{
    $path = PUREBLOG_PAGES_PATH . '/' . $slug . '.md';
    if (!is_file($path)) {
        return false;
    }

    $deleted = unlink($path);
    if (!$deleted) {
        return false;
    }

    $imageDir = PUREBLOG_BASE_PATH . '/assets/images/' . $slug;
    if (is_dir($imageDir)) {
        $files = glob($imageDir . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file) && !unlink($file)) {
                return false;
            }
        }
        if (!rmdir($imageDir)) {
            return false;
        }
    }

    return true;
}

function find_post_filepath_by_slug(string $slug): ?string
{
    if (!is_dir(PUREBLOG_POSTS_PATH)) {
        return null;
    }

    $files = glob(PUREBLOG_POSTS_PATH . '/*.md') ?: [];
    foreach ($files as $file) {
        $parsed = parse_post_file($file);
        $front = $parsed['front_matter'];
        if (($front['slug'] ?? '') === $slug) {
            return $file;
        }
    }

    return null;
}

function delete_post_by_slug(string $slug): bool
{
    $path = find_post_filepath_by_slug($slug);
    if ($path === null) {
        return false;
    }

    $deleted = unlink($path);
    if ($deleted) {
        $imageDir = PUREBLOG_BASE_PATH . '/assets/images/' . $slug;
        if (is_dir($imageDir)) {
            $files = glob($imageDir . '/*') ?: [];
            foreach ($files as $file) {
                if (is_file($file) && !unlink($file)) {
                    return false;
                }
            }
            if (!rmdir($imageDir)) {
                return false;
            }
        }
        build_search_index();
        build_tag_index();
        call_hook('on_post_deleted', [$slug]);
    }
    return $deleted;
}

function slug_in_use(string $slug, string $type, ?string $originalSlug = null): bool
{
    if ($type === 'post') {
        $postPath = find_post_filepath_by_slug($slug);
        if ($postPath !== null && ($originalSlug === null || $originalSlug !== $slug)) {
            return true;
        }
        if (get_page_by_slug($slug, true)) {
            return true;
        }
        return false;
    }

    if ($type === 'page') {
        $page = get_page_by_slug($slug, true);
        if ($page && ($originalSlug === null || $originalSlug !== $slug)) {
            return true;
        }
        if (find_post_filepath_by_slug($slug) !== null) {
            return true;
        }
        return false;
    }

    return false;
}

function save_post(array &$post, ?string $originalSlug = null, ?string $originalDate = null, ?string $originalStatus = null, ?string &$error = null): bool
{
    $error = null;
    $title = trim($post['title'] ?? '');
    $slug = trim($post['slug'] ?? '');
    $date = trim($post['date'] ?? '');
    $status = trim($post['status'] ?? 'draft');
    $tags = $post['tags'] ?? [];
    $content = $post['content'] ?? '';
    $description = trim($post['description'] ?? '');

    if ($slug === '') {
        $slug = slugify($title);
    }

    if ($slug !== '') {
        $baseSlug = $slug;
        $suffix = 2;
        while (slug_in_use($slug, 'post', $originalSlug)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
    }
    $post['slug'] = $slug;

    if ($date === '') {
        $date = date('Y-m-d H:i');
    }

    $datePrefix = date('Y-m-d', strtotime($date));
    $filename = $datePrefix . '-' . $slug . '.md';
    $path = PUREBLOG_POSTS_PATH . '/' . $filename;

    $frontMatter = [
        'title' => $title,
        'slug' => $slug,
        'date' => $date,
        'status' => $status,
        'tags' => $tags,
        'description' => $description,
    ];

    $frontLines = ["---"];
    foreach ($frontMatter as $key => $value) {
        if ($key === 'tags') {
            $value = '[' . implode(', ', $value) . ']';
        }
        $frontLines[] = $key . ': ' . $value;
    }
    $frontLines[] = "---";

    $body = implode("\n", $frontLines) . "\n\n" . ltrim($content) . "\n";

    if (!is_dir(PUREBLOG_POSTS_PATH)) {
        mkdir(PUREBLOG_POSTS_PATH, 0755, true);
    }

    $existingPath = null;
    $renameFrom = null;
    if ($originalSlug !== null && $originalSlug !== $slug) {
        $existingPath = find_post_filepath_by_slug($originalSlug);
    } elseif ($originalDate !== null && $originalDate !== '') {
        $originalPrefix = date('Y-m-d', strtotime($originalDate));
        $originalFilename = $originalPrefix . '-' . $slug . '.md';
        $candidate = PUREBLOG_POSTS_PATH . '/' . $originalFilename;
        if (is_file($candidate) && $candidate !== $path) {
            $renameFrom = $candidate;
        }
    }

    if ($renameFrom !== null) {
        if (!rename($renameFrom, $path)) {
            $error = 'Unable to rename post file after date change.';
            return false;
        }
    }

    if (file_put_contents($path, $body) === false) {
        $error = 'Unable to write post file.';
        return false;
    }

    if ($existingPath && $existingPath !== $path) {
        if (!unlink($existingPath)) {
            $error = 'Post saved, but could not remove the old file. Check permissions.';
            return false;
        }
    }

    build_search_index();
    build_tag_index();

    if ($status === 'published') {
        call_hook('on_post_updated', [$slug]);
        if ($originalStatus !== 'published') {
            call_hook('on_post_published', [$slug]);
        }
    }
    return true;
}

function render_markdown(string $markdown): string
{
    static $parsedown = null;
    if ($parsedown === null) {
        require_once __DIR__ . '/lib/Parsedown.php';
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(false);
    }

    return $parsedown->text($markdown);
}

function get_excerpt(string $markdown, int $length = 200): string
{
    $parts = explode('<!--more-->', $markdown, 2);
    $excerpt = $parts[0];
    $excerpt = preg_replace('/```.*?```/s', ' ', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/`[^`]*`/', ' ', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/!\[[^\]]*\]\([^)]+\)/', ' ', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/\[[^\]]*\]\([^)]+\)/', ' ', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/[*_~>#-]+/', ' ', $excerpt) ?? $excerpt;
    $excerpt = strip_tags($excerpt);
    $excerpt = preg_replace('/\s+/', ' ', $excerpt) ?? $excerpt;
    $excerpt = trim($excerpt);

    if (mb_strlen($excerpt) > $length) {
        return rtrim(mb_substr($excerpt, 0, $length)) . '...';
    }

    return $excerpt;
}

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
        $links[] = '<a href="/tag/' . e($slug) . '">' . e($tag) . '</a>';
    }

    return implode(', ', $links);
}

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

function filter_posts_by_query(array $posts, string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return $posts;
    }

    $needle = mb_strtolower($query);
    return array_values(array_filter($posts, function (array $post) use ($needle): bool {
        $haystack = implode(' ', [
            (string) ($post['title'] ?? ''),
            (string) ($post['description'] ?? ''),
            (string) ($post['excerpt'] ?? ''),
            implode(' ', $post['tags'] ?? []),
        ]);
        return mb_stripos($haystack, $needle) !== false;
    }));
}

function build_search_index(): bool
{
    $posts = get_all_posts(false, true);
    $index = array_map(function (array $post): array {
        $excerpt = get_excerpt((string) ($post['content'] ?? ''), 500);
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
            $tagSlug = normalize_tag((string) $tag);
            if ($tagSlug === '') {
                continue;
            }
            $index[$tagSlug] ??= [];
            $index[$tagSlug][] = $slug;
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
