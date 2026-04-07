<?php

declare(strict_types=1);

require __DIR__ . '/functions.php';

if (is_installed()) {
    header('Location: ' . base_path() . '/admin/index.php');
    exit;
}

send_security_headers();
start_admin_session();

$config = default_config();
$errors = [];
$values = [
    'site_title' => '',
    'site_tagline' => '',
    'language' => 'en',
    'base_url' => '',
    'admin_username' => '',
];

// Determine language early so t() renders in the chosen language throughout.
$values['language'] = trim($_POST['language'] ?? $_GET['lang'] ?? 'en');
lang_init($values['language']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values['site_title'] = trim($_POST['site_title'] ?? '');
    $values['site_tagline'] = trim($_POST['site_tagline'] ?? '');
    $values['language'] = trim($_POST['language'] ?? 'en');
    $values['base_url'] = trim($_POST['base_url'] ?? '');
    $values['admin_username'] = trim($_POST['admin_username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($values['site_title'] === '') {
        $errors[] = t('setup.error_title');
    }

    if ($values['admin_username'] === '') {
        $errors[] = t('setup.error_username');
    }

    if ($password === '' || $confirm === '') {
        $errors[] = t('setup.error_password');
    } elseif ($password !== $confirm) {
        $errors[] = t('setup.error_mismatch');
    }

    if (!$errors) {
        $config = default_config();
        $config['site_title'] = $values['site_title'];
        $config['site_tagline'] = $values['site_tagline'];
        $config['language'] = $values['language'] !== '' ? $values['language'] : 'en';
        $config['base_url'] = $values['base_url'] !== '' ? $values['base_url'] : get_base_url();
        $config['admin_username'] = $values['admin_username'];
        $config['admin_password_hash'] = password_hash($password, PASSWORD_DEFAULT);

        $configDir = dirname(PUREBLOG_CONFIG_PATH);
        if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
            $errors[] = t('setup.error_config_dir');
        } elseif (save_config($config)) {
            header('Location: ' . base_path() . '/admin/index.php?setup=1', true, 303);
            exit;
        }

        if (!$errors) {
            $errors[] = t('setup.error_config_write');
        }
    }
}

$adminTitle = t('setup.page_title');
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$hideAdminNav = true;
require __DIR__ . '/includes/admin-head.php';
?>
    <main class="narrow">
        <h1><?= e(t('setup.heading')) ?></h1>
        <br>
        <?php if ($errors): ?>
            <div class="notice">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <label for="language"><?= e(t('setup.language')) ?></label>
            <select id="language" name="language" onchange="window.location.href='?lang='+encodeURIComponent(this.value)">
                <?php foreach (lang_available() as $code => $nativeName): ?>
                    <option value="<?= e($code) ?>"<?= $code === $values['language'] ? ' selected' : '' ?>><?= e($nativeName) ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="site_title"><?= e(t('setup.site_title')) ?></label>
            <input type="text" id="site_title" name="site_title" value="<?= e($values['site_title']) ?>" placeholder="Sally's Blog" required>

            <label for="site_tagline"><?= e(t('setup.tagline')) ?></label>
            <input type="text" id="site_tagline" name="site_tagline" value="<?= e($values['site_tagline']) ?>" placeholder="A blog about my thoughts...">

            <label for="base_url"><?= e(t('setup.base_url')) ?></label>
            <input type="text" id="base_url" name="base_url" value="<?= e($values['base_url']) ?>" placeholder="https://example.com" required>

            <label for="admin_username"><?= e(t('setup.username')) ?></label>
            <input type="text" id="admin_username" name="admin_username" value="<?= e($values['admin_username']) ?>" required>

            <label for="password"><?= e(t('setup.password')) ?></label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password"><?= e(t('setup.confirm_password')) ?></label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <p><button type="submit"><svg class="icon" aria-hidden="true"><use href="#icon-circle-check"></use></svg> <?= e(t('setup.submit')) ?></button></p>
        </form>
    </main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
