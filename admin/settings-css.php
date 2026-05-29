<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$errors = [];
$notice = '';

$frontCssDir = __DIR__ . '/../content/css';
$adminCssDir = __DIR__ . '/../content/css';
$frontCssPath = $frontCssDir . '/custom.css';
$adminCssPath = $adminCssDir . '/admin-custom.css';
$frontCss = is_file($frontCssPath) ? (string) file_get_contents($frontCssPath) : '';
$adminCss = is_file($adminCssPath) ? (string) file_get_contents($adminCssPath) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action_id'])) {
    verify_csrf();
    $frontCss = $_POST['front_css'] ?? '';
    $adminCss = $_POST['admin_css'] ?? '';

    if (!is_dir($frontCssDir) && !mkdir($frontCssDir, 0755, true)) {
        $errors[] = t('admin.settings.css.error_dir_front');
    }

    if (!is_dir($adminCssDir) && !mkdir($adminCssDir, 0755, true)) {
        $errors[] = t('admin.settings.css.error_dir_admin');
    }

    if (!$errors) {
        if (file_put_contents($frontCssPath, $frontCss) === false) {
            $errors[] = t('admin.settings.css.error_save_front');
        }

        if (file_put_contents($adminCssPath, $adminCss) === false) {
            $errors[] = t('admin.settings.css.error_save_admin');
        }
    }

    if (!$errors) {
        $notice = t('admin.settings.css.notice_saved');
    }
}

$adminTitle = t('admin.settings.css.page_title');
$codeMirror = 'css';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1><?= e(t('admin.settings.css.heading')) ?></h1>
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
                <span class="title"><?= e(t('admin.settings.css.section_front')) ?></span>
                <textarea id="front_css" name="front_css" rows="16" spellcheck="false" placeholder="<?= e(t('admin.settings.css.placeholder_front')) ?>"><?= e($frontCss) ?></textarea>
            </section>
            <section class="section-divider">
                <span class="title"><?= e(t('admin.settings.css.section_admin')) ?></span>
                <textarea id="admin_css" name="admin_css" rows="16" spellcheck="false" placeholder="<?= e(t('admin.settings.css.placeholder_admin')) ?>"><?= e($adminCss) ?></textarea>
            </section>
        </form>
    </main>
    <script>
        const frontCssField = document.getElementById('front_css');
        const adminCssField = document.getElementById('admin_css');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const adminTheme = document.documentElement?.dataset?.adminTheme || 'auto';
        const useDarkTheme = adminTheme === 'dark' || (adminTheme === 'auto' && prefersDark);
        const cmConfig = {
            mode: 'css',
            lineNumbers: false,
            lineWrapping: true,
            viewportMargin: Infinity,
            inputStyle: 'contenteditable',
            spellcheck: false,
            theme: useDarkTheme ? 'material-darker' : 'default',
        };

        if (frontCssField) {
            CodeMirror.fromTextArea(frontCssField, {
                ...cmConfig,
                placeholder: frontCssField.getAttribute('placeholder') || '',
            });
        }

        if (adminCssField) {
            CodeMirror.fromTextArea(adminCssField, {
                ...cmConfig,
                placeholder: adminCssField.getAttribute('placeholder') || '',
            });
        }

    </script>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
