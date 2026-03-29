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
$tagCounts          = [];
$totalWords         = 0;
$allTimeMonthCounts = array_fill(1, 12, 0);
$allTimeDayCounts   = array_fill(1, 7, 0); // 1=Mon … 7=Sun (ISO 8601)

// Build rolling 12-month chart slots (oldest first)
$chartMonths = [];
for ($i = 11; $i >= 0; $i--) {
    $dt    = $now->modify("-{$i} months");
    $month = (int) $dt->format('n');
    $chartMonths[] = [
        'year'  => (int) $dt->format('Y'),
        'month' => $month,
        'label' => t('date.months_short.' . ($month - 1)),
        'count' => 0,
    ];
}
$chartIndex = [];
foreach ($chartMonths as $idx => $cm) {
    $chartIndex[$cm['year'] . '-' . $cm['month']] = $idx;
}

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

        if ($postYear === $currentYear) {
            $publishedThisYear++;
        }

        $allTimeMonthCounts[$postMon]++;
        $allTimeDayCounts[$postDay]++;
    }

    $tags = $post['tags'] ?? [];
    if (!is_array($tags)) {
        continue;
    }
    foreach ($tags as $tag) {
        $name = trim((string) $tag);
        if ($name !== '') {
            $tagCounts[$name] = ($tagCounts[$name] ?? 0) + 1;
        }
    }
}

$avgWordsAllTime = $publishedCount > 0 ? (int) round($totalWords / $publishedCount) : 0;
$booksEquivalent = $totalWords > 0 ? round($totalWords / 80000, 1) : 0;

$maxChartCount = max(1, ...array_column($chartMonths, 'count'));
$maxMonthCount = max(1, max($allTimeMonthCounts));
$maxDayCount   = max(1, max($allTimeDayCounts));

uasort($tagCounts, static fn(int $a, int $b): int => $b <=> $a);
$topTagEntries = [];
$n = 0;
foreach ($tagCounts as $tag => $count) {
    $topTagEntries[] = '<strong>' . e((string) $tag) . '</strong> (' . (int) $count . ')';
    if (++$n >= 5) {
        break;
    }
}
$topTagsLabel = $topTagEntries ? implode(', ', $topTagEntries) : t('admin.dashboard.stat_no_tags');

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

$fontStack  = font_stack_css($config['theme']['admin_font_stack'] ?? 'sans');
$adminTitle = t('admin.dashboard.page_title');
require __DIR__ . '/../includes/admin-head.php';
?>
    <main class="mid">

        <?php $chartTotal = array_sum(array_column($chartMonths, 'count')); ?>
        <?php if ($chartTotal > 0): ?>
        <p class="dashboard-chart-title"><?= e(t('admin.dashboard.chart_title')) ?></p>
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
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_published')) ?></p>
                <p class="dashboard-stat-value"><?= e(number_format($publishedCount)) ?></p>
            </article>
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_total_words')) ?></p>
                <p class="dashboard-stat-value"><?= e(number_format($totalWords)) ?></p>
            </article>
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_books')) ?></p>
                <p class="dashboard-stat-value"><?= e(number_format($booksEquivalent, 1)) ?></p>
            </article>
        </div>

        <div class="dashboard-stats-3">
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_this_year', ['year' => $currentYear])) ?></p>
                <p class="dashboard-stat-value"><?= e((string) $publishedThisYear) ?></p>
            </article>
            <article class="dashboard-stat-card dashboard-stat-card-metric">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_avg_words')) ?></p>
                <p class="dashboard-stat-value"><?= e(number_format($avgWordsAllTime)) ?></p>
            </article>
            <article class="dashboard-stat-card dashboard-stat-card-tags">
                <p class="dashboard-stat-label"><?= e(t('admin.dashboard.stat_top_tags')) ?></p>
                <p class="dashboard-stat-value dashboard-stat-tags"><?= $topTagsLabel ?></p>
            </article>
        </div>

        <div class="dashboard-chart-pair">
            <div class="dashboard-chart-section">
                <p class="dashboard-chart-title"><?= e(t('admin.dashboard.chart_all_months')) ?></p>
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
                <p class="dashboard-chart-title"><?= e(t('admin.dashboard.chart_all_days')) ?></p>
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
            <p class="dashboard-chart-title"><?= e(t('admin.dashboard.all_tags')) ?></p>
            <ul class="dashboard-all-tags-list">
                <?php foreach ($tagCounts as $tag => $count): ?>
                    <li><a href="<?= base_path() ?>/<?= urlencode((string) $tag) ?>"><?= e((string) $tag) ?></a> <span class="dashboard-tag-count">(<?= (int) $count ?>)</span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </main>
<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
