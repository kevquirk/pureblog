<?php

declare(strict_types=1);

require __DIR__ . '/../functions.php';
require_setup_redirect();

start_admin_session();
require_admin_login();

$config = load_config();

$publishedPosts = array_values(array_filter(get_all_posts(true), static fn(array $post): bool => ($post['status'] ?? 'draft') === 'published'));
$publishedCount = count($publishedPosts);
$tz             = site_timezone_object($config);
$now            = new DateTimeImmutable('now', $tz);
$currentYear    = (int) $now->format('Y');

$publishedThisYear  = 0;
$totalWordsThisYear = 0;
$tagCounts          = [];
$tagCountsThisYear  = [];
$totalWords         = 0;
$allTimeMonthCounts = array_fill(1, 12, 0);
$allTimeDayCounts   = array_fill(1, 7, 0); // 1=Mon … 7=Sun (ISO 8601)
$yearCounts         = [];

// Build rolling 12-month chart slots (oldest first)
$chartMonths = [];
$firstOfMonth = new DateTimeImmutable($now->format('Y-m-01'), $tz);
for ($i = 11; $i >= 0; $i--) {
    $dt    = $firstOfMonth->modify("-{$i} months");
    $month = (int) $dt->format('n');
    $chartMonths[] = [
        'year'  => (int) $dt->format('Y'),
        'month' => $month,
        'label' => t('date.months_short.' . ($month - 1)),
        'count' => 0,
    ];
}
$chartIndex      = [];
foreach ($chartMonths as $idx => $cm) {
    $chartIndex[$cm['year'] . '-' . $cm['month']] = $idx;
}
$recentCutoff = $firstOfMonth->modify('-11 months')->getTimestamp();

foreach ($publishedPosts as $post) {
    $content    = (string) ($post['content'] ?? '');
    $totalWords += str_word_count($content);

    $timestamp = (int) ($post['timestamp'] ?? 0);
    if ($timestamp > 0) {
        $dt       = (new DateTimeImmutable('@' . $timestamp))->setTimezone($tz);
        $postYear = (int) $dt->format('Y');
        $postMon  = (int) $dt->format('n');
        $postDay  = (int) $dt->format('N'); // 1=Mon … 7=Sun

        $chartKey = $postYear . '-' . $postMon;
        if (isset($chartIndex[$chartKey])) {
            $chartMonths[$chartIndex[$chartKey]]['count']++;
        }

        if ($timestamp >= $recentCutoff) {
            $publishedThisYear++;
            $totalWordsThisYear += str_word_count($content);
        }

        $allTimeMonthCounts[$postMon]++;
        $allTimeDayCounts[$postDay]++;
        $yearCounts[$postYear] = ($yearCounts[$postYear] ?? 0) + 1;
    }

    $tags = $post['tags'] ?? [];
    if (!is_array($tags)) {
        continue;
    }
    foreach ($tags as $tag) {
        $name = trim((string) $tag);
        if ($name !== '') {
            $tagCounts[$name] = ($tagCounts[$name] ?? 0) + 1;
            if ($timestamp >= $recentCutoff) {
                $tagCountsThisYear[$name] = ($tagCountsThisYear[$name] ?? 0) + 1;
            }
        }
    }
}

ksort($yearCounts);
$maxYearCount = !empty($yearCounts) ? max($yearCounts) : 1;

$avgWordsAllTime    = $publishedCount > 0 ? (int) round($totalWords / $publishedCount) : 0;
$avgWordsThisYear   = $publishedThisYear > 0 ? (int) round($totalWordsThisYear / $publishedThisYear) : 0;
$booksEquivalent    = $totalWords > 0 ? round($totalWords / 80000, 1) : 0;
$booksThisYear      = $totalWordsThisYear > 0 ? round($totalWordsThisYear / 80000, 1) : 0;

// Last published time
$lastTimestamp = 0;
foreach ($publishedPosts as $post) {
    $ts = (int) ($post['timestamp'] ?? 0);
    if ($ts > $lastTimestamp) {
        $lastTimestamp = $ts;
    }
}
$lastPublishedAgo = $lastTimestamp > 0 ? relative_time($lastTimestamp) : t('admin.dashboard.no_posts_yet');

$maxChartCount = max(1, ...array_column($chartMonths, 'count'));
$maxMonthCount = max(1, max($allTimeMonthCounts));
$maxDayCount   = max(1, max($allTimeDayCounts));

uasort($tagCounts, static fn(int $a, int $b): int => $b <=> $a);
uasort($tagCountsThisYear, static fn(int $a, int $b): int => $b <=> $a);

$topTagEntries = [];
$n = 0;
foreach ($tagCounts as $tag => $count) {
    $topTagEntries[] = '<strong>' . e((string) $tag) . '</strong> (' . (int) $count . ')';
    if (++$n >= 5) break;
}
$topTagsLabel = $topTagEntries ? implode(', ', $topTagEntries) : t('admin.dashboard.stat_no_tags');

$topTagEntriesThisYear = [];
$n = 0;
foreach ($tagCountsThisYear as $tag => $count) {
    $topTagEntriesThisYear[] = '<strong>' . e((string) $tag) . '</strong> (' . (int) $count . ')';
    if (++$n >= 5) break;
}
$topTagsThisYearLabel = $topTagEntriesThisYear ? implode(', ', $topTagEntriesThisYear) : t('admin.dashboard.stat_no_tags');

// Month labels for all-time chart
$monthLabels = [];
for ($m = 1; $m <= 12; $m++) {
    $monthLabels[$m] = t('date.months_short.' . ($m - 1));
}

// Day labels for all-time chart (1=Mon … 7=Sun → days_short index $d % 7)
$dayLabels = [];
for ($d = 1; $d <= 7; $d++) {
    $dayLabels[$d] = t('date.days_short.' . ($d % 7));
}

// Version check — cached for 6 hours to avoid a GitHub API call on every load
$currentVersion   = detect_current_pureblog_version();
$versionCacheFile = PUREBLOG_BASE_PATH . '/content/.version-cache';
$latestVersion    = '';
$cacheAge         = is_file($versionCacheFile) ? (time() - (int) @filemtime($versionCacheFile)) : PHP_INT_MAX;
if ($cacheAge > 21600) {
    $ctx  = stream_context_create(['http' => [
        'timeout' => 3,
        'header'  => "User-Agent: Pureblog-Dashboard\r\nAccept: application/vnd.github+json\r\n",
        'ignore_errors' => true,
    ]]);
    $json = @file_get_contents('https://api.github.com/repos/kevquirk/pureblog/releases/latest', false, $ctx);
    if (is_string($json)) {
        $data = @json_decode($json, true);
        if (is_array($data) && isset($data['tag_name']) && is_string($data['tag_name'])) {
            $latestVersion = trim($data['tag_name']);
            @file_put_contents($versionCacheFile, $latestVersion);
        }
    }
} else {
    $cached = @file_get_contents($versionCacheFile);
    $latestVersion = is_string($cached) ? trim($cached) : '';
}
$updateAvailable = $latestVersion !== '' && $currentVersion !== 'unknown' && !versions_match($currentVersion, $latestVersion);

$fontStack  = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$adminTitle = t('admin.dashboard.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">

        <p class="dashboard-write-post">
            <a class="save" href="<?= base_path() ?>/admin/edit-post.php?action=new">
                <svg class="icon" aria-hidden="true"><use href="#icon-file-plus-corner"></use></svg>
                <?= e(t('admin.dashboard.write_post')) ?>
            </a>
        </p>

        <!-- Top row: snapshot -->
        <div class="dashboard-stats-3">
            <?php if ($currentVersion !== 'unknown'): ?>
            <div class="dashboard-stat-card dashboard-version-card">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.version_label')) ?></p>
                <p class="dashboard-stat-value"><?= e($currentVersion) ?></p>
                <?php if ($updateAvailable): ?>
                    <a class="dashboard-version-update" href="<?= base_path() ?>/admin/settings-updates.php"><?= e(t('admin.dashboard.version_update_link', ['latest' => $latestVersion])) ?></a>
                <?php elseif ($latestVersion !== ''): ?>
                    <p class="dashboard-version-uptodate"><?= e(t('admin.dashboard.version_uptodate')) ?></p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="dashboard-stat-card"></div>
            <?php endif; ?>
            <div class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.last_published')) ?></p>
                <p class="dashboard-stat-value"><?= e($lastPublishedAgo) ?></p>
            </div>
            <div class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_published')) ?></p>
                <p class="dashboard-stat-value"><?= e(number_format($publishedCount)) ?></p>
            </div>
        </div>

        <!-- This year -->
        <h2 class="dashboard-h2"><?= e(t('admin.dashboard.section_this_year', ['year' => $currentYear])) ?></h2>

        <?php $chartTotal = array_sum(array_column($chartMonths, 'count')); ?>
        <?php if ($chartTotal > 0): ?>
        <div class="dashboard-chart dashboard-chart-full" aria-label="<?= e(t('admin.dashboard.chart_title')) ?>">
            <?php foreach ($chartMonths as $cm): ?>
                <?php $barPx = $cm['count'] > 0 ? max(3, (int) round(($cm['count'] / $maxChartCount) * 120)) : 0; ?>
                <div class="dashboard-chart-col">
                    <span class="dashboard-chart-count"><?= $cm['count'] > 0 ? $cm['count'] : '' ?></span>
                    <div class="dashboard-chart-bar" style="height: <?= $barPx ?>px"></div>
                    <span class="dashboard-chart-label"><?= e($cm['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="dashboard-stats-3">
            <div class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_this_year')) ?></p>
                <p class="dashboard-stat-value"><?= e((string) $publishedThisYear) ?></p>
            </div>
            <div class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_words_this_year')) ?></p>
                <p class="dashboard-stat-value"><?= e(number_format($totalWordsThisYear)) ?><?php if ($booksThisYear > 0): ?><br><span class="dashboard-stat-books">(<?= e(number_format($booksThisYear, 1)) ?> books)</span><?php endif; ?></p>
            </div>
            <div class="dashboard-stat-card dashboard-stat-card-tags">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_top_tags')) ?></p>
                <p class="dashboard-stat-value dashboard-stat-tags"><?= $topTagsThisYearLabel ?></p>
            </div>
        </div>

        <!-- All time -->
        <h2 class="dashboard-h2"><?= e(t('admin.dashboard.section_all_time')) ?></h2>

        <?php if (!empty($yearCounts)): ?>
        <div class="dashboard-chart dashboard-chart-full" aria-label="<?= e(t('admin.dashboard.chart_all_years')) ?>">
            <?php foreach ($yearCounts as $year => $count): ?>
                <?php $barPx = $count > 0 ? max(3, (int) round(($count / $maxYearCount) * 120)) : 0; ?>
                <div class="dashboard-chart-col">
                    <span class="dashboard-chart-count"><?= $count ?></span>
                    <div class="dashboard-chart-bar" style="height: <?= $barPx ?>px"></div>
                    <span class="dashboard-chart-label"><?= e((string) $year) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="dashboard-stats-3">
            <div class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_total_words')) ?></p>
                <p class="dashboard-stat-value"><?= e(number_format($totalWords)) ?></p>
            </div>
            <div class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_books')) ?></p>
                <p class="dashboard-stat-value"><?= e(number_format($booksEquivalent, 1)) ?></p>
            </div>
            <div class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_avg_words')) ?></p>
                <p class="dashboard-stat-value"><?= e(number_format($avgWordsAllTime)) ?></p>
            </div>
        </div>

        <div class="dashboard-chart-pair">
            <div class="dashboard-chart-section">
                <h3 class="dashboard-h3"><?= e(t('admin.dashboard.chart_all_months')) ?></h3>
                <div class="dashboard-chart" aria-label="<?= e(t('admin.dashboard.chart_all_months')) ?>">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <?php $barPx = $allTimeMonthCounts[$m] > 0 ? max(3, (int) round(($allTimeMonthCounts[$m] / $maxMonthCount) * 100)) : 0; ?>
                        <div class="dashboard-chart-col">
                            <span class="dashboard-chart-count"><?= $allTimeMonthCounts[$m] > 0 ? $allTimeMonthCounts[$m] : '' ?></span>
                            <div class="dashboard-chart-bar" style="height: <?= $barPx ?>px"></div>
                            <span class="dashboard-chart-label"><?= e($monthLabels[$m]) ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="dashboard-chart-section">
                <h3 class="dashboard-h3"><?= e(t('admin.dashboard.chart_all_days')) ?></h3>
                <div class="dashboard-chart" aria-label="<?= e(t('admin.dashboard.chart_all_days')) ?>">
                    <?php for ($d = 1; $d <= 7; $d++): ?>
                        <?php $barPx = $allTimeDayCounts[$d] > 0 ? max(3, (int) round(($allTimeDayCounts[$d] / $maxDayCount) * 100)) : 0; ?>
                        <div class="dashboard-chart-col">
                            <span class="dashboard-chart-count"><?= $allTimeDayCounts[$d] > 0 ? $allTimeDayCounts[$d] : '' ?></span>
                            <div class="dashboard-chart-bar" style="height: <?= $barPx ?>px"></div>
                            <span class="dashboard-chart-label"><?= e($dayLabels[$d]) ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <?php if ($tagCounts): ?>
        <div class="dashboard-all-tags">
            <h2 class="dashboard-h2"><?= e(t('admin.dashboard.all_tags')) ?></h2>
            <ul class="dashboard-all-tags-list">
                <?php foreach ($tagCounts as $tag => $count): ?>
                    <li><a href="<?= base_path() ?>/tag/<?= urlencode((string) $tag) ?>"><?= e((string) $tag) ?></a> <span class="dashboard-tag-count">(<?= (int) $count ?>)</span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
