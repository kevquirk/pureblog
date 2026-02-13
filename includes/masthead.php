<header>
    <h1><a href="/"><?= e($config['site_title']) ?></a></h1>
    <?php if ($siteTagline !== ''): ?>
    <p class="tagline"><?= e($siteTagline) ?></p>
    <?php endif; ?>
    <?php if ($navPages || $customNavItems): ?>
        <nav class="site-nav">
            <ul>
                <li><a href="/"<?= $currentPath === '' ? ' class="current"' : '' ?>>Home</a></li>
                <?php foreach ($navPages as $navPage): ?>
                    <?php $isCurrent = $currentPath === $navPage['slug']; ?>
                    <li><a href="/<?= e($navPage['slug']) ?>"<?= $isCurrent ? ' class="current"' : '' ?>><?= e($navPage['title']) ?></a></li>
                <?php endforeach; ?>
                <?php foreach ($customNavItems as $item): ?>
                    <?php
                    $itemPath = parse_url($item['url'], PHP_URL_PATH) ?? '';
                    $itemPath = trim($itemPath, '/');
                    $isCurrentCustom = $itemPath !== '' && $itemPath === $currentPath;
                    ?>
                    <li><a href="<?= e($item['url']) ?>"<?= $isCurrentCustom ? ' class="current"' : '' ?>><?= e($item['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
</header>
