<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

require_setup_redirect();

$config = load_config();

$feedCacheEnabled = !empty($config['cache']['enabled']);
$feedTtl = (int) ($config['cache']['rss_ttl'] ?? 3600);
if ($feedCacheEnabled) {
    $cachedXml = cache_read('__feed__', $feedTtl, 'xml');
    if ($cachedXml !== null) {
        header('Content-Type: application/rss+xml; charset=UTF-8');
        echo $cachedXml;
        exit;
    }
    ob_start();
}

$posts = array_slice(get_all_posts(false), 0, 10);

$baseUrl = trim($config['base_url'] ?? '');
if (PHP_SAPI === 'cli-server') {
    $baseUrl = get_base_url();
} elseif ($baseUrl === '') {
    $baseUrl = get_base_url();
}
$siteTitle = $config['site_title'] ?? 'My Blog';
$siteTagline = $config['site_tagline'] ?? '';
$baseUrl = rtrim($baseUrl, '/');

function absolutize_feed_html(string $html, string $baseUrl): string
{
    $patterns = [
        '/href=\"\\//i',
        '/src=\"\\//i',
    ];
    $replacements = [
        'href="' . $baseUrl . '/',
        'src="' . $baseUrl . '/',
    ];

    return preg_replace($patterns, $replacements, $html) ?? $html;
}

header('Content-Type: application/rss+xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0">
    <channel>
        <title><?= e($siteTitle) ?></title>
        <link><?= e($baseUrl) ?></link>
        <description><?= e($siteTagline !== '' ? $siteTagline : $siteTitle) ?></description>
        <language>en</language>
        <?php foreach ($posts as $post): ?>
            <?php
            $postUrl = $baseUrl . '/' . $post['slug'];
            $pubDate = format_post_date_for_rss((string) ($post['date'] ?? ''), $config);
            $content = render_markdown($post['content'], ['post_title' => (string) ($post['title'] ?? '')]);
            $content = absolutize_feed_html($content, $baseUrl);
            ?>
            <item>
                <title><?= e($post['title']) ?></title>
                <link><?= e($postUrl) ?></link>
                <guid><?= e($postUrl) ?></guid>
                <pubDate><?= e($pubDate) ?></pubDate>
                <description><![CDATA[<?= $content ?>]]></description>
            </item>
        <?php endforeach; ?>
    </channel>
</rss>
<?php
if ($feedCacheEnabled) {
    $xml = ob_get_clean();
    cache_write('__feed__', $xml, 'xml');
    echo $xml;
}
?>
