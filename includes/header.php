<?php
// Shared site document head + body open. Expects $config and $fontStack to be defined.
$siteTitle = $config['site_title'] ?? '';
$pageTitle = $pageTitle ?? $siteTitle;
$metaDescription = $metaDescription ?? '';
$siteDescription = trim((string) ($config['site_description'] ?? ''));
$metaDescription = $metaDescription !== '' ? $metaDescription : $siteDescription;
$mode = $config['theme']['color_mode'] ?? 'light';
$headInject = get_contextual_inject($config, 'head', [
    'post' => $post ?? null,
    'page' => $page ?? null,
]);
$frontCssVersion = (string) @filemtime(__DIR__ . '/../assets/css/style.css');
$ogImagePreferred = $config['assets']['og_image_preferred'] ?? 'banner';
$ogImageBanner = $config['assets']['og_image'] ?? '';
$ogImageSquare = $config['assets']['og_image_square'] ?? '';
$ogImage = $ogImagePreferred === 'square' && $ogImageSquare !== '' ? $ogImageSquare : $ogImageBanner;
$isSquareOgImage = $ogImagePreferred === 'square' && $ogImageSquare !== '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($mode) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $currentPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '', '/');
    $isHome = $currentPath === '';
    $fullTitle = $isHome ? $pageTitle : trim($pageTitle . ' - ' . $siteTitle);
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    if ($requestPath === '' || $requestPath === '/index.php') {
        $requestPath = '/';
    }
    $canonicalUrl = rtrim(get_base_url(), '/') . $requestPath;
    ?>
    <title><?= e($fullTitle) ?></title>
    <?php if ($metaDescription !== ''): ?>
        <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php if (!empty($config['assets']['favicon'])): ?>
        <link rel="icon" href="<?= e($config['assets']['favicon']) ?>">
    <?php endif; ?>
    <?php if ($ogImage !== ''): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
        <?php if ($isSquareOgImage): ?>
            <meta property="og:image:width" content="600">
            <meta property="og:image:height" content="600">
        <?php endif; ?>
    <?php endif; ?>
    <link rel="alternate" type="application/rss+xml" title="<?= e($config['site_title']) ?> RSS" href="/feed.php">
    <style>
        body { background: <?= e($config['theme']['background_color']) ?>; }
    </style>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= e($frontCssVersion) ?>">
    <style>
        :root {
            --bg-light: <?= e($config['theme']['background_color']) ?>;
            --text-light: <?= e($config['theme']['text_color']) ?>;
            --accent-light: <?= e($config['theme']['accent_color']) ?>;
            --border-light: <?= e($config['theme']['border_color']) ?>;
            --accent-bg-light: <?= e($config['theme']['accent_bg_color']) ?>;
            --bg-dark: <?= e($config['theme']['background_color_dark']) ?>;
            --text-dark: <?= e($config['theme']['text_color_dark']) ?>;
            --accent-dark: <?= e($config['theme']['accent_color_dark']) ?>;
            --border-dark: <?= e($config['theme']['border_color_dark']) ?>;
            --accent-bg-dark: <?= e($config['theme']['accent_bg_color_dark']) ?>;
            --font-stack: <?= $fontStack ?>;
        }
    <?php if (is_file(__DIR__ . '/../content/css/custom.css')): ?>
<?php readfile(__DIR__ . '/../content/css/custom.css'); ?>
<?php endif; ?>
    </style>
<?php if (trim($headInject) !== ''): ?>
<?= $headInject . "\n" ?>
    <?php endif; ?>
</head>
<body>
    <?php readfile(__DIR__ . '/../assets/icons/sprite.svg'); ?>
