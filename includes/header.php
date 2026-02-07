<?php
// Shared site head + header. Expects $config and $fontStack to be defined.
$siteTitle = $config['site_title'] ?? '';
$pageTitle = $pageTitle ?? $siteTitle;
$metaDescription = $metaDescription ?? '';
$siteDescription = trim((string) ($config['site_description'] ?? ''));
$metaDescription = $metaDescription !== '' ? $metaDescription : $siteDescription;
$mode = $config['theme']['color_mode'] ?? 'light';
$siteTagline = trim((string) ($config['site_tagline'] ?? ''));
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
    ?>
    <title><?= e($fullTitle) ?></title>
    <?php if ($metaDescription !== ''): ?>
        <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <?php if (!empty($config['assets']['favicon'])): ?>
        <link rel="icon" href="<?= e($config['assets']['favicon']) ?>">
    <?php endif; ?>
    <?php if (!empty($config['assets']['og_image'])): ?>
        <meta property="og:image" content="<?= e($config['assets']['og_image']) ?>">
    <?php endif; ?>
    <link rel="alternate" type="application/rss+xml" title="<?= e($config['site_title']) ?> RSS" href="/feed.php">
    <link rel="stylesheet" href="/assets/css/style.css">
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
<?php if (is_file(__DIR__ . '/../assets/css/custom.css')): ?>
<?php readfile(__DIR__ . '/../assets/css/custom.css'); ?>
<?php endif; ?>
    </style>
</head>
<body>
    <?php readfile(__DIR__ . '/../assets/icons/sprite.svg'); ?>
<header>
    <h1 ><?= e($config['site_title']) ?></h1>
    <?php if ($siteTagline !== ''): ?>
    <p class="tagline"><?= e($siteTagline) ?></p>
    <?php endif; ?>
    <?php
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
    ?>
    <?php if ($navPages || $customNavItems): ?>
        <nav class="site-nav">
            <ul>
                <li><a href="/"<?= $currentPath === '' ? ' class="current"' : '' ?>>Home</a></li>
                <?php foreach ($navPages as $navPage): ?>
                    <?php $isCurrent = $currentPath === $navPage['slug']; ?>
                    <li><a href="/<?= e($navPage['slug']) ?>"<?= $isCurrent ? ' class="current"' : '' ?>><?= e($navPage['title']) ?></a></li>
                <?php endforeach; ?>
                <?php foreach ($customNavItems as $item): ?>
                    <li><a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
</header>
