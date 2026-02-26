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

$publishedPosts = array_values(array_filter($allPosts, static fn(array $post): bool => ($post['status'] ?? 'draft') === 'published'));
$publishedCount = count($publishedPosts);
$currentYear = (int) (new DateTimeImmutable('now', site_timezone_object($config)))->format('Y');
$publishedThisYear = 0;
$lastPublishedTimestamp = 0;
$tagCounts = [];

foreach ($publishedPosts as $post) {
    $timestamp = (int) ($post['timestamp'] ?? 0);
    if ($timestamp > 0) {
        $postYear = (int) (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(site_timezone_object($config))
            ->format('Y');
        if ($postYear === $currentYear) {
            $publishedThisYear++;
        }
        if ($timestamp > $lastPublishedTimestamp) {
            $lastPublishedTimestamp = $timestamp;
        }
    }

    $tags = $post['tags'] ?? [];
    if (!is_array($tags)) {
        continue;
    }
    foreach ($tags as $tag) {
        $name = trim((string) $tag);
        if ($name === '') {
            continue;
        }
        if (!isset($tagCounts[$name])) {
            $tagCounts[$name] = 0;
        }
        $tagCounts[$name]++;
    }
}

uasort($tagCounts, static function (int $a, int $b): int {
    if ($a === $b) {
        return 0;
    }
    return $a > $b ? -1 : 1;
});

$topTagEntries = [];
foreach ($tagCounts as $tag => $count) {
    $topTagEntries[] = '<strong>' . e($tag) . '</strong> (' . (int) $count . ')';
    if (count($topTagEntries) >= 5) {
        break;
    }
}

$topTagsLabel = $topTagEntries ? implode(', ', $topTagEntries) : 'No tags yet';
$timeSinceLastPublished = '0';
if ($lastPublishedTimestamp > 0) {
    $delta = time() - $lastPublishedTimestamp;
    if ($delta < 60) {
        $timeSinceLastPublished = 'Just now';
    } elseif ($delta < 3600) {
        $minutes = (int) floor($delta / 60);
        $timeSinceLastPublished = $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    } elseif ($delta < 86400) {
        $hours = (int) floor($delta / 3600);
        $timeSinceLastPublished = $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    } else {
        $days = (int) floor($delta / 86400);
        $timeSinceLastPublished = $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }
}

$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$adminTitle = 'Dashboard - Pureblog';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <?php if (!empty($_GET['saved'])): ?>
            <p class="notice" data-auto-dismiss>Post saved.</p>
        <?php endif; ?>
        <?php if (!empty($_GET['deleted'])): ?>
            <p class="notice" data-auto-dismiss>Post deleted.</p>
        <?php endif; ?>

        <section class="dashboard-stats" aria-label="Dashboard stats">
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label">Published posts</p>
                <p class="dashboard-stat-value"><?= e((string) $publishedCount) ?></p>
            </article>
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label">Posts in <?= e((string) $currentYear) ?></p>
                <p class="dashboard-stat-value"><?= e((string) $publishedThisYear) ?></p>
            </article>
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label">Last post published</p>
                <p class="dashboard-stat-value"><?= e($timeSinceLastPublished) ?></p>
            </article>
            <article class="dashboard-stat-card dashboard-stat-card-tags">
                <p class="dashboard-stat-label">Top tags</p>
                <p class="dashboard-stat-value dashboard-stat-tags"><?= $topTagsLabel ?></p>
            </article>
        </section>

        <nav class="editor-actions">
            <a href="/admin/edit-post.php?action=new">
                <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-file-plus-corner"></use></svg>
                New post
            </a>
        </nav>


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
                            <span><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-calendar"></use></svg> <?= e(format_datetime_for_display((string) ($post['date'] ?? ''), $config, 'Y-m-d @ H:i')) ?></span>
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
