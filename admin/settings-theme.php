<?php

declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action_id'])) {
    verify_csrf();
    $resetThemeLight = ($_POST['reset_theme_light'] ?? '') === '1';
    $resetThemeDark = ($_POST['reset_theme_dark'] ?? '') === '1';
    if ($resetThemeLight || $resetThemeDark) {
        $defaults = default_config();
        if ($resetThemeLight) {
            foreach ([
                'background_color',
                'text_color',
                'accent_color',
                'border_color',
                'accent_bg_color',
            ] as $key) {
                $config['theme'][$key] = $defaults['theme'][$key] ?? '';
            }
        }

        if ($resetThemeDark) {
            foreach ([
                'background_color_dark',
                'text_color_dark',
                'accent_color_dark',
                'border_color_dark',
                'accent_bg_color_dark',
            ] as $key) {
                $config['theme'][$key] = $defaults['theme'][$key] ?? '';
            }
        }

        if (save_config($config)) {
            $notice = t('admin.settings.theme.notice_reset');
        } else {
            $errors[] = t('admin.settings.theme.error_save');
        }
    } else {
    $fontChoice = $_POST['font_stack'] ?? 'sans';
    $adminFontChoice = $_POST['admin_font_stack'] ?? 'sans';
    $adminColorMode = $_POST['admin_color_mode'] ?? 'auto';
    $backgroundColor = trim($_POST['background_color'] ?? '');
    $textColor = trim($_POST['text_color'] ?? '');
    $accentColor = trim($_POST['accent_color'] ?? '');
    $borderColor = trim($_POST['border_color'] ?? '');
    $accentBgColor = trim($_POST['accent_bg_color'] ?? '');
    $backgroundColorDark = trim($_POST['background_color_dark'] ?? '');
    $textColorDark = trim($_POST['text_color_dark'] ?? '');
    $accentColorDark = trim($_POST['accent_color_dark'] ?? '');
    $borderColorDark = trim($_POST['border_color_dark'] ?? '');
    $accentBgColorDark = trim($_POST['accent_bg_color_dark'] ?? '');
    $colorMode = $_POST['color_mode'] ?? 'light';
    $postListLayout = $_POST['post_list_layout'] ?? 'excerpt';

    if (!in_array($fontChoice, ['sans', 'serif', 'mono'], true)) {
        $errors[] = t('admin.settings.theme.error_font');
    }

    if (!in_array($adminFontChoice, ['sans', 'serif', 'mono'], true)) {
        $errors[] = t('admin.settings.theme.error_admin_font');
    }

    if (!in_array($adminColorMode, ['light', 'dark', 'auto'], true)) {
        $errors[] = t('admin.settings.theme.error_admin_color');
    }

    if (!in_array($colorMode, ['light', 'dark', 'auto'], true)) {
        $errors[] = t('admin.settings.theme.error_color_mode');
    }

    if (!in_array($postListLayout, ['excerpt', 'full', 'archive'], true)) {
        $errors[] = t('admin.settings.theme.error_post_layout');
    }

    if (!$errors) {
        $config['theme']['font_stack'] = $fontChoice;
        $config['theme']['admin_font_stack'] = $adminFontChoice;
        $config['theme']['admin_color_mode'] = $adminColorMode;
        $config['theme']['color_mode'] = $colorMode;
        $config['theme']['background_color'] = $backgroundColor;
        $config['theme']['text_color'] = $textColor;
        $config['theme']['accent_color'] = $accentColor;
        $config['theme']['border_color'] = $borderColor;
        $config['theme']['accent_bg_color'] = $accentBgColor;
        $config['theme']['background_color_dark'] = $backgroundColorDark;
        $config['theme']['text_color_dark'] = $textColorDark;
        $config['theme']['accent_color_dark'] = $accentColorDark;
        $config['theme']['border_color_dark'] = $borderColorDark;
        $config['theme']['accent_bg_color_dark'] = $accentBgColorDark;
        $config['theme']['post_list_layout'] = $postListLayout;

            if (save_config($config)) {
                $notice = t('admin.settings.theme.notice_updated');
            } else {
                $errors[] = t('admin.settings.theme.error_save');
            }
        }
    }
}

$adminTitle = t('admin.settings.theme.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1><?= e(t('admin.settings.theme.heading')) ?></h1>
        <?php require __DIR__ . '/../includes/admin-notices.php'; ?>

        <?php $settingsSaveFormId = 'settings-form'; ?>
        <nav class="editor-actions settings-actions">
            <?php require __DIR__ . '/../includes/admin-settings-nav.php'; ?>
        </nav>

        <form method="post" id="settings-form">
            <?= csrf_field() ?>

            <section class="section-divider">
                <span class="title"><?= e(t('admin.settings.theme.section_fonts')) ?></span>

                <label><b><?= e(t('admin.settings.theme.site_font')) ?></b></label>
                <label class="inline-radio font-preview font-preview-sans" for="font_stack_sans">
                    <input type="radio" id="font_stack_sans" name="font_stack" value="sans" <?= ($config['theme']['font_stack'] ?? 'sans') === 'sans' ? 'checked' : '' ?>>
                    Sans
                </label>
                <label class="inline-radio font-preview font-preview-serif" for="font_stack_serif">
                    <input type="radio" id="font_stack_serif" name="font_stack" value="serif" <?= ($config['theme']['font_stack'] ?? 'sans') === 'serif' ? 'checked' : '' ?>>
                    Serif
                </label>
                <label class="inline-radio font-preview font-preview-mono" for="font_stack_mono">
                    <input type="radio" id="font_stack_mono" name="font_stack" value="mono" <?= ($config['theme']['font_stack'] ?? 'sans') === 'mono' ? 'checked' : '' ?>>
                    Mono
                </label>

                <label><b><?= e(t('admin.settings.theme.admin_font')) ?></b></label>
                <label class="inline-radio font-preview font-preview-sans" for="admin_font_stack_sans">
                    <input type="radio" id="admin_font_stack_sans" name="admin_font_stack" value="sans" <?= ($config['theme']['admin_font_stack'] ?? 'sans') === 'sans' ? 'checked' : '' ?>>
                    Sans
                </label>
                <label class="inline-radio font-preview font-preview-serif" for="admin_font_stack_serif">
                    <input type="radio" id="admin_font_stack_serif" name="admin_font_stack" value="serif" <?= ($config['theme']['admin_font_stack'] ?? 'sans') === 'serif' ? 'checked' : '' ?>>
                    Serif
                </label>
                <label class="inline-radio font-preview font-preview-mono" for="admin_font_stack_mono">
                    <input type="radio" id="admin_font_stack_mono" name="admin_font_stack" value="mono" <?= ($config['theme']['admin_font_stack'] ?? 'sans') === 'mono' ? 'checked' : '' ?>>
                    Mono
                </label>
            </section>

            <section class="section-divider">
                <span class="title"><?= e(t('admin.settings.theme.section_color_mode')) ?></span>

                <label><b><?= e(t('admin.settings.theme.site_color_mode')) ?></b></label>
                <label class="inline-radio" for="color_mode_light">
                    <input type="radio" id="color_mode_light" name="color_mode" value="light" <?= ($config['theme']['color_mode'] ?? 'light') === 'light' ? 'checked' : '' ?>>
                    <?= e(t('admin.settings.theme.color_light')) ?>
                </label>
                <label class="inline-radio" for="color_mode_dark">
                    <input type="radio" id="color_mode_dark" name="color_mode" value="dark" <?= ($config['theme']['color_mode'] ?? 'light') === 'dark' ? 'checked' : '' ?>>
                    <?= e(t('admin.settings.theme.color_dark')) ?>
                </label>
                <label class="inline-radio" for="color_mode_auto">
                    <input type="radio" id="color_mode_auto" name="color_mode" value="auto" <?= ($config['theme']['color_mode'] ?? 'light') === 'auto' ? 'checked' : '' ?>>
                    <?= e(t('admin.settings.theme.color_auto')) ?>
                </label>

                <label><b><?= e(t('admin.settings.theme.admin_color_mode')) ?></b></label>
                <label class="inline-radio" for="admin_color_mode_light">
                    <input type="radio" id="admin_color_mode_light" name="admin_color_mode" value="light" <?= ($config['theme']['admin_color_mode'] ?? 'auto') === 'light' ? 'checked' : '' ?>>
                    <?= e(t('admin.settings.theme.color_light')) ?>
                </label>
                <label class="inline-radio" for="admin_color_mode_dark">
                    <input type="radio" id="admin_color_mode_dark" name="admin_color_mode" value="dark" <?= ($config['theme']['admin_color_mode'] ?? 'auto') === 'dark' ? 'checked' : '' ?>>
                    <?= e(t('admin.settings.theme.color_dark')) ?>
                </label>
                <label class="inline-radio" for="admin_color_mode_auto">
                    <input type="radio" id="admin_color_mode_auto" name="admin_color_mode" value="auto" <?= ($config['theme']['admin_color_mode'] ?? 'auto') === 'auto' ? 'checked' : '' ?>>
                    <?= e(t('admin.settings.theme.color_auto')) ?>
                </label>
            </section>

            <section class="section-divider">
                <span class="title"><?= e(t('admin.settings.theme.section_colors')) ?></span>

                <?php
                $galleryLink = '<a target="_blank" rel="noopener noreferrer" href="https://pureblog.org/themes">' . e(t('admin.settings.theme.gallery_link')) . '</a>';
                ?>
                <p><b><?= str_replace('{link}', $galleryLink, t('admin.settings.theme.gallery_promo')) ?></b></p>

                <h3><?= e(t('admin.settings.theme.light_mode')) ?></h3>
                <div class="color-grid">
                    <div class="color-field">
                        <label for="background_color"><?= e(t('admin.settings.theme.color_background')) ?></label>
                        <input type="text" id="background_color" name="background_color" value="<?= e($config['theme']['background_color']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="text_color"><?= e(t('admin.settings.theme.color_text')) ?></label>
                        <input type="text" id="text_color" name="text_color" value="<?= e($config['theme']['text_color']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="accent_color"><?= e(t('admin.settings.theme.color_accent')) ?></label>
                        <input type="text" id="accent_color" name="accent_color" value="<?= e($config['theme']['accent_color']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="border_color"><?= e(t('admin.settings.theme.color_border')) ?></label>
                        <input type="text" id="border_color" name="border_color" value="<?= e($config['theme']['border_color']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="accent_bg_color"><?= e(t('admin.settings.theme.color_accent_bg')) ?></label>
                        <input type="text" id="accent_bg_color" name="accent_bg_color" value="<?= e($config['theme']['accent_bg_color']) ?>">
                    </div>
                </div>
                <button class="link-button delete" type="submit" form="settings-form" name="reset_theme_light" value="1" aria-label="<?= e(t('admin.settings.theme.reset_light_confirm')) ?>" onclick="return confirm(<?= e(json_encode(t('admin.settings.theme.reset_light_confirm'))) ?>);">
                    <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                    <?= e(t('admin.settings.theme.reset_light')) ?>
                </button>

                <h3><?= e(t('admin.settings.theme.dark_mode')) ?></h3>
                <div class="color-grid">
                    <div class="color-field">
                        <label for="background_color_dark"><?= e(t('admin.settings.theme.color_background')) ?></label>
                        <input type="text" id="background_color_dark" name="background_color_dark" value="<?= e($config['theme']['background_color_dark']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="text_color_dark"><?= e(t('admin.settings.theme.color_text')) ?></label>
                        <input type="text" id="text_color_dark" name="text_color_dark" value="<?= e($config['theme']['text_color_dark']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="accent_color_dark"><?= e(t('admin.settings.theme.color_accent')) ?></label>
                        <input type="text" id="accent_color_dark" name="accent_color_dark" value="<?= e($config['theme']['accent_color_dark']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="border_color_dark"><?= e(t('admin.settings.theme.color_border')) ?></label>
                        <input type="text" id="border_color_dark" name="border_color_dark" value="<?= e($config['theme']['border_color_dark']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="accent_bg_color_dark"><?= e(t('admin.settings.theme.color_accent_bg')) ?></label>
                        <input type="text" id="accent_bg_color_dark" name="accent_bg_color_dark" value="<?= e($config['theme']['accent_bg_color_dark']) ?>">
                    </div>
                </div>
                <button class="link-button delete" type="submit" form="settings-form" name="reset_theme_dark" value="1" aria-label="<?= e(t('admin.settings.theme.reset_dark_confirm')) ?>" onclick="return confirm(<?= e(json_encode(t('admin.settings.theme.reset_dark_confirm'))) ?>);">
                    <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                    <?= e(t('admin.settings.theme.reset_dark')) ?>
                </button>
            </section>

            <section class="section-divider">
                <span class="title"><?= e(t('admin.settings.theme.section_post_layout')) ?></span>

                <div class="layout-options">
                    <label class="layout-choice" for="post_list_excerpt">
                        <input type="radio" id="post_list_excerpt" name="post_list_layout" value="excerpt" <?= ($config['theme']['post_list_layout'] ?? 'excerpt') === 'excerpt' ? 'checked' : '' ?>>
                        <picture class="layout-preview">
                            <source srcset="<?= base_path() ?>/admin/images/layouts/layout-excerpt-dark.png" media="(prefers-color-scheme: dark)">
                            <img src="<?= base_path() ?>/admin/images/layouts/layout-excerpt-light.png" alt="<?= e(t('admin.settings.theme.layout_excerpt')) ?>" loading="lazy">
                        </picture>
                        <span><?= e(t('admin.settings.theme.layout_excerpt')) ?></span>
                    </label>
                    <label class="layout-choice" for="post_list_full">
                        <input type="radio" id="post_list_full" name="post_list_layout" value="full" <?= ($config['theme']['post_list_layout'] ?? 'excerpt') === 'full' ? 'checked' : '' ?>>
                        <picture class="layout-preview">
                            <source srcset="<?= base_path() ?>/admin/images/layouts/layout-full-dark.png" media="(prefers-color-scheme: dark)">
                            <img src="<?= base_path() ?>/admin/images/layouts/layout-full-light.png" alt="<?= e(t('admin.settings.theme.layout_full')) ?>" loading="lazy">
                        </picture>
                        <span><?= e(t('admin.settings.theme.layout_full')) ?></span>
                    </label>
                    <label class="layout-choice" for="post_list_archive">
                        <input type="radio" id="post_list_archive" name="post_list_layout" value="archive" <?= ($config['theme']['post_list_layout'] ?? 'excerpt') === 'archive' ? 'checked' : '' ?>>
                        <picture class="layout-preview">
                            <source srcset="<?= base_path() ?>/admin/images/layouts/layout-archive-dark.png" media="(prefers-color-scheme: dark)">
                            <img src="<?= base_path() ?>/admin/images/layouts/layout-archive-light.png" alt="<?= e(t('admin.settings.theme.layout_archive')) ?>" loading="lazy">
                        </picture>
                        <span><?= e(t('admin.settings.theme.layout_archive')) ?></span>
                    </label>
                </div>
            </section>
        </form>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
