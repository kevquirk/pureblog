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
$frontCssVersion = (string) @filemtime(PUREBLOG_BASE_PATH . '/assets/css/style.css');
$ogImagePreferred = $config['assets']['og_image_preferred'] ?? 'banner';
$featureImageRaw  = (is_array($post ?? null) ? ($post['feature_image'] ?? '') : '')
                 ?: (is_array($page ?? null) ? ($page['feature_image'] ?? '') : '');
if ($featureImageRaw !== '') {
    $ogImage = $featureImageRaw[0] === '/'
        ? get_base_url() . $featureImageRaw
        : $featureImageRaw;
    $isSquareOgImage = false;
} else {
    $customOgImage = trim((string) ($config['assets']['og_image'] ?? ''));
    if ($customOgImage !== '' && $customOgImage !== '/assets/images/og-image.png') {
        $ogImage = $customOgImage[0] === '/' ? get_base_url() . $customOgImage : $customOgImage;
        $isSquareOgImage = $ogImagePreferred === 'square';
    } elseif ($ogImagePreferred === 'banner') {
        $ogImage = get_dynamic_og_image_url($post ?? null, $page ?? null);
        $isSquareOgImage = false;
    } else {
        $ogImage = $customOgImage !== '' ? ($customOgImage[0] === '/' ? get_base_url() . $customOgImage : $customOgImage) : '';
        $isSquareOgImage = $ogImagePreferred === 'square';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e($config['language'] ?? 'en') ?>" data-theme="<?= e($mode) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="generator" content="Pure Blog">
    <?php
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $bp = base_path();
    if ($bp !== '' && str_starts_with($uriPath, $bp)) {
        $uriPath = substr($uriPath, strlen($bp));
    }
    $currentPath = trim($uriPath, '/');
    $isHome = $currentPath === '';
    $fullTitle = $isHome ? $pageTitle : trim($pageTitle . ' - ' . $siteTitle);
    $requestPath = $uriPath !== '' ? $uriPath : '/';
    if ($requestPath === '/index.php') {
        $requestPath = '/';
    }
    $canonicalUrl = get_base_url() . $requestPath;
    ?>
    <title><?= e($fullTitle) ?></title>
    <?php if ($metaDescription !== ''): ?>
        <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php if (!empty($config['assets']['favicon'])): ?>
        <?php $faviconHref = $config['assets']['favicon']; ?>
        <?php if ($faviconHref[0] === '/') { $faviconHref = get_base_url() . $faviconHref; } ?>
        <link rel="icon" href="<?= e($faviconHref) ?>">
        <link rel="apple-touch-icon" href="<?= e($faviconHref) ?>">
    <?php endif; ?>
    <meta property="og:type" content="<?= isset($post) ? 'article' : 'website' ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:site_name" content="<?= e($siteTitle) ?>">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <?php if ($metaDescription !== ''): ?>
        <meta property="og:description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <?php
    $ogLocaleMap = ['de' => 'de_DE', 'fr' => 'fr_FR', 'es' => 'es_ES', 'it' => 'it_IT', 'nl' => 'nl_NL', 'pt' => 'pt_PT', 'ro' => 'ro_RO'];
    $ogLocale = $ogLocaleMap[$config['language'] ?? 'en'] ?? 'en_US';
    ?>
    <meta property="og:locale" content="<?= e($ogLocale) ?>">
    <?php if ($ogImage !== ''): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
        <?php if ($isSquareOgImage): ?>
            <meta property="og:image:width" content="600">
            <meta property="og:image:height" content="600">
        <?php else: ?>
            <meta property="og:image:width" content="1360">
            <meta property="og:image:height" content="712">
        <?php endif; ?>
    <?php endif; ?>
    <link rel="alternate" type="application/rss+xml" title="<?= e($config['site_title']) ?> RSS" href="<?= get_base_url() ?>/feed">
    <?php
    $themePreview = null;
    if (function_exists('is_admin_logged_in') && !empty($_GET['theme_preview'])) {
        start_admin_session();
        if (is_admin_logged_in()) {
            $themePreview = get_theme_by_id((string) $_GET['theme_preview']);
        }
    }
    $bgLight = $themePreview['light']['bg'] ?? $config['theme']['background_color'];
    $textLight = $themePreview['light']['text'] ?? $config['theme']['text_color'];
    $accentLight = $themePreview['light']['accent'] ?? $config['theme']['accent_color'];
    $borderLight = $themePreview['light']['border'] ?? $config['theme']['border_color'];
    $accentBgLight = $themePreview['light']['accent_bg'] ?? $config['theme']['accent_bg_color'];

    $bgDark = $themePreview['dark']['bg'] ?? $config['theme']['background_color_dark'];
    $textDark = $themePreview['dark']['text'] ?? $config['theme']['text_color_dark'];
    $accentDark = $themePreview['dark']['accent'] ?? $config['theme']['accent_color_dark'];
    $borderDark = $themePreview['dark']['border'] ?? $config['theme']['border_color_dark'];
    $accentBgDark = $themePreview['dark']['accent_bg'] ?? $config['theme']['accent_bg_color_dark'];
    ?>
    <style>
        body { background: <?= e($bgLight) ?>; }
    </style>
    <?php $fontUrl = font_stack_url($config['theme']['font_stack'] ?? 'sans'); ?>
    <?php if ($fontUrl !== null): ?>
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="stylesheet" href="<?= e($fontUrl) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= get_base_url() ?>/assets/css/style.css?v=<?= e($frontCssVersion) ?>">
    <style>
        :root {
            --bg-light: <?= e($bgLight) ?>;
            --text-light: <?= e($textLight) ?>;
            --accent-light: <?= e($accentLight) ?>;
            --border-light: <?= e($borderLight) ?>;
            --accent-bg-light: <?= e($accentBgLight) ?>;
            --bg-dark: <?= e($bgDark) ?>;
            --text-dark: <?= e($textDark) ?>;
            --accent-dark: <?= e($accentDark) ?>;
            --border-dark: <?= e($borderDark) ?>;
            --accent-bg-dark: <?= e($accentBgDark) ?>;
            --font-stack: <?= $fontStack ?>;
            --mono-font-stack: <?= font_stack_css('mono') ?>;
        }
    <?php if (is_file(PUREBLOG_CONTENT_CSS_PATH . '/custom.css')): ?>
<?php readfile(PUREBLOG_CONTENT_CSS_PATH . '/custom.css'); ?>
<?php endif; ?>
    </style>
<?php if (trim($headInject) !== ''): ?>
<?= $headInject . "\n" ?>
    <?php endif; ?>
</head>
<body>
    <?php readfile(PUREBLOG_BASE_PATH . '/assets/icons/sprite.svg'); ?>
    <?php if ($themePreview !== null): ?>
        <aside class="theme-preview-banner" style="position: sticky; top: 0; z-index: 9999; background: var(--bg-color); color: var(--text-color); border-bottom: 2px solid var(--border-color); padding: 0.5rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; font-size: 0.85rem; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
            <span><?= e(t('admin.settings.theme.previewing')) ?>: <strong><?= e($themePreview['name'] ?? 'Theme') ?></strong> (<?= e(t('admin.settings.theme.preview_admin_only')) ?>)</span>
            <form method="post" action="<?= e(base_path()) ?>/admin/settings-theme.php" style="margin: 0; display: inline;">
                <input type="hidden" name="apply_theme_id" value="<?= e($themePreview['id'] ?? '') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="save" style="cursor: pointer; background: var(--bg-color); color: var(--green, #2e7d32); border: 2px solid var(--green, #2e7d32); text-transform: uppercase; font-weight: bold; padding: 0.35rem 0.65rem; font-size: 0.75rem; font-family: inherit; line-height: 1.15; border-radius: 0;">
                    <?= e(t('admin.settings.theme.apply')) ?>
                </button>
            </form>
        </aside>
    <?php endif; ?>
