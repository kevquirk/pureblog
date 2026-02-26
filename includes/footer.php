<footer>
    <p>&copy; <?= e((new DateTimeImmutable('now', site_timezone_object($config)))->format('Y')) ?> <?= e($config['site_title']) ?></p>
</footer>
