<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Config admin helpers
// ---------------------------------------------------------------------------

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
        header('Location: ' . base_path() . '/setup.php');
        exit;
    }
}

// ---------------------------------------------------------------------------
// URL and output helpers
// ---------------------------------------------------------------------------

function base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (PHP_SAPI === 'cli-server') {
        return $cached = '';
    }

    $config = load_config();
    $configuredBase = trim((string) ($config['base_url'] ?? ''));
    if ($configuredBase !== '') {
        $parsed = parse_url($configuredBase);
        if (is_array($parsed)) {
            return $cached = rtrim((string) ($parsed['path'] ?? ''), '/');
        }
    }

    // Derive path prefix from the project root relative to the document root.
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(PUREBLOG_BASE_PATH) ?: PUREBLOG_BASE_PATH;
    if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        $rel = substr($appRoot, strlen($docRoot));
        $rel = str_replace('\\', '/', $rel);
        return $cached = rtrim($rel, '/');
    }

    return $cached = '';
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

    return $scheme . '://' . $host . base_path();
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

/**
 * Validate that a resolved path is within the allowed base directory.
 */
function validate_image_path(string $baseDir, string $targetPath): bool
{
    $resolvedBase = realpath($baseDir);
    $resolvedTarget = realpath($targetPath);
    if ($resolvedBase === false || $resolvedTarget === false) {
        return false;
    }
    return str_starts_with($resolvedTarget, $resolvedBase . DIRECTORY_SEPARATOR);
}

/**
 * Validate that a slug is safe for use as an image folder name.
 */
function is_safe_image_slug(string $slug): bool
{
    return $slug !== '' && preg_match('/^[\p{L}\p{N}\-_]+$/u', $slug) === 1;
}

function strip_image_metadata(string $path, string $mimeType): void
{
    if (class_exists('Imagick')) {
        try {
            $img = new Imagick($path);
            $img->stripImage();
            $img->writeImage($path);
            $img->destroy();
        } catch (Exception $e) {
            // silently fail — upload still succeeds
        }
        return;
    }

    // GD fallback: re-encoding drops all metadata
    [$loader, $saver] = match($mimeType) {
        'image/jpeg' => ['imagecreatefromjpeg', fn($i) => imagejpeg($i, $path, 90)],
        'image/png'  => ['imagecreatefrompng',  fn($i) => imagepng($i, $path)],
        'image/webp' => ['imagecreatefromwebp', fn($i) => imagewebp($i, $path)],
        default      => [null, null],
    };

    if ($loader === null || !function_exists($loader)) {
        return;
    }

    $img = $loader($path);
    if ($img === false) {
        return;
    }

    $saver($img);
    imagedestroy($img);
}

function slugify(string $value): string
{
    $value = trim($value);
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }

    // Transliterate common diacritics to ASCII equivalents
    $value = strtr($value, [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
        'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y',
        'ÿ' => 'y',
    ]);

    $value = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $value) ?? '';
    $value = preg_replace('/[\s-]+/u', '-', $value) ?? '';

    return trim($value, '-');
}

// ---------------------------------------------------------------------------
// Date and timezone helpers
// ---------------------------------------------------------------------------

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

function site_timezone_identifier(array $config): string
{
    $timezone = trim((string) ($config['timezone'] ?? ''));
    if ($timezone === '') {
        return date_default_timezone_get();
    }

    return in_array($timezone, DateTimeZone::listIdentifiers(), true)
        ? $timezone
        : date_default_timezone_get();
}

function site_timezone_object(array $config): DateTimeZone
{
    return new DateTimeZone(site_timezone_identifier($config));
}

function site_date_format(array $config): string
{
    $format = trim((string) ($config['date_format'] ?? ''));
    return $format !== '' ? $format : 'F j, Y';
}

function current_site_datetime_for_storage(array $config): string
{
    return (new DateTimeImmutable('now', site_timezone_object($config)))->format('Y-m-d H:i');
}

function parse_post_datetime_with_timezone(?string $value, array $config): ?DateTimeImmutable
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $tz = site_timezone_object($config);
    $formats = [
        'Y-m-d H:i',
        'Y-m-d H:i:s',
        'Y-m-d\\TH:i:s.u\\Z',
        'Y-m-d\\TH:i:s\\Z',
        'Y-m-d\\TH:i:s.uP',
        'Y-m-d\\TH:i:sP',
    ];

    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $raw, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->setTimezone($tz);
        }
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return null;
    }

    return (new DateTimeImmutable('@' . $timestamp))->setTimezone($tz);
}

function format_post_date_for_display(?string $value, array $config): string
{
    return format_datetime_for_display($value, $config, null);
}

function format_datetime_for_display(?string $value, array $config, ?string $format = null): string
{
    $dt = parse_post_datetime_with_timezone($value, $config);
    if (!$dt) {
        return '';
    }

    $effectiveFormat = $format !== null && trim($format) !== '' ? $format : site_date_format($config);
    return _lang_translate_date($dt->format($effectiveFormat));
}

function relative_time(int $timestamp): string
{
    $diff = max(0, time() - $timestamp);
    if ($diff < 60) {
        return t('admin.dashboard.time_just_now');
    }
    if ($diff < 3600) {
        $n = (int) floor($diff / 60);
        return t($n === 1 ? 'admin.dashboard.time_minute_ago' : 'admin.dashboard.time_minutes_ago', ['n' => $n]);
    }
    if ($diff < 86400) {
        $n = (int) floor($diff / 3600);
        return t($n === 1 ? 'admin.dashboard.time_hour_ago' : 'admin.dashboard.time_hours_ago', ['n' => $n]);
    }
    $n = (int) floor($diff / 86400);
    return t($n === 1 ? 'admin.dashboard.time_day_ago' : 'admin.dashboard.time_days_ago', ['n' => $n]);
}

function format_post_date_for_rss(?string $value, array $config): string
{
    $dt = parse_post_datetime_with_timezone($value, $config);
    if (!$dt) {
        return (new DateTimeImmutable('now', site_timezone_object($config)))->format(DATE_RSS);
    }

    return $dt->format(DATE_RSS);
}

// ---------------------------------------------------------------------------
// Post and page I/O
// ---------------------------------------------------------------------------

function _parse_front_matter_lines(array $lines): array
{
    $frontMatter = [];
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
        $frontMatter['tags'] = array_values(array_unique(array_merge($existingTags, $categoryTags)));
    }

    return $frontMatter;
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
            $frontMatter = _parse_front_matter_lines(explode("\n", trim($parts[0], "-\n")));
            $content = $parts[1];
        }
    }

    return [
        'front_matter' => $frontMatter,
        'content' => ltrim($content),
    ];
}

function parse_post_meta_only(string $filepath): array
{
    $fh = @fopen($filepath, 'r');
    if ($fh === false) {
        return [];
    }

    $firstLine = fgets($fh);
    if ($firstLine === false || rtrim($firstLine) !== '---') {
        fclose($fh);
        return [];
    }

    $lines = [];
    while (($line = fgets($fh)) !== false) {
        $trimmed = rtrim($line);
        if ($trimmed === '---') {
            break;
        }
        $lines[] = $trimmed;
    }
    fclose($fh);

    return _parse_front_matter_lines($lines);
}

function get_all_posts_meta(bool $includeDrafts = false, bool $bustCache = false): array
{
    static $all = null;
    static $published = null;
    if ($bustCache) {
        $all = null;
        $published = null;
    }
    if ($all === null) {
        if (!is_dir(PUREBLOG_POSTS_PATH)) {
            $all = [];
        } else {
            $files = glob(PUREBLOG_POSTS_PATH . '/*.md') ?: [];
            $posts = [];
            $config = load_config();
            foreach ($files as $file) {
                $front = parse_post_meta_only($file);
                $status = $front['status'] ?? 'draft';
                $dateString = $front['date'] ?? '';
                $dt = parse_post_datetime_with_timezone($dateString, $config);
                $timestamp = $dt ? $dt->getTimestamp() : 0;
                $knownFrontKeys = ['title', 'slug', 'date', 'status', 'tags', 'description', 'categories'];
                $extraFront = array_diff_key($front, array_flip($knownFrontKeys));
                $posts[] = array_merge($extraFront, [
                    'title'       => $front['title'] ?? 'Untitled',
                    'slug'        => $front['slug'] ?? '',
                    'date'        => $dateString,
                    'timestamp'   => $timestamp,
                    'status'      => $status,
                    'tags'        => $front['tags'] ?? [],
                    'description' => $front['description'] ?? '',
                    'path'        => $file,
                ]);
            }
            usort($posts, fn($a, $b) => ($b['timestamp'] <=> $a['timestamp']));
            $all = $posts;
            $published = array_values(array_filter($all, fn($p) => ($p['status'] ?? 'draft') === 'published'));
        }
    }

    return $includeDrafts ? $all : $published;
}

function get_all_posts(bool $includeDrafts = false, bool $bustCache = false): array
{
    static $all = null;
    static $published = null;
    if ($bustCache) {
        $all = null;
        $published = null;
    }
    if ($all === null) {
        if (!is_dir(PUREBLOG_POSTS_PATH)) {
            $all = [];
        } else {
            $files = glob(PUREBLOG_POSTS_PATH . '/*.md') ?: [];
            $posts = [];

            $config = load_config();
            foreach ($files as $file) {
                $parsed = parse_post_file($file);
                $front = $parsed['front_matter'];
                $status = $front['status'] ?? 'draft';

                $dateString = $front['date'] ?? '';
                $dt = parse_post_datetime_with_timezone($dateString, $config);
                $timestamp = $dt ? $dt->getTimestamp() : 0;

                $knownFrontKeys = ['title', 'slug', 'date', 'status', 'tags', 'description', 'categories'];
                $extraFront = array_diff_key($front, array_flip($knownFrontKeys));

                $posts[] = array_merge($extraFront, [
                    'title' => $front['title'] ?? 'Untitled',
                    'slug' => $front['slug'] ?? '',
                    'date' => $dateString,
                    'timestamp' => $timestamp,
                    'status' => $status,
                    'tags' => $front['tags'] ?? [],
                    'description' => $front['description'] ?? '',
                    'content' => $parsed['content'],
                    'path' => $file,
                ]);
            }

            usort($posts, fn($a, $b) => ($b['timestamp'] <=> $a['timestamp']));
            $all = $posts;
            $published = array_values(array_filter($all, fn($p) => ($p['status'] ?? 'draft') === 'published'));
        }
    }

    return $includeDrafts ? $all : $published;
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

function get_adjacent_posts_by_slug(string $slug, bool $includeDrafts = false): array
{
    $posts = get_all_posts($includeDrafts);
    $count = count($posts);
    for ($i = 0; $i < $count; $i++) {
        if (($posts[$i]['slug'] ?? '') !== $slug) {
            continue;
        }

        return [
            'previous' => $posts[$i + 1] ?? null,
            'next' => $posts[$i - 1] ?? null,
        ];
    }

    return [
        'previous' => null,
        'next' => null,
    ];
}

function get_all_pages(bool $includeDrafts = false, bool $bustCache = false): array
{
    static $all = null;
    static $published = null;
    if ($bustCache) {
        $all = null;
        $published = null;
    }
    if ($all === null) {
        if (!is_dir(PUREBLOG_PAGES_PATH)) {
            $all = [];
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
            $all = $pages;
            $published = array_values(array_filter($all, fn($p) => ($p['status'] ?? 'draft') === 'published'));
        }
    }

    return $includeDrafts ? $all : $published;
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
    $content = str_replace("\r", '', $page['content'] ?? '');

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

    $wasPublished = ($originalStatus === 'published');
    $isPublished = ($status === 'published');

    if ($isPublished) {
        call_hook('on_page_updated', [$slug]);
        if (!$wasPublished) {
            call_hook('on_page_published', [$slug]);
        }
        if ($wasPublished && $originalSlug !== null && $originalSlug !== '' && $originalSlug !== $slug) {
            // Old URL can remain cached when a published page slug changes.
            call_hook('on_page_deleted', [$originalSlug]);
        }
    } elseif ($wasPublished) {
        // Page was removed from public output (unpublished).
        call_hook('on_page_deleted', [$slug]);
    }

    get_all_pages(true, true);
    cache_clear();
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

    $imageDir = PUREBLOG_CONTENT_IMAGES_PATH . '/' . $slug;
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

    cache_clear();
    return true;
}

function find_post_filepath_by_slug(string $slug): ?string
{
    foreach (get_all_posts(true) as $post) {
        if ($post['slug'] === $slug) {
            return $post['path'];
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
        $imageDir = PUREBLOG_CONTENT_IMAGES_PATH . '/' . $slug;
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
        cache_clear();
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
    $content = str_replace("\r", '', $post['content'] ?? '');
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

    $config = load_config();

    if ($date === '') {
        $date = current_site_datetime_for_storage($config);
    }

    $datePrefix = format_datetime_for_display($date, $config, 'Y-m-d');
    if ($datePrefix === '') {
        $datePrefix = format_datetime_for_display(current_site_datetime_for_storage($config), $config, 'Y-m-d');
    }
    $filename = $datePrefix . '-' . $slug . '.md';
    $path = PUREBLOG_POSTS_PATH . '/' . $filename;

    $layout = trim($post['layout'] ?? '');
    $layoutFields = is_array($post['layout_fields'] ?? null) ? $post['layout_fields'] : [];

    $frontMatter = [
        'title' => $title,
        'slug' => $slug,
        'date' => $date,
        'status' => $status,
        'tags' => $tags,
        'description' => $description,
    ];

    if ($layout !== '') {
        $frontMatter['layout'] = $layout;
    }

    foreach ($layoutFields as $fieldName => $fieldValue) {
        $fieldName = trim((string) $fieldName);
        if ($fieldName !== '') {
            $frontMatter[$fieldName] = trim((string) $fieldValue);
        }
    }

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
    } elseif ($originalSlug !== null) {
        $actualPath = find_post_filepath_by_slug($originalSlug);
        if ($actualPath !== null && $actualPath !== $path) {
            $renameFrom = $actualPath;
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
    cache_clear();

    if ($status === 'published') {
        call_hook('on_post_updated', [$slug]);
        if ($originalStatus !== 'published') {
            call_hook('on_post_published', [$slug]);
        }
    }
    return true;
}
