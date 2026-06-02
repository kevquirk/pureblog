<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$config    = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$deleted     = isset($_GET['deleted']);
$deleteError = trim((string) ($_GET['delete_error'] ?? ''));
$search      = trim((string) ($_GET['q'] ?? ''));
$perPage     = 32;
$page        = max(1, (int) ($_GET['page'] ?? 1));

// Collect all images from all slug folders
$allImages = [];

if (is_dir(PUREBLOG_CONTENT_IMAGES_PATH)) {
    $slugFolders = glob(PUREBLOG_CONTENT_IMAGES_PATH . '/*', GLOB_ONLYDIR) ?: [];
    foreach ($slugFolders as $folderPath) {
        $slug = basename($folderPath);
        if (!is_safe_image_slug($slug)) {
            continue;
        }
        foreach (glob($folderPath . '/*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $basename = basename($file);
            $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
                continue;
            }
            $altText    = pathinfo($basename, PATHINFO_FILENAME) ?: 'image';
            $url        = base_path() . '/content/images/' . $slug . '/' . $basename;
            $allImages[] = [
                'slug'     => $slug,
                'filename' => $basename,
                'url'      => $url,
                'markdown' => '![' . $altText . '](' . $url . ')',
                'mtime'    => filemtime($file) ?: 0,
            ];
        }
    }
}

// Filter by search
if ($search !== '') {
    $allImages = array_values(array_filter(
        $allImages,
        fn($img) => stripos($img['filename'], $search) !== false
    ));
}

// Sort newest first
usort($allImages, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

// Pagination
$totalImages = count($allImages);
$totalPages  = $totalImages > 0 ? (int) ceil($totalImages / $perPage) : 1;
$page        = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;
$pageImages  = array_slice($allImages, $offset, $perPage);

// Build usage map by scanning all post/page .md files once
$usageMap = [];
foreach ([
    ['dir' => PUREBLOG_POSTS_PATH, 'type' => 'post'],
    ['dir' => PUREBLOG_PAGES_PATH, 'type' => 'page'],
] as $source) {
    if (!is_dir($source['dir'])) {
        continue;
    }
    foreach (glob($source['dir'] . '/*.md') ?: [] as $mdFile) {
        $raw = file_get_contents($mdFile);
        if ($raw === false) {
            continue;
        }
        if (!preg_match_all('#/content/images/([^/\s\)\'"]+)/([^\s\)\'"]+)#', $raw, $matches, PREG_SET_ORDER)) {
            continue;
        }
        $parsed = parse_post_file($mdFile);
        $front  = $parsed['front_matter'];
        $entry  = [
            'title' => $front['title'] ?? basename($mdFile),
            'slug'  => $front['slug'] ?? '',
            'type'  => $source['type'],
        ];
        $seenKeys = [];
        foreach ($matches as $match) {
            $key = $match[1] . '/' . $match[2];
            if (isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;
            $usageMap[$key][] = $entry;
        }
    }
}

// Attach usage info to the current page of images only
foreach ($pageImages as &$image) {
    $image['used_in'] = $usageMap[$image['slug'] . '/' . $image['filename']] ?? [];
}
unset($image);

$adminTitle = t('admin.images.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1><?= e(t('admin.images.heading')) ?></h1>

        <?php if ($deleted): ?>
            <p class="notice" data-auto-dismiss><?= e(t('admin.images.deleted')) ?></p>
        <?php endif; ?>
        <?php if ($deleteError !== ''): ?>
            <p class="notice delete" data-auto-dismiss><?= e($deleteError) ?></p>
        <?php endif; ?>

        <form method="get" action="<?= base_path() ?>/admin/images.php" class="image-search-form">
            <label class="hidden" for="q"><?= e(t('admin.images.search_label')) ?></label>
            <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('admin.images.search_placeholder')) ?>">
            <button type="submit">
                <svg class="icon" aria-hidden="true"><use href="#icon-search"></use></svg>
                <?= e(t('admin.images.search_label')) ?>
            </button>
            <?php if ($search !== ''): ?>
                <a href="<?= base_path() ?>/admin/images.php" class="link-button delete">
                    <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                    <?= e(t('admin.images.clear_search')) ?>
                </a>
            <?php endif; ?>
        </form>

        <?php if (!$allImages): ?>
            <p><?= e(t('admin.images.no_images')) ?></p>
        <?php else: ?>
            <div class="image-library-grid">
                <?php foreach ($pageImages as $i => $image): ?>
                    <?php $dialogId = 'delete-warn-' . $i; ?>
                    <div class="image-library-card">
                        <img src="<?= e($image['url']) ?>" class="image-library-thumb" alt="<?= e($image['filename']) ?>" loading="lazy">
                        <div class="image-library-info">
                            <code class="image-library-name" title="<?= e($image['filename']) ?>"><?= e($image['filename']) ?></code>
                            <span class="image-library-slug"><?= e(t('admin.images.slug_label')) ?> <?= e($image['slug']) ?></span>
                        </div>
                        <div class="image-library-actions">
                            <button type="button" class="link-button copy-markdown" data-markdown="<?= e($image['markdown']) ?>">
                                <svg class="icon" aria-hidden="true"><use href="#icon-copy"></use></svg>
                                <?= e(t('admin.editor.copy')) ?>
                            </button>

                            <?php if (!empty($image['used_in'])): ?>
                                <button type="button" class="link-button delete" onclick="document.getElementById('<?= e($dialogId) ?>').showModal()">
                                    <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                                    <?= e(t('admin.editor.delete')) ?>
                                </button>
                                <dialog id="<?= e($dialogId) ?>" class="image-delete-dialog">
                                    <p><?= e(t('admin.images.delete_used_warning')) ?></p>
                                    <ul>
                                        <?php foreach ($image['used_in'] as $usage): ?>
                                            <li>
                                                <a href="<?= base_path() ?>/admin/edit-<?= e($usage['type']) ?>.php?slug=<?= urlencode($usage['slug']) ?>" target="_blank" rel="noopener noreferrer">
                                                    <?= e($usage['title']) ?>
                                                    <svg class="icon" aria-hidden="true"><use href="#icon-eye"></use></svg>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="image-delete-dialog-actions">
                                        <form method="post" action="<?= base_path() ?>/admin/delete-image-library.php" class="inline-form">
                                            <input type="hidden" name="slug" value="<?= e($image['slug']) ?>">
                                            <input type="hidden" name="filename" value="<?= e($image['filename']) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="delete">
                                                <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                                                <?= e(t('admin.images.delete_anyway')) ?>
                                            </button>
                                        </form>
                                        <button type="button" onclick="this.closest('dialog').close()">
                                            <?= e(t('admin.images.cancel')) ?>
                                        </button>
                                    </div>
                                </dialog>
                            <?php else: ?>
                                <form method="post" action="<?= base_path() ?>/admin/delete-image-library.php" class="inline-form" onsubmit="return confirm('<?= e(t('admin.images.delete_confirm')) ?>')">
                                    <input type="hidden" name="slug" value="<?= e($image['slug']) ?>">
                                    <input type="hidden" name="filename" value="<?= e($image['filename']) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-button delete">
                                        <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                                        <?= e(t('admin.editor.delete')) ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($totalPages > 1): ?>
                <?php $pageParams = $search !== '' ? ['q' => $search] : []; ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= base_path() ?>/admin/images.php?<?= e(http_build_query($pageParams + ['page' => $page - 1])) ?>"><?= e(t('admin.images.pagination_prev')) ?></a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= base_path() ?>/admin/images.php?<?= e(http_build_query($pageParams + ['page' => $page + 1])) ?>"><?= e(t('admin.images.pagination_next')) ?></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <script>
    (function () {
        const basePath = <?= json_encode(base_path(), JSON_HEX_TAG) ?>;
        const strings = {
            copied:    <?= json_encode(t('admin.editor.js_copied'),    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            copy:      <?= json_encode(t('admin.editor.copy'),         JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            copyFailed:<?= json_encode(t('admin.editor.js_copy_failed'),JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        };

        document.querySelectorAll('.copy-markdown').forEach((button) => {
            button.addEventListener('click', async () => {
                const markdown = button.getAttribute('data-markdown') || '';
                if (markdown === '') return;
                try {
                    await navigator.clipboard.writeText(markdown);
                    button.innerHTML = '<svg class="icon" aria-hidden="true"><use href="' + basePath + '/admin/icons/sprite.svg#icon-circle-check"></use></svg> ' + strings.copied;
                    setTimeout(() => {
                        button.innerHTML = '<svg class="icon" aria-hidden="true"><use href="' + basePath + '/admin/icons/sprite.svg#icon-copy"></use></svg> ' + strings.copy;
                    }, 1500);
                } catch (e) {
                    alert(strings.copyFailed);
                }
            });
        });

        document.querySelectorAll('.image-delete-dialog').forEach((dialog) => {
            dialog.addEventListener('click', (e) => {
                if (e.target === dialog) dialog.close();
            });
        });
    })();
    </script>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
