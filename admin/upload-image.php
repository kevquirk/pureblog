<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

verify_csrf();

$slug = trim($_POST['slug'] ?? '');
$date = trim($_POST['date'] ?? '');
$editorType = trim($_POST['editor_type'] ?? 'post');
$message = '';
$error = '';

if ($slug === '') {
    $error = 'Save the post first so it has a slug.';
} elseif (!isset($_FILES['image'])) {
    $error = 'No image uploaded.';
} elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $error = 'Upload failed.';
} elseif ($_FILES['image']['size'] > (3 * 1024 * 1024)) {
    $error = 'Image is too large. Max size is 3MB.';
} else {
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['image']['tmp_name']) ?: '';
    if (!isset($allowedTypes[$mimeType])) {
        $error = 'Unsupported image type. Use JPG, PNG, GIF, or WebP.';
    }
}

if ($error === '') {
    if ($editorType === 'page') {
        $folder = $slug;
    } else {
        $folder = $slug;
    }
    $uploadDir = __DIR__ . '/../content/images/' . $folder;

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $error = 'Unable to create image folder.';
    } else {
        $filename = basename($_FILES['image']['name']);
        $filename = strtolower($filename);
        $filename = preg_replace('/[^a-z0-9._-]/', '-', $filename) ?? $filename;
        $filename = preg_replace('/-+/', '-', $filename) ?? $filename;
        $filename = trim($filename, '-');
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if ($ext === '') {
            $filename .= '.' . $allowedTypes[$mimeType];
        }

        if ($filename === '') {
            $error = 'Invalid file name.';
        } elseif (is_file($uploadDir . '/' . $filename)) {
            $error = 'Duplicate name, please rename the image (sanitized as "' . $filename . '").';
        } else {
            $destination = $uploadDir . '/' . $filename;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $error = 'Unable to save uploaded file.';
            } else {
                $url = '/content/images/' . $folder . '/' . $filename;
                $altText = pathinfo($filename, PATHINFO_FILENAME) ?: 'image';
                $message = '![' . $altText . '](' . $url . ')';
            }
        }
    }
}

$redirect = $editorType === 'page'
    ? '/admin/edit-page.php?slug=' . urlencode($slug)
    : '/admin/edit-post.php?slug=' . urlencode($slug);
if ($message !== '') {
    $redirect .= '&uploaded=' . urlencode($message);
} elseif ($error !== '') {
    $redirect .= '&upload_error=' . urlencode($error);
}

header('Location: ' . $redirect);
exit;
