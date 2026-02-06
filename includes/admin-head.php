<?php
// Shared admin <head>. Expects $adminTitle (optional) and $fontStack (optional).
$adminTitle = $adminTitle ?? 'Admin - Pureblog';
$fontStack = $fontStack ?? font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$adminColorMode = $adminColorMode ?? ($config['theme']['admin_color_mode'] ?? 'auto');
$extraHead = $extraHead ?? '';
$codeMirror = $codeMirror ?? null; // 'markdown' or 'css'
$hideAdminNav = $hideAdminNav ?? false;
?>
<!DOCTYPE html>
<html lang="en" data-admin-theme="<?= e($adminColorMode) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/assets/images/favicon.png">
    <title><?= e($adminTitle) ?></title>
    <style>
        :root {
            --font-stack: <?= $fontStack ?>;
        }
    </style>
    <link rel="stylesheet" href="/admin/css/admin.css">
    <?php if (is_file(__DIR__ . '/../admin/css/admin-custom.css')): ?>
        <link rel="stylesheet" href="/admin/css/admin-custom.css">
    <?php endif; ?>
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
    <?php endif; ?>
