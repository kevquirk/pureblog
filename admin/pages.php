<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();
$pages = get_all_pages(true);
usort($pages, function (array $a, array $b): int {
    if ($a['status'] !== $b['status']) {
        return $a['status'] === 'draft' ? -1 : 1;
    }
    return ($a['title'] <=> $b['title']);
});
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$adminTitle = 'Pages - Pureblog';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1>Pages</h1>
        <nav class="editor-actions">
            <a href="/admin/edit-page.php?action=new">
                <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-file-plus-corner"></use></svg>
                New page
            </a>
        </nav>

        <?php if (!empty($_GET['saved'])): ?>
            <p class="notice" data-auto-dismiss>Page saved.</p>
        <?php endif; ?>
        <?php if (!empty($_GET['deleted'])): ?>
            <p class="notice" data-auto-dismiss>Page deleted.</p>
        <?php endif; ?>

        <?php if (!$pages): ?>
            <p>No pages yet.</p>
        <?php else: ?>
            <ul class="admin-list">
                <?php foreach ($pages as $page): ?>
                    <li class="admin-list-item">
                        <a class="admin-list-title" href="/admin/edit-page.php?slug=<?= e($page['slug']) ?>">
                            <?= e($page['title']) ?>
                        </a>
                        <div class="admin-list-meta">
                            <span class="status <?= e($page['status']) ?>"><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-toggle-right"></use></svg> <?= e($page['status']) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
