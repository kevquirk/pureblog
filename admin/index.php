<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';

require_setup_redirect();

start_admin_session();
maybe_restore_admin_from_cookie();

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$error = '';
$username = '';
$now = time();
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$failureState = get_login_failure_state($clientIp);
$lockoutUntil = $failureState['lockout_until'];
$isLockedOut = $lockoutUntil > $now;

if (is_admin_logged_in()) {
    $blogPostsEnabled = $config['enable_blog_posts'] ?? true;
    $adminLanding = (!$blogPostsEnabled || ($config['admin_homepage'] ?? 'dashboard') === 'content') ? 'content.php' : 'dashboard.php';
    header('Location: ' . base_path() . '/admin/' . $adminLanding);
    exit;
}

$isMfaPending = !empty($_SESSION['mfa_pending']);

// Handle cancel MFA action
if (isset($_GET['cancel_mfa']) && $isMfaPending) {
    unset($_SESSION['mfa_pending'], $_SESSION['mfa_remember_me'], $_SESSION['mfa_username']);
    header('Location: ' . base_path() . '/admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($isLockedOut) {
        $remaining = $lockoutUntil - $now;
        $minutes = (int) ceil($remaining / 60);
        $error = t('admin.login.error_lockout', ['minutes' => $minutes]);
    } elseif ($isMfaPending) {
        // Step 2: MFA Verification
        $mfaCode = trim((string) ($_POST['mfa_code'] ?? ''));
        $mfaSecret = (string) ($config['mfa_secret'] ?? '');
        $mfaBackupCodes = (array) ($config['mfa_backup_codes'] ?? []);

        $mfaVerified = false;
        $usedBackupIndex = null;

        if ($mfaCode !== '' && $mfaSecret !== '' && totp_verify_code($mfaSecret, $mfaCode)) {
            $mfaVerified = true;
        } elseif ($mfaCode !== '' && !empty($mfaBackupCodes)) {
            $usedBackupIndex = totp_verify_backup_code($mfaCode, $mfaBackupCodes);
            if ($usedBackupIndex !== null) {
                $mfaVerified = true;
                // Remove used backup code from config
                array_splice($mfaBackupCodes, $usedBackupIndex, 1);
                $config['mfa_backup_codes'] = $mfaBackupCodes;
                save_config($config);
            }
        }

        if ($mfaVerified) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $rememberMe = !empty($_SESSION['mfa_remember_me']);
            unset($_SESSION['mfa_pending'], $_SESSION['mfa_remember_me'], $_SESSION['mfa_username']);
            clear_login_failures($clientIp);
            if ($rememberMe) {
                set_remember_me_cookie();
            }
            $blogPostsEnabled = $config['enable_blog_posts'] ?? true;
            $adminLanding = (!$blogPostsEnabled || ($config['admin_homepage'] ?? 'dashboard') === 'content') ? 'content.php' : 'dashboard.php';
            header('Location: ' . base_path() . '/admin/' . $adminLanding);
            exit;
        }

        $state = record_login_failure($clientIp);
        if ($state['lockout_until'] > $now) {
            $lockoutUntil = $state['lockout_until'];
            $isLockedOut = true;
            $error = t('admin.login.error_lockout_5');
        } else {
            $error = t('admin.login.mfa_error_invalid');
        }
    } else {
        // Step 1: Username & Password Verification
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username !== '' && hash_equals($config['admin_username'] ?? '', $username)
            && password_verify($password, $config['admin_password_hash'] ?? '')
        ) {
            $mfaActive = !empty($config['mfa_enabled']) && !empty($config['mfa_secret']);
            if ($mfaActive) {
                session_regenerate_id(true);
                $_SESSION['mfa_pending'] = true;
                $_SESSION['mfa_remember_me'] = !empty($_POST['remember_me']);
                $_SESSION['mfa_username'] = $username;
                $isMfaPending = true;
            } else {
                session_regenerate_id(true);
                $_SESSION['is_admin'] = true;
                clear_login_failures($clientIp);
                if (!empty($_POST['remember_me'])) {
                    set_remember_me_cookie();
                }
                $blogPostsEnabled = $config['enable_blog_posts'] ?? true;
                $adminLanding = (!$blogPostsEnabled || ($config['admin_homepage'] ?? 'dashboard') === 'content') ? 'content.php' : 'dashboard.php';
                header('Location: ' . base_path() . '/admin/' . $adminLanding);
                exit;
            }
        } else {
            $state = record_login_failure($clientIp);
            if ($state['lockout_until'] > $now) {
                $lockoutUntil = $state['lockout_until'];
                $isLockedOut = true;
                $error = t('admin.login.error_lockout_5');
            } else {
                $error = t('admin.login.error_invalid');
            }
        }
    }
}

$adminTitle = $isMfaPending ? t('admin.login.mfa_page_title') : t('admin.login.page_title');
$hideAdminNav = true;
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="narrow">
        <br>
        <?php if ($isMfaPending): ?>
            <h1><?= e(t('admin.login.mfa_heading')) ?></h1>
            <p><?= e(t('admin.login.mfa_prompt')) ?></p>
            <?php if ($error !== ''): ?>
                <p class="notice delete"><?= e($error) ?></p>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <label for="mfa_code"><?= e(t('admin.login.mfa_code_label')) ?></label>
                <input type="text" id="mfa_code" name="mfa_code" autofocus inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required<?= $isLockedOut ? ' disabled' : '' ?>>
                <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 1.25rem;">
                    <button type="submit"<?= $isLockedOut ? ' disabled' : '' ?>><svg class="icon" aria-hidden="true"><use href="#icon-circle-check"></use></svg> <?= e(t('admin.login.mfa_submit')) ?></button>
                    <a href="?cancel_mfa=1" class="button link-button"><svg class="icon" aria-hidden="true"><use href="#icon-arrow-left"></use></svg> <?= e(t('admin.login.mfa_cancel')) ?></a>
                </div>
            </form>
        <?php else: ?>
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

                <label class="checkbox-label">
                    <input type="checkbox" name="remember_me" value="1"<?= $isLockedOut ? ' disabled' : '' ?>>
                    <?= e(t('admin.login.remember_me')) ?>
                </label>
                <button type="submit"<?= $isLockedOut ? ' disabled' : '' ?>><svg class="icon" aria-hidden="true"><use href="#icon-circle-check"></use></svg> <?= e(t('admin.login.submit')) ?></button>
            </form>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
