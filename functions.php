<?php

declare(strict_types=1);

const PUREBLOG_BASE_PATH = __DIR__;
const PUREBLOG_VERSION_FILE = PUREBLOG_BASE_PATH . '/VERSION';
const PUREBLOG_CONFIG_PATH = PUREBLOG_BASE_PATH . '/config/config.php';
const PUREBLOG_POSTS_PATH = PUREBLOG_BASE_PATH . '/content/posts';
const PUREBLOG_PAGES_PATH = PUREBLOG_BASE_PATH . '/content/pages';
const PUREBLOG_SEARCH_INDEX_PATH = PUREBLOG_BASE_PATH . '/content/search-index.json';
const PUREBLOG_TAG_INDEX_PATH = PUREBLOG_BASE_PATH . '/content/tag-index.json';
const PUREBLOG_DATA_PATH = PUREBLOG_BASE_PATH . '/data';
const PUREBLOG_CONTENT_IMAGES_PATH = PUREBLOG_BASE_PATH . '/content/images';
const PUREBLOG_CONTENT_CSS_PATH = PUREBLOG_BASE_PATH . '/content/css';
const PUREBLOG_HOOKS_PATH = PUREBLOG_BASE_PATH . '/config/hooks.php';

function detect_pureblog_version(): string
{
    if (!is_file(PUREBLOG_VERSION_FILE)) {
        return 'unknown';
    }

    $raw = @file_get_contents(PUREBLOG_VERSION_FILE);
    if (!is_string($raw)) {
        return 'unknown';
    }

    $version = trim($raw);
    return $version !== '' ? $version : 'unknown';
}

if (!defined('PUREBLOG_VERSION')) {
    define('PUREBLOG_VERSION', detect_pureblog_version());
}

function default_config(): array
{
    return [
        'site_title' => 'My Blog',
        'site_tagline' => '',
        'site_description' => '',
        'site_email' => '',
        'custom_nav' => '',
        'custom_routes' => '',
        'head_inject_page' => '',
        'head_inject_post' => '',
        'footer_inject_page' => '',
        'footer_inject_post' => '',
        'posts_per_page' => 20,
        'homepage_slug' => '',
        'blog_page_slug' => '',
        'hide_homepage_title' => true,
        'hide_blog_page_title' => true,
        'base_url' => '',
        'timezone' => date_default_timezone_get(),
        'date_format' => 'F j, Y',
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
            'og_image_square' => '',
            'og_image_preferred' => 'banner',
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

/**
 * @return list<array{id:string,label:string,class:string,confirm:string,icon:string}>
 */
function get_admin_action_buttons(): array
{
    load_hooks();
    if (!function_exists('admin_action_buttons')) {
        return [];
    }

    $raw = admin_action_buttons();
    if (!is_array($raw)) {
        return [];
    }

    $buttons = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = strtolower(trim((string) ($item['id'] ?? '')));
        $id = preg_replace('/[^a-z0-9_-]/', '', $id) ?? '';
        $label = trim((string) ($item['label'] ?? ''));
        if ($id === '' || $label === '') {
            continue;
        }

        $class = trim((string) ($item['class'] ?? ''));
        $class = preg_replace('/[^a-zA-Z0-9_ -]/', '', $class) ?? '';

        $buttons[] = [
            'id' => $id,
            'label' => $label,
            'class' => $class,
            'confirm' => trim((string) ($item['confirm'] ?? '')),
            'icon' => trim((string) ($item['icon'] ?? '')),
        ];
    }

    return $buttons;
}

/**
 * @return array{ok:bool,message:string}
 */
function run_admin_action(string $actionId): array
{
    load_hooks();
    if (!function_exists('on_admin_action')) {
        return ['ok' => false, 'message' => 'No admin action handler is configured.'];
    }

    try {
        $result = on_admin_action($actionId);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Action failed: ' . $e->getMessage()];
    }

    if (is_array($result)) {
        $ok = (bool) ($result['ok'] ?? false);
        $message = trim((string) ($result['message'] ?? ''));
        if ($message === '') {
            $message = $ok ? 'Action completed.' : 'Action failed.';
        }
        return ['ok' => $ok, 'message' => $message];
    }

    if (is_bool($result)) {
        return [
            'ok' => $result,
            'message' => $result ? 'Action completed.' : 'Action failed.',
        ];
    }

    if (is_string($result) && trim($result) !== '') {
        return ['ok' => true, 'message' => trim($result)];
    }

    return ['ok' => true, 'message' => 'Action completed.'];
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
    $value = trim($value);
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }

    $value = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $value) ?? '';
    $value = preg_replace('/[\s-]+/u', '-', $value) ?? '';

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
    return $dt->format($effectiveFormat);
}

function format_post_date_for_rss(?string $value, array $config): string
{
    $dt = parse_post_datetime_with_timezone($value, $config);
    if (!$dt) {
        return (new DateTimeImmutable('now', site_timezone_object($config)))->format(DATE_RSS);
    }

    return $dt->format(DATE_RSS);
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

            $config = load_config();
            foreach ($files as $file) {
                $parsed = parse_post_file($file);
                $front = $parsed['front_matter'];
                $status = $front['status'] ?? 'draft';

                $dateString = $front['date'] ?? '';
                $dt = parse_post_datetime_with_timezone($dateString, $config);
                $timestamp = $dt ? $dt->getTimestamp() : 0;

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
        $originalPrefix = format_datetime_for_display($originalDate, $config, 'Y-m-d');
        if ($originalPrefix === '') {
            $normalizedOriginal = normalize_date_value($originalDate);
            $originalPrefix = $normalizedOriginal !== null
                ? format_datetime_for_display($normalizedOriginal, $config, 'Y-m-d')
                : '';
        }
        if ($originalPrefix === '') {
            $originalPrefix = $datePrefix;
        }
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

function render_markdown(string $markdown, array $context = []): string
{
    $markdown = filter_content($markdown, $context);
    $parsedown = get_markdown_parser();

    $html = $parsedown->text($markdown);

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

function get_markdown_parser(): object
{
    static $parsedown = null;
    if ($parsedown !== null) {
        return $parsedown;
    }

    require_once __DIR__ . '/lib/Parsedown.php';
    if (is_file(__DIR__ . '/lib/ParsedownExtra.php')) {
        require_once __DIR__ . '/lib/ParsedownExtra.php';
    }

    if (class_exists('ParsedownExtra')) {
        $parsedown = new ParsedownExtra();
    } else {
        $parsedown = new Parsedown();
    }
    $parsedown->setSafeMode(false);

    return $parsedown;
}

function load_yaml_list(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return [];
    }

    $items = [];
    $current = null;
    foreach ($lines as $line) {
        $line = rtrim($line);
        if ($line === '' || str_starts_with(ltrim($line), '#')) {
            continue;
        }

        if (preg_match('/^\s*-\s*(.*)$/', $line, $matches)) {
            if ($current !== null) {
                $items[] = $current;
            }
            $current = [];
            $rest = trim($matches[1]);
            if ($rest !== '' && strpos($rest, ':') !== false) {
                [$key, $value] = array_map('trim', explode(':', $rest, 2));
                $current[$key] = trim($value, "\"'");
            }
            continue;
        }

        if ($current === null || strpos(ltrim($line), ':') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode(':', trim($line), 2));
        $current[$key] = trim($value, "\"'");
    }

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

function render_liquid_loop(string $markdown, string $dataFile, string $pattern): string
{
    if (!preg_match($pattern, $markdown, $matches)) {
        return $markdown;
    }

    $items = load_yaml_list($dataFile);
    if (!$items) {
        return preg_replace($pattern, '', $markdown, 1);
    }

    $template = $matches[1];
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

    return preg_replace($pattern, $rendered, $markdown, 1);
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

    $markdown = restore_inline_code_spans($markdown, $inlineCodeSpans);

    return restore_fenced_code_blocks($markdown, $codeBlocks);
}

function get_excerpt(string $markdown, int $length = 200): string
{
    $parts = explode('<!--more-->', $markdown, 2);
    $excerpt = $parts[0];
    $excerpt = preg_replace('/```.*?```/s', ' ', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/`[^`]*`/', ' ', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/!\[[^\]]*\]\([^)]+\)/', ' ', $excerpt) ?? $excerpt;
    $excerpt = preg_replace('/\[([^\]]*)\]\([^)]+\)/', '$1', $excerpt) ?? $excerpt;
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
        $links[] = '<a href="/tag/' . e(rawurlencode($slug)) . '">' . e($tag) . '</a>';
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

function resolve_layout_file(string $name): ?string
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
    $footerPath = resolve_layout_file('footer') ?? (PUREBLOG_BASE_PATH . '/includes/footer.php');

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
    $mastheadPath = resolve_layout_file('masthead') ?? (PUREBLOG_BASE_PATH . '/includes/masthead.php');

    $siteTagline = trim((string) ($config['site_tagline'] ?? ''));
    $currentPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '', '/');
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
            !preg_match('#^/[a-zA-Z0-9/_-]+$#', $path)
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
