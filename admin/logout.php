<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $config = load_config();
    $blogPostsEnabled = $config['enable_blog_posts'] ?? true;
    header('Location: ' . base_path() . '/admin/' . ($blogPostsEnabled ? 'dashboard.php' : 'content.php'));
    exit;
}

verify_csrf();
clear_remember_me_cookie();
$_SESSION = [];
session_destroy();

header('Location: ' . base_path() . '/admin/index.php');
exit;
