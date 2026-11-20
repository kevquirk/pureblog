<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// Release the session lock immediately so concurrent thumbnail requests run in parallel.
session_write_close();

$slug     = trim((string) ($_GET['slug'] ?? ''));
$filename = basename(trim((string) ($_GET['file'] ?? '')));

if ($slug === '' || $filename === '' || !is_safe_image_slug($slug)) {
    http_response_code(400);
    exit;
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'avif' => 'image/avif',
];

if (!isset($mimeMap[$ext])) {
    http_response_code(400);
    exit;
}

$baseDir    = realpath(PUREBLOG_CONTENT_IMAGES_PATH);
$targetDir  = PUREBLOG_CONTENT_IMAGES_PATH . '/' . $slug;
$sourcePath = $targetDir . '/' . $filename;

if ($baseDir === false || !is_file($sourcePath) || !validate_image_path($baseDir, $sourcePath)) {
    http_response_code(404);
    exit;
}

$thumbPath = $targetDir . '/thumbs/' . $filename;

if (!is_file($thumbPath) || filesize($thumbPath) === 0) {
    generate_image_thumbnail($sourcePath, $thumbPath);
}

$servePath = (is_file($thumbPath) && filesize($thumbPath) > 0) ? $thumbPath : $sourcePath;
$size      = @filesize($servePath);

header('Content-Type: ' . $mimeMap[$ext]);
header('Cache-Control: public, max-age=2592000');
if ($size !== false && $size > 0) {
    header('Content-Length: ' . $size);
}
readfile($servePath);
exit;
