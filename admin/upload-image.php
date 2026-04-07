<?php

declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

verify_csrf();

$slug = trim($_POST['slug'] ?? '');
$date = trim($_POST['date'] ?? '');
$editorType = trim($_POST['editor_type'] ?? 'post');
$message = '';
$error = '';

// Validate slug to prevent path traversal
if ($slug === '') {
    $error = t('admin.editor.error_upload_no_slug');
} elseif (!isset($_FILES['image'])) {
    $error = t('admin.editor.error_upload_no_file');
} elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $error = t('admin.editor.error_upload_failed');
} elseif ($_FILES['image']['size'] > (3 * 1024 * 1024)) {
    $error = t('admin.editor.error_upload_too_large');
} else {
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];
    $extToMime = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    ];
    $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    $mimeType = $finfo ? ($finfo->file($_FILES['image']['tmp_name']) ?: '') : '';
    if ($mimeType === '') {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $mimeType = $extToMime[$ext] ?? '';
    }
    if (!isset($allowedTypes[$mimeType])) {
        $error = t('admin.editor.error_upload_type');
    }
}

if ($error === '') {
    $folder = $slug;

    if (!is_safe_image_slug($folder)) {
        $error = t('admin.editor.error_upload_invalid_slug');
    }
}

if ($error === '') {
    $baseDir = realpath(__DIR__ . '/../content/images');
    $uploadDir = __DIR__ . '/../content/images/' . $folder;

    if ($baseDir === false) {
        $error = t('admin.editor.error_image_folder_missing');
    } elseif (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $error = t('admin.editor.error_upload_folder_create');
    } elseif (!validate_image_path($baseDir, $uploadDir)) {
        @rmdir($uploadDir);
        $error = t('admin.editor.error_image_invalid_path');
    }
}

if ($error === '') {
    // Force extension based on detected MIME type (prevents polyglot file attacks)
    $basename = pathinfo(basename($_FILES['image']['name']), PATHINFO_FILENAME);
    $basename = strtolower($basename);
    $basename = preg_replace('/[^a-z0-9_-]/', '-', $basename) ?? '';
    $basename = preg_replace('/-+/', '-', $basename) ?? '';
    $basename = trim($basename, '-');
    if ($basename === '') {
        $basename = 'image-' . bin2hex(random_bytes(4));
    }
    $filename = $basename . '.' . $allowedTypes[$mimeType];

    if ($filename === '') {
        $error = t('admin.editor.error_upload_invalid_name');
    } elseif (is_file($uploadDir . '/' . $filename)) {
        $error = t('admin.editor.error_upload_duplicate', ['filename' => $filename]);
    } else {
        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            $error = t('admin.editor.error_upload_save');
        } else {
            $url = base_path() . '/content/images/' . $folder . '/' . $filename;
            $altText = pathinfo($filename, PATHINFO_FILENAME) ?: 'image';
            $message = '![' . $altText . '](' . $url . ')';
        }
    }
}

$redirect = $editorType === 'page'
    ? base_path() . '/admin/edit-page.php?slug=' . urlencode($slug)
    : base_path() . '/admin/edit-post.php?slug=' . urlencode($slug);
if ($message !== '') {
    $redirect .= '&uploaded=' . urlencode($message);
} elseif ($error !== '') {
    $redirect .= '&upload_error=' . urlencode($error);
}

header('Location: ' . $redirect);
exit;
