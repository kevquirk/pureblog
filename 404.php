<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_setup_redirect();
send_security_headers();

$config = load_config();
$fontStack = font_stack_css($config['theme']['font_stack'] ?? 'sans');
$pageTitle = t('frontend.page_not_found');
$metaDescription = '';

http_response_code(404);
require __DIR__ . '/includes/header.php';
render_masthead_layout($config, ['page' => $page ?? null]);
?>
    <main>
        <h1><?= e(t('frontend.page_not_found')) ?></h1>
        <p><?= e(t('frontend.page_not_found_detail')) ?></p>
    </main>
    <?php render_footer_layout($config, ['page' => $page ?? null]); ?>
</body>
</html>
