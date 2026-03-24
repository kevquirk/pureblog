<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$slug = '';
$page = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action_id'])) {
    verify_csrf();
    $slug = trim($_POST['slug'] ?? '');
    $page = $slug !== '' ? get_page_by_slug($slug, true) : null;
    if ($slug === '') {
        $errors[] = t('admin.delete_page.error_missing');
    } elseif (!$page) {
        $errors[] = t('admin.delete_page.error_not_found');
    } elseif (!delete_page_by_slug($slug)) {
        $errors[] = t('admin.delete_page.error_delete');
    } else {
        header('Location: ' . base_path() . '/admin/pages.php?deleted=1');
        exit;
    }
} else {
    $slug = trim($_GET['slug'] ?? '');
    $page = $slug !== '' ? get_page_by_slug($slug, true) : null;
}

$adminTitle = t('admin.delete_page.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main>
        <h1><?= e(t('admin.delete_page.heading')) ?></h1>


        <?php if ($errors): ?>
            <div class="notice">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$page): ?>
            <p><?= e(t('admin.delete_page.already_deleted')) ?></p>
        <?php else: ?>
            <p><?= e(t('admin.delete_page.confirm', ['title' => $page['title']])) ?></p>
            <form method=”post”>
                <input type=”hidden” name=”slug” value=”<?= e($page['slug']) ?>”>
                <?= csrf_field() ?>
                <button type=”submit” class=”delete”><?= e(t('admin.delete_page.submit')) ?></button>
            </form>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
