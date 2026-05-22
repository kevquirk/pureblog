<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Session, CSRF, and login helpers
// ---------------------------------------------------------------------------

function start_admin_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function csrf_token(): string
{
    start_admin_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function verify_csrf(): void
{
    start_admin_session();
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if ($token === '' || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['is_admin']);
}

function get_login_failure_state(string $ip): array
{
    $file = PUREBLOG_DATA_PATH . '/login-failures.json';
    if (!is_file($file)) {
        return ['failures' => 0, 'lockout_until' => 0];
    }
    $data = json_decode((string) @file_get_contents($file), true);
    if (!is_array($data) || !isset($data[$ip])) {
        return ['failures' => 0, 'lockout_until' => 0];
    }
    return [
        'failures'      => (int) ($data[$ip]['failures'] ?? 0),
        'lockout_until' => (int) ($data[$ip]['lockout_until'] ?? 0),
    ];
}

function record_login_failure(string $ip): array
{
    $file = PUREBLOG_DATA_PATH . '/login-failures.json';
    $data = [];
    if (is_file($file)) {
        $decoded = json_decode((string) @file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $now = time();
    foreach (array_keys($data) as $storedIp) {
        $until = (int) ($data[$storedIp]['lockout_until'] ?? 0);
        if ($until > 0 && $until < $now - 3600) {
            unset($data[$storedIp]);
        }
    }

    $entry = $data[$ip] ?? ['failures' => 0, 'lockout_until' => 0];
    $entry['failures'] = (int) $entry['failures'] + 1;
    if ($entry['failures'] >= 5) {
        $entry['lockout_until'] = $now + (5 * 60);
    }
    $data[$ip] = $entry;

    if (!is_dir(PUREBLOG_DATA_PATH)) {
        mkdir(PUREBLOG_DATA_PATH, 0755, true);
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);

    return [
        'failures'      => (int) $entry['failures'],
        'lockout_until' => (int) ($entry['lockout_until'] ?? 0),
    ];
}

function clear_login_failures(string $ip): void
{
    $file = PUREBLOG_DATA_PATH . '/login-failures.json';
    if (!is_file($file)) {
        return;
    }
    $data = json_decode((string) @file_get_contents($file), true);
    if (!is_array($data)) {
        return;
    }
    unset($data[$ip]);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function set_remember_me_cookie(): void
{
    $selector  = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $expires   = time() + (90 * 24 * 60 * 60);

    $file   = PUREBLOG_DATA_PATH . '/remember-me.json';
    $tokens = [];
    if (is_file($file)) {
        $decoded = json_decode((string) @file_get_contents($file), true);
        if (is_array($decoded)) {
            $tokens = $decoded;
        }
    }

    $now = time();
    foreach (array_keys($tokens) as $storedSelector) {
        if ((int) ($tokens[$storedSelector]['expires'] ?? 0) < $now) {
            unset($tokens[$storedSelector]);
        }
    }

    $tokens[$selector] = [
        'validator_hash' => hash('sha256', $validator),
        'expires'        => $expires,
    ];

    if (!is_dir(PUREBLOG_DATA_PATH)) {
        mkdir(PUREBLOG_DATA_PATH, 0755, true);
    }
    if (file_put_contents($file, json_encode($tokens, JSON_PRETTY_PRINT), LOCK_EX) === false) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    setcookie('pb_remember', $selector . ':' . $validator, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_remember_me_cookie(): void
{
    $cookie = $_COOKIE['pb_remember'] ?? '';
    if ($cookie !== '') {
        $parts = explode(':', $cookie, 2);
        if (count($parts) === 2) {
            $file = PUREBLOG_DATA_PATH . '/remember-me.json';
            if (is_file($file)) {
                $tokens = json_decode((string) @file_get_contents($file), true);
                if (is_array($tokens)) {
                    unset($tokens[$parts[0]]);
                    file_put_contents($file, json_encode($tokens, JSON_PRETTY_PRINT), LOCK_EX);
                }
            }
        }
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    setcookie('pb_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_all_remember_me_tokens(): void
{
    $file = PUREBLOG_DATA_PATH . '/remember-me.json';
    if (is_file($file)) {
        file_put_contents($file, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
    }
}

function maybe_restore_admin_from_cookie(): void
{
    if (is_admin_logged_in()) {
        return;
    }
    $cookie = $_COOKIE['pb_remember'] ?? '';
    if ($cookie === '') {
        return;
    }
    $parts = explode(':', $cookie, 2);
    if (count($parts) !== 2) {
        return;
    }
    [$selector, $validator] = $parts;

    $file = PUREBLOG_DATA_PATH . '/remember-me.json';
    if (!is_file($file)) {
        return;
    }
    $tokens = json_decode((string) @file_get_contents($file), true);
    if (!is_array($tokens) || !isset($tokens[$selector])) {
        return;
    }

    $entry = $tokens[$selector];
    if ((int) ($entry['expires'] ?? 0) < time()) {
        unset($tokens[$selector]);
        file_put_contents($file, json_encode($tokens, JSON_PRETTY_PRINT), LOCK_EX);
        return;
    }

    if (!hash_equals((string) ($entry['validator_hash'] ?? ''), hash('sha256', $validator))) {
        return;
    }

    session_regenerate_id(true);
    $_SESSION['is_admin'] = true;
}

function require_admin_login(): void
{
    maybe_restore_admin_from_cookie();
    if (!is_admin_logged_in()) {
        header('Location: ' . base_path() . '/admin/index.php');
        exit;
    }
}
