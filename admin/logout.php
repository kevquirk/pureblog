<?php

declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: ' . base_path() . '/admin/dashboard.php');
    exit;
}

verify_csrf();
$_SESSION = [];
session_destroy();

header('Location: ' . base_path() . '/admin/index.php');
exit;
