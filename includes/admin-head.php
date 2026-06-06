<?php
// Shared admin <head>. Expects $adminTitle (optional) and $fontStack (optional).
// Compat shim: if this file is included mid-request by the old 1.9.7 updater,
// functions.php in memory won't have t() yet. Define a no-op fallback so the
// page renders (with raw keys) rather than crashing with "undefined function".
if (!function_exists('t')) {
    function t(string $key, array $replacements = []): string { return $key; }
}
$adminTitle = $adminTitle ?? 'Admin - Pureblog';
$blogPostsEnabled = $config['enable_blog_posts'] ?? true;
$fontStack = $fontStack ?? font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$adminColorMode = $adminColorMode ?? ($config['theme']['admin_color_mode'] ?? 'auto');
$extraHead = $extraHead ?? '';
$codeMirror = $codeMirror ?? null; // 'markdown' or 'css'
$hideAdminNav = $hideAdminNav ?? false;
$adminCssVersion = (string) @filemtime(__DIR__ . '/../admin/css/admin.css');

if (!$hideAdminNav && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['search_page_action'])) {
    verify_csrf();
    if ($_POST['search_page_action'] === 'create') {
        $newPage = [
            'title'          => 'Search',
            'slug'           => 'search',
            'status'         => 'published',
            'description'    => 'Search posts on this site.',
            'include_in_nav' => true,
            'content'        => '',
        ];
        $saveError = null;
        save_page($newPage, null, null, $saveError);
        $_SESSION['admin_action_flash'] = ['ok' => true, 'message' => t('admin.settings.site.notice_search_created')];
    }
    $config['search_page_notified'] = true;
    save_config($config);
    $defaultAdminLanding = base_path() . '/admin/' . ($blogPostsEnabled ? 'dashboard.php' : 'content.php');
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? $defaultAdminLanding));
    exit;
}

if (!$hideAdminNav && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['admin_action_id'])) {
    verify_csrf();
    $actionId = strtolower(trim((string) ($_POST['admin_action_id'] ?? '')));
    $actionId = preg_replace('/[^a-z0-9_-]/', '', $actionId) ?? '';
    if ($actionId === 'clear_cache') {
        cache_clear();
        $_SESSION['admin_action_flash'] = ['ok' => true, 'message' => t('admin.nav.cache_cleared')];
    } elseif ($actionId !== '') {
        $_SESSION['admin_action_flash'] = run_admin_action($actionId);
    } else {
        $_SESSION['admin_action_flash'] = ['ok' => false, 'message' => t('admin.nav.invalid_action')];
    }
    $defaultAdminLanding = base_path() . '/admin/' . ($blogPostsEnabled ? 'dashboard.php' : 'content.php');
    $redirectTo = (string) ($_SERVER['REQUEST_URI'] ?? $defaultAdminLanding);
    header('Location: ' . $redirectTo);
    exit;
}

$adminActionButtons = !$hideAdminNav ? get_admin_action_buttons() : [];
$adminActionFlash = $_SESSION['admin_action_flash'] ?? null;
unset($_SESSION['admin_action_flash']);
?>
<!DOCTYPE html>
<html lang="<?= e($config['language'] ?? 'en') ?>" data-admin-theme="<?= e($adminColorMode) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $adminFavicon = $config['assets']['favicon'] ?? '/assets/images/favicon.png'; ?>
    <?php if ($adminFavicon[0] === '/') { $adminFavicon = base_path() . $adminFavicon; } ?>
    <link rel="icon" href="<?= e($adminFavicon) ?>">
    <title><?= e($adminTitle) ?></title>
    <?php $adminFontUrl = font_stack_url($config['theme']['admin_font_stack'] ?? 'sans'); ?>
    <?php if ($adminFontUrl !== null): ?>
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="stylesheet" href="<?= e($adminFontUrl) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= base_path() ?>/admin/css/admin.css?v=<?= e($adminCssVersion) ?>">
    <script>try{if(localStorage.getItem('pb-sidebar')==='collapsed')document.documentElement.setAttribute('data-sidebar','collapsed');}catch(e){}</script>
    <style>
        :root {
            --font-stack: <?= $fontStack ?>;
        }
<?php if (is_file(__DIR__ . '/../content/css/admin-custom.css')): ?>
<?php readfile(__DIR__ . '/../content/css/admin-custom.css'); ?>
<?php endif; ?>
    </style>
    <?php if ($codeMirror === 'markdown'): ?>
        <link rel="stylesheet" href="https://unpkg.com/codemirror@5.65.16/lib/codemirror.css">
        <script src="https://unpkg.com/codemirror@5.65.16/lib/codemirror.js"></script>
        <script src="https://unpkg.com/codemirror@5.65.16/mode/markdown/markdown.js"></script>
        <script src="https://unpkg.com/codemirror@5.65.16/mode/xml/xml.js"></script>
        <script src="https://unpkg.com/codemirror@5.65.16/mode/htmlmixed/htmlmixed.js"></script>
        <script src="https://unpkg.com/codemirror@5.65.16/addon/edit/continuelist.js"></script>
    <?php elseif ($codeMirror === 'css'): ?>
        <link rel="stylesheet" href="https://unpkg.com/codemirror@5.65.16/lib/codemirror.css">
        <link rel="stylesheet" href="https://unpkg.com/codemirror@5.65.16/theme/material-darker.css">
        <script src="https://unpkg.com/codemirror@5.65.16/lib/codemirror.js"></script>
        <script src="https://unpkg.com/codemirror@5.65.16/addon/display/placeholder.js"></script>
        <script src="https://unpkg.com/codemirror@5.65.16/mode/css/css.js"></script>
    <?php endif; ?>
    <?= $extraHead ?>
</head>
<body>
    <!-- SVG sprite: add support for rendering admin icons via <use> -->
    <?php readfile(__DIR__ . '/../admin/icons/sprite.svg'); ?>
    <div class="admin-shell">
    <?php if (!$hideAdminNav): ?>
        <?php
        $adminUriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $bp = base_path();
        if ($bp !== '' && str_starts_with($adminUriPath, $bp)) {
            $adminUriPath = substr($adminUriPath, strlen($bp));
        }
        $adminPath = trim($adminUriPath, '/');
        $isSettings = str_starts_with($adminPath, 'admin/settings');
        ?>
        <?php
        $adminHomepageSetting = $blogPostsEnabled ? ($config['admin_homepage'] ?? 'dashboard') : 'content';
        $adminHideDashboard = !$blogPostsEnabled || (!empty($config['admin_hide_dashboard']) && $adminHomepageSetting === 'content');
        $defaultTab = $blogPostsEnabled ? 'posts' : 'pages';
        ?>
        <nav class="admin-nav" aria-label="Admin">
            <div class="sidebar-header">
                <a href="<?= base_path() ?>/admin/<?= $adminHomepageSetting === 'content' ? 'content.php' : 'dashboard.php' ?>" class="sidebar-logo">
                    <span class="logo"><span class="pure">PURE</span><span class="service">BLOG</span></span>
                </a>
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
                    <svg class="icon" aria-hidden="true"><use href="#icon-panel-left-close"></use></svg>
                    <svg class="icon" aria-hidden="true"><use href="#icon-panel-left-open"></use></svg>
                </button>
            </div>
            <?php $sidebarLayouts = get_layouts(); ?>
            <ul class="admin-nav-list">
                <li>
                    <?php if (!$blogPostsEnabled): ?>
                        <a class="save link-button" href="<?= base_path() ?>/admin/edit-page.php?action=new" title="<?= e(t('admin.pages.new_page')) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#icon-file-plus-corner"></use></svg>
                            <span><?= e(t('admin.pages.new_page')) ?></span>
                        </a>
                    <?php elseif ($sidebarLayouts): ?>
                        <button type="button" id="sidebar-new-post-button" class="save link-button js-open-layout-picker" title="<?= e(t('admin.dashboard.write_post')) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#icon-file-plus-corner"></use></svg>
                            <span><?= e(t('admin.dashboard.write_post')) ?></span>
                        </button>
                    <?php else: ?>
                        <a class="save link-button" href="<?= base_path() ?>/admin/edit-post.php?action=new" title="<?= e(t('admin.dashboard.write_post')) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#icon-file-plus-corner"></use></svg>
                            <span><?= e(t('admin.dashboard.write_post')) ?></span>
                        </a>
                    <?php endif; ?>
                </li>
                <?php if ($adminHomepageSetting === 'content'): ?>
                    <li>
                        <a href="<?= base_path() ?>/admin/content.php"<?= $adminPath === 'admin/content.php' ? ' class="current"' : '' ?> title="<?= e(t('admin.nav.content')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-file-text"></use></svg><span><?= e(t('admin.nav.content')) ?></span></a>
                        <?php if ($adminPath === 'admin/content.php' && $blogPostsEnabled): ?>
                        <ul class="admin-nav-list sidebar-subnav">
                            <li><a href="<?= base_path() ?>/admin/content.php?tab=posts"<?= ($tab ?? $defaultTab) === 'posts' ? ' class="current"' : '' ?> title="<?= e(t('admin.content.tab_posts')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-notebook-pen"></use></svg><span><?= e(t('admin.content.tab_posts')) ?></span></a></li>
                            <li><a href="<?= base_path() ?>/admin/content.php?tab=pages"<?= ($tab ?? $defaultTab) === 'pages' ? ' class="current"' : '' ?> title="<?= e(t('admin.content.tab_pages')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-file-text"></use></svg><span><?= e(t('admin.content.tab_pages')) ?></span></a></li>
                        </ul>
                        <?php endif; ?>
                    </li>
                    <?php if (!$adminHideDashboard): ?>
                        <li><a href="<?= base_path() ?>/admin/dashboard.php"<?= $adminPath === 'admin/dashboard.php' ? ' class="current"' : '' ?> title="<?= e(t('admin.nav.dashboard')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-circle-gauge"></use></svg><span><?= e(t('admin.nav.dashboard')) ?></span></a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li><a href="<?= base_path() ?>/admin/dashboard.php"<?= $adminPath === 'admin/dashboard.php' ? ' class="current"' : '' ?> title="<?= e(t('admin.nav.dashboard')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-circle-gauge"></use></svg><span><?= e(t('admin.nav.dashboard')) ?></span></a></li>
                    <li>
                        <a href="<?= base_path() ?>/admin/content.php"<?= $adminPath === 'admin/content.php' ? ' class="current"' : '' ?> title="<?= e(t('admin.nav.content')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-file-text"></use></svg><span><?= e(t('admin.nav.content')) ?></span></a>
                        <?php if ($adminPath === 'admin/content.php' && $blogPostsEnabled): ?>
                        <ul class="admin-nav-list sidebar-subnav">
                            <li><a href="<?= base_path() ?>/admin/content.php?tab=posts"<?= ($tab ?? $defaultTab) === 'posts' ? ' class="current"' : '' ?> title="<?= e(t('admin.content.tab_posts')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-notebook-pen"></use></svg><span><?= e(t('admin.content.tab_posts')) ?></span></a></li>
                            <li><a href="<?= base_path() ?>/admin/content.php?tab=pages"<?= ($tab ?? $defaultTab) === 'pages' ? ' class="current"' : '' ?> title="<?= e(t('admin.content.tab_pages')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-file-text"></use></svg><span><?= e(t('admin.content.tab_pages')) ?></span></a></li>
                        </ul>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="<?= base_path() ?>/admin/images.php"<?= $adminPath === 'admin/images.php' ? ' class="current"' : '' ?> title="<?= e(t('admin.nav.images')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-image"></use></svg><span><?= e(t('admin.nav.images')) ?></span></a>
                </li>
                <li>
                    <a href="<?= base_path() ?>/admin/settings-site.php"<?= $isSettings ? ' class="current"' : '' ?> title="<?= e(t('admin.nav.settings')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-settings"></use></svg><span><?= e(t('admin.nav.settings')) ?></span></a>
                    <?php if ($isSettings): ?>
                    <?php
                    $sidebarSettingsPath = $adminPath;
                    $sidebarSettingsItems = [
                        'admin/settings-site.php'    => ['label' => t('admin.settings.nav.site'),    'icon' => 'globe'],
                        'admin/settings-theme.php'   => ['label' => t('admin.settings.nav.theme'),   'icon' => 'paintbrush'],
                        'admin/settings-css.php'     => ['label' => t('admin.settings.nav.css'),     'icon' => 'braces'],
                        'admin/settings-user.php'    => ['label' => t('admin.settings.nav.user'),    'icon' => 'user'],
                        'admin/settings-updates.php' => ['label' => t('admin.settings.nav.updates'), 'icon' => 'upgrade'],
                    ];
                    ?>
                    <ul class="admin-nav-list sidebar-subnav">
                        <?php foreach ($sidebarSettingsItems as $sPath => $sItem): ?>
                            <li><a href="<?= base_path() ?>/<?= e($sPath) ?>"<?= $sidebarSettingsPath === $sPath ? ' class="current"' : '' ?> title="<?= e($sItem['label']) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-<?= e($sItem['icon']) ?>"></use></svg><span><?= e($sItem['label']) ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </li>
                <li><a target="_blank" rel="noopener noreferrer" href="<?= base_path() ?>/" title="<?= e(t('admin.nav.view_site')) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-eye"></use></svg><span><?= e(t('admin.nav.view_site')) ?></span></a></li>
                <?php if (!empty($config['cache']['enabled'])): ?>
                    <li>
                        <form method="post" action="<?= e($_SERVER['REQUEST_URI'] ?? (base_path() . '/admin/' . ($blogPostsEnabled ? 'dashboard.php' : 'content.php'))) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="admin_action_id" value="clear_cache">
                            <button class="delete link-button" type="submit" title="<?= e(t('admin.nav.clear_cache')) ?>">
                                <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                                <span><?= e(t('admin.nav.clear_cache')) ?></span>
                            </button>
                        </form>
                    </li>
                <?php endif; ?>
                <?php foreach ($adminActionButtons as $actionButton): ?>
                    <?php
                    $buttonClass = trim('link-button ' . $actionButton['class']);
                    $confirmAttr = $actionButton['confirm'] !== '' ? ' onclick="return confirm(\'' . e($actionButton['confirm']) . '\');"' : '';
                    ?>
                    <li>
                        <form method="post" action="<?= e($_SERVER['REQUEST_URI'] ?? (base_path() . '/admin/' . ($blogPostsEnabled ? 'dashboard.php' : 'content.php'))) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="admin_action_id" value="<?= e($actionButton['id']) ?>">
                            <button type="submit" class="<?= e($buttonClass) ?>"<?= $confirmAttr ?> title="<?= e($actionButton['label']) ?>">
                                <?php if ($actionButton['icon'] !== ''): ?>
                                    <svg class="icon" aria-hidden="true"><use href="#icon-<?= e($actionButton['icon']) ?>"></use></svg>
                                <?php endif; ?>
                                <span><?= e($actionButton['label']) ?></span>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <li class="logout-item">
                    <form method="post" action="<?= base_path() ?>/admin/logout.php" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="link-button delete logout-button" title="<?= e(t('admin.nav.log_out')) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#icon-log-out"></use></svg>
                            <span><?= e(t('admin.nav.log_out')) ?></span>
                        </button>
                    </form>
                </li>
            </ul>
            <?php if ($sidebarLayouts): ?>
                <dialog id="sidebar-layout-picker" aria-labelledby="sidebar-layout-picker-title">
                    <h2 id="sidebar-layout-picker-title"><?= e(t('admin.content.choose_layout')) ?></h2>
                    <ul class="layout-picker-list">
                        <li><a href="<?= base_path() ?>/admin/edit-post.php?action=new"><?= e(t('admin.content.default_post')) ?></a></li>
                        <?php foreach ($sidebarLayouts as $layout): ?>
                            <li><a href="<?= base_path() ?>/admin/edit-post.php?action=new&amp;layout=<?= urlencode($layout['name']) ?>"><?= e($layout['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" id="layout-picker-close" class="delete">
                        <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                        <?= e(t('admin.content.cancel')) ?>
                    </button>
                </dialog>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <div class="admin-main">
    <?php if (!$hideAdminNav): ?>
        <div class="mobile-nav-header">
            <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Open navigation">
                <svg class="icon" aria-hidden="true"><use href="#icon-menu"></use></svg>
            </button>
        </div>
    <?php endif; ?>
    <div class="admin-content">
    <?php if (!$hideAdminNav): ?>
        <?php if (is_array($adminActionFlash) && isset($adminActionFlash['message'])): ?>
            <?php $flashOk = (bool) ($adminActionFlash['ok'] ?? false); ?>
            <p class="notice<?= $flashOk ? '' : ' delete' ?>" data-auto-dismiss><?= e((string) $adminActionFlash['message']) ?></p>
        <?php endif; ?>
        <?php
        $searchSlug = trim((string) ($config['search_page_slug'] ?? 'search'));
        if ($searchSlug !== '' && empty($config['search_page_notified']) && get_page_by_slug($searchSlug) === null):
        ?>
            <div class="notice">
                <?= e(t('admin.settings.site.notice_search_missing', ['slug' => $searchSlug])) ?>
                <form method="post" action="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="search_page_action" value="create">
                    <button type="submit" class="autosave-btn"><?= e(t('admin.settings.site.create_search_page')) ?></button>
                </form>
                <form method="post" action="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="search_page_action" value="dismiss">
                    <button type="submit" class="autosave-btn delete"><?= e(t('admin.settings.site.dismiss_search_notice')) ?></button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
