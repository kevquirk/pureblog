<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => t('admin.editor.error_autosave_method')]);
    exit;
}

$token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if ($token === '' || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => t('admin.editor.error_autosave_csrf')]);
    exit;
}

$editorType = trim($_POST['editor_type'] ?? 'post');
$slug       = trim($_POST['slug'] ?? '');
$action     = trim($_POST['action'] ?? 'save');

if ($slug === '') {
    echo json_encode(['success' => false, 'error' => t('admin.editor.error_autosave_slug')]);
    exit;
}

$autosaveDir  = PUREBLOG_BASE_PATH . '/content/autosaves';
$autosaveFile = $autosaveDir . '/' . $editorType . '-' . $slug . '.json';

if ($action === 'discard') {
    if (is_file($autosaveFile)) {
        @unlink($autosaveFile);
    }
    echo json_encode(['success' => true]);
    exit;
}

// Ensure the autosaves directory exists and is protected.
if (!is_dir($autosaveDir)) {
    mkdir($autosaveDir, 0755, true);
    file_put_contents($autosaveDir . '/.htaccess', "Deny from all\n");
}

if ($editorType === 'page') {
    $data = [
        'timestamp'      => time(),
        'title'          => trim($_POST['title'] ?? ''),
        'content'        => trim($_POST['content'] ?? ''),
        'description'    => trim($_POST['description'] ?? ''),
        'status'         => trim($_POST['status'] ?? 'draft'),
        'include_in_nav' => trim($_POST['include_in_nav'] ?? 'yes'),
    ];
} else {
    $data = [
        'timestamp'   => time(),
        'title'       => trim($_POST['title'] ?? ''),
        'content'     => trim($_POST['content'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'status'      => trim($_POST['status'] ?? 'draft'),
        'tags'        => trim($_POST['tags'] ?? ''),
        'date'        => trim($_POST['date'] ?? ''),
        'layout'      => trim($_POST['post_layout'] ?? ''),
    ];
}

$written = file_put_contents($autosaveFile, json_encode($data, JSON_UNESCAPED_UNICODE));
echo json_encode($written !== false ? ['success' => true] : ['success' => false, 'error' => t('admin.editor.error_autosave_write')]);
