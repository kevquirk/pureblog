<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action_id'])) {
    verify_csrf();
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
        }

        if (save_config($config)) {
            $notice = t('admin.settings.user.notice_updated');
        } else {
            $errors[] = t('admin.settings.user.error_save');
        }
    }
}

$adminTitle = t('admin.settings.user.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1><?= e(t('admin.settings.user.heading')) ?></h1>
        <?php require __DIR__ . '/../includes/admin-notices.php'; ?>

        <?php $settingsSaveFormId = 'settings-form'; ?>
        <nav class="editor-actions settings-actions">
            <?php require __DIR__ . '/../includes/admin-settings-nav.php'; ?>
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
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
