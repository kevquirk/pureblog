<?php
// Expects: $posts, $postListLayout, $currentPage, $totalPages, $paginationBase
// Optional: $paginationQueryParams (associative array of extra query params to preserve)
$paginationQueryParams = (isset($paginationQueryParams) && is_array($paginationQueryParams)) ? $paginationQueryParams : [];
?>
<?php if (!$posts): ?>
    <p>No posts yet, get writing! 🙃</p>
<?php else: ?>
    <?php foreach ($posts as $post): ?>
        <article class="post-item">
            <!-- Archive view -->
            <?php if ($postListLayout === 'archive'): ?>
                <p class="post-archive-view">
                    <time><?= e(format_post_date_for_display((string) ($post['date'] ?? ''), $config ?? [])) ?></time>
                    <span class="post-archive-title"><a href="/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></span>
                </p>
            
            <!-- Excerpt view -->
            <?php elseif ($postListLayout === 'excerpt'): ?>
                <div class="excerpt-view">
                    <h2><a href="/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></h2>
                    <?php if ($post['date']): ?>
                        <p><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-calendar"></use></svg> <time><?= e(format_post_date_for_display((string) $post['date'], $config ?? [])) ?></time></p>
                    <?php endif; ?>
                    <?php
                    $excerptSource = trim((string) ($post['description'] ?? ''));
                    if ($excerptSource === '') {
                        $excerptSource = get_excerpt($post['content']);
                    }
                    ?>
                    <p class="post-excerpt"><?= e($excerptSource) ?></p>
                    <?php if (!empty($post['tags'])): ?>
                        <p class="tag-list"><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-tag"></use></svg> <?= render_tag_links($post['tags']) ?></p>
                    <?php endif; ?>
                </div>
            
            <!-- Full post view -->
            <?php elseif ($postListLayout === 'full'): ?>
                <div class="full-post-view">
                    <h1><a href="/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></h1>
                    <?php if ($post['date']): ?>
                        <p class="post-date"><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-calendar"></use></svg> <time><?= e(format_post_date_for_display((string) $post['date'], $config ?? [])) ?></time></p>
                    <?php endif; ?>
                    <?= render_markdown($post['content'], ['post_title' => (string) ($post['title'] ?? '')]) ?>
                    <?php if (!empty($post['tags'])): ?>
                        <p class="tag-list"><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-tag"></use></svg> <?= render_tag_links($post['tags']) ?></p>
                    <?php endif; ?>
                    <hr>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if ($totalPages > 1): ?>
        <nav class="pagination">
            <?php if ($currentPage > 1): ?>
                <?php
                $prevParams = array_merge($paginationQueryParams, ['page' => (string) ($currentPage - 1)]);
                $prevHref = e($paginationBase) . '?' . e(http_build_query($prevParams));
                ?>
                <a href="<?= $prevHref ?>">&larr; Newer posts</a>
            <?php endif; ?>
            <?php if ($currentPage < $totalPages): ?>
                <?php
                $nextParams = array_merge($paginationQueryParams, ['page' => (string) ($currentPage + 1)]);
                $nextHref = e($paginationBase) . '?' . e(http_build_query($nextParams));
                ?>
                <a href="<?= $nextHref ?>">Older posts &rarr;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
