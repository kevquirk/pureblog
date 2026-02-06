<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $editorType = trim($_POST['editor_type'] ?? 'post');
    $slug = trim($_POST['slug'] ?? '');
    $previewKey = $editorType . ':' . ($slug !== '' ? $slug : 'new');
    $_SESSION['preview'][$previewKey] = [
        'markdown' => $_POST['markdown'] ?? '',
        'title' => trim($_POST['title'] ?? ''),
        'date' => trim($_POST['date'] ?? ''),
        'tags' => trim($_POST['tags'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'editor_type' => $editorType,
        'slug' => $slug,
        'created_at' => time(),
    ];
    $redirect = '/admin/preview.php?type=' . urlencode($editorType);
    if ($slug !== '') {
        $redirect .= '&slug=' . urlencode($slug);
    }
    header('Location: ' . $redirect);
    exit;
}

$editorType = trim($_GET['type'] ?? 'post');
$slug = trim($_GET['slug'] ?? '');
$previewKey = $editorType . ':' . ($slug !== '' ? $slug : 'new');
$preview = $_SESSION['preview'][$previewKey] ?? null;
$previewTime = $preview ? (int) ($preview['created_at'] ?? 0) : 0;

$markdown = '';
$title = '';
$date = '';
$tags = '';
$description = '';
$useSnapshot = $preview !== null;

if ($slug !== '') {
    if ($editorType === 'page') {
        $live = get_page_by_slug($slug, true);
    } else {
        $live = get_post_by_slug($slug, true);
    }

    if ($live) {
        $path = $live['path'] ?? '';
        $fileTime = $path && is_file($path) ? (int) filemtime($path) : 0;
        if ($previewTime <= $fileTime) {
            $useSnapshot = false;
            $title = (string) ($live['title'] ?? '');
            $date = (string) ($live['date'] ?? '');
            $tags = is_array($live['tags'] ?? null) ? implode(', ', $live['tags']) : '';
            $description = (string) ($live['description'] ?? '');
            $markdown = (string) ($live['content'] ?? '');
        }
    }
}

if ($useSnapshot && $preview) {
    $markdown = $preview['markdown'] ?? '';
    $title = trim($preview['title'] ?? '');
    $date = trim($preview['date'] ?? '');
    $tags = trim($preview['tags'] ?? '');
    $description = trim($preview['description'] ?? '');
    $editorType = trim($preview['editor_type'] ?? $editorType);
}

$config = load_config();
$fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
$pageTitle = $title !== '' ? $title : 'Preview';
$metaDescription = $description;
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if ($editorType === 'page') {
    $page = [
        'title' => $title !== '' ? $title : 'Preview',
        'slug' => '',
        'status' => 'draft',
        'description' => $description,
        'include_in_nav' => false,
        'content' => $markdown,
    ];
    require __DIR__ . '/../page.php';
    exit;
}

$post = [
    'title' => $title !== '' ? $title : 'Preview',
    'slug' => '',
    'date' => $date,
    'status' => 'draft',
    'tags' => $tags !== '' ? array_map('trim', explode(',', $tags)) : [],
    'description' => $description,
    'content' => $markdown,
];
require __DIR__ . '/../post.php';
