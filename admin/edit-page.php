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
        $errors[] = 'Page not found.';
        $isEditing = false;
    }
}

if ($action === 'new') {
    $isEditing = false;
    $originalSlug = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page['title'] = trim($_POST['title'] ?? '');
    $page['slug'] = trim($_POST['slug'] ?? '');
    $page['status'] = trim($_POST['status'] ?? 'draft');
    $page['description'] = trim($_POST['description'] ?? '');
    $includeChoice = $_POST['include_in_nav'] ?? 'yes';
    $page['include_in_nav'] = $includeChoice === 'yes' || $includeChoice === '1';
    $page['content'] = trim($_POST['content'] ?? '');
    $originalSlug = trim($_POST['original_slug'] ?? '');

    if ($page['title'] === '') {
        $errors[] = 'Title is required.';
    }

    if (!in_array($page['status'], ['draft', 'published'], true)) {
        $errors[] = 'Status must be draft or published.';
    }

    if (!$errors) {
        $saveError = '';
        $saved = save_page($page, $originalSlug === '' ? null : $originalSlug, $saveError);
        if ($saved) {
            $redirectSlug = $page['slug'] === '' ? slugify($page['title']) : $page['slug'];
            header('Location: /admin/edit-page.php?slug=' . urlencode($redirectSlug) . '&saved=1');
            exit;
        }
        $errors[] = $saveError !== '' ? $saveError : 'Unable to save page.';
    }
}

$imageFolder = '';
if ($page['slug'] !== '') {
    $imageFolder = __DIR__ . '/../assets/images/' . $page['slug'];
    if (is_dir($imageFolder)) {
        $files = glob($imageFolder . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $basename = basename($file);
                $altText = pathinfo($basename, PATHINFO_FILENAME) ?: 'image';
                $url = '/assets/images/' . $page['slug'] . '/' . $basename;
                $images[] = [
                    'filename' => $basename,
                    'markdown' => '![' . $altText . '](' . $url . ')',
                    'url' => $url,
                ];
            }
        }
    }
}

$adminTitle = ($isEditing ? 'Edit Page' : 'New Page') . ' - Pureblog';
$codeMirror = 'markdown';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main>
        <h1>Page editor</h1>
        <div class="editor-grid">
            <section class="editor-main">
                <?php if ($errors): ?>
                    <div class="notice">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['saved'])): ?>
                    <p class="notice" data-auto-dismiss>Page saved.</p>
                <?php endif; ?>
                <?php if (!empty($_GET['uploaded'])): ?>
                    <p class="notice" data-auto-dismiss>Image uploaded. You can copy the markdown below.</p>
                <?php endif; ?>
                <?php if (!empty($_GET['upload_error'])): ?>
                    <p class="notice" data-auto-dismiss><?= e($_GET['upload_error']) ?></p>
                <?php endif; ?>
                <form method="post" class="editor-form" id="page-form">
                    <input type="hidden" name="original_slug" value="<?= e($originalSlug) ?>">
                    <?= csrf_field() ?>

                    <nav class="editor-actions">
                        <button class="save" type="submit" form="page-form" aria-label="Save page">
                            <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-save"></use></svg>
                            Save page
                        </button>
                        <button type="button" id="preview-button" aria-label="Preview page">
                            <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-eye"></use></svg>
                            Preview page
                        </button>
                        <?php if ($isEditing && $page['slug'] !== ''): ?>
                            <button type="submit" form="delete-page-form" class="link-button delete" aria-label="Delete page" onclick="return confirm('Delete this page?');">
                                <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-x"></use></svg>
                                Delete page
                            </button>
                        <?php endif; ?>
                    </nav>

                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" value="<?= e($page['title']) ?>" required>

                    <label for="content">Content <span class="tip">(<a target="_blank" rel="noopener noreferrer" href="https://pureblog.org/markdown-helper">Markdown</a>)</span></label>
                    <textarea id="content" name="content" rows="18"><?= e($page['content']) ?></textarea>
                </form>
                <?php if ($isEditing && $page['slug'] !== ''): ?>
                    <form method="post" action="/admin/delete-page.php" id="delete-page-form">
                        <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
                        <?= csrf_field() ?>
                    </form>
                <?php endif; ?>
            </section>
            <aside class="editor-sidebar">
                <section class="sidebar-section">
                    <div class="section-divider">
                        <span class="title">Page settings</span>
                        <label for="slug">Slug (optional)</label>
                        <input type="text" id="slug" name="slug" form="page-form" value="<?= e($page['slug']) ?>">

                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" form="page-form" value="<?= e($page['description']) ?>">

                        <label for="status">Status</label>
                        <select id="status" name="status" form="page-form">
                            <option value="draft" <?= $page['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= $page['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                        </select>

                        <label for="include_in_nav">Include in navigation menu?</label>
                        <select id="include_in_nav" name="include_in_nav" form="page-form">
                            <option value="yes" <?= $page['include_in_nav'] ? 'selected' : '' ?>>Yes</option>
                            <option value="no" <?= !$page['include_in_nav'] ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                </section>

                <section class="sidebar-section">
                    <div class="section-divider">
                        <span class="title">Images</span>
                    <form method="post" action="/admin/upload-image.php" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
                        <input type="hidden" name="editor_type" value="page">
                        <?= csrf_field() ?>
                        <label class="hidden" for="image">Upload image</label>
                        <input type="file" id="image" name="image" accept="image/*">
                        <button type="submit" disabled>
                            <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-upload"></use></svg>
                            Upload
                        </button>
                    </form>
                        <?php if (!$images): ?>
                            <p>No images yet.</p>
                        <?php else: ?>
                            <p>Attached images:</p>
                            <ul class="image-list">
                            <?php foreach ($images as $image): ?>
                                <li>
                                    <code><?= e($image['filename']) ?></code>
                                    <button type="button" class="link-button copy-markdown" data-markdown="<?= e($image['markdown']) ?>"><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-copy"></use></svg> Copy</button>
                                <form method="post" action="/admin/delete-image.php" class="inline-form" onsubmit="return confirm('Delete this image?');">
                                    <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
                                    <input type="hidden" name="editor_type" value="page">
                                    <input type="hidden" name="filename" value="<?= e($image['filename']) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="link-button delete"><svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-x"></use></svg> Delete</button>
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
        };
    </script>
    <script src="/admin/js/editor.js"></script>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
