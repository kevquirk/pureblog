<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

$errors = [];
$notice = '';

if (isset($_SESSION['admin_theme_notice'])) {
    $notice = (string) $_SESSION['admin_theme_notice'];
    unset($_SESSION['admin_theme_notice']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action_id'])) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if (isset($_POST['apply_theme_id'])) {
        $themeId = trim((string) $_POST['apply_theme_id']);
        $theme = get_theme_by_id($themeId);
        if ($theme) {
            if (apply_theme_to_config($theme)) {
                $notice = t('admin.settings.theme.notice_theme_applied');
                $config = load_config(true);
            } else {
                $errors[] = t('admin.settings.theme.error_save');
            }
        } else {
            $errors[] = t('admin.settings.theme.error_theme_not_found');
        }
    } elseif (isset($_POST['delete_theme_filename'])) {
        $filename = trim((string) $_POST['delete_theme_filename']);
        if (delete_theme_file($filename)) {
            $notice = t('admin.settings.theme.notice_theme_deleted');
        } else {
            $errors[] = t('admin.settings.theme.error_theme_delete');
        }
    } elseif ($action === 'create_theme') {
        $themeName = trim((string) ($_POST['new_theme_name'] ?? ''));
        if ($themeName === '') {
            $errors[] = t('admin.settings.theme.error_theme_name');
        } else {
            $lightRaw = [
                'bg'        => trim((string) ($_POST['new_bg'] ?? '')),
                'text'      => trim((string) ($_POST['new_text'] ?? '')),
                'accent'    => trim((string) ($_POST['new_accent'] ?? '')),
                'border'    => trim((string) ($_POST['new_border'] ?? '')),
                'accent_bg' => trim((string) ($_POST['new_accent_bg'] ?? '')),
            ];
            $darkRaw = [
                'bg'        => trim((string) ($_POST['new_bg_dark'] ?? '')),
                'text'      => trim((string) ($_POST['new_text_dark'] ?? '')),
                'accent'    => trim((string) ($_POST['new_accent_dark'] ?? '')),
                'border'    => trim((string) ($_POST['new_border_dark'] ?? '')),
                'accent_bg' => trim((string) ($_POST['new_accent_bg_dark'] ?? '')),
            ];

            $lightFilled = count(array_filter($lightRaw, fn($v) => $v !== ''));
            $darkFilled  = count(array_filter($darkRaw, fn($v) => $v !== ''));

            if ($lightFilled === 0 && $darkFilled === 0) {
                $errors[] = t('admin.settings.theme.error_theme_empty');
            } elseif ($lightFilled > 0 && $lightFilled < 5) {
                $errors[] = t('admin.settings.theme.error_theme_light_incomplete');
            } elseif ($darkFilled > 0 && $darkFilled < 5) {
                $errors[] = t('admin.settings.theme.error_theme_dark_incomplete');
            } else {
                $light = $lightFilled === 5 ? $lightRaw : null;
                $dark  = $darkFilled === 5 ? $darkRaw : null;

                $res = save_theme_json($themeName, $light, $dark);
                if (!empty($res['ok'])) {
                    $notice = t('admin.settings.theme.notice_theme_created');
                } else {
                    $errors[] = $res['error'] ?? t('admin.settings.theme.error_theme_save');
                }
            }
        }
    } elseif ($action === 'import_theme') {
        if (!empty($_FILES['theme_file'])) {
            $res = save_uploaded_theme($_FILES['theme_file']);
            if (!empty($res['ok'])) {
                $notice = t('admin.settings.theme.notice_theme_imported');
            } else {
                $errors[] = $res['error'] ?? t('admin.settings.theme.error_theme_invalid');
            }
        } else {
            $errors[] = t('admin.settings.theme.error_theme_upload');
        }
    } else {
        // Standard typography & layout settings form submit
        $fontChoice = $_POST['font_stack'] ?? 'sans';
        $adminFontChoice = $_POST['admin_font_stack'] ?? 'sans';
        $adminColorMode = $_POST['admin_color_mode'] ?? 'auto';
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
            $config['theme']['post_list_layout'] = $postListLayout;

            if (save_config($config)) {
                $notice = t('admin.settings.theme.notice_updated');
            } else {
                $errors[] = t('admin.settings.theme.error_save');
            }
        }
    }
}

function is_theme_active(array $theme, array $activeThemeConfig): bool
{
    if (!empty($activeThemeConfig['active_theme_name']) && $activeThemeConfig['active_theme_name'] === $theme['name']) {
        return true;
    }

    $hasLight = !empty($theme['has_light']);
    $hasDark  = !empty($theme['has_dark']);
    if (!$hasLight && !$hasDark) {
        $hasLight = true;
        $hasDark  = true;
    }

    $lightMatch = true;
    if ($hasLight) {
        $lightMatch = strcasecmp($theme['light']['bg'], $activeThemeConfig['background_color'] ?? '') === 0
            && strcasecmp($theme['light']['text'], $activeThemeConfig['text_color'] ?? '') === 0
            && strcasecmp($theme['light']['accent'], $activeThemeConfig['accent_color'] ?? '') === 0;
    }

    $darkMatch = true;
    if ($hasDark) {
        $darkMatch = strcasecmp($theme['dark']['bg'], $activeThemeConfig['background_color_dark'] ?? '') === 0
            && strcasecmp($theme['dark']['text'], $activeThemeConfig['text_color_dark'] ?? '') === 0
            && strcasecmp($theme['dark']['accent'], $activeThemeConfig['accent_color_dark'] ?? '') === 0;
    }

    return $lightMatch && $darkMatch;
}

$availableThemes = get_available_themes();

$adminTitle = t('admin.settings.theme.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1><?= e(t('admin.settings.theme.heading')) ?></h1>
        <?php require __DIR__ . '/../includes/admin-notices.php'; ?>

        <nav class="admin-actions">
            <button class="save" type="submit" form="settings-form" aria-label="<?= e(t('admin.settings.nav.save')) ?>">
                <svg class="icon" aria-hidden="true"><use href="#icon-save"></use></svg>
                <?= e(t('admin.settings.nav.save')) ?>
            </button>
        </nav>

        <form method="post" id="settings-form">
            <?= csrf_field() ?>

            <section class="section-divider">
                <span class="title"><?= e(t('admin.settings.theme.section_fonts')) ?></span>

                <label><b><?= e(t('admin.settings.theme.site_font')) ?></b></label>
                <label class="inline-radio font-preview font-preview-sans" for="font_stack_sans">
                    <input type="radio" id="font_stack_sans" name="font_stack" value="sans" <?= ($config['theme']['font_stack'] ?? 'sans') === 'sans' ? 'checked' : '' ?>>
                    Sans (Inter)
                </label>
                <label class="inline-radio font-preview font-preview-serif" for="font_stack_serif">
                    <input type="radio" id="font_stack_serif" name="font_stack" value="serif" <?= ($config['theme']['font_stack'] ?? 'sans') === 'serif' ? 'checked' : '' ?>>
                    Serif (Merriweather)
                </label>
                <label class="inline-radio font-preview font-preview-mono" for="font_stack_mono">
                    <input type="radio" id="font_stack_mono" name="font_stack" value="mono" <?= ($config['theme']['font_stack'] ?? 'sans') === 'mono' ? 'checked' : '' ?>>
                    Mono (Iosevka)
                </label>

                <label><b><?= e(t('admin.settings.theme.admin_font')) ?></b></label>
                <label class="inline-radio font-preview font-preview-sans" for="admin_font_stack_sans">
                    <input type="radio" id="admin_font_stack_sans" name="admin_font_stack" value="sans" <?= ($config['theme']['admin_font_stack'] ?? 'sans') === 'sans' ? 'checked' : '' ?>>
                    Sans (Inter)
                </label>
                <label class="inline-radio font-preview font-preview-serif" for="admin_font_stack_serif">
                    <input type="radio" id="admin_font_stack_serif" name="admin_font_stack" value="serif" <?= ($config['theme']['admin_font_stack'] ?? 'sans') === 'serif' ? 'checked' : '' ?>>
                    Serif (Merriweather)
                </label>
                <label class="inline-radio font-preview font-preview-mono" for="admin_font_stack_mono">
                    <input type="radio" id="admin_font_stack_mono" name="admin_font_stack" value="mono" <?= ($config['theme']['admin_font_stack'] ?? 'sans') === 'mono' ? 'checked' : '' ?>>
                    Mono (Iosevka)
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

        <section class="section-divider">
            <span class="title"><?= e(t('admin.settings.theme.section_themes')) ?></span>

            <?php
            $docsLink = '<a target="_blank" rel="noopener noreferrer" href="https://docs.pureblog.org/themes/">' . e(t('admin.settings.theme.docs_link')) . '</a>';
            ?>
            <p><?= str_replace('{link}', $docsLink, t('admin.settings.theme.themes_intro')) ?></p>

            <form method="post" id="theme-action-form">
                <?= csrf_field() ?>
                <div class="theme-grid">
                    <?php foreach ($availableThemes as $th): ?>
                        <?php $isActive = is_theme_active($th, $config['theme']); ?>
                        <div class="theme-card<?= $isActive ? ' active' : '' ?>">
                            <div class="theme-card-header">
                                <span class="theme-card-title"><?= e($th['name']) ?></span>
                                <?php if ($isActive): ?>
                                    <span class="theme-badge active-badge"><?= e(t('admin.settings.theme.active')) ?></span>
                                <?php elseif ($th['is_custom']): ?>
                                    <span class="theme-badge custom-badge"><?= e(t('admin.settings.theme.custom')) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="theme-swatch-group">
                                <?php if (!empty($th['has_light']) || empty($th['has_dark'])): ?>
                                    <div class="theme-swatch-row">
                                        <span class="swatch-label"><?= e(t('admin.settings.theme.light_mode')) ?></span>
                                        <div class="swatch-chips">
                                            <span class="swatch-chip" title="Background: <?= e($th['light']['bg']) ?>" style="background: <?= e($th['light']['bg']) ?>; border: 1px solid <?= e($th['light']['border']) ?>;"></span>
                                            <span class="swatch-chip" title="Text: <?= e($th['light']['text']) ?>" style="background: <?= e($th['light']['text']) ?>;"></span>
                                            <span class="swatch-chip" title="Accent: <?= e($th['light']['accent']) ?>" style="background: <?= e($th['light']['accent']) ?>;"></span>
                                            <span class="swatch-chip" title="Border: <?= e($th['light']['border']) ?>" style="background: <?= e($th['light']['border']) ?>;"></span>
                                            <span class="swatch-chip" title="Accent BG: <?= e($th['light']['accent_bg']) ?>" style="background: <?= e($th['light']['accent_bg']) ?>;"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($th['has_dark']) || empty($th['has_light'])): ?>
                                    <div class="theme-swatch-row">
                                        <span class="swatch-label"><?= e(t('admin.settings.theme.dark_mode')) ?></span>
                                        <div class="swatch-chips">
                                            <span class="swatch-chip" title="Background: <?= e($th['dark']['bg']) ?>" style="background: <?= e($th['dark']['bg']) ?>; border: 1px solid <?= e($th['dark']['border']) ?>;"></span>
                                            <span class="swatch-chip" title="Text: <?= e($th['dark']['text']) ?>" style="background: <?= e($th['dark']['text']) ?>;"></span>
                                            <span class="swatch-chip" title="Accent: <?= e($th['dark']['accent']) ?>" style="background: <?= e($th['dark']['accent']) ?>;"></span>
                                            <span class="swatch-chip" title="Border: <?= e($th['dark']['border']) ?>" style="background: <?= e($th['dark']['border']) ?>;"></span>
                                            <span class="swatch-chip" title="Accent BG: <?= e($th['dark']['accent_bg']) ?>" style="background: <?= e($th['dark']['accent_bg']) ?>;"></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="theme-card-actions">
                                <?php if (!$isActive): ?>
                                    <button type="submit" name="apply_theme_id" value="<?= e($th['id']) ?>" class="link-button apply">
                                        <?= e(t('admin.settings.theme.apply')) ?>
                                    </button>
                                <?php endif; ?>
                                <a href="<?= e(base_path()) ?>/?theme_preview=<?= urlencode($th['id']) ?>" class="link-button preview" target="_blank" rel="noopener noreferrer">
                                    <?= e(t('admin.settings.theme.preview')) ?>
                                </a>
                                <?php if ($th['is_custom']): ?>
                                    <button type="submit" name="delete_theme_filename" value="<?= e($th['filename']) ?>" class="link-button delete" onclick="return confirm(<?= e(json_encode(t('admin.settings.theme.delete_confirm'))) ?>);">
                                        <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                                        <?= e(t('admin.editor.delete')) ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>
        </section>

        <details class="theme-creator-details">
            <summary class="theme-creator-summary"><b><?= e(t('admin.settings.theme.creator_title')) ?></b></summary>
            <div class="theme-creator-body">
                <p class="tip"><?= e(t('admin.settings.theme.creator_tip')) ?></p>
                <form method="post" class="theme-creator-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_theme">

                    <label for="new_theme_name"><b><?= e(t('admin.settings.theme.theme_name')) ?></b></label>
                    <input type="text" id="new_theme_name" name="new_theme_name" placeholder="e.g. Sunset" required>

                    <h3><?= e(t('admin.settings.theme.light_mode')) ?></h3>
                    <div class="color-grid">
                        <div class="color-field">
                            <label for="new_bg"><?= e(t('admin.settings.theme.color_background')) ?></label>
                            <input type="text" id="new_bg" name="new_bg" class="color-input" placeholder="#FAFAFA">
                        </div>
                        <div class="color-field">
                            <label for="new_text"><?= e(t('admin.settings.theme.color_text')) ?></label>
                            <input type="text" id="new_text" name="new_text" class="color-input" placeholder="#212121">
                        </div>
                        <div class="color-field">
                            <label for="new_accent"><?= e(t('admin.settings.theme.color_accent')) ?></label>
                            <input type="text" id="new_accent" name="new_accent" class="color-input" placeholder="#0D47A1">
                        </div>
                        <div class="color-field">
                            <label for="new_border"><?= e(t('admin.settings.theme.color_border')) ?></label>
                            <input type="text" id="new_border" name="new_border" class="color-input" placeholder="#898EA4">
                        </div>
                        <div class="color-field">
                            <label for="new_accent_bg"><?= e(t('admin.settings.theme.color_accent_bg')) ?></label>
                            <input type="text" id="new_accent_bg" name="new_accent_bg" class="color-input" placeholder="#F5F7FF">
                        </div>
                    </div>

                    <h3><?= e(t('admin.settings.theme.dark_mode')) ?></h3>
                    <div class="color-grid">
                        <div class="color-field">
                            <label for="new_bg_dark"><?= e(t('admin.settings.theme.color_background')) ?></label>
                            <input type="text" id="new_bg_dark" name="new_bg_dark" class="color-input" placeholder="#212121">
                        </div>
                        <div class="color-field">
                            <label for="new_text_dark"><?= e(t('admin.settings.theme.color_text')) ?></label>
                            <input type="text" id="new_text_dark" name="new_text_dark" class="color-input" placeholder="#DCDCDC">
                        </div>
                        <div class="color-field">
                            <label for="new_accent_dark"><?= e(t('admin.settings.theme.color_accent')) ?></label>
                            <input type="text" id="new_accent_dark" name="new_accent_dark" class="color-input" placeholder="#FFB300">
                        </div>
                        <div class="color-field">
                            <label for="new_border_dark"><?= e(t('admin.settings.theme.color_border')) ?></label>
                            <input type="text" id="new_border_dark" name="new_border_dark" class="color-input" placeholder="#555555">
                        </div>
                        <div class="color-field">
                            <label for="new_accent_bg_dark"><?= e(t('admin.settings.theme.color_accent_bg')) ?></label>
                            <input type="text" id="new_accent_bg_dark" name="new_accent_bg_dark" class="color-input" placeholder="#2B2B2B">
                        </div>
                    </div>

                    <div style="margin-top: 1rem;">
                        <button type="submit" class="save">
                            <svg class="icon" aria-hidden="true"><use href="#icon-save"></use></svg>
                            <?= e(t('admin.settings.theme.save_theme')) ?>
                        </button>
                    </div>
                </form>
            </div>
        </details>

        <script>
        document.querySelectorAll('.color-input').forEach(function(input) {
            function updateColor() {
                var val = input.value.trim();
                if (val !== '' && !val.startsWith('#')) {
                    val = '#' + val;
                }
                var hex = val.replace(/^#/, '');
                if (/^([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(hex)) {
                    if (hex.length === 3) {
                        hex = hex.split('').map(function(c) { return c + c; }).join('');
                    }
                    var r = parseInt(hex.substring(0, 2), 16);
                    var g = parseInt(hex.substring(2, 4), 16);
                    var b = parseInt(hex.substring(4, 6), 16);
                    var yiq = (r * 299 + g * 587 + b * 114) / 1000;
                    input.style.backgroundColor = '#' + hex;
                    input.style.color = yiq >= 128 ? '#000000' : '#ffffff';
                } else {
                    input.style.backgroundColor = '';
                    input.style.color = '';
                }
            }
            input.addEventListener('input', updateColor);
            input.addEventListener('change', updateColor);
            if (input.value) updateColor();
        });
        </script>

        <section class="section-divider">
            <span class="title"><?= e(t('admin.settings.theme.section_import')) ?></span>
            <form method="post" enctype="multipart/form-data" class="theme-import-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import_theme">
                <label for="theme_file"><?= e(t('admin.settings.theme.import_file_label')) ?> <span class="tip">(<?= e(t('admin.settings.theme.import_file_tip')) ?>)</span></label>
                <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.5rem;">
                    <input type="file" id="theme_file" name="theme_file" accept=".json,application/json" required style="margin-bottom: 0;">
                    <button type="submit" class="save" style="margin: 0; white-space: nowrap;">
                        <?= e(t('admin.settings.theme.import_button')) ?>
                    </button>
                </div>
            </form>
        </section>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
