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
    ? base_path() . '/admin/edit-page.php?slug=' . urlencode($slug)
    : base_path() . '/admin/edit-post.php?slug=' . urlencode($slug);

if ($slug === '' || ($editorType !== 'page' && $date === '') || $filename === '') {
    header('Location: ' . $redirect . '&upload_error=' . urlencode(t('admin.editor.error_delete_missing_data')));
    exit;
}

$folderName = $slug;
$baseDir = realpath(__DIR__ . '/../content/images');
if ($baseDir === false) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode(t('admin.editor.error_image_folder_missing')));
    exit;
}

if (!is_safe_image_slug($folderName)) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode(t('admin.editor.error_image_invalid_path')));
    exit;
}

// Validate folder path to prevent path traversal
$targetDir = realpath($baseDir . '/' . $folderName);
if ($targetDir === false || !str_starts_with($targetDir, $baseDir . '/')) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode('Invalid folder.'));
    exit;
}
$targetFile = $targetDir . '/' . basename($filename);

if (!validate_image_path($baseDir, $targetDir)) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode(t('admin.editor.error_image_invalid_path')));
    exit;
}

if (!validate_image_path($baseDir, $targetFile)) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode(t('admin.editor.error_image_invalid_path')));
    exit;
}

if (!is_file($targetFile)) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode(t('admin.editor.error_image_not_found')));
    exit;
}

if (!unlink($targetFile)) {
    header('Location: ' . $redirect . '&upload_error=' . urlencode(t('admin.editor.error_delete_failed')));
    exit;
}

$remaining = glob($targetDir . '/*') ?: [];
if (!$remaining) {
    @rmdir($targetDir);
}

header('Location: ' . $redirect);
exit;
