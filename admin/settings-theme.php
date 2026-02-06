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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $notice = 'Theme colors reset to defaults.';
        } else {
            $errors[] = 'Failed to save settings.';
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
        $errors[] = 'Font stack must be sans, serif, or mono.';
    }

    if (!in_array($adminFontChoice, ['sans', 'serif', 'mono'], true)) {
        $errors[] = 'Admin font stack must be sans, serif, or mono.';
    }

    if (!in_array($adminColorMode, ['light', 'dark', 'auto'], true)) {
        $errors[] = 'Admin color mode must be light, dark, or auto.';
    }

    if (!in_array($colorMode, ['light', 'dark', 'auto'], true)) {
        $errors[] = 'Color mode must be light, dark, or auto.';
    }

    if (!in_array($postListLayout, ['excerpt', 'full', 'archive'], true)) {
        $errors[] = 'Post list layout must be excerpt, full, or archive.';
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
                $notice = 'Settings updated.';
            } else {
                $errors[] = 'Failed to save settings.';
            }
        }
    }
}

$adminTitle = 'Theme Settings - Pureblog';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1>Theme & layout settings</h1>
        <?php require __DIR__ . '/../includes/admin-notices.php'; ?>

        <?php $settingsSaveFormId = 'settings-form'; ?>
        <nav class="editor-actions settings-actions">
            <?php require __DIR__ . '/../includes/admin-settings-nav.php'; ?>
        </nav>

        <form method="post" id="settings-form">
            <?= csrf_field() ?>

            <section class="section-divider">
                <span class="title">Font Settings</span>

                <label><b>Site font</b></label>
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

                <label><b>Admin font</b></label>
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
                <span class="title">Color Mode</span>

                <label><b>Site color mode</b></label>
                <label class="inline-radio" for="color_mode_light">
                    <input type="radio" id="color_mode_light" name="color_mode" value="light" <?= ($config['theme']['color_mode'] ?? 'light') === 'light' ? 'checked' : '' ?>>
                    Light
                </label>
                <label class="inline-radio" for="color_mode_dark">
                    <input type="radio" id="color_mode_dark" name="color_mode" value="dark" <?= ($config['theme']['color_mode'] ?? 'light') === 'dark' ? 'checked' : '' ?>>
                    Dark
                </label>
                <label class="inline-radio" for="color_mode_auto">
                    <input type="radio" id="color_mode_auto" name="color_mode" value="auto" <?= ($config['theme']['color_mode'] ?? 'light') === 'auto' ? 'checked' : '' ?>>
                    Auto
                </label>

                <label><b>Admin color mode</b></label>
                <label class="inline-radio" for="admin_color_mode_light">
                    <input type="radio" id="admin_color_mode_light" name="admin_color_mode" value="light" <?= ($config['theme']['admin_color_mode'] ?? 'auto') === 'light' ? 'checked' : '' ?>>
                    Light
                </label>
                <label class="inline-radio" for="admin_color_mode_dark">
                    <input type="radio" id="admin_color_mode_dark" name="admin_color_mode" value="dark" <?= ($config['theme']['admin_color_mode'] ?? 'auto') === 'dark' ? 'checked' : '' ?>>
                    Dark
                </label>
                <label class="inline-radio" for="admin_color_mode_auto">
                    <input type="radio" id="admin_color_mode_auto" name="admin_color_mode" value="auto" <?= ($config['theme']['admin_color_mode'] ?? 'auto') === 'auto' ? 'checked' : '' ?>>
                    Auto
                </label>
            </section>

            <section class="section-divider">
                <span class="title">Site custom colors</span>

                <h3>Light mode</h3>
                <div class="color-grid">
                    <div class="color-field">
                        <label for="background_color">Background color</label>
                        <input type="text" id="background_color" name="background_color" value="<?= e($config['theme']['background_color']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="text_color">Text color</label>
                        <input type="text" id="text_color" name="text_color" value="<?= e($config['theme']['text_color']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="accent_color">Accent color</label>
                        <input type="text" id="accent_color" name="accent_color" value="<?= e($config['theme']['accent_color']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="border_color">Border color</label>
                        <input type="text" id="border_color" name="border_color" value="<?= e($config['theme']['border_color']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="accent_bg_color">Accent background</label>
                        <input type="text" id="accent_bg_color" name="accent_bg_color" value="<?= e($config['theme']['accent_bg_color']) ?>">
                    </div>
                </div>
                <button class="link-button delete" type="submit" form="settings-form" name="reset_theme_light" value="1" aria-label="Reset light mode colors to defaults" onclick="return confirm('Reset light mode colors to defaults?');">
                    <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-x"></use></svg>
                    Reset light colors
                </button>

                <h3>Dark mode</h3>
                <div class="color-grid">
                    <div class="color-field">
                        <label for="background_color_dark">Background color</label>
                        <input type="text" id="background_color_dark" name="background_color_dark" value="<?= e($config['theme']['background_color_dark']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="text_color_dark">Text color</label>
                        <input type="text" id="text_color_dark" name="text_color_dark" value="<?= e($config['theme']['text_color_dark']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="accent_color_dark">Accent color</label>
                        <input type="text" id="accent_color_dark" name="accent_color_dark" value="<?= e($config['theme']['accent_color_dark']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="border_color_dark">Border color</label>
                        <input type="text" id="border_color_dark" name="border_color_dark" value="<?= e($config['theme']['border_color_dark']) ?>">
                    </div>
                    <div class="color-field">
                        <label for="accent_bg_color_dark">Accent background</label>
                        <input type="text" id="accent_bg_color_dark" name="accent_bg_color_dark" value="<?= e($config['theme']['accent_bg_color_dark']) ?>">
                    </div>
                </div>
                <button class="link-button delete" type="submit" form="settings-form" name="reset_theme_dark" value="1" aria-label="Reset dark mode colors to defaults" onclick="return confirm('Reset dark mode colors to defaults?');">
                    <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-x"></use></svg>
                    Reset dark colors
                </button>
            </section>

            <section class="section-divider">
                <span class="title">Post list layout</span>
                
                <div class="layout-options">
                    <label class="layout-choice" for="post_list_excerpt">
                        <input type="radio" id="post_list_excerpt" name="post_list_layout" value="excerpt" <?= ($config['theme']['post_list_layout'] ?? 'excerpt') === 'excerpt' ? 'checked' : '' ?>>
                        <picture class="layout-preview">
                            <source srcset="/admin/images/layouts/layout-excerpt-dark.png" media="(prefers-color-scheme: dark)">
                            <img src="/admin/images/layouts/layout-excerpt-light.png" alt="Post excerpt layout preview" loading="lazy">
                        </picture>
                        <span>Post excerpt</span>
                    </label>
                    <label class="layout-choice" for="post_list_full">
                        <input type="radio" id="post_list_full" name="post_list_layout" value="full" <?= ($config['theme']['post_list_layout'] ?? 'excerpt') === 'full' ? 'checked' : '' ?>>
                        <picture class="layout-preview">
                            <source srcset="/admin/images/layouts/layout-full-dark.png" media="(prefers-color-scheme: dark)">
                            <img src="/admin/images/layouts/layout-full-light.png" alt="Full post layout preview" loading="lazy">
                        </picture>
                        <span>Full post</span>
                    </label>
                    <label class="layout-choice" for="post_list_archive">
                        <input type="radio" id="post_list_archive" name="post_list_layout" value="archive" <?= ($config['theme']['post_list_layout'] ?? 'excerpt') === 'archive' ? 'checked' : '' ?>>
                        <picture class="layout-preview">
                            <source srcset="/admin/images/layouts/layout-archive-dark.png" media="(prefers-color-scheme: dark)">
                            <img src="/admin/images/layouts/layout-archive-light.png" alt="Archive layout preview" loading="lazy">
                        </picture>
                        <span>Date & title</span>
                    </label>
                </div>
            </section>
        </form>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
