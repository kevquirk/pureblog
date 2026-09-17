<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$errors = [];
$notice = '';
$newBackupCodes = $_SESSION['new_backup_codes'] ?? null;
unset($_SESSION['new_backup_codes']);

$mfaEnabled = !empty($config['mfa_enabled']) && !empty($config['mfa_secret']);
$isSettingUpMfa = !empty($_SESSION['mfa_setup_secret']) && !$mfaEnabled;

// Handle cancelling MFA setup
if (isset($_GET['cancel_mfa_setup'])) {
    unset($_SESSION['mfa_setup_secret']);
    header('Location: ' . base_path() . '/admin/settings-user.php');
    exit;
}

// Start MFA setup (only generate a new secret if one does not already exist in session)
if (isset($_GET['setup_mfa']) && !$mfaEnabled) {
    if (empty($_SESSION['mfa_setup_secret'])) {
        $_SESSION['mfa_setup_secret'] = totp_generate_secret(16);
    }
    $isSettingUpMfa = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action_id'])) {
    verify_csrf();
    $mfaAction = $_POST['mfa_action'] ?? '';

    if ($mfaAction === 'confirm_setup' && !empty($_SESSION['mfa_setup_secret'])) {
        $secret = (string) $_SESSION['mfa_setup_secret'];
        $code = trim((string) ($_POST['mfa_code'] ?? ''));

        if (totp_verify_code($secret, $code)) {
            $plainCodes = totp_generate_backup_codes(8);
            $config['mfa_enabled'] = true;
            $config['mfa_secret'] = $secret;
            $config['mfa_backup_codes'] = totp_hash_backup_codes($plainCodes);

            if (save_config($config)) {
                unset($_SESSION['mfa_setup_secret']);
                $_SESSION['new_backup_codes'] = $plainCodes;
                clear_all_remember_me_tokens();
                $notice = t('admin.settings.user.mfa_notice_enabled');
                $mfaEnabled = true;
                $isSettingUpMfa = false;
                $newBackupCodes = $plainCodes;
            } else {
                $errors[] = t('admin.settings.user.error_save');
            }
        } else {
            $errors[] = t('admin.settings.user.mfa_error_verify_code');
            $isSettingUpMfa = true;
        }
    } elseif ($mfaAction === 'disable_mfa' && $mfaEnabled) {
        $password = (string) ($_POST['mfa_disable_password'] ?? '');
        if (password_verify($password, $config['admin_password_hash'] ?? '')) {
            $config['mfa_enabled'] = false;
            $config['mfa_secret'] = '';
            $config['mfa_backup_codes'] = [];

            if (save_config($config)) {
                clear_all_remember_me_tokens();
                $notice = t('admin.settings.user.mfa_notice_disabled');
                $mfaEnabled = false;
            } else {
                $errors[] = t('admin.settings.user.error_save');
            }
        } else {
            $errors[] = t('admin.settings.user.mfa_error_password');
        }
    } elseif ($mfaAction === 'regenerate_backup_codes' && $mfaEnabled) {
        $password = (string) ($_POST['mfa_regenerate_password'] ?? '');
        if (password_verify($password, $config['admin_password_hash'] ?? '')) {
            $plainCodes = totp_generate_backup_codes(8);
            $config['mfa_backup_codes'] = totp_hash_backup_codes($plainCodes);

            if (save_config($config)) {
                $_SESSION['new_backup_codes'] = $plainCodes;
                $notice = t('admin.settings.user.mfa_notice_codes_regenerated');
                $newBackupCodes = $plainCodes;
            } else {
                $errors[] = t('admin.settings.user.error_save');
            }
        } else {
            $errors[] = t('admin.settings.user.mfa_error_password');
        }
    } else {
        // Main user profile update (username / password)
        $adminUsername = trim($_POST['admin_username'] ?? '');
        $passwordCurrent = $_POST['current_password'] ?? '';
        $passwordNew = $_POST['new_password'] ?? '';
        $passwordConfirm = $_POST['confirm_password'] ?? '';

        if ($adminUsername === '') {
            $errors[] = t('admin.settings.user.error_username');
        }

        if (($passwordNew !== '' || $passwordConfirm !== '') && $passwordNew !== $passwordConfirm) {
            $errors[] = t('admin.settings.user.error_password_match');
        }

        if ($passwordNew !== '' && !password_verify($passwordCurrent, $config['admin_password_hash'] ?? '')) {
            $errors[] = t('admin.settings.user.error_password_wrong');
        }

        if (!$errors) {
            $config['admin_username'] = $adminUsername;

            if ($passwordNew !== '') {
                $config['admin_password_hash'] = password_hash($passwordNew, PASSWORD_DEFAULT);
                clear_all_remember_me_tokens();
            }

            if (save_config($config)) {
                $notice = t('admin.settings.user.notice_updated');
            } else {
                $errors[] = t('admin.settings.user.error_save');
            }
        }
    }
}

// Generate QR code if setting up MFA
$mfaQrSvg = '';
$mfaProvisioningUri = '';
$mfaSecret = '';
if ($isSettingUpMfa && !empty($_SESSION['mfa_setup_secret'])) {
    $mfaSecret = (string) $_SESSION['mfa_setup_secret'];
    $siteTitle = $config['site_title'] ?? 'Pure Blog';
    $accountName = $config['admin_username'] ?? 'admin';
    $mfaProvisioningUri = totp_get_provisioning_uri($mfaSecret, $accountName, $siteTitle);
    $mfaQrSvg = qrcode_svg($mfaProvisioningUri, 200);
}

$adminTitle = t('admin.settings.user.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1><?= e(t('admin.settings.user.heading')) ?></h1>
        <?php require __DIR__ . '/../includes/admin-notices.php'; ?>

        <nav class="admin-actions">
            <button class="save" type="submit" form="settings-form" aria-label="<?= e(t('admin.settings.nav.save')) ?>">
                <svg class="icon" aria-hidden="true"><use href="#icon-save"></use></svg>
                <?= e(t('admin.settings.nav.save')) ?>
            </button>
        </nav>

        <form method="post" id="settings-form">
            <?= csrf_field() ?>
            <section class="section-divider">
                <span class="title"><?= e(t('admin.settings.user.section_account')) ?></span>
                <label for="admin_username"><?= e(t('admin.settings.user.username')) ?></label>
                <input type="text" id="admin_username" name="admin_username" value="<?= e($config['admin_username'] ?? '') ?>" required>
            </section>

            <section class="section-divider">
                <span class="title"><?= e(t('admin.settings.user.section_password')) ?></span>
                <label for="current_password"><?= e(t('admin.settings.user.current_password')) ?></label>
                <input type="password" id="current_password" name="current_password">

                <label for="new_password"><?= e(t('admin.settings.user.new_password')) ?></label>
                <input type="password" id="new_password" name="new_password">

                <label for="confirm_password"><?= e(t('admin.settings.user.confirm_password')) ?></label>
                <input type="password" id="confirm_password" name="confirm_password">
            </section>
        </form>

        <section class="section-divider">
            <span class="title"><?= e(t('admin.settings.user.mfa_section_title')) ?></span>

            <?php if (!empty($newBackupCodes)): ?>
                <div class="notice" style="margin-bottom: 1.5rem;">
                    <strong><?= e(t('admin.settings.user.mfa_backup_codes_heading')) ?></strong>
                    <p><?= e(t('admin.settings.user.mfa_backup_codes_warning')) ?></p>
                    <pre style="background: var(--bg-color); padding: 1rem; font-size: 1.1rem; line-height: 1.8; user-select: all; border: 1px dashed var(--border-color); margin: 0.75rem 0;"><code><?= e(implode("\n", $newBackupCodes)) ?></code></pre>
                    <button type="button" class="link-button" onclick="navigator.clipboard.writeText(<?= e(json_encode(implode("\n", $newBackupCodes))) ?>).then(()=>{this.innerHTML='<svg class=\'icon\' aria-hidden=\'true\'><use href=\'#icon-circle-check\'></use></svg> <?= e(addslashes(t('admin.settings.user.mfa_copied'))) ?>'; setTimeout(()=>{this.innerHTML='<svg class=\'icon\' aria-hidden=\'true\'><use href=\'#icon-copy\'></use></svg> <?= e(addslashes(t('admin.settings.user.mfa_copy_codes'))) ?>';}, 2500);})">
                        <svg class="icon" aria-hidden="true"><use href="#icon-copy"></use></svg> <?= e(t('admin.settings.user.mfa_copy_codes')) ?>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($mfaEnabled): ?>
                <p>
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #2e7d32; font-weight: bold;">
                        <svg class="icon" aria-hidden="true"><use href="#icon-circle-check"></use></svg>
                        <?= e(t('admin.settings.user.mfa_status_enabled')) ?>
                    </span>
                </p>
                <p><?= e(t('admin.settings.user.mfa_enabled_desc')) ?></p>
                <br>

                <details style="margin-bottom: 1.25rem;">
                    <summary style="cursor: pointer; font-weight: bold;"><?= e(t('admin.settings.user.mfa_regenerate_codes_title')) ?></summary>
                    <form method="post" style="margin-top: 1rem;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="mfa_action" value="regenerate_backup_codes">
                        <label for="mfa_regenerate_password"><?= e(t('admin.settings.user.mfa_confirm_password_label')) ?></label>
                        <input type="password" id="mfa_regenerate_password" name="mfa_regenerate_password" required>
                        <button type="submit"><svg class="icon" aria-hidden="true"><use href="#icon-save"></use></svg> <?= e(t('admin.settings.user.mfa_regenerate_codes_btn')) ?></button>
                    </form>
                </details>

                <details>
                    <summary style="cursor: pointer; font-weight: bold; color: var(--delete-color, inherit);"><?= e(t('admin.settings.user.mfa_disable_title')) ?></summary>
                    <form method="post" style="margin-top: 1rem;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="mfa_action" value="disable_mfa">
                        <label for="mfa_disable_password"><?= e(t('admin.settings.user.mfa_confirm_password_label')) ?></label>
                        <input type="password" id="mfa_disable_password" name="mfa_disable_password" required>
                        <button type="submit" class="delete"><svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg> <?= e(t('admin.settings.user.mfa_disable_btn')) ?></button>
                    </form>
                </details>
            <?php elseif ($isSettingUpMfa): ?>
                <p><?= e(t('admin.settings.user.mfa_setup_instructions')) ?></p>

                <div style="display: flex; flex-wrap: wrap; gap: 2rem; align-items: flex-start; margin: 1.5rem 0;">
                    <div style="background: #ffffff; padding: 0.75rem; border: 1px solid var(--border-color); display: inline-block; border-radius: 4px;">
                        <?= $mfaQrSvg ?>
                    </div>
                    <div style="flex: 1; min-width: 250px;">
                        <p style="margin-top: 0;"><?= e(t('admin.settings.user.mfa_manual_entry_intro')) ?></p>
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin: 0.5rem 0 1.25rem 0;">
                            <code style="font-size: 1.15rem; font-weight: bold; padding: 0.35rem 0.6rem; letter-spacing: 0.05em; user-select: all;"><?= e($mfaSecret) ?></code>
                            <button type="button" class="link-button" onclick="navigator.clipboard.writeText('<?= e($mfaSecret) ?>').then(()=>{this.innerHTML='<svg class=\'icon\' aria-hidden=\'true\'><use href=\'#icon-circle-check\'></use></svg> <?= e(addslashes(t('admin.settings.user.mfa_copied'))) ?>'; setTimeout(()=>{this.innerHTML='<svg class=\'icon\' aria-hidden=\'true\'><use href=\'#icon-copy\'></use></svg> <?= e(addslashes(t('admin.settings.user.mfa_copy'))) ?>';}, 2000);})">
                                <svg class="icon" aria-hidden="true"><use href="#icon-copy"></use></svg> <?= e(t('admin.settings.user.mfa_copy')) ?>
                            </button>
                        </div>

                        <form method="post" action="<?= e(base_path()) ?>/admin/settings-user.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mfa_action" value="confirm_setup">
                            <label for="mfa_code"><?= e(t('admin.settings.user.mfa_enter_code_label')) ?></label>
                            <input type="text" id="mfa_code" name="mfa_code" autofocus inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required>
                            <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 1rem;">
                                <button type="submit"><svg class="icon" aria-hidden="true"><use href="#icon-circle-check"></use></svg> <?= e(t('admin.settings.user.mfa_activate_btn')) ?></button>
                                <a href="?cancel_mfa_setup=1" class="button link-button"><svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg> <?= e(t('admin.settings.user.mfa_cancel_btn')) ?></a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <p><?= e(t('admin.settings.user.mfa_disabled_desc')) ?></p>
                <a href="?setup_mfa=1" class="button"><svg class="icon" aria-hidden="true"><use href="#icon-circle-check"></use></svg> <?= e(t('admin.settings.user.mfa_setup_btn')) ?></a>
            <?php endif; ?>
        </section>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
