<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => t('admin.editor.error_autosave_method')]);
    exit;
}

$token        = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if ($token === '' || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => t('admin.editor.error_autosave_csrf')]);
    exit;
}

$slug       = trim($_POST['slug'] ?? '');
$editorType = trim($_POST['editor_type'] ?? 'post');
$filename   = trim($_POST['filename'] ?? '');

if ($slug === '' || !in_array($editorType, ['post', 'page'], true)) {
    echo json_encode(['success' => false, 'error' => t('admin.editor.error_autosave_slug')]);
    exit;
}

if (!is_safe_image_slug($slug)) {
    echo json_encode(['success' => false, 'error' => t('admin.editor.error_image_invalid_path')]);
    exit;
}

if ($editorType === 'post') {
    $filepath = find_post_filepath_by_slug($slug);
} else {
    $candidate = PUREBLOG_PAGES_PATH . '/' . $slug . '.md';
    $filepath  = is_file($candidate) ? $candidate : null;
}

if ($filepath === null) {
    echo json_encode(['success' => false, 'error' => t('admin.editor.error_not_found_' . $editorType)]);
    exit;
}

if ($filename !== '') {
    $cleanFilename = basename($filename);
    $ext = strtolower(pathinfo($cleanFilename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
        echo json_encode(['success' => false, 'error' => t('admin.editor.error_image_invalid_path')]);
        exit;
    }

    $baseDir = realpath(PUREBLOG_CONTENT_IMAGES_PATH);
    if ($baseDir === false) {
        echo json_encode(['success' => false, 'error' => t('admin.editor.error_image_folder_missing')]);
        exit;
    }

    $imagePath = PUREBLOG_CONTENT_IMAGES_PATH . '/' . $slug . '/' . $cleanFilename;
    if (!validate_image_path($baseDir, $imagePath) || !is_file($imagePath)) {
        echo json_encode(['success' => false, 'error' => t('admin.editor.error_image_not_found')]);
        exit;
    }

    $featureValue = '/content/images/' . $slug . '/' . $cleanFilename;
} else {
    $featureValue = '';
}

if (!update_front_matter_field($filepath, 'feature_image', $featureValue)) {
    echo json_encode(['success' => false, 'error' => t('admin.editor.js_feature_image_failed')]);
    exit;
}

echo json_encode(['success' => true]);
