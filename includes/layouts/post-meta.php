<?php

declare(strict_types=1);

$post = $post ?? [];
?>
<?php if (!empty($post['tags'])): ?>
    <p><svg class="icon" aria-hidden="true"><use href="/assets/icons/sprite.svg#icon-tag"></use></svg> <?= render_tag_links($post['tags']) ?></p>
<?php endif; ?>
