<?php

declare(strict_types=1);

require __DIR__ . '/functions.php';

if (is_installed()) {
    header('Location: /admin/index.php');
    exit;
}

$config = default_config();
$errors = [];
$values = [
    'site_title' => '',
    'site_tagline' => '',
    'base_url' => '',
    'admin_username' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['site_title'] = trim($_POST['site_title'] ?? '');
    $values['site_tagline'] = trim($_POST['site_tagline'] ?? '');
    $values['base_url'] = trim($_POST['base_url'] ?? '');
    $values['admin_username'] = trim($_POST['admin_username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($values['site_title'] === '') {
        $errors[] = 'Site title is required.';
    }

    if ($values['admin_username'] === '') {
        $errors[] = 'Admin username is required.';
    }

    if ($password === '' || $confirm === '') {
        $errors[] = 'Password and confirmation are required.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $config = default_config();
        $config['site_title'] = $values['site_title'];
        $config['site_tagline'] = $values['site_tagline'];
        $config['base_url'] = $values['base_url'] !== '' ? $values['base_url'] : get_base_url();
        $config['admin_username'] = $values['admin_username'];
        $config['admin_password_hash'] = password_hash($password, PASSWORD_DEFAULT);

        $configDir = dirname(PUREBLOG_CONFIG_PATH);
        if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
            $errors[] = 'Failed to create config directory. Check permissions.';
        } elseif (save_config($config)) {
            header('Location: /admin/index.php?setup=1', true, 303);
            exit;
        }

        if (!$errors) {
            $errors[] = 'Failed to write config file. Check permissions.';
        }
    }
}

$adminTitle = 'Setup - Pureblog';
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$hideAdminNav = true;
require __DIR__ . '/includes/admin-head.php';
?>
    <main class="narrow">
        <h1>Setup your new site</h1>
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
            <label for="site_title">Site title</label>
            <input type="text" id="site_title" name="site_title" value="<?= e($values['site_title']) ?>" placeholder="Sally's Blog" required>

            <label for="site_tagline">Site tagline (optional)</label>
            <input type="text" id="site_tagline" name="site_tagline" value="<?= e($values['site_tagline']) ?>" placeholder="A blog about my thoughts...">

            <label for="base_url">Site URL</label>
            <input type="text" id="base_url" name="base_url" value="<?= e($values['base_url']) ?>" placeholder="https://example.com" required>

            <label for="admin_username">Admin username</label>
            <input type="text" id="admin_username" name="admin_username" value="<?= e($values['admin_username']) ?>" required>

            <label for="password">Admin password</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <p><button type="submit"><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-check"></use></svg> Create Site</button></p>
        </form>
    </main>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
