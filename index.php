<?php

declare(strict_types=1);

require __DIR__ . '/functions.php';

require_setup_redirect();

// Basic request parsing + route flags.
$config = load_config();
$requestUriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$requestPath = trim($requestUriPath, '/');
$requestPathWithSlash = $requestPath === '' ? '/' : ('/' . $requestPath);

$customRoutes = parse_custom_routes((string) ($config['custom_routes'] ?? ''));
foreach ($customRoutes as $customRoute) {
    if (($customRoute['path'] ?? '') !== $requestPathWithSlash) {
        continue;
    }

    $templatePath = resolve_custom_route_template((string) ($customRoute['target'] ?? ''));
    if ($templatePath === null) {
        break;
    }

    $fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
    $pageTitle = $config['site_title'] ?? 'Page';
    $metaDescription = $config['site_description'] ?? '';
    $post = null;
    $page = null;
    include $templatePath;
    exit;
}

$isTag = str_starts_with($requestPath, 'tag/');
$tagParam = $isTag ? substr($requestPath, 4) : '';
$reservedPaths = [
    '',
    'index.php',
    'post.php',
    'setup.php',
    'page.php',
    'search.php',
];
$isSingle = !$isTag && $requestPath !== ''
    && !str_contains($requestPath, '.')
    && !in_array($requestPath, $reservedPaths, true)
    && !str_starts_with($requestPath, 'admin')
    && !str_starts_with($requestPath, 'assets')
    && !str_starts_with($requestPath, 'content')
    && !str_starts_with($requestPath, 'config');

$pageData = $isSingle ? get_page_by_slug($requestPath, false) : null;
$post = $isSingle && !$pageData ? get_post_by_slug($requestPath, false) : null;
$tagSlug = $isTag ? normalize_tag($tagParam) : '';
$tagPosts = [];
if ($isTag && $tagSlug !== '') {
    $tagIndex = load_tag_index();
    if ($tagIndex !== null && isset($tagIndex[$tagSlug]) && is_array($tagIndex[$tagSlug])) {
        $slugLookup = array_fill_keys($tagIndex[$tagSlug], true);
        $tagPosts = array_values(array_filter(get_all_posts(false), function (array $post) use ($slugLookup): bool {
            return isset($slugLookup[$post['slug'] ?? '']);
        }));
    } else {
        $tagPosts = array_values(array_filter(get_all_posts(false), function (array $post) use ($tagSlug) {
            foreach ($post['tags'] ?? [] as $tag) {
                if (normalize_tag((string) $tag) === $tagSlug) {
                    return true;
                }
            }
            return false;
        }));
    }
}

$homepageSlug = trim((string) ($config['homepage_slug'] ?? ''));
$blogPageSlug = trim((string) ($config['blog_page_slug'] ?? ''));

if ($isSingle && $homepageSlug !== '' && $requestPath === $homepageSlug) {
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $location = '/' . ($queryString !== '' ? ('?' . $queryString) : '');
    header('Location: ' . $location);
    exit;
}
if (!$isTag && $requestPath === '' && $homepageSlug !== '') {
    $homepage = get_page_by_slug($homepageSlug, true);
    if ($homepage) {
        $page = $homepage;
        $hidePageTitle = !empty($config['hide_homepage_title']);
        $fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
        $pageTitle = $page['title'] ?? 'Page not found';
        $metaDescription = !empty($page['description']) ? $page['description'] : '';
        require __DIR__ . '/page.php';
        exit;
    }
}

// If a path looks like a file (has an extension) but doesn't exist, show 404.
if (
    !$isTag
    && $requestPath !== ''
    && str_contains($requestPath, '.')
    && !in_array($requestPath, $reservedPaths, true)
    && !str_starts_with($requestPath, 'admin')
    && !str_starts_with($requestPath, 'assets')
    && !str_starts_with($requestPath, 'content')
    && !str_starts_with($requestPath, 'config')
    && !is_file(__DIR__ . '/' . $requestPath)
) {
    require __DIR__ . '/404.php';
    exit;
}

// For single slugs, delegate to page/post templates.
if ($isSingle) {
    $_GET['slug'] = $requestPath;
    if ($pageData) {
        $page = $pageData;
        $fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
        $pageTitle = $page['title'] ?? 'Page not found';
        $metaDescription = !empty($page['description']) ? $page['description'] : '';
        require __DIR__ . '/page.php';
    } else {
        $post = $post ?? null;
        if ($post) {
            $fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
            $pageTitle = $post['title'] ?? 'Post not found';
            $metaDescription = !empty($post['description']) ? $post['description'] : '';
            require __DIR__ . '/post.php';
        } else {
            require __DIR__ . '/404.php';
        }
    }
    exit;
}

// List views (home + tag) with pagination.
$perPage = (int) ($config['posts_per_page'] ?? 20);
$currentPage = (int) ($_GET['page'] ?? 1);
$allPosts = $isTag ? $tagPosts : get_all_posts(false);
$pagination = paginate_posts($allPosts, $perPage, $currentPage);
$posts = $pagination['posts'];
$totalPages = $pagination['totalPages'];
$currentPage = $pagination['currentPage'];
$fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
$pageTitle = $isTag && $tagParam !== '' ? 'Tag: ' . $tagParam : $config['site_title'];
$metaDescription = '';
$postListLayout = $config['theme']['post_list_layout'] ?? 'excerpt';

?>
<?php require __DIR__ . '/includes/header.php'; ?>
<?php render_masthead_layout($config, ['post' => $post ?? null, 'page' => $page ?? null]); ?>
    <main>
        <!-- Tag archive view -->
        <?php if ($isTag): ?>
            <h1 ><?= e($tagParam !== '' ? 'Tag: ' . $tagParam : 'Tags') ?></h1>
            <?php if ($tagSlug === ''): ?>
                <p>No tag selected.</p>
            <?php elseif (!$allPosts): ?>
                <p>No posts found for this tag.</p>
            <?php else: ?>
                <?php
                $paginationBase = '/tag/' . $tagSlug;
                require __DIR__ . '/includes/post-list.php';
                ?>
            <?php endif; ?>
        <?php else: ?>
            
            <!-- Home page list view -->
            <?php
            $paginationBase = '/';
            require __DIR__ . '/includes/post-list.php';
            ?>
        <?php endif; ?>
    </main>
    <?php render_footer_layout($config, ['post' => $post ?? null, 'page' => $page ?? null]); ?>
</body>
</html>
