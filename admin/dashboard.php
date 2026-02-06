<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();
$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
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
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$adminTitle = 'Dashboard - Pureblog';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1>Dashboard</h1>
        <nav class="editor-actions">
            <a href="/admin/edit-post.php?action=new">
                <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-file-plus-corner"></use></svg>
                New post
            </a>
        </nav>

        <?php if (!empty($_GET['saved'])): ?>
            <p class="notice" data-auto-dismiss>Post saved.</p>
        <?php endif; ?>
        <?php if (!empty($_GET['deleted'])): ?>
            <p class="notice" data-auto-dismiss>Post deleted.</p>
        <?php endif; ?>

        <form method="get" class="admin-search">
            <label class="hidden" for="search">Search posts</label>
            <input type="search" id="search" name="q" value="<?= e($search) ?>" placeholder="Search for a post...">
        </form>

        <?php if (!$posts): ?>
            <?php if ($search !== ''): ?>
                <p>No posts found for "<?= e($search) ?>".</p>
            <?php else: ?>
                <p>No posts yet, get writing!</p>
            <?php endif; ?>
        <?php else: ?>
            <ul class="admin-list">
                <?php foreach ($posts as $post): ?>
                    <li class="admin-list-item">
                        <a class="admin-list-title" href="/admin/edit-post.php?slug=<?= e($post['slug']) ?>">
                            <?= e($post['title']) ?>
                        </a>
                        <div class="admin-list-meta">
                            <span><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-calendar"></use></svg> <?= e($post['date'] ? date('Y-m-d @ H:i', strtotime($post['date'])) : '') ?></span>
                            <span class="status <?= e($post['status']) ?>"><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-toggle-right"></use></svg> <?= e($post['status']) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($totalPages > 1): ?>
                <?php $searchQuery = $search !== '' ? '&q=' . urlencode($search) : ''; ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="/admin/dashboard.php?page=<?= e((string) ($page - 1)) ?><?= $searchQuery ?>">&larr; Newer posts</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="/admin/dashboard.php?page=<?= e((string) ($page + 1)) ?><?= $searchQuery ?>">Older posts &rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
