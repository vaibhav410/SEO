<?php
require __DIR__ . '/../_init.php';

$leadTrend = trend_count('leads', 'created_at', 30, "status <> 'spam'");
$postTrend = trend_count('posts', 'published_at', 30, "status = 'published'");
$conversion = lead_conversion_rate();
$coverage = keyword_coverage_rate();
$backlinks = backlink_counts();
$funnel = lead_funnel();
unset($funnel['spam']);

admin_header('Analytics', 'analytics', ['Analytics' => null]);
echo page_header('Analytics', 'Growth metrics counted from GrowthHub\'s own data. Traffic and ranking metrics need a connected analytics source.');
?>
<div class="alert alert-info"><?= icon('trending', 'icon icon-sm') ?>
    <span>Organic traffic, landing-page visits, impressions and positions are <strong>not connected</strong>. GrowthHub shows them as empty rather than estimating them. Lead, content, keyword and backlink figures below are real.</span></div>

<section class="kpis" aria-label="Metrics">
    <?= kpi_card(['label' => 'Organic traffic', 'value' => null, 'icon' => 'trending', 'connect' => 'Connect Google Analytics 4.']) ?>
    <?= kpi_card(['label' => 'Landing page visits', 'value' => null, 'icon' => 'zap', 'connect' => 'Connect Google Analytics 4.']) ?>
    <?= kpi_card(['label' => 'Leads (30 days)', 'value' => (string) $leadTrend['current'], 'icon' => 'users', 'trend' => $leadTrend, 'href' => '/admin/leads/']) ?>
    <?= kpi_card(['label' => 'Lead conversion', 'value' => $conversion !== null ? (string) $conversion : null, 'suffix' => '%', 'icon' => 'zap', 'note' => 'Converted ÷ all leads', 'connect' => 'No leads yet.']) ?>
    <?= kpi_card(['label' => 'Content published (30 days)', 'value' => (string) $postTrend['current'], 'icon' => 'book', 'trend' => $postTrend, 'href' => '/admin/posts/']) ?>
    <?= kpi_card(['label' => 'Keyword coverage', 'value' => $coverage !== null ? (string) $coverage : null, 'suffix' => '%', 'icon' => 'search', 'note' => 'Keywords with a live target page', 'href' => '/admin/keywords/', 'connect' => 'No keywords yet.']) ?>
    <?= kpi_card(['label' => 'Live backlinks', 'value' => (string) $backlinks['live'], 'icon' => 'globe', 'note' => 'Verified or recorded live', 'href' => '/admin/backlinks/']) ?>
</section>

<div class="grid-2">
    <section class="panel"><div class="panel-head"><h2>Organic traffic trend</h2><span class="badge badge-muted">Not connected</span></div>
        <?= connect_panel('No traffic data', 'Sessions from organic search will appear here once a Google Analytics 4 property is connected.') ?></section>
    <section class="panel"><div class="panel-head"><h2>Lead trend</h2><span class="small muted">Last 12 weeks</span></div>
        <?= chart_bars(leads_weekly(12), 'Leads per week', 'leads') ?></section>
</div>

<div class="grid-3">
    <section class="panel"><div class="panel-head"><h2>Keyword distribution</h2><span class="small muted">By search intent</span></div>
        <?= chart_donut(keyword_distribution(), 'Keywords by intent') ?></section>
    <section class="panel"><div class="panel-head"><h2>Lead pipeline</h2><span class="small muted">By status</span></div>
        <?= chart_donut($funnel, 'Leads by status') ?></section>
    <section class="panel"><div class="panel-head"><h2>Off-page pipeline</h2><span class="small muted">By status</span></div>
        <?= chart_donut($backlinks, 'Backlinks by status') ?></section>
</div>

<div class="grid-2">
    <section class="panel"><div class="panel-head"><h2>Top landing pages</h2><span class="small muted">By leads generated</span></div>
        <?php $sources = leads_by_source(8); ?>
        <?php if ($sources): $max = max(array_column($sources, 'total')); ?>
            <?php foreach ($sources as $s): ?><?= progress_row($s['source_page'], (int) $s['total'], (int) $max) ?><?php endforeach; ?>
        <?php else: ?><p class="muted">No leads yet.</p><?php endif; ?>
    </section>
    <section class="panel"><div class="panel-head"><h2>Content published</h2><span class="small muted">Articles per month</span></div>
        <?= chart_bars(content_monthly(6), 'Articles published per month', 'articles') ?></section>
</div>

<section class="panel">
    <div class="panel-head"><h2>Content performance</h2><span class="small muted">Leads attributed to each article · shares recorded in Organic distribution</span></div>
    <div class="table-wrap"><table class="table-cards">
        <thead><tr><th>Article</th><th>Published</th><th>Leads</th><th>Shares</th><th>Page views</th></tr></thead>
        <tbody>
        <?php foreach (content_performance(10) as $p): ?>
            <tr>
                <td class="primary"><a href="<?= e(url('/admin/posts/edit.php?id=' . $p['id'])) ?>"><?= e($p['title']) ?></a></td>
                <td data-label="Published" class="muted nowrap"><?= e(format_date($p['published_at'])) ?></td>
                <td data-label="Leads"><strong><?= (int) $p['leads'] ?></strong></td>
                <td data-label="Shares"><?= (int) $p['shares'] ?></td>
                <td data-label="Page views"><span class="muted" data-tooltip="Connect Google Analytics to see page views">—</span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php admin_footer(); ?>
