<?php

declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
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

$adminTitle = t('admin.pages.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1><?= e(t('admin.pages.heading')) ?></h1>
        <nav class="editor-actions">
            <a href="<?= base_path() ?>/admin/edit-page.php?action=new">
                <svg class="icon" aria-hidden="true"><use href="#icon-file-plus-corner"></use></svg>
                <?= e(t('admin.pages.new_page')) ?>
            </a>
        </nav>

        <?php if (!empty($_GET['saved'])): ?>
            <p class="notice" data-auto-dismiss><?= e(t('admin.pages.notice_saved')) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['deleted'])): ?>
            <p class="notice" data-auto-dismiss><?= e(t('admin.pages.notice_deleted')) ?></p>
        <?php endif; ?>

        <?php if (!$pages): ?>
            <p><?= e(t('admin.pages.no_pages')) ?></p>
        <?php else: ?>
            <ul class="admin-list">
                <?php foreach ($pages as $page): ?>
                    <li class="admin-list-item">
                        <a class="admin-list-title" href="<?= base_path() ?>/admin/edit-page.php?slug=<?= e($page['slug']) ?>">
                            <?= e($page['title']) ?>
                        </a>
                        <div class="admin-list-meta">
                            <span class="status <?= e($page['status']) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-toggle-right"></use></svg> <?= e(t('admin.editor.status_' . $page['status'])) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
