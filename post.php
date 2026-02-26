<?php

declare(strict_types=1);

if (!function_exists('font_stack_css') || !function_exists('require_setup_redirect')) {
    header('Location: /');
    exit;
}

$post = $post ?? null;
$config = $config ?? [];
$fontStack = $fontStack ?? font_stack_css($config['theme']['font_stack'] ?? 'sans');
$pageTitle = $pageTitle ?? ($post['title'] ?? 'Post not found');
$metaDescription = $metaDescription ?? (!empty($post['description']) ? $post['description'] : '');

?>
<?php require __DIR__ . '/includes/header.php'; ?>
<?php render_masthead_layout($config, ['post' => $post ?? null]); ?>
    <main>
        <?php if (!$post): ?>
            <h2>Post not found</h2>
            <p>The post you requested could not be found.</p>
        <?php else: ?>
            <?php $adjacentPosts = get_adjacent_posts_by_slug((string) ($post['slug'] ?? ''), false); ?>
            <article>
                <h1 ><?= e($post['title']) ?></h1>
                <?php if ($post['date']): ?>
                    <p class="post-date"><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-calendar"></use></svg> <time><?= e(format_post_date_for_display((string) $post['date'], $config)) ?></time></p>
                <?php endif; ?>
                
                <?= render_markdown($post['content'], ['post_title' => (string) ($post['title'] ?? '')]) ?>
                <?= render_layout_partial('post-meta', [
                    'post' => $post,
                    'config' => $config,
                    'post_title' => (string) ($post['title'] ?? ''),
                    'content_title' => (string) ($post['title'] ?? ''),
                    'previous_post' => $adjacentPosts['previous'] ?? null,
                    'next_post' => $adjacentPosts['next'] ?? null,
                ]) ?>
            </article>
        <?php endif; ?>
    </main>
    <?php render_footer_layout($config, ['post' => $post ?? null]); ?>
</body>
</html>
