<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function api_json_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_extract_bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (strcasecmp((string) $k, 'Authorization') === 0) {
                    $header = (string) $v;
                    break;
                }
            }
        }
    }
    if (preg_match('/^\s*Bearer\s+(.+?)\s*$/i', $header, $m)) {
        return $m[1];
    }
    return '';
}

function api_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        api_json_response(400, ['error' => 'Invalid JSON request body.']);
    }
    return $decoded;
}

function api_normalize_tags(mixed $raw): array
{
    if (is_array($raw)) {
        return array_values(array_filter(array_map(fn($t) => trim((string) $t), $raw), fn($t) => $t !== ''));
    }
    if (is_string($raw)) {
        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn($t) => $t !== ''));
    }
    return [];
}

function api_format_post(array $post, bool $includeContent = true): array
{
    $out = [
        'title'            => (string) ($post['title'] ?? ''),
        'slug'             => (string) ($post['slug'] ?? ''),
        'date'             => (string) ($post['date'] ?? ''),
        'status'           => (string) ($post['status'] ?? 'draft'),
        'tags'             => array_values((array) ($post['tags'] ?? [])),
        'description'      => (string) ($post['description'] ?? ''),
        'layout'           => (string) ($post['layout'] ?? ''),
        'feature_image'    => (string) ($post['feature_image'] ?? ''),
        'meta_title'       => (string) ($post['meta_title'] ?? ''),
        'meta_description' => (string) ($post['meta_description'] ?? ''),
    ];
    if ($includeContent) {
        $out['content'] = (string) ($post['content'] ?? '');
    }
    return $out;
}

function api_format_page(array $page, bool $includeContent = true): array
{
    $out = [
        'title'            => (string) ($page['title'] ?? ''),
        'slug'             => (string) ($page['slug'] ?? ''),
        'status'           => (string) ($page['status'] ?? 'draft'),
        'description'      => (string) ($page['description'] ?? ''),
        'include_in_nav'   => (bool) ($page['include_in_nav'] ?? true),
        'nav_order'        => (int) ($page['nav_order'] ?? 0),
        'nav_label'        => (string) ($page['nav_label'] ?? ''),
        'feature_image'    => (string) ($page['feature_image'] ?? ''),
        'meta_title'       => (string) ($page['meta_title'] ?? ''),
        'meta_description' => (string) ($page['meta_description'] ?? ''),
    ];
    if ($includeContent) {
        $out['content'] = (string) ($page['content'] ?? '');
    }
    return $out;
}

$config = load_config();
$expectedHash = (string) ($config['api_token_hash'] ?? '');
if ($expectedHash === '') {
    api_json_response(401, ['error' => 'API access is not enabled.']);
}

$providedToken = api_extract_bearer_token();
if ($providedToken === '' || !hash_equals($expectedHash, hash('sha256', $providedToken))) {
    api_json_response(401, ['error' => 'Invalid or missing API token.']);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
$bp = base_path();
if ($bp !== '' && str_starts_with($uriPath, $bp)) {
    $uriPath = substr($uriPath, strlen($bp));
}
if (str_starts_with($uriPath, '/api.php')) {
    $route = substr($uriPath, strlen('/api.php'));
} elseif (str_starts_with($uriPath, '/api')) {
    $route = substr($uriPath, strlen('/api'));
} else {
    $route = $uriPath;
}
$route = trim($route, '/');
$segments = $route === '' ? [] : explode('/', $route);

// Health / root
if ($segments === [] || ($segments[0] === 'health' && count($segments) === 1)) {
    if ($method !== 'GET') {
        api_json_response(405, ['error' => 'Method not allowed.']);
    }
    api_json_response(200, [
        'ok'      => true,
        'version' => defined('PUREBLOG_VERSION') ? PUREBLOG_VERSION : null,
    ]);
}

$resource = $segments[0];
$targetSlug = isset($segments[1]) ? urldecode($segments[1]) : null;
if (count($segments) > 2) {
    api_json_response(404, ['error' => 'Endpoint not found.']);
}

// Posts endpoints
if ($resource === 'posts') {
    if ($targetSlug === null) {
        if ($method === 'GET') {
            $statusFilter = trim((string) ($_GET['status'] ?? ''));
            $includeContent = ($_GET['include_content'] ?? '1') !== '0';
            $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : null;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

            $posts = get_all_posts(true);
            if ($statusFilter !== '') {
                $posts = array_values(array_filter($posts, fn(array $p): bool => ($p['status'] ?? '') === $statusFilter));
            }
            $total = count($posts);
            if ($offset > 0 || $limit !== null) {
                $posts = array_slice($posts, $offset, $limit);
            }
            api_json_response(200, [
                'total' => $total,
                'posts' => array_map(fn(array $p): array => api_format_post($p, $includeContent), $posts),
            ]);
        }

        if ($method === 'POST') {
            $body = api_read_json_body();
            $title = trim((string) ($body['title'] ?? ''));
            if ($title === '') {
                api_json_response(422, ['error' => 'Field "title" is required.']);
            }
            $status = trim((string) ($body['status'] ?? 'draft'));
            if (!in_array($status, ['draft', 'scheduled', 'published'], true)) {
                api_json_response(422, ['error' => 'Field "status" must be draft, scheduled, or published.']);
            }

            $post = [
                'title'            => $title,
                'slug'             => trim((string) ($body['slug'] ?? '')),
                'date'             => trim((string) ($body['date'] ?? '')),
                'status'           => $status,
                'tags'             => api_normalize_tags($body['tags'] ?? []),
                'description'      => trim((string) ($body['description'] ?? '')),
                'layout'           => trim((string) ($body['layout'] ?? '')),
                'feature_image'    => trim((string) ($body['feature_image'] ?? '')),
                'meta_title'       => trim((string) ($body['meta_title'] ?? '')),
                'meta_description' => trim((string) ($body['meta_description'] ?? '')),
                'content'          => (string) ($body['content'] ?? ''),
            ];
            $saveError = null;
            if (!save_post($post, null, null, null, $saveError)) {
                api_json_response(500, ['error' => $saveError ?? 'Unable to save post.']);
            }
            $saved = get_post_by_slug($post['slug'], true) ?? $post;
            api_json_response(201, ['post' => api_format_post($saved)]);
        }

        api_json_response(405, ['error' => 'Method not allowed.']);
    }

    $existing = get_post_by_slug($targetSlug, true);
    if ($existing === null) {
        api_json_response(404, ['error' => 'Post not found.']);
    }

    if ($method === 'GET') {
        api_json_response(200, ['post' => api_format_post($existing)]);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $body = api_read_json_body();
        $origSlug = (string) $existing['slug'];
        $origDate = (string) ($existing['date'] ?? '');
        $origStatus = (string) ($existing['status'] ?? 'draft');

        $updated = $existing;
        if (array_key_exists('title', $body)) {
            $updated['title'] = trim((string) $body['title']);
        }
        if (trim((string) ($updated['title'] ?? '')) === '') {
            api_json_response(422, ['error' => 'Field "title" cannot be empty.']);
        }
        if (array_key_exists('slug', $body)) {
            $updated['slug'] = trim((string) $body['slug']);
        }
        if (array_key_exists('date', $body)) {
            $updated['date'] = trim((string) $body['date']);
        }
        if (array_key_exists('status', $body)) {
            $status = trim((string) $body['status']);
            if (!in_array($status, ['draft', 'scheduled', 'published'], true)) {
                api_json_response(422, ['error' => 'Field "status" must be draft, scheduled, or published.']);
            }
            $updated['status'] = $status;
        }
        if (array_key_exists('tags', $body)) {
            $updated['tags'] = api_normalize_tags($body['tags']);
        }
        foreach (['description', 'layout', 'feature_image', 'meta_title', 'meta_description'] as $field) {
            if (array_key_exists($field, $body)) {
                $updated[$field] = trim((string) $body[$field]);
            }
        }
        if (array_key_exists('content', $body)) {
            $updated['content'] = (string) $body['content'];
        }

        $saveError = null;
        if (!save_post($updated, $origSlug, $origDate, $origStatus, $saveError)) {
            api_json_response(500, ['error' => $saveError ?? 'Unable to update post.']);
        }
        $saved = get_post_by_slug($updated['slug'], true) ?? $updated;
        api_json_response(200, ['post' => api_format_post($saved)]);
    }

    if ($method === 'DELETE') {
        if (!delete_post_by_slug($existing['slug'])) {
            api_json_response(500, ['error' => 'Unable to delete post.']);
        }
        api_json_response(200, ['ok' => true, 'deleted' => $existing['slug']]);
    }

    api_json_response(405, ['error' => 'Method not allowed.']);
}

// Pages endpoints
if ($resource === 'pages') {
    if ($targetSlug === null) {
        if ($method === 'GET') {
            $statusFilter = trim((string) ($_GET['status'] ?? ''));
            $includeContent = ($_GET['include_content'] ?? '1') !== '0';
            $pages = get_all_pages(true);
            if ($statusFilter !== '') {
                $pages = array_values(array_filter($pages, fn(array $p): bool => ($p['status'] ?? '') === $statusFilter));
            }
            api_json_response(200, [
                'total' => count($pages),
                'pages' => array_map(fn(array $p): array => api_format_page($p, $includeContent), $pages),
            ]);
        }

        if ($method === 'POST') {
            $body = api_read_json_body();
            $title = trim((string) ($body['title'] ?? ''));
            if ($title === '') {
                api_json_response(422, ['error' => 'Field "title" is required.']);
            }
            $status = trim((string) ($body['status'] ?? 'draft'));
            if (!in_array($status, ['draft', 'published'], true)) {
                api_json_response(422, ['error' => 'Field "status" must be draft or published.']);
            }

            $page = [
                'title'            => $title,
                'slug'             => trim((string) ($body['slug'] ?? '')),
                'status'           => $status,
                'description'      => trim((string) ($body['description'] ?? '')),
                'include_in_nav'   => array_key_exists('include_in_nav', $body) ? (bool) $body['include_in_nav'] : true,
                'nav_order'        => (int) ($body['nav_order'] ?? 0),
                'nav_label'        => trim((string) ($body['nav_label'] ?? '')),
                'feature_image'    => trim((string) ($body['feature_image'] ?? '')),
                'meta_title'       => trim((string) ($body['meta_title'] ?? '')),
                'meta_description' => trim((string) ($body['meta_description'] ?? '')),
                'content'          => (string) ($body['content'] ?? ''),
            ];
            $saveError = null;
            if (!save_page($page, null, null, $saveError)) {
                api_json_response(500, ['error' => $saveError ?? 'Unable to save page.']);
            }
            $saved = get_page_by_slug($page['slug'], true) ?? $page;
            api_json_response(201, ['page' => api_format_page($saved)]);
        }

        api_json_response(405, ['error' => 'Method not allowed.']);
    }

    $existing = get_page_by_slug($targetSlug, true);
    if ($existing === null) {
        api_json_response(404, ['error' => 'Page not found.']);
    }

    if ($method === 'GET') {
        api_json_response(200, ['page' => api_format_page($existing)]);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $body = api_read_json_body();
        $origSlug = (string) $existing['slug'];
        $origStatus = (string) ($existing['status'] ?? 'draft');

        $updated = $existing;
        if (array_key_exists('title', $body)) {
            $updated['title'] = trim((string) $body['title']);
        }
        if (trim((string) ($updated['title'] ?? '')) === '') {
            api_json_response(422, ['error' => 'Field "title" cannot be empty.']);
        }
        if (array_key_exists('slug', $body)) {
            $updated['slug'] = trim((string) $body['slug']);
        }
        if (array_key_exists('status', $body)) {
            $status = trim((string) $body['status']);
            if (!in_array($status, ['draft', 'published'], true)) {
                api_json_response(422, ['error' => 'Field "status" must be draft or published.']);
            }
            $updated['status'] = $status;
        }
        if (array_key_exists('include_in_nav', $body)) {
            $updated['include_in_nav'] = (bool) $body['include_in_nav'];
        }
        if (array_key_exists('nav_order', $body)) {
            $updated['nav_order'] = (int) $body['nav_order'];
        }
        foreach (['description', 'nav_label', 'feature_image', 'meta_title', 'meta_description'] as $field) {
            if (array_key_exists($field, $body)) {
                $updated[$field] = trim((string) $body[$field]);
            }
        }
        if (array_key_exists('content', $body)) {
            $updated['content'] = (string) $body['content'];
        }

        $saveError = null;
        if (!save_page($updated, $origSlug, $origStatus, $saveError)) {
            api_json_response(500, ['error' => $saveError ?? 'Unable to update page.']);
        }
        $saved = get_page_by_slug($updated['slug'], true) ?? $updated;
        api_json_response(200, ['page' => api_format_page($saved)]);
    }

    if ($method === 'DELETE') {
        if (!delete_page_by_slug($existing['slug'])) {
            api_json_response(500, ['error' => 'Unable to delete page.']);
        }
        api_json_response(200, ['ok' => true, 'deleted' => $existing['slug']]);
    }

    api_json_response(405, ['error' => 'Method not allowed.']);
}

// Images endpoint
if ($resource === 'images' && $targetSlug === null) {
    if ($method !== 'POST') {
        api_json_response(405, ['error' => 'Method not allowed.']);
    }

    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        api_json_response(413, ['error' => 'Uploaded payload exceeds post_max_size.']);
    }

    if (!isset($_FILES['image']) || is_array($_FILES['image']['name'])) {
        api_json_response(422, ['error' => 'A single file field named "image" is required.']);
    }

    $file = $_FILES['image'];
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        api_json_response(422, ['error' => 'File upload failed with error code ' . (int) $file['error'] . '.']);
    }

    $folder = trim((string) ($_POST['slug'] ?? 'library'));
    if ($folder === '') {
        $folder = 'library';
    }
    if (!is_safe_image_slug($folder)) {
        api_json_response(422, ['error' => 'Invalid image slug/folder name.']);
    }

    $baseDir = realpath(PUREBLOG_BASE_PATH . '/content/images');
    $uploadDir = PUREBLOG_BASE_PATH . '/content/images/' . $folder;
    if ($baseDir !== false && !is_file($baseDir . '/.htaccess')) {
        @file_put_contents($baseDir . '/.htaccess', "<FilesMatch \"\.ph(p[0-9]?|tml)$\">\n    Require all denied\n</FilesMatch>\n");
    }
    if ($baseDir === false) {
        api_json_response(500, ['error' => 'Image base directory does not exist.']);
    }
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        api_json_response(500, ['error' => 'Unable to create image directory.']);
    }
    if (!validate_image_path($baseDir, $uploadDir)) {
        @rmdir($uploadDir);
        api_json_response(422, ['error' => 'Invalid image directory path.']);
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file((string) $file['tmp_name']) ?: '';
    if (!isset($allowedTypes[$mimeType])) {
        api_json_response(422, ['error' => 'Unsupported image type.']);
    }

    $uploadedFilename = basename((string) $file['name']);
    $uploadedFilename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $uploadedFilename) ?? $uploadedFilename;
    $uploadedFilename = preg_replace('/-+/', '-', $uploadedFilename) ?? $uploadedFilename;
    $uploadedFilename = trim($uploadedFilename, '-');

    $baseName = str_replace('.', '-', pathinfo($uploadedFilename, PATHINFO_FILENAME));
    $filename = $baseName . '.' . $allowedTypes[$mimeType];
    if ($baseName === '') {
        api_json_response(422, ['error' => 'Invalid image filename.']);
    }

    $destination = $uploadDir . '/' . $filename;
    if (is_file($destination)) {
        api_json_response(409, ['error' => 'An image with that filename already exists in this folder.']);
    }
    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        api_json_response(500, ['error' => 'Unable to store uploaded image.']);
    }

    strip_image_metadata($destination, $mimeType);
    call_hook('on_image_uploaded', [$destination]);
    $webpDestination = preg_replace('/\.[^.]+$/', '.webp', $destination) ?? $destination;
    if ($webpDestination !== $destination && file_exists($webpDestination)) {
        $destination = $webpDestination;
    }
    $filename = basename($destination);
    generate_image_thumbnail($destination, $uploadDir . '/thumbs/' . $filename);

    $url = base_path() . '/content/images/' . $folder . '/' . $filename;
    $altText = pathinfo($filename, PATHINFO_FILENAME) ?: 'image';
    api_json_response(201, [
        'ok'       => true,
        'filename' => $filename,
        'folder'   => $folder,
        'url'      => $url,
        'markdown' => '![' . $altText . '](' . $url . ')',
    ]);
}

api_json_response(404, ['error' => 'Endpoint not found.']);
