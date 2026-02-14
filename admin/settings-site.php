<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$errors = [];
$notice = '';
$pages = get_all_pages(true);
$pageOptions = array_values(array_filter($pages, fn($page) => ($page['slug'] ?? '') !== ''));
$pageSlugLookup = array_fill_keys(array_map(fn($page) => $page['slug'], $pageOptions), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action_id'])) {
    verify_csrf();
    $siteTitle = trim($_POST['site_title'] ?? '');
    $siteTagline = trim($_POST['site_tagline'] ?? '');
    $siteDescription = trim($_POST['site_description'] ?? '');
    $siteEmail = trim($_POST['site_email'] ?? '');
    $customNav = trim($_POST['custom_nav'] ?? '');
    $customRoutes = trim($_POST['custom_routes'] ?? '');
    $headInjectPage = trim($_POST['head_inject_page'] ?? '');
    $headInjectPost = trim($_POST['head_inject_post'] ?? '');
    $footerInjectPage = trim($_POST['footer_inject_page'] ?? '');
    $footerInjectPost = trim($_POST['footer_inject_post'] ?? '');
    $postsPerPage = (int) ($_POST['posts_per_page'] ?? 20);
    $baseUrl = trim($_POST['base_url'] ?? '');
    $homepageSlug = trim($_POST['homepage_slug'] ?? '');
    $blogPageSlug = trim($_POST['blog_page_slug'] ?? '');
    $hideHomepageTitle = !empty($_POST['hide_homepage_title']);
    $hideBlogPageTitle = !empty($_POST['hide_blog_page_title']);

    if ($siteTitle === '') {
        $errors[] = 'Site title is required.';
    }

    if ($postsPerPage < 1 || $postsPerPage > 100) {
        $errors[] = 'Posts per page must be between 1 and 100.';
    }

    if ($homepageSlug !== '' && !isset($pageSlugLookup[$homepageSlug])) {
        $errors[] = 'Homepage must reference an existing page.';
    }

    if ($blogPageSlug !== '' && !isset($pageSlugLookup[$blogPageSlug])) {
        $errors[] = 'Blog page must reference an existing page.';
    }

    if (!$errors) {
        $config['site_title'] = $siteTitle;
        $config['site_tagline'] = $siteTagline;
        $config['site_description'] = $siteDescription;
        $config['site_email'] = $siteEmail;
        $config['custom_nav'] = $customNav;
        $config['custom_routes'] = $customRoutes;
        $config['head_inject_page'] = $headInjectPage;
        $config['head_inject_post'] = $headInjectPost;
        $config['footer_inject_page'] = $footerInjectPage;
        $config['footer_inject_post'] = $footerInjectPost;
        $config['posts_per_page'] = $postsPerPage;
        $config['base_url'] = $baseUrl;
        $config['homepage_slug'] = $homepageSlug;
        $config['blog_page_slug'] = $blogPageSlug;
        $config['hide_homepage_title'] = $hideHomepageTitle;
        $config['hide_blog_page_title'] = $hideBlogPageTitle;

        if (!isset($config['assets'])) {
            $config['assets'] = ['favicon' => '', 'og_image' => ''];
        }

        $assetDir = PUREBLOG_CONTENT_IMAGES_PATH;
        if (!is_dir($assetDir)) {
            mkdir($assetDir, 0755, true);
        }

        if (!empty($_FILES['favicon']['name']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $name = basename($_FILES['favicon']['name']);
            $name = strtolower($name);
            $name = preg_replace('/[^a-z0-9._-]/', '-', $name) ?? $name;
            $name = preg_replace('/-+/', '-', $name) ?? $name;
            $name = trim($name, '-');
            if ($name !== '') {
                $dest = $assetDir . '/' . $name;
                if (move_uploaded_file($_FILES['favicon']['tmp_name'], $dest)) {
                    $config['assets']['favicon'] = '/content/images/' . $name;
                }
            }
        }

        if (!empty($_FILES['og_image']['name']) && $_FILES['og_image']['error'] === UPLOAD_ERR_OK) {
            $name = basename($_FILES['og_image']['name']);
            $name = strtolower($name);
            $name = preg_replace('/[^a-z0-9._-]/', '-', $name) ?? $name;
            $name = preg_replace('/-+/', '-', $name) ?? $name;
            $name = trim($name, '-');
            if ($name !== '') {
                $dest = $assetDir . '/' . $name;
                if (move_uploaded_file($_FILES['og_image']['tmp_name'], $dest)) {
                    $config['assets']['og_image'] = '/content/images/' . $name;
                }
            }
        }

        if (save_config($config)) {
            $notice = 'Settings updated.';
        } else {
            $errors[] = 'Failed to save settings.';
        }
    }
}

$adminTitle = 'Site Settings - Pureblog';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1>Site settings</h1>
        <?php require __DIR__ . '/../includes/admin-notices.php'; ?>

        <?php $settingsSaveFormId = 'settings-form'; ?>
        <nav class="editor-actions settings-actions">
            <?php require __DIR__ . '/../includes/admin-settings-nav.php'; ?>
        </nav>

        <form method="post" enctype="multipart/form-data" id="settings-form">
            <?= csrf_field() ?>
            <section class="section-divider">
                <span class="title">Site Settings</span>
                <label for="site_title">Site title</label>
                <input type="text" id="site_title" name="site_title" value="<?= e($config['site_title']) ?>" required>

                <label for="site_tagline">Site tagline (optional)</label>
                <input type="text" id="site_tagline" name="site_tagline" value="<?= e($config['site_tagline']) ?>">

                <label for="site_description">Site description</label>
                <textarea id="site_description" name="site_description" rows="4"><?= e($config['site_description'] ?? '') ?></textarea>

                <label for="site_email">Site email (optional)</label>
                <input type="email" id="site_email" name="site_email" value="<?= e($config['site_email'] ?? '') ?>" placeholder="you@example.com">

                <label for="posts_per_page">Posts per page</label>
                <input type="number" id="posts_per_page" name="posts_per_page" min="1" max="100" value="<?= e((string) ($config['posts_per_page'] ?? 20)) ?>">

                <label for="homepage_slug">Homepage</label>
                <select id="homepage_slug" name="homepage_slug">
                    <option value="">Blog posts (default)</option>
                    <?php foreach ($pageOptions as $pageOption): ?>
                        <option value="<?= e($pageOption['slug']) ?>"<?= ($config['homepage_slug'] ?? '') === $pageOption['slug'] ? ' selected' : '' ?>>
                            <?= e($pageOption['title']) ?> (<?= e($pageOption['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="inline-checkbox" for="hide_homepage_title">
                    <input type="checkbox" id="hide_homepage_title" name="hide_homepage_title" <?= !empty($config['hide_homepage_title']) ? 'checked' : '' ?>>
                    Hide homepage title
                </label>

                <label for="blog_page_slug">Blog page</label>
                <select id="blog_page_slug" name="blog_page_slug">
                    <option value="">Use homepage</option>
                    <?php foreach ($pageOptions as $pageOption): ?>
                        <option value="<?= e($pageOption['slug']) ?>"<?= ($config['blog_page_slug'] ?? '') === $pageOption['slug'] ? ' selected' : '' ?>>
                            <?= e($pageOption['title']) ?> (<?= e($pageOption['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="inline-checkbox" for="hide_blog_page_title">
                    <input type="checkbox" id="hide_blog_page_title" name="hide_blog_page_title" <?= !empty($config['hide_blog_page_title']) ? 'checked' : '' ?>>
                    Hide blog page title
                </label>

                <label for="base_url">Base URL</label>
                <input type="text" id="base_url" name="base_url" value="<?= e($config['base_url']) ?>">

                <label for="favicon">Favicon <span class="tip">(512px square works best)</span></label>
                <input type="file" id="favicon" name="favicon" accept="image/*">
                <?php if (!empty($config['assets']['favicon'])): ?>
                    <p class="current-image">Current: <a href="<?= e($config['assets']['favicon']) ?>" target="_blank" rel="noopener noreferrer"><?= e($config['assets']['favicon']) ?></a></p>
                <?php endif; ?>

                <label for="og_image">Open Graph image <span class="tip">(1360x712 works best)</span></label>
                <input type="file" id="og_image" name="og_image" accept="image/*">
                <?php if (!empty($config['assets']['og_image'])): ?>
                    <p class="current-image">Current: <a href="<?= e($config['assets']['og_image']) ?>" target="_blank" rel="noopener noreferrer"><?= e($config['assets']['og_image']) ?></a></p>
                <?php endif; ?>

                <label for="custom_nav">Custom nav items <span class="tip">(one per line)</span></label>
                <textarea id="custom_nav" name="custom_nav" rows="4" placeholder="GitHub | https://github.com/you&#10;Projects | /projects"><?= e($config['custom_nav'] ?? '') ?></textarea>

                <label for="custom_routes">Custom routes <span class="tip">(one per line)</span></label>
                <textarea id="custom_routes" name="custom_routes" rows="4" placeholder="/archive | /content/includes/archive.php&#10;/reading | reading.php"><?= e($config['custom_routes'] ?? '') ?></textarea>
            </section>

            <section class="section-divider">
                <span class="title">Header Injects</span>
                <label for="head_inject_page">Page head HTML <span class="tip">(optional)</span></label>
                <textarea id="head_inject_page" name="head_inject_page" rows="6" placeholder="&lt;link rel=&quot;stylesheet&quot; href=&quot;/content/css/comments.css&quot;&gt;"><?= e($config['head_inject_page'] ?? '') ?></textarea>

                <label for="head_inject_post">Post head HTML <span class="tip">(optional)</span></label>
                <textarea id="head_inject_post" name="head_inject_post" rows="6" placeholder="&lt;meta name=&quot;x-custom&quot; content=&quot;value&quot;&gt;"><?= e($config['head_inject_post'] ?? '') ?></textarea>
            </section>

            <section class="section-divider">
                <span class="title">Footer Injects</span>
                <label for="footer_inject_page">Page footer HTML <span class="tip">(optional)</span></label>
                <textarea id="footer_inject_page" name="footer_inject_page" rows="6" placeholder="&lt;script src=&quot;/assets/js/page-only.js&quot; defer&gt;&lt;/script&gt;"><?= e($config['footer_inject_page'] ?? '') ?></textarea>

                <label for="footer_inject_post">Post footer HTML <span class="tip">(optional)</span></label>
                <textarea id="footer_inject_post" name="footer_inject_post" rows="6" placeholder="&lt;script src=&quot;/assets/js/post-only.js&quot; defer&gt;&lt;/script&gt;"><?= e($config['footer_inject_post'] ?? '') ?></textarea>
            </section>
        </form>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
