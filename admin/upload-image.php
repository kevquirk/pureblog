<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// Detect when post_max_size is exceeded (PHP empties $_POST and $_FILES on POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $postMaxSize = ini_get('post_max_size') ?: 'unknown';
    $error = t('admin.editor.error_upload_post_size', ['limit' => $postMaxSize]);
    $redirect = base_path() . '/admin/content.php?upload_error=' . urlencode($error);
    header('Location: ' . $redirect);
    exit;
}

verify_csrf();

$slug = trim($_POST['slug'] ?? '');
$date = trim($_POST['date'] ?? '');
$editorType = trim($_POST['editor_type'] ?? 'post');
$message = '';
$error = '';

if ($slug === '') {
    $error = t('admin.editor.error_upload_no_slug');
} elseif (!isset($_FILES['image'])) {
    $error = t('admin.editor.error_upload_no_file');
} elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $uploadErr = (int) $_FILES['image']['error'];
    $maxFileSize = ini_get('upload_max_filesize') ?: 'unknown';
    $error = match ($uploadErr) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => t('admin.editor.error_upload_size', ['limit' => $maxFileSize]),
        UPLOAD_ERR_PARTIAL   => t('admin.editor.error_upload_partial'),
        UPLOAD_ERR_NO_FILE   => t('admin.editor.error_upload_no_file'),
        UPLOAD_ERR_NO_TMP_DIR => t('admin.editor.error_upload_no_tmp_dir'),
        UPLOAD_ERR_CANT_WRITE => t('admin.editor.error_upload_cant_write'),
        UPLOAD_ERR_EXTENSION => t('admin.editor.error_upload_extension'),
        default              => t('admin.editor.error_upload_failed'),
    };
} else {
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['image']['tmp_name']) ?: '';
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

    if ($baseDir !== false && !is_file($baseDir . '/.htaccess')) {
        file_put_contents($baseDir . '/.htaccess', "<FilesMatch \"\.ph(p[0-9]?|tml)$\">\n    Require all denied\n</FilesMatch>\n");
    }

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
    $uploadedFilename = basename($_FILES['image']['name']);
    $uploadedFilename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $uploadedFilename) ?? $uploadedFilename;
    $uploadedFilename = preg_replace('/-+/', '-', $uploadedFilename) ?? $uploadedFilename;
    $uploadedFilename = trim($uploadedFilename, '-');

    $expectedExt = $allowedTypes[$mimeType];
    $baseName = pathinfo($uploadedFilename, PATHINFO_FILENAME);
    $baseName = str_replace('.', '-', $baseName);
    $filename = $baseName . '.' . $expectedExt;

    if ($filename === '') {
        $error = t('admin.editor.error_upload_invalid_name');
    } elseif (is_file($uploadDir . '/' . $filename)) {
        $error = t('admin.editor.error_upload_duplicate', ['filename' => $filename]);
    } else {
        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            $error = t('admin.editor.error_upload_save');
        } else {
            strip_image_metadata($destination, $mimeType);
            call_hook('on_image_uploaded', [$destination]);
            $webpDestination = preg_replace('/\.[^.]+$/', '.webp', $destination) ?? $destination;
            if ($webpDestination !== $destination && file_exists($webpDestination)) {
                $destination = $webpDestination;
            }
            $filename = basename($destination);
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
