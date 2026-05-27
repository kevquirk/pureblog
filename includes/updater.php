<?php

declare(strict_types=1);

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

/**
 * Load ignore patterns from config/update-ignore.
 * Lines starting with '#' and blank lines are skipped.
 * A leading '/' is stripped so patterns match internal relative paths.
 *
 * @return list<string>
 */
function load_update_ignore_patterns(): array
{
    $path = PUREBLOG_BASE_PATH . '/config/update-ignore';
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }
    $patterns = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $patterns[] = ltrim($line, '/');
    }
    return $patterns;
}

/**
 * Return true if $relative matches any of the given ignore patterns.
 * Patterns support fnmatch() globs (e.g. lang/*).
 *
 * @param list<string> $patterns
 */
function is_path_ignored(string $relative, array $patterns): bool
{
    return matched_ignore_pattern($relative, $patterns) !== null;
}

/**
 * Returns the matched pattern (exact) or the pattern with '/*' appended
 * (directory prefix match). Returns null if no pattern matches.
 */
function matched_ignore_pattern(string $relative, array $patterns): ?string
{
    foreach ($patterns as $pattern) {
        if (fnmatch($pattern, $relative)) {
            return $pattern;
        }
        if (str_starts_with($relative, rtrim($pattern, '/') . '/')) {
            return rtrim($pattern, '/') . '/*';
        }
    }
    return null;
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
    return pureblog_http_download(
        $url,
        $destination,
        20,
        ['User-Agent: Pureblog-Upgrader', 'Accept: */*']
    );
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

        // Check for a breaking-upgrade notice bundled in the release zip.
        $breakingInstructions = null;
        $noticeFile = $sourceRoot . '/upgrade-notice.json';
        if (is_file($noticeFile)) {
            $noticeRaw = @file_get_contents($noticeFile);
            if ($noticeRaw !== false) {
                $notice = json_decode($noticeRaw, true);
                if (is_array($notice) && !empty($notice['breaking'])) {
                    $breakingInstructions = trim((string) ($notice['instructions'] ?? ''));
                }
            }
        }

        $preserveTop = preserved_top_level_paths();
        $ignorePatterns = load_update_ignore_patterns();
        $willAdd = [];
        $willReplace = [];
        $willIgnore = [];  // keyed by display label to collapse directory patterns
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
                } elseif (($matchedPattern = matched_ignore_pattern($relative, $ignorePatterns)) !== null) {
                    $willIgnore['/' . $matchedPattern] = '/' . $matchedPattern;
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
            // Ignored files always surface in will_ignore regardless of whether
            // they were actually at risk, so the plan reflects the full ignore list.
            if (!isset($sourceCoreSet[$relative])) {
                if (($matchedPattern = matched_ignore_pattern($relative, $ignorePatterns)) !== null) {
                    $willIgnore['/' . $matchedPattern] = '/' . $matchedPattern;
                } elseif (isset($sourceTopItems[$top])) {
                    // Only flag as deleted if inside a directory the update will wipe.
                    $localOnly[] = '/' . $relative;
                }
            }
        }

        sort($willAdd);
        sort($willReplace);
        sort($willIgnore);
        sort($unchanged);
        sort($willSkip);
        sort($localOnly);

        return [
            'ok' => true,
            'breaking' => $breakingInstructions !== null,
            'breaking_instructions' => $breakingInstructions ?? '',
            'counts' => [
                'add' => count($willAdd),
                'replace' => count($willReplace),
                'ignore' => count($willIgnore),
                'unchanged' => count($unchanged),
                'skip' => count($willSkip),
                'local_only' => count($localOnly),
            ],
            'will_add' => $willAdd,
            'will_replace' => $willReplace,
            'will_ignore' => array_values($willIgnore),
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

        // Save content of files the user has opted to ignore so they can be
        // restored after the wipe-and-copy. Only files that already exist
        // locally are saved; new files from the release are always written.
        $ignorePatterns = load_update_ignore_patterns();
        $savedIgnoredFiles = [];
        if ($ignorePatterns) {
            foreach (collect_relative_files(PUREBLOG_BASE_PATH) as $relative) {
                if (is_path_ignored($relative, $ignorePatterns)) {
                    $content = @file_get_contents(PUREBLOG_BASE_PATH . '/' . $relative);
                    if (is_string($content)) {
                        $savedIgnoredFiles[$relative] = $content;
                    }
                }
            }
        }

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

        // Restore files the user has opted to ignore.
        foreach ($savedIgnoredFiles as $relative => $content) {
            $target = PUREBLOG_BASE_PATH . '/' . $relative;
            $dir = dirname($target);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($target, $content);
        }

        // Flush the opcode cache so the next request immediately picks up
        // the newly written files rather than the pre-update cached bytecode.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        @unlink(PUREBLOG_BASE_PATH . '/content/.version-cache');

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
