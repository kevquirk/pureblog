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
$messages = [];
$errors = [];

if ($slug === '') {
    $errors[] = t('admin.editor.error_upload_no_slug');
} elseif (!isset($_FILES['image'])) {
    $errors[] = t('admin.editor.error_upload_no_file');
}

$fileList = [];
if (empty($errors) && isset($_FILES['image'])) {
    if (is_array($_FILES['image']['name'])) {
        $count = count($_FILES['image']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['image']['error'][$i] === UPLOAD_ERR_NO_FILE && $count > 1) {
                continue;
            }
            $fileList[] = [
                'name'     => (string) $_FILES['image']['name'][$i],
                'type'     => (string) ($_FILES['image']['type'][$i] ?? ''),
                'tmp_name' => (string) ($_FILES['image']['tmp_name'][$i] ?? ''),
                'error'    => (int) $_FILES['image']['error'][$i],
                'size'     => (int) $_FILES['image']['size'][$i],
            ];
        }
    } else {
        $fileList[] = [
            'name'     => (string) $_FILES['image']['name'],
            'type'     => (string) ($_FILES['image']['type'] ?? ''),
            'tmp_name' => (string) ($_FILES['image']['tmp_name'] ?? ''),
            'error'    => (int) $_FILES['image']['error'],
            'size'     => (int) $_FILES['image']['size'],
        ];
    }

    if (empty($fileList)) {
        $errors[] = t('admin.editor.error_upload_no_file');
    }
}

$folder = $slug;
if (empty($errors)) {
    if (!is_safe_image_slug($folder)) {
        $errors[] = t('admin.editor.error_upload_invalid_slug');
    }
}

if (empty($errors)) {
    $baseDir = realpath(__DIR__ . '/../content/images');
    $uploadDir = __DIR__ . '/../content/images/' . $folder;

    if ($baseDir !== false && !is_file($baseDir . '/.htaccess')) {
        file_put_contents($baseDir . '/.htaccess', "<FilesMatch \"\.ph(p[0-9]?|tml)$\">\n    Require all denied\n</FilesMatch>\n");
    }

    if ($baseDir === false) {
        $errors[] = t('admin.editor.error_image_folder_missing');
    } elseif (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $errors[] = t('admin.editor.error_upload_folder_create');
    } elseif (!validate_image_path($baseDir, $uploadDir)) {
        @rmdir($uploadDir);
        $errors[] = t('admin.editor.error_image_invalid_path');
    }
}

if (empty($errors)) {
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($fileList as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErr = $file['error'];
            $maxFileSize = ini_get('upload_max_filesize') ?: 'unknown';
            $errors[] = match ($uploadErr) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => t('admin.editor.error_upload_size', ['limit' => $maxFileSize]),
                UPLOAD_ERR_PARTIAL    => t('admin.editor.error_upload_partial'),
                UPLOAD_ERR_NO_FILE    => t('admin.editor.error_upload_no_file'),
                UPLOAD_ERR_NO_TMP_DIR => t('admin.editor.error_upload_no_tmp_dir'),
                UPLOAD_ERR_CANT_WRITE => t('admin.editor.error_upload_cant_write'),
                UPLOAD_ERR_EXTENSION  => t('admin.editor.error_upload_extension'),
                default               => t('admin.editor.error_upload_failed'),
            };
            continue;
        }

        $mimeType = $finfo->file($file['tmp_name']) ?: '';
        if (!isset($allowedTypes[$mimeType])) {
            $errors[] = t('admin.editor.error_upload_type') . ' (' . htmlspecialchars($file['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')';
            continue;
        }

        $uploadedFilename = basename($file['name']);
        $uploadedFilename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $uploadedFilename) ?? $uploadedFilename;
        $uploadedFilename = preg_replace('/-+/', '-', $uploadedFilename) ?? $uploadedFilename;
        $uploadedFilename = trim($uploadedFilename, '-');

        $expectedExt = $allowedTypes[$mimeType];
        $baseName = pathinfo($uploadedFilename, PATHINFO_FILENAME);
        $baseName = str_replace('.', '-', $baseName);
        $filename = $baseName . '.' . $expectedExt;

        if ($filename === '') {
            $errors[] = t('admin.editor.error_upload_invalid_name');
            continue;
        }

        if (is_file($uploadDir . '/' . $filename)) {
            $errors[] = t('admin.editor.error_upload_duplicate', ['filename' => $filename]);
            continue;
        }

        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = t('admin.editor.error_upload_save') . ' (' . htmlspecialchars($filename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')';
            continue;
        }

        strip_image_metadata($destination, $mimeType);
        call_hook('on_image_uploaded', [$destination]);
        $webpDestination = preg_replace('/\.[^.]+$/', '.webp', $destination) ?? $destination;
        if ($webpDestination !== $destination && file_exists($webpDestination)) {
            $destination = $webpDestination;
        }
        $filename = basename($destination);
        $url = base_path() . '/content/images/' . $folder . '/' . $filename;
        $altText = pathinfo($filename, PATHINFO_FILENAME) ?: 'image';
        $messages[] = '![' . $altText . '](' . $url . ')';
    }
}

$redirect = $editorType === 'page'
    ? base_path() . '/admin/edit-page.php?slug=' . urlencode($slug)
    : base_path() . '/admin/edit-post.php?slug=' . urlencode($slug);
if (!empty($messages)) {
    $redirect .= '&uploaded=' . urlencode(implode("\n", $messages));
}
if (!empty($errors)) {
    $redirect .= '&upload_error=' . urlencode(implode(' ', $errors));
}

header('Location: ' . $redirect);
exit;
