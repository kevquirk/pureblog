<?php
// Shared admin <head>. Expects $adminTitle (optional) and $fontStack (optional).
// Compat shim: if this file is included mid-request by the old 1.9.7 updater,
// functions.php in memory won't have t() yet. Define a no-op fallback so the
// page renders (with raw keys) rather than crashing with "undefined function".
if (!function_exists('t')) {
    function t(string $key, array $replacements = []): string { return $key; }
}
$adminTitle = $adminTitle ?? 'Admin - Pureblog';
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
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php'));
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
    $redirectTo = (string) ($_SERVER['REQUEST_URI'] ?? admin_url('dashboard.php'));
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
    <link rel="stylesheet" href="<?= base_path() ?>/admin/css/admin.css?v=<?= e($adminCssVersion) ?>">
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
        $adminHomepageSetting = $config['admin_homepage'] ?? 'dashboard';
        $adminHideDashboard = !empty($config['admin_hide_dashboard']) && $adminHomepageSetting === 'content';
        ?>
        <nav class="admin-nav" aria-label="Admin">
            <ul class="admin-nav-list">
                <?php if ($adminHomepageSetting === 'content'): ?>
                    <li><a href="<?= base_path() ?>/admin/content.php"<?= $adminPath === 'admin/content.php' ? ' class="current"' : '' ?>><svg class="icon" aria-hidden="true"><use href="#icon-file-text"></use></svg> <?= e(t('admin.nav.content')) ?></a></li>
                    <?php if (!$adminHideDashboard): ?>
                        <li><a href="<?= base_path() ?>/admin/dashboard.php"<?= $adminPath === 'admin/dashboard.php' ? ' class="current"' : '' ?>><svg class="icon" aria-hidden="true"><use href="#icon-circle-gauge"></use></svg> <?= e(t('admin.nav.dashboard')) ?></a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li><a href="<?= base_path() ?>/admin/dashboard.php"<?= $adminPath === 'admin/dashboard.php' ? ' class="current"' : '' ?>><svg class="icon" aria-hidden="true"><use href="#icon-circle-gauge"></use></svg> <?= e(t('admin.nav.dashboard')) ?></a></li>
                    <li><a href="<?= base_path() ?>/admin/content.php"<?= $adminPath === 'admin/content.php' ? ' class="current"' : '' ?>><svg class="icon" aria-hidden="true"><use href="#icon-file-text"></use></svg> <?= e(t('admin.nav.content')) ?></a></li>
                <?php endif; ?>
                <li><a href="<?= base_path() ?>/admin/settings-site.php"<?= $isSettings ? ' class="current"' : '' ?>><svg class="icon" aria-hidden="true"><use href="#icon-settings"></use></svg> <?= e(t('admin.nav.settings')) ?></a></li>
                <li><a target="_blank" rel="noopener noreferrer" href="<?= base_path() ?>/"><svg class="icon" aria-hidden="true"><use href="#icon-eye"></use></svg> <?= e(t('admin.nav.view_site')) ?></a></li>
                <?php if (!empty($config['cache']['enabled'])): ?>
                    <li>
                        <form method="post" action="<?= e($_SERVER['REQUEST_URI'] ?? admin_url('dashboard.php')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="admin_action_id" value="clear_cache">
                            <button class="delete" type="submit" class="link-button">
                                <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                                <?= e(t('admin.nav.clear_cache')) ?>
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
                        <form method="post" action="<?= e($_SERVER['REQUEST_URI'] ?? admin_url('dashboard.php')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="admin_action_id" value="<?= e($actionButton['id']) ?>">
                            <button type="submit" class="<?= e($buttonClass) ?>"<?= $confirmAttr ?>>
                                <?php if ($actionButton['icon'] !== ''): ?>
                                    <svg class="icon" aria-hidden="true"><use href="#icon-<?= e($actionButton['icon']) ?>"></use></svg>
                                <?php endif; ?>
                                <?= e($actionButton['label']) ?>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <li>
                    <form method="post" action="<?= base_path() ?>/admin/logout.php" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="link-button delete">
                            <svg class="icon" aria-hidden="true"><use href="#icon-log-out"></use></svg>
                            <?= e(t('admin.nav.log_out')) ?>
                        </button>
                    </form>
                </li>
            </ul>
        </nav>
        <?php if (is_array($adminActionFlash) && isset($adminActionFlash['message'])): ?>
            <?php $flashOk = (bool) ($adminActionFlash['ok'] ?? false); ?>
            <p class="notice<?= $flashOk ? '' : ' delete' ?>" data-auto-dismiss><?= e((string) $adminActionFlash['message']) ?></p>
        <?php endif; ?>
        <?php if (!is_dir(PUREBLOG_BASE_PATH . '/lang')): ?>
            <p class="notice delete"><?= e(t('admin.notices.lang_missing')) ?> <a href="<?= base_path() ?>/admin/settings-updates.php?repair_lang=1"><?= e(t('admin.notices.lang_missing_repair')) ?></a>.</p>
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
