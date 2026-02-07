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
    'date' => date('Y-m-d H:i'),
    'status' => 'draft',
    'tags' => [],
    'description' => '',
    'content' => '',
];

$originalSlug = '';

if ($isEditing) {
    $existing = get_post_by_slug($slugParam, true);
    if ($existing) {
        $post = [
            'title' => $existing['title'] ?? '',
            'slug' => $existing['slug'] ?? '',
            'date' => $existing['date'] ?? date('Y-m-d H:i'),
            'status' => $existing['status'] ?? 'draft',
            'tags' => $existing['tags'] ?? [],
            'description' => $existing['description'] ?? '',
            'content' => $existing['content'] ?? '',
        ];
        $originalSlug = $existing['slug'] ?? '';
    } else {
        $errors[] = 'Post not found.';
        $isEditing = false;
    }
}

if ($action === 'new') {
    $isEditing = false;
    $originalSlug = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post['title'] = trim($_POST['title'] ?? '');
    $post['slug'] = trim($_POST['slug'] ?? '');
    $post['date'] = trim($_POST['date'] ?? '');
    $post['status'] = trim($_POST['status'] ?? 'draft');
    $post['content'] = trim($_POST['content'] ?? '');
    $post['description'] = trim($_POST['description'] ?? '');
    $tagsInput = trim($_POST['tags'] ?? '');
    $post['tags'] = $tagsInput === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $tagsInput))));
    $originalSlug = trim($_POST['original_slug'] ?? '');
    $originalStatus = trim($_POST['original_status'] ?? '');
    $originalDate = trim($_POST['original_date'] ?? '');

    if ($post['title'] === '') {
        $errors[] = 'Title is required.';
    }

    if (!in_array($post['status'], ['draft', 'published'], true)) {
        $errors[] = 'Status must be draft or published.';
    }

    if (!$errors) {
        if ($post['status'] === 'published' && $originalStatus !== 'published') {
            $post['date'] = date('Y-m-d H:i');
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
            header('Location: /admin/edit-post.php?slug=' . urlencode($redirectSlug) . '&saved=1');
            exit;
        }
        $errors[] = $saveError !== '' ? $saveError : 'Unable to save post.';
    }
}

$imageFolder = '';
if ($post['slug'] !== '') {
    $imageFolder = __DIR__ . '/../assets/images/' . $post['slug'];
    if (is_dir($imageFolder)) {
        $files = glob($imageFolder . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $basename = basename($file);
                $altText = pathinfo($basename, PATHINFO_FILENAME) ?: 'image';
                $url = '/assets/images/' . $post['slug'] . '/' . $basename;
                $images[] = [
                    'filename' => $basename,
                    'markdown' => '![' . $altText . '](' . $url . ')',
                    'url' => $url,
                ];
            }
        }
    }
}

$adminTitle = ($isEditing ? 'Edit Post' : 'New Post') . ' - Pureblog';
$codeMirror = 'markdown';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main>
        <h1>Post editor</h1>
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
                    <p class="notice" data-auto-dismiss>Post saved.</p>
                <?php endif; ?>
                <?php if (!empty($_GET['uploaded'])): ?>
                    <p class="notice" data-auto-dismiss>Image uploaded. You can copy the markdown below.</p>
                <?php endif; ?>
                <?php if (!empty($_GET['upload_error'])): ?>
                    <p class="notice" data-auto-dismiss><?= e($_GET['upload_error']) ?></p>
                <?php endif; ?>

                <form method="post" class="editor-form" id="editor-form">
                    <input type="hidden" name="original_slug" value="<?= e($originalSlug) ?>">
                    <input type="hidden" name="original_status" value="<?= e($post['status']) ?>">
                    <input type="hidden" name="original_date" value="<?= e($post['date']) ?>">
                    <?= csrf_field() ?>

                    <nav class="editor-actions">
                        <button class="save" type="submit" form="editor-form" aria-label="Save post">
                            <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-save"></use></svg>
                            Save post
                        </button>
                        <button type="button" id="preview-button" aria-label="Preview post">
                            <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-eye"></use></svg>
                            Preview post
                        </button>
                        <?php if ($isEditing && $post['slug'] !== ''): ?>
                            <button type="submit" form="delete-post-form" class="link-button delete" aria-label="Delete post" onclick="return confirm('Delete this post?');">
                                <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-x"></use></svg>
                                Delete post
                            </button>
                        <?php endif; ?>
                    </nav>

                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" value="<?= e($post['title']) ?>" required>

                    <label for="content">Content <span class="tip">(<a target="_blank" rel="noopener noreferrer" href="https://pureblog.org/markdown-helper">Markdown</a>)</span></label>
                    <textarea id="content" name="content" rows="18"><?= e($post['content']) ?></textarea>
                </form>
                <?php if ($isEditing && $post['slug'] !== ''): ?>
                    <form method="post" action="/admin/delete-post.php" id="delete-post-form">
                        <input type="hidden" name="slug" value="<?= e($post['slug']) ?>">
                        <?= csrf_field() ?>
                    </form>
                <?php endif; ?>

            </section>
            <aside class="editor-sidebar">
                <section class="sidebar-section">
                    <div class="section-divider">
                        <span class="title">Post settings</span>
                        <label for="slug">Slug (optional)</label>
                        <input type="text" id="slug" name="slug" form="editor-form" value="<?= e($post['slug']) ?>">
                        
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" form="editor-form" value="<?= e($post['description']) ?>">

                        <label for="date">Date</label>
                        <input type="text" id="date" name="date" form="editor-form" value="<?= e($post['date']) ?>">

                        <label for="status">Status</label>
                        <select id="status" name="status" form="editor-form">
                            <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                        </select>

                        <label for="tags">Tags (comma separated)</label>
                        <input type="text" id="tags" name="tags" form="editor-form" value="<?= e(implode(', ', $post['tags'])) ?>">
                    </div>
                </section>

                <section class="sidebar-section">
                    <div class="section-divider">
                        <span class="title">Images</span>
                        <form method="post" action="/admin/upload-image.php" enctype="multipart/form-data" class="upload-form">
                            <input type="hidden" name="slug" value="<?= e($post['slug']) ?>">
                            <input type="hidden" name="date" value="<?= e($post['date']) ?>">
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
                                    <input type="hidden" name="slug" value="<?= e($post['slug']) ?>">
                                    <input type="hidden" name="date" value="<?= e($post['date']) ?>">
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
            editorType: 'post',
            formId: 'editor-form',
            csrfToken: '<?= e(csrf_token()) ?>',
        };
    </script>
    <script src="/admin/js/editor.js"></script>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
