<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Page cache
// ---------------------------------------------------------------------------

function cache_should_bypass(array $config): bool
{
    if (empty($config['cache']['enabled'])) {
        return true;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return true;
    }
    if (isset($_GET['q'])) {
        return true;
    }
    if (!empty($_COOKIE[session_name()])) {
        start_admin_session();
        if (is_admin_logged_in()) {
            return true;
        }
    }
    return false;
}

function get_cache_file_path(string $key, string $ext = 'html'): string
{
    return PUREBLOG_CACHE_PATH . '/' . md5($key) . '.' . $ext;
}

function cache_read(string $key, int $ttl = 0, string $ext = 'html'): ?string
{
    $path = get_cache_file_path($key, $ext);
    if (!is_file($path)) {
        return null;
    }
    if ($ttl > 0 && (time() - filemtime($path)) > $ttl) {
        @unlink($path);
        return null;
    }
    $content = file_get_contents($path);
    return $content !== false ? $content : null;
}

function cache_write(string $key, string $content, string $ext = 'html'): void
{
    if (!is_dir(PUREBLOG_CACHE_PATH)) {
        mkdir(PUREBLOG_CACHE_PATH, 0755, true);
    }
    $timestamp = gmdate('Y-m-d H:i:s') . ' UTC';
    if ($ext === 'xml') {
        $content = str_replace('</rss>', '<!-- Cached at ' . $timestamp . " -->\n</rss>", $content);
    } else {
        $content .= "\n<!-- Cached at " . $timestamp . ' -->';
    }
    file_put_contents(get_cache_file_path($key, $ext), $content, LOCK_EX);
}

function cache_clear(): void
{
    if (!is_dir(PUREBLOG_CACHE_PATH)) {
        return;
    }
    $files = glob(PUREBLOG_CACHE_PATH . '/*') ?: [];
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

// ---------------------------------------------------------------------------
// Version helpers
// ---------------------------------------------------------------------------

function detect_current_pureblog_version(): string
{
    $versionFile = PUREBLOG_BASE_PATH . '/VERSION';
    if (is_file($versionFile)) {
        $raw = @file_get_contents($versionFile);
        if (is_string($raw)) {
            $fromFile = trim($raw);
            if ($fromFile !== '') {
                return $fromFile;
            }
        }
    }

    if (defined('PUREBLOG_VERSION') && is_string(PUREBLOG_VERSION) && PUREBLOG_VERSION !== '' && strtolower(PUREBLOG_VERSION) !== 'unknown') {
        return PUREBLOG_VERSION;
    }

    return 'unknown';
}

function normalize_version_label(string $version): string
{
    $trimmed = trim($version);
    if ($trimmed === '') {
        return 'unknown';
    }

    return ltrim($trimmed, "vV");
}

function versions_match(string $current, string $latest): bool
{
    $a = strtolower(trim($current));
    $b = strtolower(trim($latest));

    if ($a === '' || $b === '') {
        return false;
    }

    $a = ltrim($a, 'v');
    $b = ltrim($b, 'v');

    return $a === $b;
}

// ---------------------------------------------------------------------------
// HTTP helpers
// ---------------------------------------------------------------------------

function pureblog_http_get(string $url, int $timeout = 5, array $headers = []): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init failed'];
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => $err !== '' ? $err : "HTTP {$status}"];
        }
        return ['ok' => true, 'body' => $raw];
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'GET',
        'timeout'       => $timeout,
        'header'        => implode("\r\n", $headers),
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw)) {
        return ['ok' => false, 'error' => 'Network request failed'];
    }
    return ['ok' => true, 'body' => $raw];
}

function pureblog_http_download(string $url, string $destination, int $timeout = 20, array $headers = []): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return 'curl_init failed';
        }
        $fp = @fopen($destination, 'wb');
        if ($fp === false) {
            curl_close($ch);
            return 'Could not open destination file for writing';
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $ok     = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        fclose($fp);
        curl_close($ch);

        if ($ok !== true || $status < 200 || $status >= 300) {
            return $err !== '' ? $err : "HTTP {$status}";
        }
        return null;
    }

    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'timeout' => $timeout,
        'header'  => implode("\r\n", $headers),
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw)) {
        return 'Network request failed';
    }
    if (@file_put_contents($destination, $raw) === false) {
        return 'Could not write to destination file';
    }
    return null;
}

function fetch_latest_pureblog_release(): array
{
    $result = pureblog_http_get(
        'https://codeberg.org/api/v1/repos/kevquirk/pureblog/releases/latest',
        5,
        ['User-Agent: Pureblog-Updates-Check', 'Accept: application/json']
    );
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error']];
    }
    $json = json_decode($result['body'], true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_github_json')];
    }
    return [
        'ok'           => true,
        'tag'          => (string) ($json['tag_name'] ?? ''),
        'name'         => (string) ($json['name'] ?? ''),
        'url'          => (string) ($json['html_url'] ?? 'https://codeberg.org/kevquirk/pureblog/releases'),
        'zipball_url'  => (string) ($json['zipball_url'] ?? ''),
        'published_at' => (string) ($json['published_at'] ?? ''),
    ];
}
