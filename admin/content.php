<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();

$tab = (string) ($_GET['tab'] ?? 'posts');
if (!in_array($tab, ['posts', 'pages'], true)) {
    $tab = 'posts';
}

// Posts data
$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim((string) ($_GET['q'] ?? ''));
$allPosts = get_all_posts(true);
usort($allPosts, function (array $a, array $b): int {
    if ($a['status'] !== $b['status']) {
        return $a['status'] === 'draft' ? -1 : 1;
    }
    return ($b['timestamp'] <=> $a['timestamp']);
});
$filteredPosts = filter_posts_by_query($allPosts, $search);
$totalPosts = count($filteredPosts);
$totalPages = $totalPosts > 0 ? (int) ceil($totalPosts / $perPage) : 1;
$offset = ($page - 1) * $perPage;
$posts = array_slice($filteredPosts, $offset, $perPage);
$availableLayouts = get_layouts();

// Pages data
$pages = get_all_pages(true);
usort($pages, function (array $a, array $b): int {
    if ($a['status'] !== $b['status']) {
        return $a['status'] === 'draft' ? -1 : 1;
    }
    return ($a['title'] <=> $b['title']);
});

$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$adminTitle = t('admin.content.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <nav class="content-tabs" aria-label="<?= e(t('admin.content.tabs_label')) ?>">
            <a href="<?= base_path() ?>/admin/content.php?tab=posts"<?= $tab === 'posts' ? ' class="current" aria-current="page"' : '' ?>><?= e(t('admin.content.tab_posts')) ?></a>
            <a href="<?= base_path() ?>/admin/content.php?tab=pages"<?= $tab === 'pages' ? ' class="current" aria-current="page"' : '' ?>><?= e(t('admin.content.tab_pages')) ?></a>
        </nav>

        <?php if ($tab === 'posts'): ?>

            <?php if (!empty($_GET['saved'])): ?>
                <p class="notice" data-auto-dismiss><?= e(t('admin.content.notice_post_saved')) ?></p>
            <?php endif; ?>
            <?php if (!empty($_GET['deleted'])): ?>
                <p class="notice" data-auto-dismiss><?= e(t('admin.content.notice_post_deleted')) ?></p>
            <?php endif; ?>

            <nav class="editor-actions">
                <?php if ($availableLayouts): ?>
                    <button type="button" id="new-post-button">
                        <svg class="icon" aria-hidden="true"><use href="#icon-file-plus-corner"></use></svg>
                        <?= e(t('admin.content.new_post')) ?>
                    </button>
                    <dialog id="layout-picker" aria-labelledby="layout-picker-title">
                        <h2 id="layout-picker-title"><?= e(t('admin.content.choose_layout')) ?></h2>
                        <ul class="layout-picker-list">
                            <li><a href="<?= base_path() ?>/admin/edit-post.php?action=new"><?= e(t('admin.content.default_post')) ?></a></li>
                            <?php foreach ($availableLayouts as $layout): ?>
                                <li><a href="<?= base_path() ?>/admin/edit-post.php?action=new&amp;layout=<?= urlencode($layout['name']) ?>"><?= e($layout['label']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" id="layout-picker-close" class="delete">
                            <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                            <?= e(t('admin.content.cancel')) ?>
                        </button>
                    </dialog>
                    <script>
                    (function () {
                        const button = document.getElementById('new-post-button');
                        const dialog = document.getElementById('layout-picker');
                        const close = document.getElementById('layout-picker-close');
                        button.addEventListener('click', () => dialog.showModal());
                        close.addEventListener('click', () => dialog.close());
                        dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
                    })();
                    </script>
                <?php else: ?>
                    <a href="<?= base_path() ?>/admin/edit-post.php?action=new">
                        <svg class="icon" aria-hidden="true"><use href="#icon-file-plus-corner"></use></svg>
                        <?= e(t('admin.content.new_post')) ?>
                    </a>
                <?php endif; ?>
            </nav>

            <form method="get" class="admin-search">
                <input type="hidden" name="tab" value="posts">
                <label class="hidden" for="search"><?= e(t('admin.content.search_label')) ?></label>
                <input type="search" id="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('admin.content.search_placeholder')) ?>" autocomplete="off">
            </form>

            <?php if (!$posts): ?>
                <?php if ($search !== ''): ?>
                    <p><?= e(t('admin.content.no_posts_found', ['search' => $search])) ?></p>
                <?php else: ?>
                    <p><?= e(t('admin.content.no_posts')) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <ul class="admin-list">
                    <?php foreach ($posts as $post): ?>
                        <li class="admin-list-item">
                            <a class="admin-list-title" href="<?= base_path() ?>/admin/edit-post.php?slug=<?= e($post['slug']) ?>">
                                <?= e($post['title']) ?>
                            </a>
                            <div class="admin-list-meta">
                                <span><svg class="icon" aria-hidden="true"><use href="#icon-calendar"></use></svg> <?= e(format_datetime_for_display((string) ($post['date'] ?? ''), $config, 'Y-m-d @ H:i')) ?></span>
                                <span class="status <?= e($post['status']) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-toggle-right"></use></svg> <?= e(t('admin.editor.status_' . $post['status'])) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($totalPages > 1): ?>
                    <?php $searchQuery = $search !== '' ? '&q=' . urlencode($search) : ''; ?>
                    <nav class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?= base_path() ?>/admin/content.php?tab=posts&page=<?= e((string) ($page - 1)) ?><?= $searchQuery ?>"><?= e(t('admin.content.pagination_newer')) ?></a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= base_path() ?>/admin/content.php?tab=posts&page=<?= e((string) ($page + 1)) ?><?= $searchQuery ?>"><?= e(t('admin.content.pagination_older')) ?></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>

            <?php if (!empty($_GET['saved'])): ?>
                <p class="notice" data-auto-dismiss><?= e(t('admin.content.notice_page_saved')) ?></p>
            <?php endif; ?>
            <?php if (!empty($_GET['deleted'])): ?>
                <p class="notice" data-auto-dismiss><?= e(t('admin.content.notice_page_deleted')) ?></p>
            <?php endif; ?>

            <nav class="editor-actions">
                <a href="<?= base_path() ?>/admin/edit-page.php?action=new">
                    <svg class="icon" aria-hidden="true"><use href="#icon-file-plus-corner"></use></svg>
                    <?= e(t('admin.content.new_page')) ?>
                </a>
            </nav>

            <?php if (!$pages): ?>
                <p><?= e(t('admin.content.no_pages')) ?></p>
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

        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
