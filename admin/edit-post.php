<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$errors = [];
$images = [];
$action = $_GET['action'] ?? '';
$slugParam = trim($_GET['slug'] ?? '');
$isEditing = $slugParam !== '';

$post = [
    'title' => '',
    'slug' => '',
    'date' => current_site_datetime_for_storage($config),
    'status' => 'draft',
    'tags' => [],
    'description' => '',
    'content' => '',
];

$originalSlug = '';

if ($isEditing) {
    $existing = get_post_by_slug($slugParam, true);
    if ($existing !== null && $existing !== false) {
        $post = [
            'title' => $existing['title'] ?? '',
            'slug' => $existing['slug'] ?? '',
            'date' => $existing['date'] ?? current_site_datetime_for_storage($config),
            'status' => $existing['status'] ?? 'draft',
            'tags' => $existing['tags'] ?? [],
            'description' => $existing['description'] ?? '',
            'content' => $existing['content'] ?? '',
            'layout' => $existing['layout'] ?? '',
        ];
        $originalSlug = $existing['slug'] ?? '';
        $originalExisting = $existing;
    } else {
        $errors[] = t('admin.editor.error_not_found_post');
        $isEditing = false;
        $originalExisting = null;
    }
} else {
    $originalExisting = null;
}

if ($action === 'new') {
    $isEditing = false;
    $originalSlug = '';
    $post['layout'] = trim($_GET['layout'] ?? '');
}

$autosaveData = null;
if ($isEditing && $originalSlug !== '') {
    $autosavePath = PUREBLOG_BASE_PATH . '/content/autosaves/post-' . $originalSlug . '.json';
    if (is_file($autosavePath)) {
        $raw     = file_get_contents($autosavePath);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            if (($decoded['content'] ?? '') !== $post['content'] || ($decoded['title'] ?? '') !== $post['title']) {
                $autosaveData = $decoded;
            } else {
                @unlink($autosavePath);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action_id'])) {
    $post['title'] = trim($_POST['title'] ?? '');
    $post['slug'] = trim($_POST['slug'] ?? '');
    $post['date'] = trim($_POST['date'] ?? '');
    $post['status'] = trim($_POST['status'] ?? 'draft');
    $post['content'] = trim($_POST['content'] ?? '');
    $post['description'] = trim($_POST['description'] ?? '');
    $post['layout'] = trim($_POST['post_layout'] ?? '');
    $tagsInput = trim($_POST['tags'] ?? '');
    $post['tags'] = $tagsInput === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $tagsInput))));
    $originalSlug = trim($_POST['original_slug'] ?? '');
    $originalStatus = trim($_POST['original_status'] ?? '');
    $originalDate = trim($_POST['original_date'] ?? '');

    $post['layout_fields'] = [];
    foreach ($_POST as $key => $value) {
        if (str_starts_with($key, 'layout_field__')) {
            $fieldName = substr($key, strlen('layout_field__'));
            $post['layout_fields'][$fieldName] = trim((string) $value);
        }
    }

    if ($post['title'] === '') {
        $errors[] = t('admin.editor.error_title_required');
    } elseif ($post['slug'] === '' && slugify($post['title']) === '') {
        $errors[] = t('admin.editor.error_empty_slug');
    }

    if (!in_array($post['status'], ['draft', 'published'], true)) {
        $errors[] = t('admin.editor.error_invalid_status');
    }

    if (!$errors) {
        if ($post['status'] === 'published' && $originalStatus !== 'published') {
            $post['date'] = current_site_datetime_for_storage($config);
        }
        $saveError = '';
        $saved = save_post(
            $post,
            $originalSlug === '' ? null : $originalSlug,
            $originalDate === '' ? null : $originalDate,
            $originalStatus === '' ? null : $originalStatus,
            $saveError
        );
        if ($saved) {
            $redirectSlug = $post['slug'] === '' ? slugify($post['title']) : $post['slug'];
            $autosaveDir = PUREBLOG_BASE_PATH . '/content/autosaves';
            @unlink($autosaveDir . '/post-' . $originalSlug . '.json');
            @unlink($autosaveDir . '/post-' . $redirectSlug . '.json');
            header('Location: ' . base_path() . '/admin/edit-post.php?slug=' . urlencode($redirectSlug) . '&saved=1');
            exit;
        }
        $errors[] = $saveError !== '' ? $saveError : t('admin.editor.error_save_post');
    }
}

$imageFolder = '';
if ($post['slug'] !== '') {
    $imageFolder = __DIR__ . '/../content/images/' . $post['slug'];
    if (is_dir($imageFolder)) {
        $files = glob($imageFolder . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $basename = basename($file);
                $altText = pathinfo($basename, PATHINFO_FILENAME) ?: 'image';
                $url = base_path() . '/content/images/' . $post['slug'] . '/' . $basename;
                $images[] = [
                    'filename' => $basename,
                    'markdown' => '![' . $altText . '](' . $url . ')',
                    'url' => $url,
                ];
            }
        }
    }
}

$allLayouts = get_layouts();
$currentLayout = trim($post['layout'] ?? '');
$layoutDef = null;
foreach ($allLayouts as $l) {
    if ($l['name'] === $currentLayout) {
        $layoutDef = $l;
        break;
    }
}
$layoutFields = $layoutDef ? ($layoutDef['fields'] ?? []) : [];

$adminTitle = t($isEditing ? 'admin.post_editor.edit_title' : 'admin.post_editor.new_title');
$codeMirror = 'markdown';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main>
        <h1><?= e(t('admin.post_editor.page_title')) ?></h1>
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
                    <p class="notice" data-auto-dismiss><?= e(t('admin.editor.notice_saved_post')) ?></p>
                <?php endif; ?>
                <?php if (!empty($_GET['uploaded'])): ?>
                    <p class="notice" data-auto-dismiss><?= e(t('admin.editor.notice_image_uploaded')) ?></p>
                <?php endif; ?>
                <?php if (!empty($_GET['upload_error'])): ?>
                    <p class="notice delete" data-auto-dismiss><?= e($_GET['upload_error']) ?></p>
                <?php endif; ?>

                <form method="post" class="editor-form" id="editor-form">
                    <input type="hidden" name="original_slug" value="<?= e($originalSlug) ?>">
                    <input type="hidden" name="original_status" value="<?= e($post['status']) ?>">
                    <input type="hidden" name="original_date" value="<?= e($post['date']) ?>">
                    <input type="hidden" name="post_layout" value="<?= e($currentLayout) ?>">
                    <?= csrf_field() ?>

                    <nav class="editor-actions">
                        <button class="save" type="submit" form="editor-form" aria-label="<?= e(t('admin.post_editor.save')) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#icon-save"></use></svg>
                            <?= e(t('admin.post_editor.save')) ?>
                        </button>
                        <button type="button" id="preview-button" aria-label="<?= e(t('admin.post_editor.preview')) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#icon-eye"></use></svg>
                            <?= e(t('admin.post_editor.preview')) ?>
                        </button>
                        <?php if ($isEditing && $post['slug'] !== ''): ?>
                            <button type="submit" form="delete-post-form" class="link-button delete" aria-label="<?= e(t('admin.post_editor.delete')) ?>" onclick="return confirm('<?= e(t('admin.post_editor.delete_confirm')) ?>');">
                                <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                                <?= e(t('admin.post_editor.delete')) ?>
                            </button>
                        <?php endif; ?>
                        <span id="autosave-status" class="autosave-status" aria-live="polite"></span>
                    </nav>

                    <label for="title"><?= e(t('admin.editor.title_label')) ?></label>
                    <input type="text" id="title" name="title" value="<?= e($post['title']) ?>" required autocomplete="off">

                    <label for="content"><?= e(t('admin.editor.content_label')) ?> <span class="tip">(<a target="_blank" rel="noopener noreferrer" href="https://pureblog.org/markdown-helper"><?= e(t('admin.editor.tip_markdown')) ?></a>)</span></label>
                    <textarea id="content" name="content" rows="18" autocomplete="off"><?= e($post['content']) ?></textarea>

                    <?php if ($layoutFields): ?>
                        <?php foreach ($layoutFields as $field):
                            $fieldName = (string) ($field['name'] ?? '');
                            $fieldLabel = (string) ($field['label'] ?? $fieldName);
                            $fieldType = (string) ($field['type'] ?? 'text');
                            $fieldId = 'layout_field_' . $fieldName;
                            $inputName = 'layout_field__' . $fieldName;
                            $fieldValue = (string) ($originalExisting[$fieldName] ?? $post['layout_fields'][$fieldName] ?? '');
                            if ($fieldName === ''): continue; endif;
                        ?>
                            <?php if ($fieldType !== 'checkbox'): ?>
                            <label for="<?= e($fieldId) ?>"><?= e($fieldLabel) ?></label>
                            <?php endif; ?>
                            <?php if ($fieldType === 'markdown'): ?>
                                <textarea id="<?= e($fieldId) ?>" name="<?= e($inputName) ?>" rows="8" data-layout-markdown autocomplete="off"><?= e($fieldValue) ?></textarea>
                            <?php elseif ($fieldType === 'select'): ?>
                                <?php $fieldOptions = is_array($field['options'] ?? null) ? $field['options'] : []; ?>
                                <select id="<?= e($fieldId) ?>" name="<?= e($inputName) ?>">
                                    <option value=""></option>
                                    <?php foreach ($fieldOptions as $opt):
                                        $opt = (string) $opt;
                                    ?>
                                        <option value="<?= e($opt) ?>" <?= $fieldValue === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($fieldType === 'checkbox'): ?>
                                <input type="hidden" name="<?= e($inputName) ?>" value="">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="<?= e($fieldId) ?>" name="<?= e($inputName) ?>" value="1" <?= $fieldValue === '1' ? 'checked' : '' ?>>
                                    <?= e($fieldLabel) ?>
                                </label>
                            <?php else: ?>
                                <input type="text" id="<?= e($fieldId) ?>" name="<?= e($inputName) ?>" value="<?= e($fieldValue) ?>" autocomplete="off">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </form>
                <?php if ($isEditing && $post['slug'] !== ''): ?>
                    <form method="post" action="<?= base_path() ?>/admin/delete-post.php" id="delete-post-form">
                        <input type="hidden" name="slug" value="<?= e($post['slug']) ?>">
                        <?= csrf_field() ?>
                    </form>
                <?php endif; ?>

            </section>
            <aside class="editor-sidebar">
                <section class="sidebar-section">
                    <div class="section-divider">
                        <span class="title"><?= e(t('admin.post_editor.settings_title')) ?></span>
                        <label for="slug"><?= e(t('admin.editor.slug_label')) ?></label>
                        <input type="text" id="slug" name="slug" form="editor-form" value="<?= e($post['slug']) ?>" autocomplete="off">
                        
                        <label for="description"><?= e(t('admin.editor.description_label')) ?></label>
                        <input type="text" id="description" name="description" form="editor-form" value="<?= e($post['description']) ?>" autocomplete="off">

                        <label for="date"><?= e(t('admin.editor.date_label')) ?></label>
                        <input type="text" id="date" name="date" form="editor-form" value="<?= e($post['date']) ?>" autocomplete="off">

                        <label for="status"><?= e(t('admin.editor.status_label')) ?></label>
                        <select id="status" name="status" form="editor-form">
                            <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>><?= e(t('admin.editor.status_draft')) ?></option>
                            <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>><?= e(t('admin.editor.status_published')) ?></option>
                        </select>

                        <label for="tags"><?= e(t('admin.post_editor.tags_label')) ?></label>
                        <div class="tag-input-wrap">
                            <input type="text" id="tags" name="tags" form="editor-form" value="<?= e(implode(', ', $post['tags'])) ?>" autocomplete="off">
                            <ul id="tag-suggestions" class="tag-suggestions" hidden></ul>
                        </div>
                    </div>
                </section>

                <section class="sidebar-section">
                    <div class="section-divider">
                        <span class="title"><?= e(t('admin.editor.images_title')) ?></span>
                        <form method="post" action="<?= base_path() ?>/admin/upload-image.php" enctype="multipart/form-data" class="upload-form">
                            <input type="hidden" name="slug" value="<?= e($post['slug']) ?>">
                            <input type="hidden" name="date" value="<?= e($post['date']) ?>">
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
                                    <code><?= e($image['filename']) ?></code>
                                    <button type="button" class="link-button copy-markdown" data-markdown="<?= e($image['markdown']) ?>"><svg class="icon" aria-hidden="true"><use href="#icon-copy"></use></svg> <?= e(t('admin.editor.copy')) ?></button>
                                <form method="post" action="<?= base_path() ?>/admin/delete-image.php" class="inline-form" onsubmit="return confirm('<?= e(t('admin.post_editor.delete_image_confirm')) ?>');">
                                    <input type="hidden" name="slug" value="<?= e($post['slug']) ?>">
                                    <input type="hidden" name="date" value="<?= e($post['date']) ?>">
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
            editorType: 'post',
            formId: 'editor-form',
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
    <script>
    (function () {
        const input = document.getElementById('tags');
        const list  = document.getElementById('tag-suggestions');
        if (!input || !list) return;

        let allTags = [];
        let activeIndex = -1;

        fetch(window.PureblogEditorConfig.basePath + '/content/tag-index.json')
            .then(r => r.ok ? r.json() : null)
            .then(data => { if (data) allTags = Object.values(data).map(v => v.name); })
            .catch(() => {});

        function currentToken() {
            const val = input.value;
            const lastComma = val.lastIndexOf(',');
            return val.slice(lastComma + 1).trimStart().toLowerCase();
        }

        function currentPrefix() {
            const val = input.value;
            const lastComma = val.lastIndexOf(',');
            return lastComma === -1 ? '' : val.slice(0, lastComma + 1) + ' ';
        }

        function applySelection(tag) {
            input.value = currentPrefix() + tag;
            hide();
        }

        function hide() {
            list.hidden = true;
            list.innerHTML = '';
            activeIndex = -1;
        }

        function setActive(index) {
            const items = list.querySelectorAll('li');
            items.forEach(li => li.classList.remove('active'));
            activeIndex = Math.max(-1, Math.min(index, items.length - 1));
            if (activeIndex >= 0) items[activeIndex].classList.add('active');
        }

        input.addEventListener('input', function () {
            const token = currentToken();
            const existing = input.value.split(',').map(s => s.trim());

            list.innerHTML = '';
            activeIndex = -1;

            if (token.length === 0) { hide(); return; }

            const matches = allTags
                .filter(t => t.toLowerCase().startsWith(token) && !existing.includes(t))
                .slice(0, 10);

            if (matches.length === 0) { hide(); return; }

            matches.forEach(tag => {
                const li = document.createElement('li');
                li.textContent = tag.charAt(0).toUpperCase() + tag.slice(1);
                li.addEventListener('mousedown', e => { e.preventDefault(); applySelection(tag); });
                list.appendChild(li);
            });

            list.hidden = false;
        });

        input.addEventListener('keydown', function (e) {
            if (list.hidden) return;
            if (e.key === 'ArrowDown')  { e.preventDefault(); setActive(activeIndex + 1); }
            if (e.key === 'ArrowUp')    { e.preventDefault(); setActive(activeIndex - 1); }
            if (e.key === 'Enter' || e.key === 'Tab') {
                const items = list.querySelectorAll('li');
                if (activeIndex >= 0 && items[activeIndex]) {
                    e.preventDefault();
                    applySelection(items[activeIndex].textContent);
                }
            }
            if (e.key === 'Escape') hide();
        });

        document.addEventListener('click', e => {
            if (!input.contains(e.target) && !list.contains(e.target)) hide();
        });
    })();
    </script>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
