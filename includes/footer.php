<?php
// Shared site footer. Expects $config to be defined.
$isPostView = isset($post) && is_array($post);
$isPageView = isset($page) && is_array($page);
$footerInject = '';
if ($isPostView) {
    $footerInject = (string) ($config['footer_inject_post'] ?? '');
} elseif ($isPageView) {
    $footerInject = (string) ($config['footer_inject_page'] ?? '');
}
?>
<footer>
    <p>&copy; <?= e(date('Y')) ?> <?= e($config['site_title']) ?></p>
    <?php if (trim($footerInject) !== ''): ?>
<?= $footerInject . "\n" ?>
    <?php endif; ?>
</footer>
</body>
</html>
