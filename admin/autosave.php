<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if ($token === '' || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$config = load_config();
$editorType = trim($_POST['editor_type'] ?? 'post');
$error = '';

if ($editorType === 'page') {
    $page = [
        'title'          => trim($_POST['title'] ?? ''),
        'slug'           => trim($_POST['slug'] ?? ''),
        'status'         => trim($_POST['status'] ?? 'draft'),
        'description'    => trim($_POST['description'] ?? ''),
        'include_in_nav' => (($_POST['include_in_nav'] ?? 'yes') === 'yes' || ($_POST['include_in_nav'] ?? '') === '1'),
        'content'        => trim($_POST['content'] ?? ''),
    ];

    if ($page['title'] === '' || $page['slug'] === '') {
        echo json_encode(['success' => false, 'error' => 'Title and slug required']);
        exit;
    }

    $originalSlug   = trim($_POST['original_slug'] ?? '') ?: null;
    $originalStatus = trim($_POST['original_status'] ?? '') ?: null;
    $saved = save_page($page, $originalSlug, $originalStatus, $error);
} else {
    $post = [
        'title'       => trim($_POST['title'] ?? ''),
        'slug'        => trim($_POST['slug'] ?? ''),
        'date'        => trim($_POST['date'] ?? ''),
        'status'      => trim($_POST['status'] ?? 'draft'),
        'tags'        => [],
        'description' => trim($_POST['description'] ?? ''),
        'content'     => trim($_POST['content'] ?? ''),
        'layout'      => trim($_POST['post_layout'] ?? ''),
    ];

    $tagsInput    = trim($_POST['tags'] ?? '');
    $post['tags'] = $tagsInput === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $tagsInput))));

    $post['layout_fields'] = [];
    foreach ($_POST as $key => $value) {
        if (str_starts_with($key, 'layout_field__')) {
            $post['layout_fields'][substr($key, strlen('layout_field__'))] = trim((string) $value);
        }
    }

    if ($post['title'] === '' || $post['slug'] === '') {
        echo json_encode(['success' => false, 'error' => 'Title and slug required']);
        exit;
    }

    $originalSlug   = trim($_POST['original_slug'] ?? '') ?: null;
    $originalDate   = trim($_POST['original_date'] ?? '') ?: null;
    $originalStatus = trim($_POST['original_status'] ?? '') ?: null;
    $saved = save_post($post, $originalSlug, $originalDate, $originalStatus, $error);
}

echo json_encode($saved ? ['success' => true] : ['success' => false, 'error' => $error]);
