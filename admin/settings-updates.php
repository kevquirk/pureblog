<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
ob_start();

require __DIR__ . '/../includes/updater.php';

$config = load_config();
$fontStack = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');

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
            <p><strong><?= e(t('admin.settings.updates.repository')) ?></strong> <a href="https://codeberg.org/kevquirk/pureblog" target="_blank" rel="noopener noreferrer">codeberg.org/kevquirk/pureblog</a></p>
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
                <p><a href="<?= e($latest['url'] ?? 'https://codeberg.org/kevquirk/pureblog/releases') ?>" target="_blank" rel="noopener noreferrer"><?= e(t('admin.settings.updates.view_release_notes')) ?></a></p>
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
            <?php elseif (!empty($packagePlan['breaking'])): ?>
                <p class="notice delete"><strong>Manual upgrade required</strong> — this release contains breaking changes that cannot be applied automatically.</p>
                <?php if (($packagePlan['breaking_instructions'] ?? '') !== ''): ?>
                    <p><?= e((string) $packagePlan['breaking_instructions']) ?></p>
                <?php endif; ?>
                <p><a class="button" href="https://codeberg.org/kevquirk/pureblog/releases" target="_blank" rel="noopener noreferrer">Download from Codeberg</a></p>
            <?php else: ?>
            <p><strong><?= e(t('admin.settings.updates.planned_actions')) ?></strong></p>
            <ul>
                <li><strong><?= e(t('admin.settings.updates.action_add')) ?></strong> <?= e((string) ($packagePlan['counts']['add'] ?? 0)) ?></li>
                <li><strong><?= e(t('admin.settings.updates.action_replace')) ?></strong> <?= e((string) ($packagePlan['counts']['replace'] ?? 0)) ?></li>
                <li><strong><?= e(t('admin.settings.updates.action_unchanged')) ?></strong> <?= e((string) ($packagePlan['counts']['unchanged'] ?? 0)) ?></li>
                <li><strong><?= e(t('admin.settings.updates.action_preserved')) ?></strong> <?= e((string) ($packagePlan['counts']['skip'] ?? 0)) ?></li>
                <li><strong><?= e(t('admin.settings.updates.action_local_only')) ?></strong> <?= e((string) ($packagePlan['counts']['local_only'] ?? 0)) ?></li>
                <?php if (($packagePlan['counts']['ignore'] ?? 0) > 0): ?>
                <li><strong><?= e(t('admin.settings.updates.action_ignore')) ?></strong> <?= e((string) ($packagePlan['counts']['ignore'] ?? 0)) ?></li>
                <?php endif; ?>
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

            <?php if (!empty($packagePlan['will_ignore'])): ?>
                <p><strong><?= e(t('admin.settings.updates.will_ignore')) ?></strong></p>
                <ul>
                    <?php foreach ($packagePlan['will_ignore'] as $path): ?>
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
