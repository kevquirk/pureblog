<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';

header('Location: ' . base_path() . '/admin/settings-site.php', true, 302);
exit;
