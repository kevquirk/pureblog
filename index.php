<?php

declare(strict_types=1);

require __DIR__ . '/functions.php';

require_setup_redirect();
send_security_headers();

// Basic request parsing + route flags.
$config = load_config();
$requestUriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$bp = base_path();
if ($bp !== '' && str_starts_with($requestUriPath, $bp)) {
    $requestUriPath = substr($requestUriPath, strlen($bp));
}
$requestPath = trim(rawurldecode($requestUriPath), '/');
$requestPathWithSlash = $requestPath === '' ? '/' : ('/' . $requestPath);

$adminPath = normalize_admin_path_segment((string) ($config['admin_path'] ?? 'admin'));
if ($adminPath !== 'admin' && ($requestPath === $adminPath || str_starts_with($requestPath, $adminPath . '/'))) {
    $adminRelativePath = $requestPath === $adminPath
        ? 'index.php'
        : substr($requestPath, strlen($adminPath) + 1);
    $adminRelativePath = trim((string) $adminRelativePath, '/');
    if ($adminRelativePath === '') {
        $adminRelativePath = 'index.php';
    }
    if (!str_ends_with($adminRelativePath, '.php')) {
        $adminRelativePath .= '.php';
    }

    $adminRoot = realpath(__DIR__ . '/admin');
    $adminTarget = realpath(__DIR__ . '/admin/' . $adminRelativePath);
    if (
        $adminRoot === false
        || $adminTarget === false
        || !str_starts_with($adminTarget, $adminRoot . '/')
        || !is_file($adminTarget)
    ) {
        require __DIR__ . '/404.php';
        exit;
    }

    // Parse query string from REQUEST_URI since nginx may not pass it correctly during rewrite
    $adminQueryString = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    if ($adminQueryString !== null && $adminQueryString !== '') {
        $_SERVER['QUERY_STRING'] = $adminQueryString;
        parse_str($adminQueryString, $parsedQuery);
        $_GET = array_merge($_GET, $parsedQuery);
    }

    require $adminTarget;
    exit;
}

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$cacheKey = $queryString !== '' ? $requestPathWithSlash . '?' . $queryString : $requestPathWithSlash;
if (!cache_should_bypass($config)) {
    $cached = cache_read($cacheKey);
    if ($cached !== null) {
        echo $cached;
        exit;
    }
    ob_start();
    register_shutdown_function(function () use ($cacheKey): void {
        $html = ob_get_clean();
        if ($html !== false && $html !== '' && http_response_code() === 200) {
            cache_write($cacheKey, $html);
        }
        if ($html !== false) {
            echo $html;
        }
    });
}

// Block direct access to raw markdown files.
if (str_ends_with($requestPath, '.md')) {
    require __DIR__ . '/404.php';
    exit;
}

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
    $pageTitle = $config['site_title'] ?? t('frontend.site_title_fallback');
    $metaDescription = $config['site_description'] ?? '';
    $post = null;
    $page = null;
    include $templatePath;
    exit;
}

$isTag = str_starts_with($requestPath, 'tag/');
$tagParam = $isTag ? rawurldecode(substr($requestPath, 4)) : '';
$reservedPaths = [
    '',
    'index.php',
    'post.php',
    'setup.php',
    'page.php',
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
    if ($tagIndex !== null && isset($tagIndex[$tagSlug]) && is_array($tagIndex[$tagSlug]['posts'] ?? null)) {
        $slugLookup = array_fill_keys($tagIndex[$tagSlug]['posts'], true);
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
$blogFeedHidden = ($blogPageSlug === '__hidden__');

if ($isSingle && $homepageSlug !== '' && $requestPath === $homepageSlug) {
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $location = base_path() . '/' . ($queryString !== '' ? ('?' . $queryString) : '');
    header('Location: ' . $location);
    exit;
}
if (!$isTag && $requestPath === '' && $homepageSlug !== '') {
    $homepage = get_page_by_slug($homepageSlug, true);
    if ($homepage) {
        $page = $homepage;
        $fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
        $pageTitle = $page['title'] ?? t('frontend.page_not_found');
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
        $pageTitle = $page['title'] ?? t('frontend.page_not_found');
        $metaDescription = !empty($page['description']) ? $page['description'] : '';
        require __DIR__ . '/page.php';
    } else {
        $post = $post ?? null;
        if ($post) {
            $fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
            $pageTitle = $post['title'] ?? t('frontend.post_not_found');
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
                <p><?= e(t('frontend.no_tag_selected')) ?></p>
            <?php elseif (!$allPosts): ?>
                <p><?= e(t('frontend.no_posts_for_tag')) ?></p>
            <?php else: ?>
                <?php
                $paginationBase = base_path() . '/tag/' . rawurlencode($tagSlug);
                require __DIR__ . '/includes/post-list.php';
                ?>
            <?php endif; ?>
        <?php else: ?>
            
            <!-- Home page list view -->
            <?php if (!$blogFeedHidden): ?>
                <?php
                $paginationBase = base_path() . '/';
                require __DIR__ . '/includes/post-list.php';
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php render_footer_layout($config, ['post' => $post ?? null, 'page' => $page ?? null]); ?>
</body>
</html>
