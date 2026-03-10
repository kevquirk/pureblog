<?php

declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

require_setup_redirect();

start_admin_session();

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$error = '';
$username = '';

// Use file-based IP lockout instead of session (persists across cookie clears)
$isLockedOut = is_ip_locked_out();

if (is_admin_logged_in()) {
    header('Location: ' . admin_url('dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($isLockedOut) {
        $remaining = get_lockout_remaining();
        $minutes = (int) ceil($remaining / 60);
        $error = 'Too many failed attempts. Try again in ' . $minutes . ' minute(s).';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username !== '' && hash_equals($config['admin_username'] ?? '', $username)
            && password_verify($password, $config['admin_password_hash'] ?? '')
        ) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            clear_login_failures();
            header('Location: ' . admin_url('dashboard.php'));
            exit;
        }

        record_login_failure();
        if (is_ip_locked_out()) {
            $error = 'Too many failed attempts. Try again in 5 minutes.';
        } else {
            $error = 'Invalid credentials.';
        }
    }
}

$adminTitle = 'Admin Login - Pureblog';
$hideAdminNav = true;
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="narrow">
        <br>
        <h1>Admin Login</h1>
        <?php if (!empty($_GET['setup'])): ?>
            <p>Setup complete. Log in to continue.</p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="notice"><?= e($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" autofocus value="<?= e($username) ?>" required<?= $isLockedOut ? ' disabled' : '' ?>>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required<?= $isLockedOut ? ' disabled' : '' ?>>
            <button type="submit"<?= $isLockedOut ? ' disabled' : '' ?>><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-check"></use></svg> Log in</button>
        </form>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
