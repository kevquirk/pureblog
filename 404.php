<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_setup_redirect();

$config = load_config();
$fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
$pageTitle = 'Page not found';
$metaDescription = '';

http_response_code(404);
require __DIR__ . '/includes/header.php';
?>
    <main>
        <h1>Page not found</h1>
        <p>The page you requested could not be found.</p>
    </main>
    <?php require __DIR__ . '/includes/footer.php'; ?>
