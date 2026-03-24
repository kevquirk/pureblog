<?php
$settingsUriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$bp = base_path();
if ($bp !== '' && str_starts_with($settingsUriPath, $bp)) {
    $settingsUriPath = substr($settingsUriPath, strlen($bp));
}
$settingsPath = trim($settingsUriPath, '/');
$settingsItems = [
    '/admin/settings-site.php'    => ['label' => t('admin.settings.nav.site'),    'icon' => 'globe'],
    '/admin/settings-theme.php'   => ['label' => t('admin.settings.nav.theme'),   'icon' => 'paintbrush'],
    '/admin/settings-css.php'     => ['label' => t('admin.settings.nav.css'),     'icon' => 'braces'],
    '/admin/settings-user.php'    => ['label' => t('admin.settings.nav.user'),    'icon' => 'user'],
    '/admin/settings-updates.php' => ['label' => t('admin.settings.nav.updates'), 'icon' => 'upgrade'],
];
$settingsSaveFormId = $settingsSaveFormId ?? '';
?>
<ul class="settings-nav-list" aria-label="Settings sections">
    <?php foreach ($settingsItems as $href => $item): ?>
        <?php $isCurrent = $settingsPath === ltrim($href, '/'); ?>
        <li>
            <a href="<?= e($bp . $href) ?>"<?= $isCurrent ? ' class="current"' : '' ?>>
                <svg class="icon" aria-hidden="true"><use href="#icon-<?= e($item['icon']) ?>"></use></svg>
                <?= e($item['label']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php if ($settingsSaveFormId !== ''): ?>
    <button class="save" type="submit" form="<?= e($settingsSaveFormId) ?>" aria-label="<?= e(t('admin.settings.nav.save')) ?>">
        <svg class="icon" aria-hidden="true"><use href="#icon-save"></use></svg>
        <?= e(t('admin.settings.nav.save')) ?>
    </button>
<?php endif; ?>
