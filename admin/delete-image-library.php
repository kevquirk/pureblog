<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

verify_csrf();

$slug     = trim($_POST['slug'] ?? '');
$filename = trim($_POST['filename'] ?? '');
$redirect = base_path() . '/admin/images.php';

if ($slug === '' || $filename === '') {
    header('Location: ' . $redirect . '?delete_error=' . urlencode(t('admin.editor.error_delete_missing_data')));
    exit;
}

$baseDir = realpath(__DIR__ . '/../content/images');
if ($baseDir === false) {
    header('Location: ' . $redirect . '?delete_error=' . urlencode(t('admin.editor.error_image_folder_missing')));
    exit;
}

if (!is_safe_image_slug($slug)) {
    header('Location: ' . $redirect . '?delete_error=' . urlencode(t('admin.editor.error_image_invalid_path')));
    exit;
}

$targetDir  = $baseDir . '/' . $slug;
$targetFile = $targetDir . '/' . basename($filename);

if (!validate_image_path($baseDir, $targetDir) || !validate_image_path($baseDir, $targetFile)) {
    header('Location: ' . $redirect . '?delete_error=' . urlencode(t('admin.editor.error_image_invalid_path')));
    exit;
}

if (!is_file($targetFile)) {
    header('Location: ' . $redirect . '?delete_error=' . urlencode(t('admin.editor.error_image_not_found')));
    exit;
}

if (!unlink($targetFile)) {
    header('Location: ' . $redirect . '?delete_error=' . urlencode(t('admin.images.error_delete')));
    exit;
}

$remaining = glob($targetDir . '/*') ?: [];
if (!$remaining) {
    @rmdir($targetDir);
}

header('Location: ' . $redirect . '?deleted=1');
exit;
