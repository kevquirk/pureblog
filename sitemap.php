<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

require_setup_redirect();

$config  = load_config();
$baseUrl = rtrim(get_base_url(), '/');

header('Content-Type: application/xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Homepage
echo '<url><loc>' . e($baseUrl) . '/</loc><priority>1.0</priority></url>' . "\n";

// Published pages
foreach (get_all_pages(false) as $page) {
    $slug = trim((string) ($page['slug'] ?? ''));
    if ($slug === '') {
        continue;
    }
    $lastmod = date('Y-m-d', (int) filemtime((string) $page['path']));
    echo '<url>';
    echo '<loc>' . e($baseUrl . '/' . $slug) . '</loc>';
    echo '<lastmod>' . $lastmod . '</lastmod>';
    echo '<priority>0.8</priority>';
    echo '</url>' . "\n";
}

// Published posts
foreach (get_all_posts(false) as $post) {
    $slug = trim((string) ($post['slug'] ?? ''));
    if ($slug === '') {
        continue;
    }
    $lastmod = $post['timestamp'] > 0 ? date('Y-m-d', $post['timestamp']) : substr((string) $post['date'], 0, 10);
    echo '<url>';
    echo '<loc>' . e($baseUrl . '/' . $slug) . '</loc>';
    echo '<lastmod>' . $lastmod . '</lastmod>';
    echo '<priority>0.9</priority>';
    echo '</url>' . "\n";
}

echo '</urlset>' . "\n";
