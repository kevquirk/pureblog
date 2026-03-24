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
    $topTagEntries[] = '<strong>' . e((string) $tag) . '</strong> (' . (int) $count . ')';
    if (count($topTagEntries) >= 5) {
        break;
    }
}

$topTagsLabel = $topTagEntries ? implode(', ', $topTagEntries) : t('admin.dashboard.stat_no_tags');
$timeSinceLastPublished = '0';
if ($lastPublishedTimestamp > 0) {
    $delta = time() - $lastPublishedTimestamp;
    if ($delta < 60) {
        $timeSinceLastPublished = t('admin.dashboard.time_just_now');
    } elseif ($delta < 3600) {
        $minutes = (int) floor($delta / 60);
        $timeSinceLastPublished = $minutes === 1
            ? t('admin.dashboard.time_minute_ago', ['n' => $minutes])
            : t('admin.dashboard.time_minutes_ago', ['n' => $minutes]);
    } elseif ($delta < 86400) {
        $hours = (int) floor($delta / 3600);
        $timeSinceLastPublished = $hours === 1
            ? t('admin.dashboard.time_hour_ago', ['n' => $hours])
            : t('admin.dashboard.time_hours_ago', ['n' => $hours]);
    } else {
        $days = (int) floor($delta / 86400);
        $timeSinceLastPublished = $days === 1
            ? t('admin.dashboard.time_day_ago', ['n' => $days])
            : t('admin.dashboard.time_days_ago', ['n' => $days]);
    }
}

$availableLayouts = get_layouts();

$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$adminTitle = t('admin.dashboard.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <?php if (!empty($_GET['saved'])): ?>
            <p class="notice" data-auto-dismiss><?= e(t('admin.dashboard.notice_saved')) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['deleted'])): ?>
            <p class="notice" data-auto-dismiss><?= e(t('admin.dashboard.notice_deleted')) ?></p>
        <?php endif; ?>

        <section class="dashboard-stats" aria-label="Dashboard stats">
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_published')) ?></p>
                <p class="dashboard-stat-value"><?= e((string) $publishedCount) ?></p>
            </article>
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_this_year', ['year' => $currentYear])) ?></p>
                <p class="dashboard-stat-value"><?= e((string) $publishedThisYear) ?></p>
            </article>
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_last_published')) ?></p>
                <p class="dashboard-stat-value"><?= e($timeSinceLastPublished) ?></p>
            </article>
            <article class="dashboard-stat-card dashboard-stat-card-tags">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_top_tags')) ?></p>
                <p class="dashboard-stat-value dashboard-stat-tags"><?= $topTagsLabel ?></p>
            </article>
        </section>

        <nav class="editor-actions">
            <?php if ($availableLayouts): ?>
                <button type="button" id="new-post-button">
                    <svg class="icon" aria-hidden="true"><use href="#icon-file-plus-corner"></use></svg>
                    <?= e(t('admin.dashboard.new_post')) ?>
                </button>
                <dialog id="layout-picker" aria-labelledby="layout-picker-title">
                    <h2 id="layout-picker-title"><?= e(t('admin.dashboard.choose_layout')) ?></h2>
                    <ul class="layout-picker-list">
                        <li><a href="<?= base_path() ?>/admin/edit-post.php?action=new"><?= e(t('admin.dashboard.default_post')) ?></a></li>
                        <?php foreach ($availableLayouts as $layout): ?>
                            <li><a href="<?= base_path() ?>/admin/edit-post.php?action=new&amp;layout=<?= urlencode($layout['name']) ?>"><?= e($layout['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" id="layout-picker-close" class="delete">
                        <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                        <?= e(t('admin.dashboard.cancel')) ?>
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
                    <?= e(t('admin.dashboard.new_post')) ?>
                </a>
            <?php endif; ?>
        </nav>


        <form method="get" class="admin-search">
            <label class="hidden" for="search"><?= e(t('admin.dashboard.search_label')) ?></label>
            <input type="search" id="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('admin.dashboard.search_placeholder')) ?>">
        </form>

        <?php if (!$posts): ?>
            <?php if ($search !== ''): ?>
                <p><?= e(t('admin.dashboard.no_posts_found', ['search' => $search])) ?></p>
            <?php else: ?>
                <p><?= e(t('admin.dashboard.no_posts')) ?></p>
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
                            <span class="status <?= e($post['status']) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-toggle-right"></use></svg> <?= e($post['status']) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($totalPages > 1): ?>
                <?php $searchQuery = $search !== '' ? '&q=' . urlencode($search) : ''; ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= base_path() ?>/admin/dashboard.php?page=<?= e((string) ($page - 1)) ?><?= $searchQuery ?>"><?= e(t('admin.dashboard.pagination_newer')) ?></a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= base_path() ?>/admin/dashboard.php?page=<?= e((string) ($page + 1)) ?><?= $searchQuery ?>"><?= e(t('admin.dashboard.pagination_older')) ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
