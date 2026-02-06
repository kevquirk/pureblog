<?php
// Expects: $posts, $postListLayout, $currentPage, $totalPages, $paginationBase
?>
<?php if (!$posts): ?>
    <p>No posts yet, get writing! 🙃</p>
<?php else: ?>
    <?php foreach ($posts as $post): ?>
        <article class="post-item">
            <!-- Archive view -->
            <?php if ($postListLayout === 'archive'): ?>
                <p class="post-archive-view">
                    <time><?= e(date('Y-m-d', strtotime($post['date']))) ?></time>
                    <span class="post-archive-title"><a href="/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></span>
                </p>
            
            <!-- Excerpt view -->
            <?php elseif ($postListLayout === 'excerpt'): ?>
                <div class="excerpt-view">
                    <h2><a href="/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></h2>
                    <?php if ($post['date']): ?>
                        <p><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-calendar"></use></svg> <time><?= e(date('F j, Y', strtotime($post['date']))) ?></time></p>
                    <?php endif; ?>
                    <p class="post-excerpt"><?= e(get_excerpt($post['content'])) ?></p>
                    <?php if (!empty($post['tags'])): ?>
                        <p><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-tag"></use></svg> <?= render_tag_links($post['tags']) ?></p>
                    <?php endif; ?>
                </div>
            
            <!-- Full post view -->
            <?php elseif ($postListLayout === 'full'): ?>
                <div class="full-post-view">
                    <h1><a href="/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></h1>
                    <?php if ($post['date']): ?>
                        <p class="post-date"><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-calendar"></use></svg> <time><?= e(date('F j, Y', strtotime($post['date']))) ?></time></p>
                    <?php endif; ?>
                    <?= render_markdown($post['content']) ?>
                    <?php if (!empty($post['tags'])): ?>
                        <p><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-tag"></use></svg> <?= render_tag_links($post['tags']) ?></p>
                    <?php endif; ?>
                    <hr>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if ($totalPages > 1): ?>
        <nav class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="<?= e($paginationBase) ?>?page=<?= e((string) ($currentPage - 1)) ?>">&larr; Newer posts</a>
            <?php endif; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= e($paginationBase) ?>?page=<?= e((string) ($currentPage + 1)) ?>">Older posts &rarr;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
