<?php

declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$slug = '';
$post = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action_id'])) {
    verify_csrf();
    $slug = trim($_POST['slug'] ?? '');
    $post = $slug !== '' ? get_post_by_slug($slug, true) : null;
    if ($slug === '') {
        $errors[] = t('admin.delete_post.error_missing');
    } elseif (!$post) {
        $errors[] = t('admin.delete_post.error_not_found');
    } elseif (!delete_post_by_slug($slug)) {
        $errors[] = t('admin.delete_post.error_delete');
    } else {
        header('Location: ' . base_path() . '/admin/content.php?tab=posts&deleted=1');
        exit;
    }
} else {
    $slug = trim($_GET['slug'] ?? '');
    $post = $slug !== '' ? get_post_by_slug($slug, true) : null;
}

$adminTitle = t('admin.delete_post.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main>
        <h1><?= e(t('admin.delete_post.heading')) ?></h1>


        <?php if ($errors): ?>
            <div class="notice delete">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$post): ?>
            <p><?= e(t('admin.delete_post.already_deleted')) ?></p>
        <?php else: ?>
            <p><?= e(t('admin.delete_post.confirm', ['title' => $post['title']])) ?></p>
            <form method=”post”>
                <input type=”hidden” name=”slug” value=”<?= e($post['slug']) ?>”>
                <?= csrf_field() ?>
                <button type=”submit” class=”delete”><?= e(t('admin.delete_post.submit')) ?></button>
            </form>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
