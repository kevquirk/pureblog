<header>
    <h1><a href="<?= base_path() ?>/"><?= e($config['site_title']) ?></a></h1>
    <?php if ($siteTagline !== ''): ?>
    <p class="tagline"><?= e($siteTagline) ?></p>
    <?php endif; ?>
    <?php if (!empty($customNavOnly) ? !empty($customNavItems) : ($navPages || $customNavItems)): ?>
        <nav class="site-nav">
            <ul>
                <?php if (empty($customNavOnly)): ?>
                    <li><a href="<?= base_path() ?>/"<?= $currentPath === '' ? ' class="current"' : '' ?>><?= e(t('frontend.nav_home')) ?></a></li>
                    <?php foreach ($navPages as $navPage): ?>
                        <?php $isCurrent = $currentPath === $navPage['slug']; ?>
                        <li><a href="<?= base_path() ?>/<?= e($navPage['slug']) ?>"<?= $isCurrent ? ' class="current"' : '' ?>><?= e($navPage['title']) ?></a></li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php foreach ($customNavItems as $item): ?>
                    <?php
                    $itemScheme = parse_url($item['url'], PHP_URL_SCHEME);
                    $isExternal = !empty($itemScheme);
                    $itemPath = parse_url($item['url'], PHP_URL_PATH) ?? '';
                    $bp = base_path();
                    if ($bp !== '' && str_starts_with($itemPath, $bp)) {
                        $itemPath = substr($itemPath, strlen($bp));
                    }
                    $itemPath = trim($itemPath, '/');
                    $isCurrentCustom = !$isExternal && ($itemPath === $currentPath);
                    ?>
                    <li><a href="<?= e($item['url']) ?>"<?= $isCurrentCustom ? ' class="current"' : '' ?>><?= e($item['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
</header>
