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
    $adminLanding = ($config['admin_homepage'] ?? 'dashboard') === 'content' ? 'content.php' : 'dashboard.php';
    header('Location: ' . base_path() . '/admin/' . $adminLanding);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($isLockedOut) {
        $remaining = get_lockout_remaining();
        $minutes = (int) ceil($remaining / 60);
        $error = t('admin.login.error_lockout', ['minutes' => $minutes]);
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username !== '' && hash_equals($config['admin_username'] ?? '', $username)
            && password_verify($password, $config['admin_password_hash'] ?? '')
        ) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['login_failures'] = 0;
            $_SESSION['lockout_until'] = 0;
            $adminLanding = ($config['admin_homepage'] ?? 'dashboard') === 'content' ? 'content.php' : 'dashboard.php';
            header('Location: ' . base_path() . '/admin/' . $adminLanding);
            exit;
        }

        $failures = (int) ($_SESSION['login_failures'] ?? 0);
        $failures++;
        $_SESSION['login_failures'] = $failures;
        if ($failures >= 5) {
            $_SESSION['lockout_until'] = $now + (5 * 60);
            $error = t('admin.login.error_lockout_5');
        } else {
            $error = t('admin.login.error_invalid');
        }
    }
}

$adminTitle = t('admin.login.page_title');
$hideAdminNav = true;
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="narrow">
        <br>
        <h1><?= e(t('admin.login.heading')) ?></h1>
        <?php if (!empty($_GET['setup'])): ?>
            <p><?= e(t('admin.login.setup_complete')) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="notice delete"><?= e($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label for="username"><?= e(t('admin.login.username')) ?></label>
            <input type="text" id="username" name="username" autofocus value="<?= e($username) ?>" required<?= $isLockedOut ? ' disabled' : '' ?>>

            <label for="password"><?= e(t('admin.login.password')) ?></label>
            <input type="password" id="password" name="password" required<?= $isLockedOut ? ' disabled' : '' ?>>
            <button type="submit"<?= $isLockedOut ? ' disabled' : '' ?>><svg class="icon" aria-hidden="true"><use href="#icon-circle-check"></use></svg> <?= e(t('admin.login.submit')) ?></button>
        </form>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
