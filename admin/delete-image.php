<?php

declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

verify_csrf();

$slug = trim($_POST['slug'] ?? '');
$date = trim($_POST['date'] ?? '');
$filename = trim($_POST['filename'] ?? '');
$editorType = trim($_POST['editor_type'] ?? 'post');

$redirect = $editorType === 'page'
    ? admin_url('edit-page.php') . '?slug=' . urlencode($slug)
    : admin_url('edit-post.php') . '?slug=' . urlencode($slug);

if ($slug === '' || ($editorType !== 'page' && $date === '') || $filename === '') {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Missing image data.'));
    exit;
}

$folderName = $editorType === 'page'
    ? $slug
    : $slug;
$baseDir = realpath(__DIR__ . '/../content/images');
if ($baseDir === false) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Image folder not found.'));
    exit;
}

// Validate folder path to prevent path traversal
$targetDir = realpath($baseDir . '/' . $folderName);
if ($targetDir === false || !str_starts_with($targetDir, $baseDir . '/')) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Invalid folder.'));
    exit;
}
$targetFile = $targetDir . '/' . basename($filename);

if (!is_file($targetFile)) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Image not found.'));
    exit;
}

if (!unlink($targetFile)) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Unable to delete image.'));
    exit;
}

$remaining = glob($targetDir . '/*') ?: [];
if (!$remaining) {
    @rmdir($targetDir);
}

header('Location: ' . $redirect);
exit;
