<?php
$settingsPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
$settingsItems = [
    '/admin/settings-site.php' => ['label' => 'Site', 'icon' => 'globe'],
    '/admin/settings-theme.php' => ['label' => 'Theme', 'icon' => 'paintbrush'],
    '/admin/settings-css.php' => ['label' => 'CSS', 'icon' => 'braces'],
    '/admin/settings-user.php' => ['label' => 'User', 'icon' => 'user'],
];
$settingsSaveFormId = $settingsSaveFormId ?? '';
?>
<ul class="settings-nav-list" aria-label="Settings sections">
    <?php foreach ($settingsItems as $href => $item): ?>
        <?php $isCurrent = $settingsPath === ltrim($href, '/'); ?>
        <li>
            <a href="<?= e($href) ?>"<?= $isCurrent ? ' class="current"' : '' ?>>
                <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-<?= e($item['icon']) ?>"></use></svg>
                <?= e($item['label']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php if ($settingsSaveFormId !== ''): ?>
    <button class="save" type="submit" form="<?= e($settingsSaveFormId) ?>" aria-label="Save settings">
        <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-save"></use></svg>
        Save settings
    </button>
<?php endif; ?>
