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
$hiddenBlogValue = '__hidden__';
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
    $searchExcerptLength = max(0, (int) ($_POST['search_excerpt_length'] ?? 2500));
    $language = trim($_POST['language'] ?? '');
    $timezone = trim($_POST['timezone'] ?? '');
    $dateFormat = trim($_POST['date_format'] ?? '');
    $baseUrl = trim($_POST['base_url'] ?? '');
    $homepageSlug = trim($_POST['homepage_slug'] ?? '');
    $blogPageSlug = trim($_POST['blog_page_slug'] ?? '');
    $searchPageSlug = trim($_POST['search_page_slug'] ?? '');
    $ogImagePreferred = trim($_POST['og_image_preferred'] ?? 'banner');
    $cacheEnabled = !empty($_POST['cache_enabled']);
    $rssttl = max(0, (int) ($_POST['rss_ttl'] ?? 3600));

    if ($siteTitle === '') {
        $errors[] = t('admin.settings.site.error_title');
    }

    if ($postsPerPage < 1 || $postsPerPage > 100) {
        $errors[] = t('admin.settings.site.error_posts_per_page');
    }
    if ($timezone === '' || !in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        $errors[] = t('admin.settings.site.error_timezone');
    }
    if ($dateFormat === '') {
        $errors[] = t('admin.settings.site.error_date_format');
    }

    if ($homepageSlug !== '' && !isset($pageSlugLookup[$homepageSlug])) {
        $errors[] = t('admin.settings.site.error_homepage');
    }

    if ($blogPageSlug !== '' && $blogPageSlug !== $hiddenBlogValue && !isset($pageSlugLookup[$blogPageSlug])) {
        $errors[] = t('admin.settings.site.error_blog_page');
    }
    if ($searchPageSlug !== '' && !isset($pageSlugLookup[$searchPageSlug])) {
        $errors[] = t('admin.settings.site.error_search_page');
    }
    if (!in_array($ogImagePreferred, ['banner', 'square'], true)) {
        $errors[] = t('admin.settings.site.error_og_format');
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
        $config['search_excerpt_length'] = $searchExcerptLength;
        $config['language'] = $language !== '' ? $language : 'en';
        $config['timezone'] = $timezone;
        $config['date_format'] = $dateFormat;
        $config['base_url'] = $baseUrl;
        $config['homepage_slug'] = $homepageSlug;
        $config['blog_page_slug'] = $blogPageSlug;
        $config['search_page_slug'] = $searchPageSlug;
        $config['cache']['enabled'] = $cacheEnabled;
        $config['cache']['rss_ttl'] = $rssttl;

        if (!isset($config['assets'])) {
            $config['assets'] = ['favicon' => '', 'og_image' => '', 'og_image_preferred' => 'banner'];
        }
        $config['assets']['og_image_preferred'] = $ogImagePreferred;

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
            $notice = t('admin.settings.site.notice_updated');
        } else {
            $errors[] = t('admin.settings.site.error_save');
        }
    }
}

$adminTitle = t('admin.settings.site.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1><?= e(t('admin.settings.site.heading')) ?></h1>
        <?php require __DIR__ . '/../includes/admin-notices.php'; ?>

        <?php $settingsSaveFormId = 'settings-form'; ?>
        <nav class="editor-actions settings-actions">
            <?php require __DIR__ . '/../includes/admin-settings-nav.php'; ?>
        </nav>

        <form method="post" enctype="multipart/form-data" id="settings-form">
            <?= csrf_field() ?>
            <section class="section-divider">
                <span class="title"><?= e(t('admin.settings.site.section_title')) ?></span>
                <label for="site_title"><?= e(t('admin.settings.site.site_title')) ?></label>
                <input type="text" id="site_title" name="site_title" value="<?= e($config['site_title']) ?>" required>

                <label for="site_tagline"><?= e(t('admin.settings.site.tagline')) ?></label>
                <input type="text" id="site_tagline" name="site_tagline" value="<?= e($config['site_tagline']) ?>">

                <label for="site_description"><?= e(t('admin.settings.site.description')) ?></label>
                <textarea id="site_description" name="site_description" rows="4"><?= e($config['site_description'] ?? '') ?></textarea>

                <label for="site_email"><?= e(t('admin.settings.site.email')) ?></label>
                <input type="email" id="site_email" name="site_email" value="<?= e($config['site_email'] ?? '') ?>" placeholder="you@example.com">

                <label for="posts_per_page"><?= e(t('admin.settings.site.posts_per_page')) ?></label>
                <input type="number" id="posts_per_page" name="posts_per_page" min="1" max="100" value="<?= e((string) ($config['posts_per_page'] ?? 20)) ?>">

                <label for="search_excerpt_length"><?= e(t('admin.settings.site.search_excerpt_length')) ?></label>
                <input type="number" id="search_excerpt_length" name="search_excerpt_length" min="0" value="<?= e((string) ($config['search_excerpt_length'] ?? 2500)) ?>">
                <p class="tip"><?= e(t('admin.settings.site.search_excerpt_length_tip')) ?></p>

                <label for="language"><?= e(t('admin.settings.site.language')) ?> <span class="tip">(<a href="https://www.w3schools.com/tags/ref_language_codes.asp" target="_blank" rel="noopener noreferrer"><?= e(t('admin.settings.site.tip_language_link')) ?></a>, e.g. en, fr, pt-BR)</span></label>
                <input type="text" id="language" name="language" value="<?= e((string) ($config['language'] ?? 'en')) ?>" placeholder="en">

                <label for="timezone"><?= e(t('admin.settings.site.timezone')) ?> <span class="tip">(<a href="https://www.php.net/manual/en/timezones.php" target="_blank" rel="noopener noreferrer"><?= e(t('admin.settings.site.tip_timezone_link')) ?></a>)</span></label>
                <input type="text" id="timezone" name="timezone" value="<?= e((string) ($config['timezone'] ?? date_default_timezone_get())) ?>" placeholder="UTC" required>

                <label for="date_format"><?= e(t('admin.settings.site.date_format')) ?> <span class="tip">(<a href="https://www.php.net/manual/en/datetime.format.php" target="_blank" rel="noopener noreferrer"><?= e(t('admin.settings.site.tip_date_format_link')) ?></a>)</span></label>
                <input type="text" id="date_format" name="date_format" value="<?= e((string) ($config['date_format'] ?? 'F j, Y')) ?>" placeholder="F j, Y" required>

                <label for="homepage_slug"><?= e(t('admin.settings.site.homepage')) ?></label>
                <select id="homepage_slug" name="homepage_slug">
                    <option value=""><?= e(t('admin.settings.site.homepage_default')) ?></option>
                    <?php foreach ($pageOptions as $pageOption): ?>
                        <option value="<?= e($pageOption['slug']) ?>"<?= ($config['homepage_slug'] ?? '') === $pageOption['slug'] ? ' selected' : '' ?>>
                            <?= e($pageOption['title']) ?> (<?= e($pageOption['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="blog_page_slug"><?= e(t('admin.settings.site.blog_page')) ?></label>
                <select id="blog_page_slug" name="blog_page_slug">
                    <option value=""><?= e(t('admin.settings.site.blog_use_homepage')) ?></option>
                    <option value="<?= e($hiddenBlogValue) ?>"<?= ($config['blog_page_slug'] ?? '') === $hiddenBlogValue ? ' selected' : '' ?>><?= e(t('admin.settings.site.blog_hidden')) ?></option>
                    <?php foreach ($pageOptions as $pageOption): ?>
                        <option value="<?= e($pageOption['slug']) ?>"<?= ($config['blog_page_slug'] ?? '') === $pageOption['slug'] ? ' selected' : '' ?>>
                            <?= e($pageOption['title']) ?> (<?= e($pageOption['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="search_page_slug"><?= e(t('admin.settings.site.search_page')) ?></label>
                <select id="search_page_slug" name="search_page_slug">
                    <option value=""><?= e(t('admin.settings.site.search_page_none')) ?></option>
                    <?php foreach ($pageOptions as $pageOption): ?>
                        <option value="<?= e($pageOption['slug']) ?>"<?= ($config['search_page_slug'] ?? 'search') === $pageOption['slug'] ? ' selected' : '' ?>>
                            <?= e($pageOption['title']) ?> (<?= e($pageOption['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="base_url"><?= e(t('admin.settings.site.base_url')) ?></label>
                <input type="text" id="base_url" name="base_url" value="<?= e($config['base_url']) ?>">

                <label for="favicon"><?= e(t('admin.settings.site.favicon')) ?> <span class="tip">(<?= e(t('admin.settings.site.tip_favicon')) ?>)</span></label>
                <input type="file" id="favicon" name="favicon" accept="image/*">
                <?php if (!empty($config['assets']['favicon'])): ?>
                    <p class="current-image"><?= e(t('admin.settings.site.current')) ?>: <a href="<?= e($config['assets']['favicon']) ?>" target="_blank" rel="noopener noreferrer"><?= e($config['assets']['favicon']) ?></a></p>
                <?php endif; ?>

                <label for="og_image"><?= e(t('admin.settings.site.og_image')) ?> <span class="tip">(<?= e(t('admin.settings.site.tip_og_image')) ?>)</span></label>
                <input type="file" id="og_image" name="og_image" accept="image/*">
                <?php if (!empty($config['assets']['og_image'])): ?>
                    <p class="current-image"><?= e(t('admin.settings.site.current')) ?>: <a href="<?= e($config['assets']['og_image']) ?>" target="_blank" rel="noopener noreferrer"><?= e($config['assets']['og_image']) ?></a></p>
                <?php endif; ?>

                <label for="og_image_preferred"><?= e(t('admin.settings.site.og_image_format')) ?></label>
                <select id="og_image_preferred" name="og_image_preferred">
                    <option value="banner"<?= ($config['assets']['og_image_preferred'] ?? 'banner') === 'banner' ? ' selected' : '' ?>><?= e(t('admin.settings.site.og_banner')) ?></option>
                    <option value="square"<?= ($config['assets']['og_image_preferred'] ?? 'banner') === 'square' ? ' selected' : '' ?>><?= e(t('admin.settings.site.og_square')) ?></option>
                </select>

                <label for="custom_nav"><?= e(t('admin.settings.site.custom_nav')) ?> <span class="tip">(<?= e(t('admin.settings.site.tip_one_per_line')) ?>)</span></label>
                <textarea id="custom_nav" name="custom_nav" rows="4" placeholder="GitHub | https://github.com/you&#10;Projects | /projects"><?= e($config['custom_nav'] ?? '') ?></textarea>

                <label for="custom_routes"><?= e(t('admin.settings.site.custom_routes')) ?> <span class="tip">(<?= e(t('admin.settings.site.tip_one_per_line')) ?>)</span></label>
                <textarea id="custom_routes" name="custom_routes" rows="4" placeholder="/archive | /content/includes/archive.php&#10;/reading | reading.php"><?= e($config['custom_routes'] ?? '') ?></textarea>
            </section>

            <section class="section-divider">
                <span class="title">Header Injects</span>
                <label for="head_inject_page">Page head HTML <span class="tip">(<?= e(t('admin.settings.site.tip_optional')) ?>)</span></label>
                <textarea id="head_inject_page" name="head_inject_page" rows="6" placeholder="&lt;link rel=&quot;stylesheet&quot; href=&quot;/content/css/comments.css&quot;&gt;"><?= e($config['head_inject_page'] ?? '') ?></textarea>

                <label for="head_inject_post">Post head HTML <span class="tip">(<?= e(t('admin.settings.site.tip_optional')) ?>)</span></label>
                <textarea id="head_inject_post" name="head_inject_post" rows="6" placeholder="&lt;meta name=&quot;x-custom&quot; content=&quot;value&quot;&gt;"><?= e($config['head_inject_post'] ?? '') ?></textarea>
            </section>

            <section class="section-divider">
                <span class="title">Footer Injects</span>
                <label for="footer_inject_page">Page footer HTML <span class="tip">(<?= e(t('admin.settings.site.tip_optional')) ?>)</span></label>
                <textarea id="footer_inject_page" name="footer_inject_page" rows="6" placeholder="&lt;script src=&quot;/assets/js/page-only.js&quot; defer&gt;&lt;/script&gt;"><?= e($config['footer_inject_page'] ?? '') ?></textarea>

                <label for="footer_inject_post">Post footer HTML <span class="tip">(<?= e(t('admin.settings.site.tip_optional')) ?>)</span></label>
                <textarea id="footer_inject_post" name="footer_inject_post" rows="6" placeholder="&lt;script src=&quot;/assets/js/post-only.js&quot; defer&gt;&lt;/script&gt;"><?= e($config['footer_inject_post'] ?? '') ?></textarea>
            </section>

            <section class="section-divider">
                <span class="title">Cache</span>
                <label class="inline-checkbox" for="cache_enabled">
                    <input type="checkbox" id="cache_enabled" name="cache_enabled" <?= !empty($config['cache']['enabled']) ? 'checked' : '' ?>>
                    <?= e(t('admin.settings.site.cache_enable')) ?>
                </label>

                <label for="rss_ttl"><?= e(t('admin.settings.site.rss_ttl')) ?> <span class="tip">(<?= e(t('admin.settings.site.tip_rss_ttl')) ?>)</span></label>
                <input type="number" id="rss_ttl" name="rss_ttl" min="0" value="<?= e((string) ($config['cache']['rss_ttl'] ?? 3600)) ?>">
            </section>
        </form>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
