<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$errors = [];
$action = $_GET['action'] ?? '';
$slugParam = trim($_GET['slug'] ?? '');
$isEditing = $slugParam !== '';

$page = [
    'title' => '',
    'slug' => '',
    'status' => 'draft',
    'description' => '',
    'include_in_nav' => true,
    'content' => '',
];
$images = [];

$originalSlug = '';

if ($isEditing) {
    $existing = get_page_by_slug($slugParam, true);
    if ($existing) {
        $page = [
            'title' => $existing['title'] ?? '',
            'slug' => $existing['slug'] ?? '',
            'status' => $existing['status'] ?? 'draft',
            'description' => $existing['description'] ?? '',
            'include_in_nav' => $existing['include_in_nav'] ?? true,
            'content' => $existing['content'] ?? '',
        ];
        $originalSlug = $existing['slug'] ?? '';
    } else {
        $errors[] = t('admin.editor.error_not_found_page');
        $isEditing = false;
    }
}

if ($action === 'new') {
    $isEditing = false;
    $originalSlug = '';
}

$autosaveData = null;
if ($isEditing && $originalSlug !== '') {
    $autosavePath = PUREBLOG_BASE_PATH . '/content/autosaves/page-' . $originalSlug . '.json';
    if (is_file($autosavePath)) {
        $raw     = file_get_contents($autosavePath);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            if (($decoded['content'] ?? '') !== $page['content'] || ($decoded['title'] ?? '') !== $page['title']) {
                $autosaveData = $decoded;
            } else {
                @unlink($autosavePath);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action_id'])) {
    $page['title'] = trim($_POST['title'] ?? '');
    $page['slug'] = trim($_POST['slug'] ?? '');
    $page['status'] = trim($_POST['status'] ?? 'draft');
    $page['description'] = trim($_POST['description'] ?? '');
    $includeChoice = $_POST['include_in_nav'] ?? 'yes';
    $page['include_in_nav'] = $includeChoice === 'yes' || $includeChoice === '1';
    $page['content'] = trim($_POST['content'] ?? '');
    $originalSlug = trim($_POST['original_slug'] ?? '');
    $originalStatus = trim($_POST['original_status'] ?? '');

    if ($page['title'] === '') {
        $errors[] = t('admin.editor.error_title_required');
    } elseif ($page['slug'] === '' && slugify($page['title']) === '') {
        $errors[] = t('admin.editor.error_empty_slug');
    }

    if (!in_array($page['status'], ['draft', 'published'], true)) {
        $errors[] = t('admin.editor.error_invalid_status');
    }

    if (!$errors) {
        $saveError = '';
        $saved = save_page(
            $page,
            $originalSlug === '' ? null : $originalSlug,
            $originalStatus === '' ? null : $originalStatus,
            $saveError
        );
        if ($saved) {
            $redirectSlug = $page['slug'] === '' ? slugify($page['title']) : $page['slug'];
            $autosaveDir = PUREBLOG_BASE_PATH . '/content/autosaves';
            @unlink($autosaveDir . '/page-' . $originalSlug . '.json');
            @unlink($autosaveDir . '/page-' . $redirectSlug . '.json');
            header('Location: ' . base_path() . '/admin/edit-page.php?slug=' . urlencode($redirectSlug) . '&saved=1');
            exit;
        }
        $errors[] = $saveError !== '' ? $saveError : t('admin.editor.error_save_page');
    }
}

$imageFolder = '';
if ($page['slug'] !== '') {
    $imageFolder = __DIR__ . '/../content/images/' . $page['slug'];
    if (is_dir($imageFolder)) {
        $files = glob($imageFolder . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $basename = basename($file);
                $altText = pathinfo($basename, PATHINFO_FILENAME) ?: 'image';
                $url = base_path() . '/content/images/' . $page['slug'] . '/' . $basename;
                $images[] = [
                    'filename' => $basename,
                    'markdown' => '![' . $altText . '](' . $url . ')',
                    'url' => $url,
                ];
            }
        }
    }
}

$adminTitle = t($isEditing ? 'admin.page_editor.edit_title' : 'admin.page_editor.new_title');
$codeMirror = 'markdown';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main>
        <h1><?= e(t('admin.page_editor.page_title')) ?></h1>
        <div class="editor-grid">
            <section class="editor-main">
                <?php if ($errors): ?>
                    <div class="notice delete">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['saved'])): ?>
                    <p class="notice" data-auto-dismiss><?= e(t('admin.editor.notice_saved_page')) ?></p>
                <?php endif; ?>
                <?php if (!empty($_GET['uploaded'])): ?>
                    <p class="notice" data-auto-dismiss><?= e(t('admin.editor.notice_image_uploaded')) ?></p>
                <?php endif; ?>
                <?php if (!empty($_GET['upload_error'])): ?>
                    <p class="notice delete" data-auto-dismiss><?= e($_GET['upload_error']) ?></p>
                <?php endif; ?>
                <form method="post" class="editor-form" id="page-form">
                    <input type="hidden" name="original_slug" value="<?= e($originalSlug) ?>">
                    <input type="hidden" name="original_status" value="<?= e($page['status']) ?>">
                    <?= csrf_field() ?>

                    <nav class="editor-actions">
                        <button class="save" type="submit" form="page-form" aria-label="<?= e(t('admin.page_editor.save')) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#icon-save"></use></svg>
                            <?= e(t('admin.page_editor.save')) ?>
                        </button>
                        <button type="button" id="preview-button" aria-label="<?= e(t('admin.page_editor.preview')) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#icon-eye"></use></svg>
                            <?= e(t('admin.page_editor.preview')) ?>
                        </button>
                        <?php if ($isEditing && $page['slug'] !== ''): ?>
                            <button type="submit" form="delete-page-form" class="link-button delete" aria-label="<?= e(t('admin.page_editor.delete')) ?>" onclick="return confirm('<?= e(t('admin.page_editor.delete_confirm')) ?>');">
                                <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                                <?= e(t('admin.page_editor.delete')) ?>
                            </button>
                        <?php endif; ?>
                        <span id="autosave-status" class="autosave-status" aria-live="polite"></span>
                    </nav>

                    <label for="title"><?= e(t('admin.editor.title_label')) ?></label>
                    <input type="text" id="title" name="title" value="<?= e($page['title']) ?>" required autocomplete="off">

                    <label for="content"><?= e(t('admin.editor.content_label')) ?> <span class="tip">(<a target="_blank" rel="noopener noreferrer" href="https://pureblog.org/markdown-helper"><?= e(t('admin.editor.tip_markdown')) ?></a>)</span></label>
                    <textarea id="content" name="content" rows="18" autocomplete="off"><?= e($page['content']) ?></textarea>
                </form>
                <?php if ($isEditing && $page['slug'] !== ''): ?>
                    <form method="post" action="<?= base_path() ?>/admin/delete-page.php" id="delete-page-form">
                        <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
                        <?= csrf_field() ?>
                    </form>
                <?php endif; ?>
            </section>
            <aside class="editor-sidebar">
                <section class="sidebar-section">
                    <div class="section-divider">
                        <span class="title"><?= e(t('admin.page_editor.settings_title')) ?></span>
                        <label for="slug"><?= e(t('admin.editor.slug_label')) ?></label>
                        <input type="text" id="slug" name="slug" form="page-form" value="<?= e($page['slug']) ?>" autocomplete="off">

                        <label for="description"><?= e(t('admin.editor.description_label')) ?></label>
                        <input type="text" id="description" name="description" form="page-form" value="<?= e($page['description']) ?>" autocomplete="off">

                        <label for="status"><?= e(t('admin.editor.status_label')) ?></label>
                        <select id="status" name="status" form="page-form">
                            <option value="draft" <?= $page['status'] === 'draft' ? 'selected' : '' ?>><?= e(t('admin.editor.status_draft')) ?></option>
                            <option value="published" <?= $page['status'] === 'published' ? 'selected' : '' ?>><?= e(t('admin.editor.status_published')) ?></option>
                        </select>

                        <label for="include_in_nav"><?= e(t('admin.page_editor.nav_label')) ?></label>
                        <select id="include_in_nav" name="include_in_nav" form="page-form">
                            <option value="yes" <?= $page['include_in_nav'] ? 'selected' : '' ?>><?= e(t('admin.page_editor.nav_yes')) ?></option>
                            <option value="no" <?= !$page['include_in_nav'] ? 'selected' : '' ?>><?= e(t('admin.page_editor.nav_no')) ?></option>
                        </select>
                    </div>
                </section>

                <section class="sidebar-section">
                    <div class="section-divider">
                        <span class="title"><?= e(t('admin.editor.images_title')) ?></span>
                    <form method="post" action="<?= base_path() ?>/admin/upload-image.php" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
                        <input type="hidden" name="editor_type" value="page">
                        <?= csrf_field() ?>
                        <label class="hidden" for="image"><?= e(t('admin.editor.upload_label')) ?></label>
                        <input type="file" id="image" name="image" accept="image/*,.avif">
                        <button type="submit" disabled>
                            <svg class="icon" aria-hidden="true"><use href="#icon-upload"></use></svg>
                            <?= e(t('admin.editor.upload')) ?>
                        </button>
                    </form>
                        <?php if (!$images): ?>
                            <p><?= e(t('admin.editor.no_images')) ?></p>
                        <?php else: ?>
                            <p><?= e(t('admin.editor.attached_images')) ?></p>
                            <ul class="image-list">
                            <?php foreach ($images as $image): ?>
                                <li>
                                    <img src="<?= e($image['url']) ?>" width="30" height="30" class="image-list-preview" alt="<?= e($image['filename']) ?>" loading="lazy"/>
                                    <code><?= e($image['filename']) ?></code>
                                    <button type="button" class="link-button copy-markdown" data-markdown="<?= e($image['markdown']) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-copy"></use></svg> <?= e(t('admin.editor.copy')) ?></button>
                                <form method="post" action="<?= base_path() ?>/admin/delete-image.php" class="inline-form" onsubmit="return confirm('<?= e(t('admin.page_editor.delete_image_confirm')) ?>');">
                                    <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
                                    <input type="hidden" name="editor_type" value="page">
                                    <input type="hidden" name="filename" value="<?= e($image['filename']) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-button delete"><svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg> <?= e(t('admin.editor.delete')) ?></button>
                                </form>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        </div>
                </section>
            </aside>
        </div>
    </main>
    <script>
        window.PureblogEditorConfig = {
            editorType: 'page',
            formId: 'page-form',
            csrfToken: '<?= e(csrf_token()) ?>',
            basePath: '<?= e(base_path()) ?>',
            autosave: <?= $autosaveData !== null ? json_encode($autosaveData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null' ?>,
            strings: <?= json_encode([
                'autosaving'        => t('admin.editor.js_autosaving'),
                'autosaved'         => t('admin.editor.js_autosaved'),
                'autosave_failed'   => t('admin.editor.js_autosave_failed'),
                'autosave_banner'   => t('admin.editor.js_autosave_banner'),
                'view'              => t('admin.editor.js_view'),
                'hide'              => t('admin.editor.js_hide'),
                'restore'           => t('admin.editor.js_restore'),
                'discard'           => t('admin.editor.js_discard'),
                'title_label'       => t('admin.editor.js_title_label'),
                'save_failed'       => t('admin.editor.js_save_failed'),
                'save_before_upload'=> t('admin.editor.js_save_before_upload'),
                'copied'            => t('admin.editor.js_copied'),
                'copy'              => t('admin.editor.copy'),
                'copy_failed'       => t('admin.editor.js_copy_failed'),
                'save_post_first'   => t('admin.editor.js_save_post_first'),
                'save_page_first'   => t('admin.editor.js_save_page_first'),
                'upload_failed'     => t('admin.editor.js_upload_failed'),
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
        };
    </script>
    <script src="<?= base_path() ?>/admin/js/editor.js?v=<?= e((string) @filemtime(__DIR__ . '/js/editor.js')) ?>"></script>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
