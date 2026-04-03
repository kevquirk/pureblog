<?php

declare(strict_types=1);

if (!function_exists('font_stack_css') || !function_exists('require_setup_redirect')) {
    header('Location: ' . (function_exists('base_path') ? base_path() : '') . '/');
    exit;
}

$post = $post ?? null;
$config = $config ?? [];
$fontStack = $fontStack ?? font_stack_css($config['theme']['font_stack'] ?? 'sans');
$pageTitle = $pageTitle ?? ($post['title'] ?? t('frontend.post_not_found'));
$metaDescription = $metaDescription ?? (!empty($post['description']) ? $post['description'] : '');

?>
<?php require __DIR__ . '/includes/header.php'; ?>
<?php render_masthead_layout($config, ['post' => $post ?? null]); ?>
    <main>
        <?php if (!$post): ?>
            <h2><?= e(t('frontend.post_not_found')) ?></h2>
            <p><?= e(t('frontend.post_not_found_detail')) ?></p>
        <?php else: ?>
            <?php
            $adjacentPosts = get_adjacent_posts_by_slug((string) ($post['slug'] ?? ''), false);
            $layoutName = trim((string) ($post['layout'] ?? ''));
            $layoutFile = PUREBLOG_BASE_PATH . '/content/layouts/' . $layoutName . '.php';
            ?>
            <?php if ($layoutName !== '' && is_file($layoutFile)): ?>
                <?php render_layout_file($layoutFile, $post, $config, $adjacentPosts); ?>
            <?php else: ?>
            <article>
                <h1><?= e($post['title']) ?></h1>
                <?php if ($post['date']): ?>
                    <p class="post-date"><svg class="icon" aria-hidden="true"><use href="#icon-calendar"></use></svg> <time datetime="<?= e(format_datetime_for_display((string) $post['date'], $config, 'c')) ?>"><?= e(format_post_date_for_display((string) $post['date'], $config)) ?></time></p>
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
        <?php endif; ?>
    </main>
    <?php render_footer_layout($config, ['post' => $post ?? null]); ?>
</body>
</html>
