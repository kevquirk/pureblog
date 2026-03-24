<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

verify_csrf();

$slug = trim($_POST['slug'] ?? '');
$date = trim($_POST['date'] ?? '');
$filename = trim($_POST['filename'] ?? '');
$editorType = trim($_POST['editor_type'] ?? 'post');

$redirect = $editorType === 'page'
    ? base_path() . '/admin/edit-page.php?slug=' . urlencode($slug)
    : base_path() . '/admin/edit-post.php?slug=' . urlencode($slug);

if ($slug === '' || ($editorType !== 'page' && $date === '') || $filename === '') {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Missing image data.'));
    exit;
}

$folderName = $slug;
$baseDir = realpath(__DIR__ . '/../content/images');
if ($baseDir === false) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Image folder not found.'));
    exit;
}

if (!is_safe_image_slug($folderName)) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Invalid image path.'));
    exit;
}

$targetDir = $baseDir . '/' . $folderName;
$targetFile = $targetDir . '/' . basename($filename);

if (!validate_image_path($baseDir, $targetDir)) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Invalid image path.'));
    exit;
}

if (!validate_image_path($baseDir, $targetFile)) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Invalid image path.'));
    exit;
}

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
