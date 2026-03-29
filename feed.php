<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

require_setup_redirect();

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

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<meta charset="utf-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    // Absolutize href attributes, skipping <code> and <pre> descendants
    foreach ($xpath->query('//*[@href and not(ancestor::code) and not(ancestor::pre)]') as $node) {
        $href = $node->getAttribute('href');
        if (str_starts_with($href, '/')) {
            $node->setAttribute('href', $baseUrl . $href);
        } elseif ($postUrl !== '' && str_starts_with($href, '#')) {
            $node->setAttribute('href', $postUrl . $href);
        }
    }

    // Absolutize src attributes, skipping <code> and <pre> descendants
    foreach ($xpath->query('//*[@src and not(ancestor::code) and not(ancestor::pre)]') as $node) {
        $src = $node->getAttribute('src');
        if (str_starts_with($src, '/')) {
            $node->setAttribute('src', $baseUrl . $src);
        }
    }

    // Extract just the body content (strip the wrapping html/body tags loadHTML adds)
    $body = $doc->getElementsByTagName('body')->item(0);
    if ($body === null) {
        return $html;
    }

    $result = '';
    foreach ($body->childNodes as $child) {
        $result .= $doc->saveHTML($child);
    }

    return $result;
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
