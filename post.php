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
<?php
require __DIR__ . '/includes/header.php';
?>
    <main>
        <?php if (!$post): ?>
            <h2>Post not found</h2>
            <p>The post you requested could not be found.</p>
        <?php else: ?>
            <article>
                <h1 ><?= e($post['title']) ?></h1>
                <?php if ($post['date']): ?>
                    <p class="post-date"><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-calendar"></use></svg> <time><?= e(date('F j, Y', strtotime($post['date']))) ?></time></p>
                <?php endif; ?>
                
                <?= render_markdown($post['content']) ?>

                <?php if (!empty($post['tags'])): ?>
                    <p><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-tag"></use></svg> <?= render_tag_links($post['tags']) ?></p>
                <?php endif; ?>
            </article>
        <?php endif; ?>
    </main>
    <?php require __DIR__ . '/includes/footer.php'; ?>
