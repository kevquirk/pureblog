<?php

declare(strict_types=1);

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
            return ['ok' => false, 'error' => 'Unable to initialize curl.'];
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            $message = $curlErr !== '' ? $curlErr : ('GitHub request failed (HTTP ' . $status . ').');
            return ['ok' => false, 'error' => $message];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $raw = @file_get_contents($endpoint, false, $context);
        if (!is_string($raw)) {
            return ['ok' => false, 'error' => 'GitHub check failed (network unavailable or allow_url_fopen disabled).'];
        }
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'GitHub returned invalid JSON.'];
    }

    return [
        'ok' => true,
        'tag' => (string) ($json['tag_name'] ?? ''),
        'name' => (string) ($json['name'] ?? ''),
        'url' => (string) ($json['html_url'] ?? 'https://github.com/kevquirk/pureblog/releases'),
        'zipball_url' => (string) ($json['zipball_url'] ?? ''),
        'tarball_url' => (string) ($json['tarball_url'] ?? ''),
        'published_at' => (string) ($json['published_at'] ?? ''),
    ];
}

function detect_current_pureblog_version(): string
{
    $versionFile = dirname(__DIR__) . '/VERSION';
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

    if (function_exists('detect_pureblog_version')) {
        $detected = (string) detect_pureblog_version();
        if ($detected !== '' && strtolower($detected) !== 'unknown') {
            return $detected;
        }
    }

    if (defined('PUREBLOG_VERSION') && is_string(PUREBLOG_VERSION) && PUREBLOG_VERSION !== '') {
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

    // Treat "v1.4.0" and "1.4.0" as equivalent.
    $a = ltrim($a, 'v');
    $b = ltrim($b, 'v');

    return $a === $b;
}

/**
 * @return list<string>
 */
function preserved_top_level_paths(): array
{
    return [
        'config',
        'content',
        'data',
        '.htaccess',
        'VERSION',
    ];
}

/**
 * @return list<string>
 */
function core_top_level_paths(): array
{
    return [
        '404.php',
        '.htaccess',
        'VERSION',
        'admin',
        'assets',
        'feed.php',
        'functions.php',
        'includes',
        'index.php',
        'lib',
        'page.php',
        'post.php',
        'search',
        'search.php',
        'setup.php',
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
    $headers = [
        'User-Agent: Pureblog-Upgrader',
        'Accept: */*',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return 'Unable to initialize curl.';
        }
        $fp = @fopen($destination, 'wb');
        if ($fp === false) {
            curl_close($ch);
            return 'Unable to create temporary download file.';
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);
        fclose($fp);
        curl_close($ch);

        if ($ok !== true || $status < 200 || $status >= 300) {
            return $curlErr !== '' ? $curlErr : ('Download failed (HTTP ' . $status . ').');
        }
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => implode("\r\n", $headers),
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw)) {
        return 'Download failed (network unavailable or allow_url_fopen disabled).';
    }
    if (@file_put_contents($destination, $raw) === false) {
        return 'Unable to write temporary download file.';
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
            throw new RuntimeException('Unable to create directory for preserved .htaccess: ' . $relative);
        }
        if (@file_put_contents($target, $content) === false) {
            throw new RuntimeException('Unable to restore preserved .htaccess: ' . $relative);
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
    $all = collect_relative_files(PUREBLOG_BASE_PATH);
    foreach ($all as $relative) {
        if (!is_htaccess_path($relative)) {
            continue;
        }
        if (isset($preservedSet[$relative])) {
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
        return ['ok' => false, 'error' => 'No zipball URL found for this release.'];
    }
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'ZipArchive extension is not available on this host.'];
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
            return ['ok' => false, 'error' => 'Unable to open downloaded release zip.'];
        }
        if (!$zip->extractTo($tmpExtract)) {
            $zip->close();
            return ['ok' => false, 'error' => 'Unable to extract release zip.'];
        }
        $zip->close();

        $entries = array_values(array_filter(scandir($tmpExtract) ?: [], fn(string $e): bool => $e !== '.' && $e !== '..'));
        $sourceRoot = $tmpExtract;
        if (count($entries) === 1 && is_dir($tmpExtract . '/' . $entries[0])) {
            $sourceRoot = $tmpExtract . '/' . $entries[0];
        }

        $sourceFiles = collect_relative_files($sourceRoot);
        if (!$sourceFiles) {
            return ['ok' => false, 'error' => 'Release archive did not contain readable files.'];
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

        $localOnly = [];
        $localCoreTop = array_fill_keys(core_top_level_paths(), true);
        $localFiles = collect_relative_files(PUREBLOG_BASE_PATH);
        foreach ($localFiles as $relative) {
            $top = strtok($relative, '/');
            if (!isset($localCoreTop[$top])) {
                continue;
            }
            if (is_htaccess_path($relative)) {
                continue;
            }
            if (in_array($top, $preserveTop, true)) {
                continue;
            }
            if (!isset($sourceCoreSet[$relative])) {
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
        if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new RuntimeException('Unable to create directory: ' . $parent);
        }
        if (!@copy($source, $destination)) {
            throw new RuntimeException('Unable to copy file: ' . $source);
        }
        return;
    }

    if (!is_dir($source)) {
        throw new RuntimeException('Source path not found: ' . $source);
    }

    if (!is_dir($destination) && !@mkdir($destination, 0755, true) && !is_dir($destination)) {
        throw new RuntimeException('Unable to create directory: ' . $destination);
    }

    $items = scandir($source);
    if (!is_array($items)) {
        throw new RuntimeException('Unable to read directory: ' . $source);
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
            if (!@copy($src, $dst)) {
                throw new RuntimeException('Unable to copy file: ' . $src);
            }
        }
    }
}

function backup_core_paths(string $backupRoot): void
{
    $corePaths = core_top_level_paths();
    foreach ($corePaths as $relative) {
        $src = PUREBLOG_BASE_PATH . '/' . $relative;
        if (!file_exists($src)) {
            continue;
        }
        $dst = $backupRoot . '/' . $relative;
        copy_path_recursive($src, $dst);
    }
}

function restore_core_paths_from_backup(string $backupRoot): void
{
    $corePaths = core_top_level_paths();
    foreach ($corePaths as $relative) {
        $target = PUREBLOG_BASE_PATH . '/' . $relative;
        if (file_exists($target)) {
            remove_directory_recursive($target);
        }
        $backup = $backupRoot . '/' . $relative;
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
        return ['ok' => false, 'error' => 'Invalid backup name.'];
    }

    $backupBase = PUREBLOG_BASE_PATH . '/backup';
    $backupBaseReal = realpath($backupBase);
    if ($backupBaseReal === false) {
        return ['ok' => false, 'error' => 'Backup directory does not exist.'];
    }

    $backupPath = $backupBaseReal . '/' . $backupName;
    $backupPathReal = realpath($backupPath);
    if ($backupPathReal === false || !is_dir($backupPathReal)) {
        return ['ok' => false, 'error' => 'Selected backup was not found.'];
    }
    if (!str_starts_with($backupPathReal, $backupBaseReal . '/')) {
        return ['ok' => false, 'error' => 'Invalid backup path.'];
    }

    try {
        restore_core_paths_from_backup($backupPathReal);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Restore failed: ' . $e->getMessage()];
    }

    return [
        'ok' => true,
        'message' => 'Backup restored successfully.',
        'backup_path' => $backupPathReal,
    ];
}

function delete_named_backup(string $backupName): array
{
    if ($backupName === '' || $backupName !== basename($backupName)) {
        return ['ok' => false, 'error' => 'Invalid backup name.'];
    }

    $backupBase = PUREBLOG_BASE_PATH . '/backup';
    $backupBaseReal = realpath($backupBase);
    if ($backupBaseReal === false) {
        return ['ok' => false, 'error' => 'Backup directory does not exist.'];
    }

    $backupPath = $backupBaseReal . '/' . $backupName;
    $backupPathReal = realpath($backupPath);
    if ($backupPathReal === false || !is_dir($backupPathReal)) {
        return ['ok' => false, 'error' => 'Selected backup was not found.'];
    }
    if (!str_starts_with($backupPathReal, $backupBaseReal . '/')) {
        return ['ok' => false, 'error' => 'Invalid backup path.'];
    }

    try {
        remove_directory_recursive($backupPathReal);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Delete failed: ' . $e->getMessage()];
    }

    return [
        'ok' => true,
        'message' => 'Backup deleted successfully.',
    ];
}

function apply_release_update(string $zipballUrl, string $releaseTag = ''): array
{
    if ($zipballUrl === '') {
        return ['ok' => false, 'error' => 'No zipball URL found for this release.'];
    }
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'ZipArchive extension is not available on this host.'];
    }

    $tmpBase = rtrim(sys_get_temp_dir(), '/') . '/pureblog-upgrader-' . bin2hex(random_bytes(6));
    $tmpZip = $tmpBase . '.zip';
    $tmpExtract = $tmpBase . '-extract';
    $backupBase = PUREBLOG_BASE_PATH . '/backup';
    if (!is_dir($backupBase) && !@mkdir($backupBase, 0755, true) && !is_dir($backupBase)) {
        return ['ok' => false, 'error' => 'Unable to create local backup directory at /backup.'];
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
            return ['ok' => false, 'error' => 'Unable to open downloaded release zip.'];
        }
        if (!$zip->extractTo($tmpExtract)) {
            $zip->close();
            return ['ok' => false, 'error' => 'Unable to extract release zip.'];
        }
        $zip->close();

        $entries = array_values(array_filter(scandir($tmpExtract) ?: [], fn(string $e): bool => $e !== '.' && $e !== '..'));
        $sourceRoot = $tmpExtract;
        if (count($entries) === 1 && is_dir($tmpExtract . '/' . $entries[0])) {
            $sourceRoot = $tmpExtract . '/' . $entries[0];
        }

        // Sanity check for expected project markers.
        if (!is_file($sourceRoot . '/functions.php') || !is_dir($sourceRoot . '/admin')) {
            return ['ok' => false, 'error' => 'Release archive does not look like a valid Pure Blog package.'];
        }

        $preservedHtaccessFiles = collect_existing_htaccess_files();
        backup_core_paths($tmpBackup);

        $corePaths = core_top_level_paths();
        $preserveTop = preserved_top_level_paths();
        foreach ($corePaths as $relative) {
            if (in_array($relative, $preserveTop, true)) {
                continue;
            }
            $source = $sourceRoot . '/' . $relative;
            $target = PUREBLOG_BASE_PATH . '/' . $relative;

            if (file_exists($target)) {
                remove_directory_recursive($target);
            }

            if (file_exists($source)) {
                copy_path_recursive($source, $target);
            }
        }

        // Set /VERSION from the release tag (zipballs may omit /VERSION).
        $versionFile = PUREBLOG_BASE_PATH . '/VERSION';
        $versionFromTag = normalize_version_label($releaseTag);
        if ($versionFromTag !== 'unknown') {
            @file_put_contents($versionFile, $versionFromTag . PHP_EOL);
        }

        restore_htaccess_files($preservedHtaccessFiles);
        remove_non_preserved_htaccess($preservedHtaccessFiles);

        return [
            'ok' => true,
            'message' => 'Update applied successfully.',
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
                'error' => 'Update failed and rollback also failed: ' . $restoreError->getMessage(),
            ];
        }
        return [
            'ok' => false,
            'error' => 'Update failed and was rolled back: ' . $e->getMessage(),
        ];
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
        $packagePlanError = (string) ($latestForPackage['error'] ?? 'Unable to fetch latest release metadata.');
    } else {
        $latestTag = (string) ($latestForPackage['tag'] ?? '');
        $currentVersion = detect_current_pureblog_version();

        if ($latestTag !== '' && versions_match($currentVersion, $latestTag)) {
            $packagePlan = [
                'ok' => true,
                'already_latest' => true,
                'message' => 'You are already on the latest release (' . $latestTag . ').',
            ];
        } else {
            $packagePlan = build_package_upgrade_plan((string) ($latestForPackage['zipball_url'] ?? ''));
            if (!($packagePlan['ok'] ?? false)) {
                $packagePlanError = (string) ($packagePlan['error'] ?? 'Unable to build package plan.');
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
                'error' => (string) ($latestForApply['error'] ?? 'Unable to fetch latest release metadata.'),
            ];
        } else {
            $applyResult = apply_release_update(
                (string) ($latestForApply['zipball_url'] ?? ''),
                (string) ($latestForApply['tag'] ?? '')
            );
            if (($applyResult['ok'] ?? false) && !empty($latestForApply['tag'])) {
                $currentVersionDisplay = normalize_version_label((string) $latestForApply['tag']);
            }
        }
    } elseif (isset($_POST['restore_backup'])) {
        $backupName = trim((string) ($_POST['backup_name'] ?? ''));
        $applyResult = restore_named_backup($backupName);
        if (!($applyResult['ok'] ?? false) && $backupName === '') {
            $applyResult = ['ok' => false, 'error' => 'Please choose a backup to restore.'];
        }
    } elseif (isset($_POST['delete_backup'])) {
        $backupName = trim((string) ($_POST['backup_name'] ?? ''));
        $applyResult = delete_named_backup($backupName);
        if (!($applyResult['ok'] ?? false) && $backupName === '') {
            $applyResult = ['ok' => false, 'error' => 'Please choose a backup to delete.'];
        }
    }
}

$availableBackups = list_available_backups();
$latestBackup = $availableBackups[0] ?? '';
$latestBackupTimestamp = $latestBackup !== '' ? format_backup_timestamp($latestBackup) : '';

if (isset($_GET['package_plan']) && $packagePlan === null && $packagePlanError === '') {
    $packagePlanError = 'Unable to inspect release package.';
}

$adminTitle = 'Updates - Pureblog';
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">
        <h1>Updates</h1>

        <?php $settingsSaveFormId = ''; ?>
        <nav class="editor-actions settings-actions">
            <?php require __DIR__ . '/../includes/admin-settings-nav.php'; ?>
        </nav>

        <section class="section-divider">
            <span class="title">Version check</span>
            <p><strong>Current version:</strong> <?= e($currentVersionDisplay) ?></p>
            <?php if ($latestBackup !== ''): ?>
                <p><strong>Last backup:</strong>
                    <?php if ($latestBackupTimestamp !== ''): ?>
                        <?= e($latestBackupTimestamp) ?>
                    <?php else: ?>
                        Unknown time
                    <?php endif; ?>
                    (<code><?= e($latestBackup) ?></code>)
                </p>
            <?php endif; ?>
            <p><strong>Repository:</strong> <a href="https://github.com/kevquirk/pureblog" target="_blank" rel="noopener noreferrer">github.com/kevquirk/pureblog</a></p>
            <p>
                <a class="button" href="/admin/settings-updates.php?check=1">
                    <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-upgrade"></use></svg>
                    Check latest release
                </a>
                <a class="button" href="/admin/settings-updates.php?package_plan=1">
                    <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-eye"></use></svg>
                    Inspect release package
                </a>
            </p>

            <?php if ($latest !== null && !($latest['ok'] ?? false)): ?>
                <p class="notice"><?= e($latest['error'] ?? 'Unable to check for updates.') ?></p>
            <?php endif; ?>

            <?php if ($latest !== null && ($latest['ok'] ?? false)): ?>
                <p><strong>Latest release:</strong> <?= e($latest['tag'] !== '' ? $latest['tag'] : ($latest['name'] ?? 'Unknown')) ?></p>
                <?php if (($latest['published_at'] ?? '') !== ''): ?>
                    <p><strong>Published:</strong> <?= e(format_datetime_for_display((string) $latest['published_at'], $config, 'Y-m-d')) ?></p>
                <?php endif; ?>
                <p><a href="<?= e($latest['url'] ?? 'https://github.com/kevquirk/pureblog/releases') ?>" target="_blank" rel="noopener noreferrer">View release notes</a></p>
            <?php endif; ?>
        </section>

        <?php if ($packagePlanError !== ''): ?>
        <section class="section-divider">
            <span class="title">Release package inspection</span>
            <p class="notice"><?= e($packagePlanError) ?></p>
        </section>
        <?php endif; ?>

        <?php if ($packagePlan !== null && ($packagePlan['ok'] ?? false)): ?>
        <section class="section-divider">
            <span class="title">Release package inspection</span>
            <?php if (!empty($packagePlan['already_latest'])): ?>
                <p><?= e((string) ($packagePlan['message'] ?? 'You are already on the latest release.')) ?></p>
            <?php else: ?>
            <p><strong>Planned file actions:</strong></p>
            <ul>
                <li><strong>Add:</strong> <?= e((string) ($packagePlan['counts']['add'] ?? 0)) ?></li>
                <li><strong>Replace:</strong> <?= e((string) ($packagePlan['counts']['replace'] ?? 0)) ?></li>
                <li><strong>Unchanged:</strong> <?= e((string) ($packagePlan['counts']['unchanged'] ?? 0)) ?></li>
                <li><strong>Preserved files (<code>/config</code>, <code>/content</code>, <code>/data</code>, all <code>.htaccess</code> files):</strong> <?= e((string) ($packagePlan['counts']['skip'] ?? 0)) ?></li>
                <li><strong>Local files not in upstream release (will be deleted):</strong> <?= e((string) ($packagePlan['counts']['local_only'] ?? 0)) ?></li>
            </ul>

            <?php if (!empty($packagePlan['will_add'])): ?>
                <p><strong>Will add:</strong></p>
                <ul>
                    <?php foreach ($packagePlan['will_add'] as $path): ?>
                        <li><code><?= e($path) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($packagePlan['will_replace'])): ?>
                <p><strong>Will replace:</strong></p>
                <ul>
                    <?php foreach ($packagePlan['will_replace'] as $path): ?>
                        <li><code><?= e($path) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($packagePlan['local_only'])): ?>
                <p><strong>Local files not in upstream release (will be deleted):</strong></p>
                <ul>
                    <?php foreach ($packagePlan['local_only'] as $path): ?>
                        <li><code><?= e($path) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" action="/admin/settings-updates.php" onsubmit="return confirm('Apply latest update now? This will replace core files and keep /config, /content, /data, and all .htaccess files.');">
                <?= csrf_field() ?>
                <button class="button save" type="submit" name="apply_update" value="1">
                    <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-upgrade"></use></svg>
                    Apply latest update
                </button>
            </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (!empty($availableBackups)): ?>
        <section class="section-divider">
            <span class="title">Backup restore</span>
            <p>Backups in <code>/backup</code> are excluded from updates and can be used for rollback.</p>
            <form method="post" action="/admin/settings-updates.php">
                <?= csrf_field() ?>
                <label for="backup-name">Available backups</label>
                <select id="backup-name" name="backup_name" required>
                    <?php foreach ($availableBackups as $backupName): ?>
                        <option value="<?= e($backupName) ?>"><?= e($backupName) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit" name="restore_backup" value="1" onclick="return confirm('Restore this backup now? Current core files will be replaced.');">
                    <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-upgrade"></use></svg>
                    Restore selected backup
                </button>
                <button class="button delete" type="submit" name="delete_backup" value="1" onclick="return confirm('Delete this backup permanently? This cannot be undone.');">
                    <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-circle-x"></use></svg>
                    Delete selected backup
                </button>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($applyResult !== null): ?>
        <section class="section-divider">
            <span class="title">Apply result</span>
            <?php if (!($applyResult['ok'] ?? false)): ?>
                <p class="notice"><?= e((string) ($applyResult['error'] ?? 'Update failed.')) ?></p>
            <?php else: ?>
                <p><?= e((string) ($applyResult['message'] ?? 'Update completed.')) ?></p>
                <?php if (!empty($applyResult['backup_path'])): ?>
                    <p><strong>Backup path:</strong> <code><?= e((string) $applyResult['backup_path']) ?></code></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php endif; ?>

    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
