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
        'published_at' => (string) ($json['published_at'] ?? ''),
    ];
}

function detect_current_pureblog_version(): string
{
    if (defined('PUREBLOG_VERSION') && is_string(PUREBLOG_VERSION) && PUREBLOG_VERSION !== '') {
        return PUREBLOG_VERSION;
    }

    return 'unknown';
}

$latest = null;
if (isset($_GET['check'])) {
    $latest = fetch_latest_pureblog_release();
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
            <p><strong>Current version:</strong> <?= e(detect_current_pureblog_version()) ?></p>
            <p><strong>Repository:</strong> <a href="https://github.com/kevquirk/pureblog" target="_blank" rel="noopener noreferrer">github.com/kevquirk/pureblog</a></p>
            <p>
                <a class="button" href="/admin/settings-updates.php?check=1">
                    <svg class="icon" aria-hidden="true"><use href="/admin/icons/sprite.svg#icon-upgrade"></use></svg>
                    Check latest release
                </a>
            </p>

            <?php if ($latest !== null && !($latest['ok'] ?? false)): ?>
                <p class="notice"><?= e($latest['error'] ?? 'Unable to check for updates.') ?></p>
            <?php endif; ?>

            <?php if ($latest !== null && ($latest['ok'] ?? false)): ?>
                <p><strong>Latest release:</strong> <?= e($latest['tag'] !== '' ? $latest['tag'] : ($latest['name'] ?? 'Unknown')) ?></p>
                <?php if (($latest['published_at'] ?? '') !== ''): ?>
                    <p><strong>Published:</strong> <?= e((string) date('Y-m-d', strtotime($latest['published_at']))) ?></p>
                <?php endif; ?>
                <p><a href="<?= e($latest['url'] ?? 'https://github.com/kevquirk/pureblog/releases') ?>" target="_blank" rel="noopener noreferrer">View release notes</a></p>
            <?php endif; ?>
        </section>

        <section class="section-divider">
            <span class="title">Upgrade steps</span>
            <ol>
                <li>Back up your site.</li>
                <li>Download the latest release.</li>
                <li>Replace everything except <code>/config</code>, <code>/content</code>, and <code>/data</code>.</li>
                <li>Reload your site and check the admin dashboard.</li>
            </ol>
        </section>
    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
