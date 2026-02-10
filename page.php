<?php

declare(strict_types=1);

if (!function_exists('font_stack_css') || !function_exists('require_setup_redirect')) {
    header('Location: /');
    exit;
}

$page = $page ?? null;
$config = $config ?? [];
$fontStack = $fontStack ?? font_stack_css($config['theme']['font_stack'] ?? 'sans');
$pageTitle = $pageTitle ?? ($page['title'] ?? 'Page not found');
$metaDescription = $metaDescription ?? (!empty($page['description']) ? $page['description'] : '');

?>
<?php require __DIR__ . '/includes/header.php'; ?>
<?php render_masthead_layout($config, ['page' => $page ?? null]); ?>
    <main>
        <?php if (!$page): ?>
            <h2>Page not found</h2>
            <p>The page you requested could not be found.</p>
        <?php else: ?>
            <?php
            $isBlogPage = !empty($config['blog_page_slug']) && ($page['slug'] ?? '') === $config['blog_page_slug'];
            $hidePageTitle = $hidePageTitle ?? ($isBlogPage ? !empty($config['hide_blog_page_title']) : false);
            ?>
            <article>
                <?php if (!$hidePageTitle): ?>
                <?php endif; ?>
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
        <?php endif; ?>
    </main>
    <?php render_footer_layout($config, ['page' => $page ?? null]); ?>
</body>
</html>
