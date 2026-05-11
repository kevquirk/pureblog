<?php

declare(strict_types=1);

// PHP 7.4 polyfills for functions added in PHP 8.0.
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

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
const PUREBLOG_CACHE_PATH = PUREBLOG_BASE_PATH . '/cache';

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
        'search_excerpt_length' => 2500,
        'homepage_slug' => '',
        'blog_page_slug' => '',
        'search_page_slug' => 'search',
        'search_page_notified' => false,

        'base_url' => '',
        'language' => 'en',
        'timezone' => date_default_timezone_get(),
        'date_format' => 'F j, Y',
        'admin_username' => '',
        'admin_password_hash' => '',
        'cache' => [
            'enabled' => true,
            'rss_ttl' => 3600,
        ],
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
            'og_image_preferred' => 'banner',
        ],
    ];
}

function load_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    if (!file_exists(PUREBLOG_CONFIG_PATH)) {
        return $cfg = default_config();
    }

    $config = require PUREBLOG_CONFIG_PATH;
    if (!is_array($config)) {
        return $cfg = default_config();
    }

    return $cfg = array_replace_recursive(default_config(), $config);
}

function load_hooks(): void
{
    if (is_file(PUREBLOG_HOOKS_PATH)) {
        require_once PUREBLOG_HOOKS_PATH;
    }
}

require __DIR__ . '/includes/lib/i18n.php';
require __DIR__ . '/includes/lib/auth.php';
require __DIR__ . '/includes/lib/content.php';
require __DIR__ . '/includes/lib/template.php';
require __DIR__ . '/includes/lib/cache.php';

$_userFunctions = PUREBLOG_BASE_PATH . '/content/functions.php';
if (is_file($_userFunctions)) {
    require $_userFunctions;
}
unset($_userFunctions);
