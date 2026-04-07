<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

require_setup_redirect();
send_security_headers();

$config = load_config();

$feedCacheEnabled = !empty($config['cache']['enabled']);
$feedTtl = (int) ($config['cache']['rss_ttl'] ?? 3600);

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

// Determine last-modified from the newest post date.
$newestTimestamp = 0;
foreach ($posts as $post) {
    $ts = strtotime((string) ($post['date'] ?? '')) ?: 0;
    if ($ts > $newestTimestamp) {
        $newestTimestamp = $ts;
    }
}
if ($newestTimestamp === 0) {
    $newestTimestamp = time();
}

$etag         = '"' . md5((string) $newestTimestamp) . '"';
$lastModified = gmdate('D, d M Y H:i:s', $newestTimestamp) . ' GMT';

// Return 304 if the client's cached copy is still fresh.
$ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
$ifModSince  = trim($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
$clientFresh = ($ifNoneMatch !== '' && $ifNoneMatch === $etag) ||
               ($ifModSince  !== '' && (strtotime($ifModSince) ?: 0) >= $newestTimestamp);
if ($clientFresh) {
    http_response_code(304);
    exit;
}

header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: public, max-age=' . $feedTtl);
header('Last-Modified: ' . $lastModified);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $feedTtl) . ' GMT');
header('ETag: ' . $etag);

// Serve from server-side cache if available.
if ($feedCacheEnabled) {
    $cachedXml = cache_read('__feed__', $feedTtl, 'xml');
    if ($cachedXml !== null) {
        echo $cachedXml;
        exit;
    }
    ob_start();
}

function absolutize_feed_html(string $html, string $baseUrl, string $postUrl = ''): string
{
    if (trim($html) === '') {
        return $html;
    }

    // Temporarily replace <pre>/<code> blocks so their contents are not modified.
    $placeholders = [];
    $html = preg_replace_callback(
        '/<(pre|code)[^>]*>.*?<\/\1>/si',
        function ($match) use (&$placeholders): string {
            $key = "\x00PH_" . count($placeholders) . "\x00";
            $placeholders[$key] = $match[0];
            return $key;
        },
        $html
    ) ?? $html;

    $html = preg_replace('/href="\//', 'href="' . $baseUrl . '/', $html) ?? $html;
    $html = preg_replace('/src="\//', 'src="' . $baseUrl . '/', $html) ?? $html;
    if ($postUrl !== '') {
        $html = preg_replace('/href="#/', 'href="' . $postUrl . '#', $html) ?? $html;
    }

    // Restore <pre>/<code> blocks.
    if ($placeholders !== []) {
        $html = str_replace(array_keys($placeholders), array_values($placeholders), $html);
    }

    return $html;
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title><?= e($siteTitle) ?></title>
        <link><?= e($baseUrl) ?></link>
        <atom:link href="<?= e($baseUrl) ?>/feed" rel="self" type="application/rss+xml"/>
        <description><?= e($siteTagline !== '' ? $siteTagline : $siteTitle) ?></description>
        <language><?= e($config['language'] ?? 'en') ?></language>
        <?php foreach ($posts as $post): ?>
            <?php
            $postUrl = $baseUrl . '/' . $post['slug'];
            $pubDate = format_post_date_for_rss((string) ($post['date'] ?? ''), $config);
            $content = render_markdown($post['content'], ['post_title' => (string) ($post['title'] ?? '')]);
            $content = absolutize_feed_html($content, $baseUrl, $postUrl);
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
