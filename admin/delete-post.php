<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
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
        $errors[] = 'Missing post slug.';
    } elseif (!$post) {
        $errors[] = 'Post not found.';
    } elseif (!delete_post_by_slug($slug)) {
        $errors[] = 'Unable to delete post.';
    } else {
        header('Location: /admin/dashboard.php?deleted=1');
        exit;
    }
} else {
    $slug = trim($_GET['slug'] ?? '');
    $post = $slug !== '' ? get_post_by_slug($slug, true) : null;
}

$adminTitle = 'Delete Post - Pureblog';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main>
        <h1>Delete Post</h1>


        <?php if ($errors): ?>
            <div class="notice">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$post): ?>
            <p>This post has already been deleted (or no longer exists).</p>
        <?php else: ?>
            <p>Are you sure you want to delete “<?= e($post['title']) ?>”?</p>
            <form method="post">
                <input type="hidden" name="slug" value="<?= e($post['slug']) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="delete">Yes, delete this post</button>
            </form>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
