<?php

declare(strict_types=1);

ob_start();

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

/**
 * Fetch latest GitHub release for pureblog.
 *
 * @return array{ok:bool, tag?:string, name?:string, url?:string, published_at?:string, error?:string}
 */
function fetch_latest_pureblog_release(): array
{
    $endpoint = 'https://api.github.com/repos/kevquirk/pureblog/releases/latest';
    $headers = [
        'User-Agent: Pureblog-Updates-Check',
        'Accept: application/vnd.github+json',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['ok' => false, 'error' => t('admin.settings.updates.error_curl_init')];
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            $message = $curlErr !== '' ? $curlErr : t('admin.settings.updates.error_github_request', ['status' => $status]);
            return ['ok' => false, 'error' => $message];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => implode("\r\n", $headers),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($endpoint, false, $context);
        if (!is_string($raw)) {
            return ['ok' => false, 'error' => t('admin.settings.updates.error_github_network')];
        }
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_github_json')];
    }

    return [
        'ok' => true,
        'tag' => (string) ($json['tag_name'] ?? ''),
        'name' => (string) ($json['name'] ?? ''),
        'url' => (string) ($json['html_url'] ?? 'https://github.com/kevquirk/pureblog/releases'),
        'zipball_url' => (string) ($json['zipball_url'] ?? ''),
        'published_at' => (string) ($json['published_at'] ?? ''),
    ];
}

/**
 * @return list<string>
 */
function preserved_top_level_paths(): array
{
    return [
        'backup',
        'cache',
        'config',
        'content',
        'data',
        '.htaccess',
        'VERSION',
    ];
}


function remove_directory_recursive(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path)) {
            @unlink($path);
        }
        return;
    }

    $items = scandir($path);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $itemPath = $path . '/' . $item;
        if (is_dir($itemPath)) {
            remove_directory_recursive($itemPath);
        } else {
            @unlink($itemPath);
        }
    }
    @rmdir($path);
}

function download_url_to_file(string $url, string $destination): ?string
{
    // Domain whitelist for security
    $allowedHosts = ['api.github.com', 'github.com', 'codeload.github.com'];
    $parsedUrl = parse_url($url);
    if (!isset($parsedUrl['host']) || !in_array($parsedUrl['host'], $allowedHosts, true)) {
        return 'Download URL not from allowed domain.';
    }

    $headers = [
        'User-Agent: Pureblog-Upgrader',
        'Accept: */*',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return t('admin.settings.updates.error_curl_init');
        }
        $fp = @fopen($destination, 'wb');
        if ($fp === false) {
            curl_close($ch);
            return t('admin.settings.updates.error_download_tmp');
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);
        fclose($fp);
        curl_close($ch);

        if ($ok !== true || $status < 200 || $status >= 300) {
            return $curlErr !== '' ? $curlErr : t('admin.settings.updates.error_download_failed', ['status' => $status]);
        }
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => implode("\r\n", $headers),
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw)) {
        return t('admin.settings.updates.error_download_network');
    }
    if (@file_put_contents($destination, $raw) === false) {
        return t('admin.settings.updates.error_download_write');
    }

    return null;
}

/**
 * @return list<string>
 */
function collect_relative_files(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $fullPath = str_replace('\\', '/', $item->getPathname());
        $prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
        if (!str_starts_with($fullPath, $prefix)) {
            continue;
        }
        $relative = substr($fullPath, strlen($prefix));
        if ($relative === '' || str_starts_with($relative, '.git/')) {
            continue;
        }
        $files[] = $relative;
    }

    sort($files);
    return $files;
}

function is_htaccess_path(string $relativePath): bool
{
    return basename(str_replace('\\', '/', $relativePath)) === '.htaccess';
}

/**
 * Capture all existing .htaccess files so they can be restored after update.
 *
 * @return array<string,string> Map of relative path => file contents
 */
function collect_existing_htaccess_files(): array
{
    $files = [];
    $all = collect_relative_files(PUREBLOG_BASE_PATH);
    foreach ($all as $relative) {
        if (!is_htaccess_path($relative)) {
            continue;
        }
        $fullPath = PUREBLOG_BASE_PATH . '/' . $relative;
        $content = @file_get_contents($fullPath);
        if (!is_string($content)) {
            continue;
        }
        $files[$relative] = $content;
    }

    return $files;
}

/**
 * @param array<string,string> $files
 */
function restore_htaccess_files(array $files): void
{
    foreach ($files as $relative => $content) {
        $target = PUREBLOG_BASE_PATH . '/' . $relative;
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException(t('admin.settings.updates.error_htaccess_dir', ['path' => $relative]));
        }
        if (@file_put_contents($target, $content) === false) {
            throw new RuntimeException(t('admin.settings.updates.error_htaccess_restore', ['path' => $relative]));
        }
    }
}

/**
 * Remove any .htaccess files that were not present before update.
 *
 * @param array<string,string> $preservedFiles
 */
function remove_non_preserved_htaccess(array $preservedFiles): void
{
    $preservedSet = array_fill_keys(array_keys($preservedFiles), true);
    $preserveTop = array_fill_keys(preserved_top_level_paths(), true);
    $all = collect_relative_files(PUREBLOG_BASE_PATH);
    foreach ($all as $relative) {
        if (!is_htaccess_path($relative)) {
            continue;
        }
        if (isset($preservedSet[$relative])) {
            continue;
        }
        $top = (string) strtok($relative, '/');
        // Never delete .htaccess inside preserved paths or the backup directory.
        if (isset($preserveTop[$top]) || $top === 'backup') {
            continue;
        }
        @unlink(PUREBLOG_BASE_PATH . '/' . $relative);
    }
}

/**
 * Build a file-level, read-only plan from a downloaded release package.
 *
 * @return array{ok:bool,error?:string,counts?:array<string,int>,will_add?:list<string>,will_replace?:list<string>,unchanged?:list<string>,will_skip?:list<string>,local_only?:list<string>}
 */
function build_package_upgrade_plan(string $zipballUrl): array
{
    if ($zipballUrl === '') {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_zip_url')];
    }
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_ziparchive')];
    }

    $tmpBase = rtrim(sys_get_temp_dir(), '/') . '/pureblog-upgrader-' . bin2hex(random_bytes(6));
    $tmpZip = $tmpBase . '.zip';
    $tmpExtract = $tmpBase . '-extract';
    @mkdir($tmpExtract, 0700, true);

    try {
        $downloadError = download_url_to_file($zipballUrl, $tmpZip);
        if ($downloadError !== null) {
            return ['ok' => false, 'error' => $downloadError];
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            return ['ok' => false, 'error' => t('admin.settings.updates.error_zip_open')];
        }
        if (!$zip->extractTo($tmpExtract)) {
            $zip->close();
            return ['ok' => false, 'error' => t('admin.settings.updates.error_zip_extract')];
        }
        $zip->close();

        $entries = array_values(array_filter(scandir($tmpExtract) ?: [], fn(string $e): bool => $e !== '.' && $e !== '..'));
        $sourceRoot = $tmpExtract;
        if (count($entries) === 1 && is_dir($tmpExtract . '/' . $entries[0])) {
            $sourceRoot = $tmpExtract . '/' . $entries[0];
        }

        $sourceFiles = collect_relative_files($sourceRoot);
        if (!$sourceFiles) {
            return ['ok' => false, 'error' => t('admin.settings.updates.error_zip_empty')];
        }

        $preserveTop = preserved_top_level_paths();
        $willAdd = [];
        $willReplace = [];
        $unchanged = [];
        $willSkip = [];
        $sourceCoreSet = [];

        foreach ($sourceFiles as $relative) {
            if (is_htaccess_path($relative)) {
                $willSkip[] = '/' . $relative;
                continue;
            }
            $top = strtok($relative, '/');
            if (in_array($top, $preserveTop, true)) {
                $willSkip[] = '/' . $relative;
                continue;
            }

            $sourceCoreSet[$relative] = true;
            $sourcePath = $sourceRoot . '/' . $relative;
            $targetPath = PUREBLOG_BASE_PATH . '/' . $relative;

            if (is_file($targetPath)) {
                $same = @sha1_file($sourcePath) === @sha1_file($targetPath);
                if ($same) {
                    $unchanged[] = '/' . $relative;
                } else {
                    $willReplace[] = '/' . $relative;
                }
            } else {
                $willAdd[] = '/' . $relative;
            }
        }

        // Build the set of top-level paths the update will actually replace.
        // The apply step only removes/replaces top-level items that exist in the release,
        // so files in directories not present in the release are never touched.
        $sourceTopItems = [];
        foreach (array_keys($sourceCoreSet) as $coreRelative) {
            $sourceTopItems[(string) strtok($coreRelative, '/')] = true;
        }

        $localOnly = [];
        $localFiles = collect_relative_files(PUREBLOG_BASE_PATH);
        foreach ($localFiles as $relative) {
            $top = (string) strtok($relative, '/');
            if (is_htaccess_path($relative)) {
                continue;
            }
            if (in_array($top, $preserveTop, true)) {
                continue;
            }
            // Only flag files that live inside a top-level directory the update will
            // delete and replace. Files in entirely separate directories are untouched.
            if (!isset($sourceCoreSet[$relative]) && isset($sourceTopItems[$top])) {
                $localOnly[] = '/' . $relative;
            }
        }

        sort($willAdd);
        sort($willReplace);
        sort($unchanged);
        sort($willSkip);
        sort($localOnly);

        return [
            'ok' => true,
            'counts' => [
                'add' => count($willAdd),
                'replace' => count($willReplace),
                'unchanged' => count($unchanged),
                'skip' => count($willSkip),
                'local_only' => count($localOnly),
            ],
            'will_add' => $willAdd,
            'will_replace' => $willReplace,
            'unchanged' => $unchanged,
            'will_skip' => $willSkip,
            'local_only' => $localOnly,
        ];
    } finally {
        @unlink($tmpZip);
        remove_directory_recursive($tmpExtract);
    }
}

function copy_path_recursive(string $source, string $destination): void
{
    if (is_file($source)) {
        $parent = dirname($destination);
        if (!is_dir($parent)) {
            error_clear_last();
            if (!@mkdir($parent, 0755, true) && !is_dir($parent)) {
                $phpError = error_get_last();
                $detail = $phpError !== null ? ' (' . $phpError['message'] . ')' : '';
                throw new RuntimeException(t('admin.settings.updates.error_dir_create', ['path' => $parent]) . $detail);
            }
        }
        error_clear_last();
        if (!@copy($source, $destination)) {
            $phpError = error_get_last();
            $detail = $phpError !== null ? ' (' . $phpError['message'] . ')' : '';
            throw new RuntimeException(t('admin.settings.updates.error_file_copy', ['path' => $source]) . $detail);
        }
        return;
    }

    if (!is_dir($source)) {
        throw new RuntimeException(t('admin.settings.updates.error_source_missing', ['path' => $source]));
    }

    if (!is_dir($destination)) {
        error_clear_last();
        if (!@mkdir($destination, 0755, true) && !is_dir($destination)) {
            $phpError = error_get_last();
            $detail = $phpError !== null ? ' (' . $phpError['message'] . ')' : '';
            throw new RuntimeException(t('admin.settings.updates.error_dir_create', ['path' => $destination]) . $detail);
        }
    }

    $items = scandir($source);
    if (!is_array($items)) {
        throw new RuntimeException(t('admin.settings.updates.error_dir_read', ['path' => $source]));
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $src = $source . '/' . $item;
        $dst = $destination . '/' . $item;
        if (is_dir($src)) {
            copy_path_recursive($src, $dst);
        } else {
            error_clear_last();
            if (!@copy($src, $dst)) {
                $phpError = error_get_last();
                $detail = $phpError !== null ? ' (' . $phpError['message'] . ')' : '';
                throw new RuntimeException(t('admin.settings.updates.error_file_copy', ['path' => $src]) . $detail);
            }
        }
    }
}

/**
 * @param list<string> $items Top-level item names (from the release zip) to back up.
 *                            Only backing up what the update will overwrite prevents
 *                            third-party directories (e.g. a FreshRSS install at /rss/)
 *                            from being included in the backup and subsequently deleted
 *                            during a rollback restore.
 */
function backup_core_paths(string $backupRoot, array $items): void
{
    foreach ($items as $item) {
        $src = PUREBLOG_BASE_PATH . '/' . $item;
        if (!file_exists($src)) {
            continue;
        }
        copy_path_recursive($src, $backupRoot . '/' . $item);
    }
}

function restore_core_paths_from_backup(string $backupRoot): void
{
    $items = scandir($backupRoot) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $target = PUREBLOG_BASE_PATH . '/' . $item;
        if (file_exists($target)) {
            remove_directory_recursive($target);
        }
        $backup = $backupRoot . '/' . $item;
        if (file_exists($backup)) {
            copy_path_recursive($backup, $target);
        }
    }
}

/**
 * @return list<string>
 */
function list_available_backups(): array
{
    $backupBase = PUREBLOG_BASE_PATH . '/backup';
    if (!is_dir($backupBase)) {
        return [];
    }

    $entries = scandir($backupBase);
    if (!is_array($entries)) {
        return [];
    }

    $backups = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!str_starts_with($entry, 'pureblog-backup-')) {
            continue;
        }
        $fullPath = $backupBase . '/' . $entry;
        if (is_dir($fullPath)) {
            $backups[] = $entry;
        }
    }

    rsort($backups);
    return $backups;
}

function format_backup_timestamp(string $backupName): string
{
    if (preg_match('/^pureblog-backup-(\d{8})-(\d{6})-/', $backupName, $matches) !== 1) {
        return '';
    }

    $dt = DateTime::createFromFormat('Ymd His', $matches[1] . ' ' . $matches[2]);
    if (!$dt) {
        return '';
    }

    return $dt->format('d M Y H:i:s');
}

function restore_named_backup(string $backupName): array
{
    if ($backupName === '' || $backupName !== basename($backupName)) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_backup_invalid_name')];
    }

    $backupBase = PUREBLOG_BASE_PATH . '/backup';
    $backupBaseReal = realpath($backupBase);
    if ($backupBaseReal === false) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_backup_dir_missing')];
    }

    $backupPath = $backupBaseReal . '/' . $backupName;
    $backupPathReal = realpath($backupPath);
    if ($backupPathReal === false || !is_dir($backupPathReal)) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_backup_not_found')];
    }
    if (!str_starts_with($backupPathReal, $backupBaseReal . '/')) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_backup_invalid_path')];
    }

    try {
        restore_core_paths_from_backup($backupPathReal);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_restore_failed', ['error' => $e->getMessage()])];
    }

    return [
        'ok' => true,
        'message' => t('admin.settings.updates.notice_backup_restored'),
        'backup_path' => $backupPathReal,
    ];
}

function delete_named_backup(string $backupName): array
{
    if ($backupName === '' || $backupName !== basename($backupName)) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_backup_invalid_name')];
    }

    $backupBase = PUREBLOG_BASE_PATH . '/backup';
    $backupBaseReal = realpath($backupBase);
    if ($backupBaseReal === false) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_backup_dir_missing')];
    }

    $backupPath = $backupBaseReal . '/' . $backupName;
    $backupPathReal = realpath($backupPath);
    if ($backupPathReal === false || !is_dir($backupPathReal)) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_backup_not_found')];
    }
    if (!str_starts_with($backupPathReal, $backupBaseReal . '/')) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_backup_invalid_path')];
    }

    try {
        remove_directory_recursive($backupPathReal);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_delete_backup_failed', ['error' => $e->getMessage()])];
    }

    return [
        'ok' => true,
        'message' => t('admin.settings.updates.notice_backup_deleted'),
    ];
}

function apply_release_update(string $zipballUrl, string $releaseTag = ''): array
{
    if ($zipballUrl === '') {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_zip_url')];
    }
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_ziparchive')];
    }

    $tmpBase = rtrim(sys_get_temp_dir(), '/') . '/pureblog-upgrader-' . bin2hex(random_bytes(6));
    $tmpZip = $tmpBase . '.zip';
    $tmpExtract = $tmpBase . '-extract';
    $backupBase = PUREBLOG_BASE_PATH . '/backup';
    if (!is_dir($backupBase) && !@mkdir($backupBase, 0755, true) && !is_dir($backupBase)) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_backup_dir')];
    }
    $versionRaw = detect_current_pureblog_version();
    $versionSlug = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $versionRaw);
    if (!is_string($versionSlug) || $versionSlug === '') {
        $versionSlug = 'unknown';
    }
    $tmpBackup = $backupBase . '/pureblog-backup-' . date('Ymd-His') . '-' . $versionSlug . '-' . bin2hex(random_bytes(4));
    @mkdir($tmpExtract, 0700, true);
    @mkdir($tmpBackup, 0700, true);

    try {
        $downloadError = download_url_to_file($zipballUrl, $tmpZip);
        if ($downloadError !== null) {
            return ['ok' => false, 'error' => $downloadError];
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            return ['ok' => false, 'error' => t('admin.settings.updates.error_zip_open')];
        }
        if (!$zip->extractTo($tmpExtract)) {
            $zip->close();
            return ['ok' => false, 'error' => t('admin.settings.updates.error_zip_extract')];
        }
        $zip->close();

        $entries = array_values(array_filter(scandir($tmpExtract) ?: [], fn(string $e): bool => $e !== '.' && $e !== '..'));
        $sourceRoot = $tmpExtract;
        if (count($entries) === 1 && is_dir($tmpExtract . '/' . $entries[0])) {
            $sourceRoot = $tmpExtract . '/' . $entries[0];
        }

        // Sanity check for expected project markers.
        if (!is_file($sourceRoot . '/functions.php') || !is_dir($sourceRoot . '/admin')) {
            return ['ok' => false, 'error' => t('admin.settings.updates.error_package_invalid')];
        }

        // Determine which top-level items the release zip will overwrite.
        // This list drives both the backup (so only PB-owned paths are saved)
        // and the copy loop below, avoiding any contact with third-party
        // directories that happen to share the webroot.
        $preserveTop = preserved_top_level_paths();
        $coreItems = array_values(array_filter(
            scandir($sourceRoot) ?: [],
            fn(string $item): bool =>
                $item !== '.' && $item !== '..' &&
                !is_htaccess_path($item) &&
                !in_array($item, $preserveTop, true)
        ));

        $preservedHtaccessFiles = collect_existing_htaccess_files();
        backup_core_paths($tmpBackup, $coreItems);

        foreach ($coreItems as $item) {
            $source = $sourceRoot . '/' . $item;
            $target = PUREBLOG_BASE_PATH . '/' . $item;

            if (file_exists($target)) {
                remove_directory_recursive($target);
            }
            copy_path_recursive($source, $target);
        }

        // Set /VERSION from the release tag (zipballs may omit /VERSION).
        $versionFile = PUREBLOG_BASE_PATH . '/VERSION';
        $versionFromTag = normalize_version_label($releaseTag);
        if ($versionFromTag !== 'unknown') {
            @file_put_contents($versionFile, $versionFromTag . PHP_EOL);
        }

        restore_htaccess_files($preservedHtaccessFiles);
        remove_non_preserved_htaccess($preservedHtaccessFiles);

        // Flush the opcode cache so the next request immediately picks up
        // the newly written files rather than the pre-update cached bytecode.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return [
            'ok' => true,
            'message' => t('admin.settings.updates.notice_update_applied'),
            'backup_path' => $tmpBackup,
        ];
    } catch (Throwable $e) {
        try {
            if (is_dir($tmpBackup)) {
                restore_core_paths_from_backup($tmpBackup);
            }
        } catch (Throwable $restoreError) {
            return [
                'ok' => false,
                'error' => t('admin.settings.updates.error_update_rollback_fail', ['error' => $restoreError->getMessage()]),
            ];
        }
        return [
            'ok' => false,
            'error' => t('admin.settings.updates.error_update_rolled_back', ['error' => $e->getMessage()]),
        ];
    } finally {
        @unlink($tmpZip);
        remove_directory_recursive($tmpExtract);
    }
}


// ── Lang repair ──────────────────────────────────────────────────────────────
// Handles installs where lang/ was missed during an update from a pre-denylist
// version of the updater.
if (isset($_GET['repair_lang'])) {
    $repairResult = repair_missing_lang();
    if ($repairResult['ok']) {
        $_SESSION['admin_action_flash'] = ['ok' => true, 'message' => t('admin.settings.updates.notice_lang_restored')];
    } else {
        $_SESSION['admin_action_flash'] = ['ok' => false, 'message' => t('admin.settings.updates.error_lang_repair', ['error' => ($repairResult['error'] ?? '')])];
    }
    header('Location: ' . base_path() . '/admin/settings-updates.php');
    exit;
}

function repair_missing_lang(): array
{
    $currentVersion = detect_current_pureblog_version();
    $tag = 'v' . ltrim($currentVersion, 'v');
    $endpoint = 'https://api.github.com/repos/kevquirk/pureblog/releases/tags/' . urlencode($tag);
    $headers = ['User-Agent: Pureblog-Updates-Check', 'Accept: application/vnd.github+json'];

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 5, 'header' => implode("\r\n", $headers)]]);
        $raw = @file_get_contents($endpoint, false, $ctx);
    }

    if (!isset($raw) || !is_string($raw)) {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_release_metadata')];
    }
    $json = json_decode($raw, true);
    $zipballUrl = is_array($json) ? (string) ($json['zipball_url'] ?? '') : '';
    if ($zipballUrl === '') {
        return ['ok' => false, 'error' => t('admin.settings.updates.error_release_metadata')];
    }

    $tmpBase    = sys_get_temp_dir() . '/pureblog-lang-repair-' . bin2hex(random_bytes(6));
    $tmpZip     = $tmpBase . '.zip';
    $tmpExtract = $tmpBase . '-extract';

    try {
        $err = download_url_to_file($zipballUrl, $tmpZip);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            return ['ok' => false, 'error' => t('admin.settings.updates.error_lang_zip_open')];
        }
        if (!$zip->extractTo($tmpExtract)) {
            $zip->close();
            return ['ok' => false, 'error' => t('admin.settings.updates.error_lang_zip_extract')];
        }
        $zip->close();

        $sourceRoot = $tmpExtract;
        $entries = array_values(array_filter(scandir($tmpExtract) ?: [], fn($e) => $e !== '.' && $e !== '..'));
        if (count($entries) === 1 && is_dir($tmpExtract . '/' . $entries[0])) {
            $sourceRoot = $tmpExtract . '/' . $entries[0];
        }

        $langSource = $sourceRoot . '/lang';
        if (!is_dir($langSource)) {
            return ['ok' => false, 'error' => t('admin.settings.updates.error_lang_dir_missing')];
        }

        copy_path_recursive($langSource, PUREBLOG_BASE_PATH . '/lang');
        return ['ok' => true];
    } finally {
        @unlink($tmpZip);
        remove_directory_recursive($tmpExtract);
    }
}

$latest = null;
if (isset($_GET['check'])) {
    $latest = fetch_latest_pureblog_release();
}
$currentVersionDisplay = detect_current_pureblog_version();
$packagePlan = null;
$packagePlanError = '';
if (isset($_GET['package_plan'])) {
    $latestForPackage = fetch_latest_pureblog_release();
    if (!($latestForPackage['ok'] ?? false)) {
        $packagePlanError = (string) ($latestForPackage['error'] ?? t('admin.settings.updates.error_release_metadata'));
    } else {
        $latestTag = (string) ($latestForPackage['tag'] ?? '');
        $currentVersion = detect_current_pureblog_version();

        if ($latestTag !== '' && versions_match($currentVersion, $latestTag)) {
            $packagePlan = [
                'ok' => true,
                'already_latest' => true,
                'message' => t('admin.settings.updates.already_latest_version', ['tag' => $latestTag]),
            ];
        } else {
            $packagePlan = build_package_upgrade_plan((string) ($latestForPackage['zipball_url'] ?? ''));
            if (!($packagePlan['ok'] ?? false)) {
                $packagePlanError = (string) ($packagePlan['error'] ?? t('admin.settings.updates.error_build_plan'));
            }
        }
    }
}
$applyResult = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !isset($_POST['admin_action_id'])) {
    verify_csrf();
    if (isset($_POST['apply_update'])) {
        $latestForApply = fetch_latest_pureblog_release();
        if (!($latestForApply['ok'] ?? false)) {
            $applyResult = [
                'ok' => false,
                'error' => (string) ($latestForApply['error'] ?? t('admin.settings.updates.error_release_metadata')),
            ];
        } else {
            $applyResult = apply_release_update(
                (string) ($latestForApply['zipball_url'] ?? ''),
                (string) ($latestForApply['tag'] ?? '')
            );
            if (($applyResult['ok'] ?? false) && !empty($latestForApply['tag'])) {
                $currentVersionDisplay = normalize_version_label((string) $latestForApply['tag']);
                // Redirect after a successful update so the result page loads
                // with the newly written files rather than the in-memory copies
                // from before the update ran.
                $_SESSION['admin_action_flash'] = ['ok' => true, 'message' => t('admin.settings.updates.notice_update_applied')];
                header('Location: ' . base_path() . '/admin/settings-updates.php?updated=1');
                exit;
            }
        }
    } elseif (isset($_POST['restore_backup'])) {
        $backupName = trim((string) ($_POST['backup_name'] ?? ''));
        $applyResult = restore_named_backup($backupName);
        if (!($applyResult['ok'] ?? false) && $backupName === '') {
            $applyResult = ['ok' => false, 'error' => t('admin.settings.updates.error_choose_restore')];
        }
    } elseif (isset($_POST['delete_backup'])) {
        $backupName = trim((string) ($_POST['backup_name'] ?? ''));
        $applyResult = delete_named_backup($backupName);
        if (!($applyResult['ok'] ?? false) && $backupName === '') {
            $applyResult = ['ok' => false, 'error' => t('admin.settings.updates.error_choose_delete')];
        }
    }
}

$availableBackups = list_available_backups();
$latestBackup = $availableBackups[0] ?? '';
$latestBackupTimestamp = $latestBackup !== '' ? format_backup_timestamp($latestBackup) : '';

if (isset($_GET['package_plan']) && $packagePlan === null && $packagePlanError === '') {
    $packagePlanError = t('admin.settings.updates.error_inspect');
}

$adminTitle = t('admin.settings.updates.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1><?= e(t('admin.settings.updates.heading')) ?></h1>

        <?php $settingsSaveFormId = ''; ?>
        <nav class="editor-actions settings-actions">
            <?php require __DIR__ . '/../includes/admin-settings-nav.php'; ?>
        </nav>

        <section class="section-divider">
            <span class="title"><?= e(t('admin.settings.updates.section_version')) ?></span>
            <p><strong><?= e(t('admin.settings.updates.current_version')) ?></strong> <?= e($currentVersionDisplay) ?></p>
            <?php if ($latestBackup !== ''): ?>
                <p><strong><?= e(t('admin.settings.updates.last_backup')) ?></strong>
                    <?php if ($latestBackupTimestamp !== ''): ?>
                        <?= e($latestBackupTimestamp) ?>
                    <?php else: ?>
                        <?= e(t('admin.settings.updates.unknown_time')) ?>
                    <?php endif; ?>
                    (<code><?= e($latestBackup) ?></code>)
                </p>
            <?php endif; ?>
            <p><strong><?= e(t('admin.settings.updates.repository')) ?></strong> <a href="https://github.com/kevquirk/pureblog" target="_blank" rel="noopener noreferrer">github.com/kevquirk/pureblog</a></p>
            <p>
                <a class="button" href="<?= base_path() ?>/admin/settings-updates.php?check=1">
                    <svg class="icon" aria-hidden="true"><use href="#icon-upgrade"></use></svg>
                    <?= e(t('admin.settings.updates.check_release')) ?>
                </a>
                <a class="button" href="<?= base_path() ?>/admin/settings-updates.php?package_plan=1">
                    <svg class="icon" aria-hidden="true"><use href="#icon-eye"></use></svg>
                    <?= e(t('admin.settings.updates.inspect_package')) ?>
                </a>
            </p>

            <?php if ($latest !== null && !($latest['ok'] ?? false)): ?>
                <p class="notice delete"><?= e($latest['error'] ?? t('admin.settings.updates.error_check')) ?></p>
            <?php endif; ?>

            <?php if ($latest !== null && ($latest['ok'] ?? false)): ?>
                <p><strong><?= e(t('admin.settings.updates.latest_release')) ?></strong> <?= e($latest['tag'] !== '' ? $latest['tag'] : ($latest['name'] ?? 'Unknown')) ?></p>
                <?php if (($latest['published_at'] ?? '') !== ''): ?>
                    <p><strong><?= e(t('admin.settings.updates.published')) ?></strong> <?= e(format_datetime_for_display((string) $latest['published_at'], $config, 'Y-m-d')) ?></p>
                <?php endif; ?>
                <p><a href="<?= e($latest['url'] ?? 'https://github.com/kevquirk/pureblog/releases') ?>" target="_blank" rel="noopener noreferrer"><?= e(t('admin.settings.updates.view_release_notes')) ?></a></p>
            <?php endif; ?>
        </section>

        <?php if ($packagePlanError !== ''): ?>
        <section class="section-divider">
            <span class="title"><?= e(t('admin.settings.updates.section_inspect')) ?></span>
            <p class="notice delete"><?= e($packagePlanError) ?></p>
        </section>
        <?php endif; ?>

        <?php if ($packagePlan !== null && ($packagePlan['ok'] ?? false)): ?>
        <section class="section-divider">
            <span class="title"><?= e(t('admin.settings.updates.section_inspect')) ?></span>
            <?php if (!empty($packagePlan['already_latest'])): ?>
                <p><?= e((string) ($packagePlan['message'] ?? t('admin.settings.updates.already_latest'))) ?></p>
            <?php else: ?>
            <p><strong><?= e(t('admin.settings.updates.planned_actions')) ?></strong></p>
            <ul>
                <li><strong><?= e(t('admin.settings.updates.action_add')) ?></strong> <?= e((string) ($packagePlan['counts']['add'] ?? 0)) ?></li>
                <li><strong><?= e(t('admin.settings.updates.action_replace')) ?></strong> <?= e((string) ($packagePlan['counts']['replace'] ?? 0)) ?></li>
                <li><strong><?= e(t('admin.settings.updates.action_unchanged')) ?></strong> <?= e((string) ($packagePlan['counts']['unchanged'] ?? 0)) ?></li>
                <li><strong><?= e(t('admin.settings.updates.action_preserved')) ?></strong> <?= e((string) ($packagePlan['counts']['skip'] ?? 0)) ?></li>
                <li><strong><?= e(t('admin.settings.updates.action_local_only')) ?></strong> <?= e((string) ($packagePlan['counts']['local_only'] ?? 0)) ?></li>
            </ul>

            <?php if (!empty($packagePlan['will_add'])): ?>
                <p><strong><?= e(t('admin.settings.updates.will_add')) ?></strong></p>
                <ul>
                    <?php foreach ($packagePlan['will_add'] as $path): ?>
                        <li><code><?= e($path) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($packagePlan['will_replace'])): ?>
                <p><strong><?= e(t('admin.settings.updates.will_replace')) ?></strong></p>
                <ul>
                    <?php foreach ($packagePlan['will_replace'] as $path): ?>
                        <li><code><?= e($path) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($packagePlan['local_only'])): ?>
                <p><strong><?= e(t('admin.settings.updates.will_delete')) ?></strong></p>
                <ul>
                    <?php foreach ($packagePlan['local_only'] as $path): ?>
                        <li><code><?= e($path) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" action="<?= base_path() ?>/admin/settings-updates.php" onsubmit="return confirm('<?= e(t('admin.settings.updates.apply_confirm')) ?>');">
                <?= csrf_field() ?>
                <button class="button save" type="submit" name="apply_update" value="1">
                    <svg class="icon" aria-hidden="true"><use href="#icon-upgrade"></use></svg>
                    <?= e(t('admin.settings.updates.apply_update')) ?>
                </button>
            </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (!empty($availableBackups)): ?>
        <section class="section-divider">
            <span class="title"><?= e(t('admin.settings.updates.section_backup')) ?></span>
            <p><?= e(t('admin.settings.updates.backup_info')) ?></p>
            <form method="post" action="<?= base_path() ?>/admin/settings-updates.php">
                <?= csrf_field() ?>
                <label for="backup-name"><?= e(t('admin.settings.updates.available_backups')) ?></label>
                <select id="backup-name" name="backup_name" required>
                    <?php foreach ($availableBackups as $backupName): ?>
                        <option value="<?= e($backupName) ?>"><?= e($backupName) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit" name="restore_backup" value="1" onclick="return confirm('<?= e(t('admin.settings.updates.restore_confirm')) ?>');">
                    <svg class="icon" aria-hidden="true"><use href="#icon-upgrade"></use></svg>
                    <?= e(t('admin.settings.updates.restore_backup')) ?>
                </button>
                <button class="button delete" type="submit" name="delete_backup" value="1" onclick="return confirm('<?= e(t('admin.settings.updates.delete_backup_confirm')) ?>');">
                    <svg class="icon" aria-hidden="true"><use href="#icon-circle-x"></use></svg>
                    <?= e(t('admin.settings.updates.delete_backup')) ?>
                </button>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($applyResult !== null): ?>
        <section class="section-divider">
            <span class="title"><?= e(t('admin.settings.updates.section_result')) ?></span>
            <?php if (!($applyResult['ok'] ?? false)): ?>
                <p class="notice delete"><?= e((string) ($applyResult['error'] ?? t('admin.settings.updates.update_failed'))) ?></p>
            <?php else: ?>
                <p><?= e((string) ($applyResult['message'] ?? t('admin.settings.updates.update_completed'))) ?></p>
                <?php if (!empty($applyResult['backup_path'])): ?>
                    <p><strong><?= e(t('admin.settings.updates.backup_path')) ?></strong> <code><?= e((string) $applyResult['backup_path']) ?></code></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php endif; ?>

    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
