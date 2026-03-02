<?php
// Shared admin <head>. Expects $adminTitle (optional) and $fontStack (optional).
$adminTitle = $adminTitle ?? 'Admin - Pureblog';
$fontStack = $fontStack ?? font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$adminColorMode = $adminColorMode ?? ($config['theme']['admin_color_mode'] ?? 'auto');
$extraHead = $extraHead ?? '';
$codeMirror = $codeMirror ?? null; // 'markdown' or 'css'
$hideAdminNav = $hideAdminNav ?? false;
$adminCssVersion = (string) @filemtime(__DIR__ . '/../admin/css/admin.css');

if (!$hideAdminNav && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['admin_action_id'])) {
    verify_csrf();
    $actionId = strtolower(trim((string) ($_POST['admin_action_id'] ?? '')));
    $actionId = preg_replace('/[^a-z0-9_-]/', '', $actionId) ?? '';
    if ($actionId === 'clear_cache') {
        cache_clear();
        $_SESSION['admin_action_flash'] = ['ok' => true, 'message' => 'Cache cleared.'];
    } elseif ($actionId !== '') {
        $_SESSION['admin_action_flash'] = run_admin_action($actionId);
    } else {
        $_SESSION['admin_action_flash'] = ['ok' => false, 'message' => 'Invalid admin action.'];
    }
    $redirectTo = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php');
    header('Location: ' . $redirectTo);
    exit;
}

$adminActionButtons = !$hideAdminNav ? get_admin_action_buttons() : [];
$adminActionFlash = $_SESSION['admin_action_flash'] ?? null;
unset($_SESSION['admin_action_flash']);
?>
<!DOCTYPE html>
<html lang="en" data-admin-theme="<?= e($adminColorMode) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= e($config['assets']['favicon'] ?? '/assets/images/favicon.png') ?>">
    <title><?= e($adminTitle) ?></title>
    <link rel="stylesheet" href="/admin/css/admin.css?v=<?= e($adminCssVersion) ?>">
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
        $adminPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
        $isSettings = str_starts_with($adminPath, 'admin/settings');
        ?>
        <nav class="admin-nav" aria-label="Admin">
            <ul class="admin-nav-list">
                <li><a href="/admin/dashboard.php"<?= $adminPath === 'admin/dashboard.php' ? ' class="current"' : '' ?>><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-gauge"></use></svg> Dashboard</a></li>
                <li><a href="/admin/pages.php"<?= $adminPath === 'admin/pages.php' ? ' class="current"' : '' ?>><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-file-text"></use></svg> Pages</a></li>
                <li><a href="/admin/settings-site.php"<?= $isSettings ? ' class="current"' : '' ?>><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-settings"></use></svg> Settings</a></li>
                <li><a target="_blank" rel="noopener noreferrer" href="/"><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-eye"></use></svg> View site</a></li>
                <?php if (!empty($config['cache']['enabled'])): ?>
                    <li>
                        <form method="post" action="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php') ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="admin_action_id" value="clear_cache">
                            <button class="delete" type="submit" class="link-button">
                                <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-x"></use></svg>
                                Clear cache
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
                        <form method="post" action="<?= e($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php') ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="admin_action_id" value="<?= e($actionButton['id']) ?>">
                            <button type="submit" class="<?= e($buttonClass) ?>"<?= $confirmAttr ?>>
                                <?php if ($actionButton['icon'] !== ''): ?>
                                    <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-<?= e($actionButton['icon']) ?>"></use></svg>
                                <?php endif; ?>
                                <?= e($actionButton['label']) ?>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <li>
                    <form method="post" action="/admin/logout.php" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="link-button delete">
                            <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-log-out"></use></svg>
                            Log out
                        </button>
                    </form>
                </li>
            </ul>
        </nav>
        <?php if (is_array($adminActionFlash) && isset($adminActionFlash['message'])): ?>
            <?php $flashOk = (bool) ($adminActionFlash['ok'] ?? false); ?>
            <p class="notice<?= $flashOk ? '' : ' delete' ?>" data-auto-dismiss><?= e((string) $adminActionFlash['message']) ?></p>
        <?php endif; ?>
    <?php endif; ?>
