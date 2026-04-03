<?php

declare(strict_types=1);

if (!function_exists('font_stack_css') || !function_exists('require_setup_redirect')) {
    header('Location: ' . (function_exists('base_path') ? base_path() : '') . '/');
    exit;
}

$page = $page ?? null;
$config = $config ?? [];
$fontStack = $fontStack ?? font_stack_css($config['theme']['font_stack'] ?? 'sans');
$pageTitle = $pageTitle ?? ($page['title'] ?? t('frontend.page_not_found'));
$metaDescription = $metaDescription ?? (!empty($page['description']) ? $page['description'] : '');
$blogFeedHidden = (($config['blog_page_slug'] ?? '') === '__hidden__');
$searchPageSlug = trim((string) ($config['search_page_slug'] ?? 'search'));

?>
<?php require __DIR__ . '/includes/header.php'; ?>
<?php render_masthead_layout($config, ['page' => $page ?? null]); ?>
    <main>
        <?php if (!$page): ?>
            <h2><?= e(t('frontend.page_not_found')) ?></h2>
            <p><?= e(t('frontend.page_not_found_detail')) ?></p>
        <?php else: ?>
            <?php
            $isBlogPage = !$blogFeedHidden && !empty($config['blog_page_slug']) && ($page['slug'] ?? '') === $config['blog_page_slug'];
            $isSearchPage = $searchPageSlug !== '' && ($page['slug'] ?? '') === $searchPageSlug;
            ?>
            <article>
                <?= render_markdown($page['content'], ['page_title' => (string) ($page['title'] ?? '')]) ?>
            </article>
            <?php if ($isBlogPage): ?>
                <?php
                $perPage = (int) ($config['posts_per_page'] ?? 20);
                $currentPage = (int) ($_GET['page'] ?? 1);
                $allPosts = get_all_posts(false);
                $pagination = paginate_posts($allPosts, $perPage, $currentPage);
                $posts = $pagination['posts'];
                $totalPages = $pagination['totalPages'];
                $currentPage = $pagination['currentPage'];
                $postListLayout = $config['theme']['post_list_layout'] ?? 'excerpt';
                $isHomepagePage = !empty($config['homepage_slug']) && ($config['homepage_slug'] === ($page['slug'] ?? ''));
                $paginationBase = $isHomepagePage ? '/' : ('/' . $page['slug']);
                ?>
                <section class="blog-feed">
                    <?php require __DIR__ . '/includes/post-list.php'; ?>
                </section>
            <?php endif; ?>
            <?php if ($isSearchPage): ?>
                <?php
                $query = trim($_GET['q'] ?? '');
                $index = load_search_index();
                $sourcePosts = $index ?? get_all_posts(false);
                $filteredPosts = filter_posts_by_query($sourcePosts, $query);
                if ($index !== null && $filteredPosts) {
                    $hydrated = [];
                    foreach ($filteredPosts as $post) {
                        $slug = (string) ($post['slug'] ?? '');
                        if ($slug === '') continue;
                        $fullPost = get_post_by_slug($slug, false);
                        if ($fullPost) $hydrated[] = $fullPost;
                    }
                    $filteredPosts = $hydrated;
                }
                $perPage = (int) ($config['posts_per_page'] ?? 20);
                $currentPage = (int) ($_GET['page'] ?? 1);
                $pagination = paginate_posts($filteredPosts, $perPage, $currentPage);
                $posts = $pagination['posts'];
                $totalPosts = $pagination['totalPosts'];
                $totalPages = $pagination['totalPages'];
                $currentPage = $pagination['currentPage'];
                $postListLayout = $config['theme']['post_list_layout'] ?? 'excerpt';
                $paginationBase = base_path() . '/' . $searchPageSlug;
                $paginationQueryParams = $query !== '' ? ['q' => $query] : [];
                ?>
                <section class="site-search">
                    <form class="site-search-form" method="get" action="<?= e(base_path() . '/' . $searchPageSlug) ?>">
                        <label class="hidden" for="search-query"><?= e(t('frontend.search_label')) ?></label>
                        <input type="search" id="search-query" name="q" value="<?= e($query) ?>" placeholder="<?= e(t('frontend.search_placeholder')) ?>">
                        <button type="submit"><?= e(t('frontend.search_button')) ?></button>
                    </form>
                    <?php if ($query === ''): ?>
                        <p><?= e(t('frontend.search_empty')) ?></p>
                    <?php elseif (!$filteredPosts): ?>
                        <p><?= e(t('frontend.no_posts_found', ['search' => $query])) ?></p>
                    <?php else: ?>
                        <p><?= e($totalPosts === 1 ? t('frontend.search_result', ['n' => $totalPosts]) : t('frontend.search_results', ['n' => $totalPosts])) ?></p>
                        <?php require __DIR__ . '/includes/post-list.php'; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php render_footer_layout($config, ['page' => $page ?? null]); ?>
</body>
</html>
