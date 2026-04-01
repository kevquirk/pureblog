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
$perPage     = 20;
$page        = max(1, (int) ($_GET['page'] ?? 1));
$search      = trim((string) ($_GET['q'] ?? ''));
$filterYear  = isset($_GET['year'])  ? (int) $_GET['year']  : 0;
$filterMonth = isset($_GET['month']) ? (int) $_GET['month'] : 0;
$filterTag    = trim((string) ($_GET['tag'] ?? ''));
$filterStatus = trim((string) ($_GET['status'] ?? ''));
$filterSince  = isset($_GET['since']) ? (int) $_GET['since'] : 0;
if (!in_array($filterStatus, ['draft', 'published'], true)) {
    $filterStatus = '';
}
if ($filterYear < 2000 || $filterYear > 2100) {
    $filterYear = 0;
}
if ($filterMonth < 1 || $filterMonth > 12) {
    $filterMonth = 0;
}
if ($filterSince < 0 || $filterSince > time()) {
    $filterSince = 0;
}

$allPosts = get_all_posts(true);
usort($allPosts, function (array $a, array $b): int {
    if ($a['status'] !== $b['status']) {
        return $a['status'] === 'draft' ? -1 : 1;
    }
    return ($b['timestamp'] <=> $a['timestamp']);
});

// Build filter option lists from the unfiltered set
$availableYears = [];
$availableTags  = [];
foreach ($allPosts as $p) {
    $ts = (int) ($p['timestamp'] ?? 0);
    if ($ts > 0) {
        $availableYears[(int) (new DateTimeImmutable('@' . $ts))->format('Y')] = true;
    }
    foreach (($p['tags'] ?? []) as $t) {
        $t = trim((string) $t);
        if ($t !== '') {
            $slug = normalize_tag($t);
            $availableTags[$slug] ??= $t;
        }
    }
}
krsort($availableYears);
ksort($availableTags);
$availableYears = array_keys($availableYears);

// Apply filters
$filteredPosts = filter_posts_by_query($allPosts, $search);
if ($filterYear > 0 || $filterMonth > 0) {
    $filteredPosts = array_values(array_filter($filteredPosts, function (array $post) use ($filterYear, $filterMonth): bool {
        $ts = (int) ($post['timestamp'] ?? 0);
        if ($ts === 0) {
            return false;
        }
        $dt = new DateTimeImmutable('@' . $ts);
        if ($filterYear > 0 && (int) $dt->format('Y') !== $filterYear) {
            return false;
        }
        if ($filterMonth > 0 && (int) $dt->format('n') !== $filterMonth) {
            return false;
        }
        return true;
    }));
}
if ($filterTag !== '') {
    $filteredPosts = array_values(array_filter($filteredPosts, function (array $post) use ($filterTag): bool {
        $tags = $post['tags'] ?? [];
        return is_array($tags) && in_array(normalize_tag($filterTag), array_map(fn($t) => normalize_tag((string) $t), $tags), true);
    }));
}
if ($filterStatus !== '') {
    $filteredPosts = array_values(array_filter($filteredPosts, function (array $post) use ($filterStatus): bool {
        return ($post['status'] ?? '') === $filterStatus;
    }));
}
if ($filterSince > 0) {
    $filteredPosts = array_values(array_filter($filteredPosts, function (array $post) use ($filterSince): bool {
        return (int) ($post['timestamp'] ?? 0) >= $filterSince;
    }));
}

// Build a human-readable label and clear-URL for any active filter
$filterLabel    = '';
$filterClearUrl = '';
$anyFilter = $filterYear > 0 || $filterMonth > 0 || $filterTag !== '' || $filterStatus !== '' || $filterSince > 0;
if ($anyFilter) {
    $parts = [];
    if ($filterYear > 0 && $filterMonth > 0) {
        $parts[] = t('date.months.' . ($filterMonth - 1)) . ' ' . $filterYear;
    } elseif ($filterYear > 0) {
        $parts[] = (string) $filterYear;
    } elseif ($filterMonth > 0) {
        $parts[] = t('date.months.' . ($filterMonth - 1));
    }
    if ($filterTag !== '') {
        $parts[] = $filterTag;
    }
    if ($filterStatus !== '') {
        $parts[] = t('admin.editor.status_' . $filterStatus);
    }
    if ($filterSince > 0 && $filterYear === 0) {
        $parts[] = t('admin.content.filter_recent');
    }
    $filterLabel    = implode(', ', $parts);
    $clearParams    = array_filter(['tab' => $tab, 'q' => $search !== '' ? $search : null]);
    $filterClearUrl = base_path() . '/admin/content.php?' . http_build_query($clearParams);
}
$detailsOpen = $anyFilter || $search !== '';

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

$fontStack    = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$postDateFmt  = site_date_format($config);
if (strpbrk($postDateFmt, 'HhGgi') === false) {
    $postDateFmt .= ' @ H:i';
}
$adminTitle = t('admin.content.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <div class="content-toolbar">
            <nav class="content-tabs" aria-label="<?= e(t('admin.content.tabs_label')) ?>">
                <a href="<?= base_path() ?>/admin/content.php?tab=posts"<?= $tab === 'posts' ? ' class="current" aria-current="page"' : '' ?>><svg class="icon" aria-hidden="true"><use href="#icon-notebook-pen"></use></svg> <?= e(t('admin.content.tab_posts')) ?></a>
                <a href="<?= base_path() ?>/admin/content.php?tab=pages"<?= $tab === 'pages' ? ' class="current" aria-current="page"' : '' ?>><svg class="icon" aria-hidden="true"><use href="#icon-file-text"></use></svg> <?= e(t('admin.content.tab_pages')) ?></a>
            </nav>
            <?php if ($tab === 'posts'): ?>
                <?php if ($availableLayouts): ?>
                    <button type="button" id="new-post-button" class="save">
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
                    <a class="save" href="<?= base_path() ?>/admin/edit-post.php?action=new">
                        <svg class="icon" aria-hidden="true"><use href="#icon-file-plus-corner"></use></svg>
                        <?= e(t('admin.content.new_post')) ?>
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <a class="save" href="<?= base_path() ?>/admin/edit-page.php?action=new">
                    <svg class="icon" aria-hidden="true"><use href="#icon-file-text"></use></svg>
                    <?= e(t('admin.content.new_page')) ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($tab === 'posts'): ?>

            <?php if (!empty($_GET['saved'])): ?>
                <p class="notice" data-auto-dismiss><?= e(t('admin.content.notice_post_saved')) ?></p>
            <?php endif; ?>
            <?php if (!empty($_GET['deleted'])): ?>
                <p class="notice" data-auto-dismiss><?= e(t('admin.content.notice_post_deleted')) ?></p>
            <?php endif; ?>

            <details class="content-filter"<?= $detailsOpen ? ' open' : '' ?>>
                <summary class="content-filter-summary">
                    <svg class="icon" aria-hidden="true"><use href="#icon-search"></use></svg>
                    <?= e(t('admin.content.filter_title')) ?>
                    <?php if ($filterLabel !== ''): ?>
                        <span class="content-filter-active-label"><?= e($filterLabel) ?></span>
                    <?php endif; ?>
                </summary>
                <form method="get" class="content-filter-form">
                    <input type="hidden" name="tab" value="posts">

                    <div class="content-filter-row">
                        <div class="content-filter-search">
                            <label for="filter-search"><?= e(t('admin.content.search_label')) ?></label>
                            <input type="search" id="filter-search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('admin.content.search_placeholder')) ?>" autocomplete="off">
                        </div>

                        <div class="content-filter-selects">
                            <?php if ($availableYears): ?>
                            <div class="content-filter-field">
                                <label for="filter-year"><?= e(t('admin.content.filter_year')) ?></label>
                                <select id="filter-year" name="year">
                                    <option value=""><?= e(t('admin.content.filter_all_years')) ?></option>
                                    <?php foreach ($availableYears as $y): ?>
                                        <option value="<?= e((string) $y) ?>"<?= $filterYear === $y ? ' selected' : '' ?>><?= e((string) $y) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="content-filter-field">
                                <label for="filter-month"><?= e(t('admin.content.filter_month')) ?></label>
                                <select id="filter-month" name="month">
                                    <option value=""><?= e(t('admin.content.filter_all_months')) ?></option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>"<?= $filterMonth === $m ? ' selected' : '' ?>><?= e(t('date.months.' . ($m - 1))) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($availableTags): ?>
                            <div class="content-filter-field">
                                <label for="filter-tag"><?= e(t('admin.content.filter_tag')) ?></label>
                                <select id="filter-tag" name="tag">
                                    <option value=""><?= e(t('admin.content.filter_all_tags')) ?></option>
                                    <?php foreach ($availableTags as $tagSlug => $tagDisplayName): ?>
                                        <option value="<?= e($tagSlug) ?>"<?= $filterTag === $tagSlug ? ' selected' : '' ?>><?= e($tagDisplayName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="content-filter-field">
                                <label for="filter-status"><?= e(t('admin.content.filter_status')) ?></label>
                                <select id="filter-status" name="status">
                                    <option value=""><?= e(t('admin.content.filter_all_statuses')) ?></option>
                                    <option value="published"<?= $filterStatus === 'published' ? ' selected' : '' ?>><?= e(t('admin.editor.status_published')) ?></option>
                                    <option value="draft"<?= $filterStatus === 'draft' ? ' selected' : '' ?>><?= e(t('admin.editor.status_draft')) ?></option>
                                </select>
                            </div>

                            <div class="content-filter-actions">
                                <button type="submit"><?= e(t('admin.content.filter_apply')) ?></button>
                                <?php if ($detailsOpen): ?>
                                    <a class="delete" href="<?= e(base_path() . '/admin/content.php?tab=' . urlencode($tab)) ?>"><?= e(t('admin.content.filter_clear')) ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </details>

            <?php if (!$posts): ?>
                <?php if ($filterLabel !== '' || $search !== ''): ?>
                    <p><?= e(t('admin.content.no_posts_filtered', ['label' => $filterLabel !== '' ? $filterLabel : $search])) ?></p>
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
                                <span><svg class="icon" aria-hidden="true"><use href="#icon-calendar"></use></svg> <?= e(format_datetime_for_display((string) ($post['date'] ?? ''), $config, $postDateFmt)) ?></span>
                                <span class="status <?= e($post['status']) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-toggle-right"></use></svg> <?= e(t('admin.editor.status_' . $post['status'])) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($totalPages > 1): ?>
                    <?php
                        $pageParams = ['tab' => 'posts'];
                        if ($filterYear > 0)   { $pageParams['year']  = $filterYear; }
                        if ($filterMonth > 0)  { $pageParams['month'] = $filterMonth; }
                        if ($filterTag !== '')    { $pageParams['tag']    = $filterTag; }
                        if ($filterStatus !== '') { $pageParams['status'] = $filterStatus; }
                        if ($filterSince > 0)    { $pageParams['since']  = $filterSince; }
                        if ($search !== '')    { $pageParams['q']     = $search; }
                    ?>
                    <nav class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?= base_path() ?>/admin/content.php?<?= e(http_build_query($pageParams + ['page' => $page - 1])) ?>"><?= e(t('admin.content.pagination_newer')) ?></a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= base_path() ?>/admin/content.php?<?= e(http_build_query($pageParams + ['page' => $page + 1])) ?>"><?= e(t('admin.content.pagination_older')) ?></a>
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
