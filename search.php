<?php

declare(strict_types=1);

require __DIR__ . '/functions.php';
require_setup_redirect();

$config = load_config();
$fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
$query = trim($_GET['q'] ?? '');
$index = load_search_index();
$sourcePosts = $index ?? get_all_posts(false);
$filteredPosts = filter_posts_by_query($sourcePosts, $query);
if ($index !== null && $filteredPosts) {
    $hydrated = [];
    foreach ($filteredPosts as $post) {
        $slug = (string) ($post['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $fullPost = get_post_by_slug($slug, false);
        if ($fullPost) {
            $hydrated[] = $fullPost;
        }
    }
    $filteredPosts = $hydrated;
}
$pagination = paginate_posts($filteredPosts, (int) ($config['posts_per_page'] ?? 20), (int) ($_GET['page'] ?? 1));
$posts = $pagination['posts'];
$totalPosts = $pagination['totalPosts'];
$totalPages = $pagination['totalPages'];
$currentPage = $pagination['currentPage'];
$postListLayout = $config['theme']['post_list_layout'] ?? 'excerpt';
$paginationBase = '/search.php';

$pageTitle = $query !== '' ? 'Search: ' . $query : 'Search';
$metaDescription = '';

require __DIR__ . '/includes/header.php';
?>
    <main>
        <h1 >Search</h1>
        <form class="site-search-form" method="get" action="/search.php">
            <label class="hidden" for="search-query">Search posts</label>
            <input type="search" id="search-query" name="q" value="<?= e($query) ?>" placeholder="Search posts">
            <button type="submit">Search</button>
        </form>

        <?php if ($query === ''): ?>
            <p>Enter a search term to find posts.</p>
        <?php elseif (!$filteredPosts): ?>
            <p>No posts found for "<?= e($query) ?>".</p>
        <?php else: ?>
            <p><?= e((string) $totalPosts) ?> result<?= $totalPosts === 1 ? '' : 's' ?> found.</p>
            <?php require __DIR__ . '/includes/post-list.php'; ?>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
