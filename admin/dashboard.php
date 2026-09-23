<?php
require __DIR__ . '/_init.php';

// Every number on this page is counted from the database. Metrics that need Google Analytics or
// Search Console are shown as "not connected" rather than estimated.
$c = db_one(
    "SELECT
        (SELECT COUNT(*) FROM posts p WHERE " . POST_PUBLIC_SQL . ") AS posts,
        (SELECT COUNT(*) FROM posts WHERE status = 'draft') AS drafts,
        (SELECT COUNT(*) FROM landing_pages WHERE status = 'published') AS landing,
        (SELECT COUNT(*) FROM services WHERE status = 'published') AS services,
        (SELECT COUNT(*) FROM internal_links WHERE status = 'active') AS link_rules,
        (SELECT COUNT(*) FROM leads WHERE status = 'new') AS new_leads,
        (SELECT COUNT(*) FROM leads WHERE status <> 'spam' AND created_at >= NOW() - INTERVAL 30 DAY) AS leads_30"
);
$kw = keyword_summary();
$backlinks = backlink_counts();
$health = db_one('SELECT ROUND(AVG(a.overall_score)) AS score, COUNT(*) AS pages FROM seo_audits a
                  JOIN (SELECT MAX(id) AS id FROM seo_audits GROUP BY url) l ON l.id = a.id');
$conversion = lead_conversion_rate();
$sitemapCount = count(sitemap_entries());
$opportunities = opportunities_all();
$openOps = array_values(array_filter($opportunities, fn($o) => $o['status'] === 'open' || $o['status'] === 'in_progress'));
$linkOps = count(array_filter($openOps, fn($o) => $o['type'] === 'internal_link'));
$issues = content_health_issues();
$issueCounts = array_count_values(array_column($issues, 'severity'));
$latest = [
    'keyword' => db_one('SELECT keyword, updated_at FROM keywords ORDER BY updated_at DESC LIMIT 1'),
    'post' => db_one('SELECT title, updated_at FROM posts ORDER BY updated_at DESC LIMIT 1'),
    'landing' => db_one('SELECT title, updated_at FROM landing_pages ORDER BY updated_at DESC LIMIT 1'),
    'audit' => db_one('SELECT url, created_at FROM seo_audits ORDER BY created_at DESC LIMIT 1'),
    'lead' => db_one("SELECT name, created_at FROM leads WHERE status <> 'spam' ORDER BY created_at DESC LIMIT 1"),
];
$recent = fn(?array $row, string $field, string $date) => $row ? str_limit($row[$field], 34) . ' · ' . time_ago($row[$date]) : 'No activity yet';

$loop = [
    ['Keyword', (int) $kw['active'], 'targeted of ' . (int) $kw['total'], (int) $kw['unmapped'] . ' unmapped', $recent($latest['keyword'], 'keyword', 'updated_at'), '/admin/keywords/'],
    ['Content', (int) $c['posts'], 'published articles', (int) $c['drafts'] . ' drafts', $recent($latest['post'], 'title', 'updated_at'), '/admin/posts/'],
    ['Landing page', (int) $c['landing'], 'live landing pages', (int) $c['services'] . ' service pages', $recent($latest['landing'], 'title', 'updated_at'), '/admin/landing-pages/'],
    ['Internal links', (int) $c['link_rules'], 'active link rules', $linkOps . ' link gaps', 'Applied automatically on render', '/admin/links/'],
    ['Organic discovery', $sitemapCount, 'URLs in sitemap', 'Schema + canonical on all', $recent($latest['audit'], 'url', 'created_at'), '/admin/technical/'],
    ['Lead', (int) $c['leads_30'], 'leads in 30 days', (int) $c['new_leads'] . ' awaiting reply', $recent($latest['lead'], 'name', 'created_at'), '/admin/leads/'],
];

admin_header('Dashboard', 'dashboard');
echo page_header('Growth dashboard', 'How SYSCOM\'s organic growth loop is performing, from keyword planning to leads.',
    '<a class="btn btn-outline" href="' . e(url('/admin/opportunities/')) . '">' . icon('zap', 'icon icon-sm') . ' ' . count($openOps) . ' opportunities</a>'
    . '<a class="btn btn-primary" href="' . e(url('/admin/posts/edit.php')) . '">New blog post</a>');
?>
<section class="kpis" aria-label="Key metrics">
    <?= kpi_card(['label' => 'Organic traffic', 'value' => null, 'icon' => 'trending', 'connect' => 'Connect Google Analytics 4 to see organic sessions.']) ?>
    <?= kpi_card(['label' => 'Target keywords', 'value' => (string) $kw['total'], 'icon' => 'search', 'href' => '/admin/keywords/',
        'trend' => trend_count('keywords', 'created_at'), 'note' => (int) $kw['high'] . ' high priority']) ?>
    <?= kpi_card(['label' => 'Keywords ranking', 'value' => null, 'icon' => 'layers', 'connect' => 'Connect Search Console to see real positions.']) ?>
    <?= kpi_card(['label' => 'Published content', 'value' => (string) ($c['posts'] + $c['landing'] + $c['services']), 'icon' => 'book', 'href' => '/admin/posts/',
        'trend' => trend_count('posts', 'published_at', 30, "status = 'published'"), 'note' => $c['posts'] . ' articles · ' . $c['landing'] . ' landing · ' . $c['services'] . ' services']) ?>
    <?= kpi_card(['label' => 'SEO health', 'value' => $health && $health['pages'] ? (string) $health['score'] : null, 'suffix' => '/100', 'icon' => 'shield', 'href' => '/admin/audits/',
        'note' => $health && $health['pages'] ? 'Internal checklist · ' . $health['pages'] . ' URL(s)' : '', 'connect' => 'Run the SEO auditor to measure on-page health.']) ?>
    <?= kpi_card(['label' => 'Backlinks', 'value' => (string) $backlinks['live'], 'suffix' => 'live', 'icon' => 'globe', 'href' => '/admin/backlinks/',
        'note' => ($backlinks['submitted'] + $backlinks['pending']) . ' in progress · ' . $backlinks['opportunity'] . ' opportunities']) ?>
    <?= kpi_card(['label' => 'Leads', 'value' => (string) $c['leads_30'], 'suffix' => '30 days', 'icon' => 'users', 'href' => '/admin/leads/',
        'trend' => trend_count('leads', 'created_at', 30, "status <> 'spam'"), 'note' => $c['new_leads'] . ' new']) ?>
    <?= kpi_card(['label' => 'Lead conversion', 'value' => $conversion !== null ? (string) $conversion : null, 'suffix' => '%', 'icon' => 'zap', 'href' => '/admin/analytics/',
        'note' => 'Leads marked converted', 'connect' => 'No leads yet.']) ?>
</section>

<section class="panel" aria-labelledby="loop-title">
    <div class="panel-head">
        <div><h2 id="loop-title">Growth loop</h2><p class="muted small">Each stage feeds the next. Select a stage to work on it.</p></div>
    </div>
    <div class="loop">
        <?php foreach ($loop as $i => [$label, $count, $unit, $status, $activity, $href]): ?>
            <a class="loop-stage" href="<?= e(url($href)) ?>">
                <span class="loop-step">Step <?= $i + 1 ?></span>
                <span class="loop-label"><?= e($label) ?></span>
                <span class="loop-count"><?= (int) $count ?></span>
                <span class="loop-meta"><?= e($unit) ?></span>
                <span class="badge badge-muted"><?= e($status) ?></span>
                <span class="loop-meta" title="Most recent activity"><?= e($activity) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<div class="grid-2-1">
    <section class="panel" aria-labelledby="leads-trend">
        <div class="panel-head"><h2 id="leads-trend">Leads per week</h2><a class="small" href="<?= e(url('/admin/analytics/')) ?>">Analytics</a></div>
        <?= chart_bars(leads_weekly(12), 'Leads per week, last 12 weeks', 'leads') ?>
        <p class="small muted">Counted from form submissions (spam excluded). Traffic-based conversion needs analytics data.</p>
    </section>
    <section class="panel" aria-labelledby="ops-title">
        <div class="panel-head"><h2 id="ops-title">Top opportunities</h2><a class="small" href="<?= e(url('/admin/opportunities/')) ?>">View all</a></div>
        <?php if ($openOps): ?>
            <ul class="issue-list">
                <?php foreach (array_slice($openOps, 0, 5) as $o): ?>
                    <li class="issue-<?= $o['priority'] === 'high' ? 'fail' : ($o['priority'] === 'medium' ? 'warn' : 'info') ?>">
                        <span class="issue-icon" aria-hidden="true"><?= $o['priority'] === 'high' ? '!' : '•' ?></span>
                        <div><?= $o['url'] ? '<a href="' . e(url($o['url'])) . '"><strong>' . e($o['title']) . '</strong></a>' : '<strong>' . e($o['title']) . '</strong>' ?>
                            <br><span class="muted small"><?= e(OPPORTUNITY_TYPES[$o['type']]) ?> · <?= e(ucfirst($o['priority'])) ?> priority</span></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">No open opportunities. Everything in the plan is covered.</p>
        <?php endif; ?>
    </section>
</div>

<div class="grid-3">
    <section class="panel" aria-labelledby="activity-title">
        <div class="panel-head"><h2 id="activity-title">Recent activity</h2></div>
        <?= activity_timeline(activity_recent(7)) ?>
    </section>
    <section class="panel" aria-labelledby="issues-title">
        <div class="panel-head"><h2 id="issues-title">Content SEO issues</h2>
            <span class="small muted"><?= (int) ($issueCounts['error'] ?? 0) ?> errors · <?= (int) ($issueCounts['warning'] ?? 0) ?> warnings</span></div>
        <?php if ($issues): ?>
            <ul class="issue-list">
                <?php foreach (array_slice($issues, 0, 5) as $i): ?>
                    <li class="issue-<?= e($i['severity']) ?>"><span class="issue-icon" aria-hidden="true"><?= $i['severity'] === 'info' ? 'i' : '!' ?></span>
                        <div><strong><?= e($i['type']) ?></strong> · <?= e(str_limit($i['item'], 50)) ?><br><span class="muted small"><?= e($i['message']) ?></span>
                            <?php if ($i['edit']): ?> <a class="small" href="<?= e(url($i['edit'])) ?>">Fix</a><?php endif; ?></div></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">No content issues found.</p>
        <?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="sources-title">
        <div class="panel-head"><h2 id="sources-title">Leads by landing page</h2><a class="small" href="<?= e(url('/admin/leads/')) ?>">Leads</a></div>
        <?php $sources = leads_by_source(6); ?>
        <?php if ($sources): $max = max(array_column($sources, 'total')); ?>
            <?php foreach ($sources as $s): ?>
                <?= progress_row($s['source_page'], (int) $s['total'], (int) $max) ?>
            <?php endforeach; ?>
            <p class="small muted">Source page is recorded server-side on every enquiry.</p>
        <?php else: ?>
            <p class="muted">No leads yet.</p>
        <?php endif; ?>
    </section>
</div>

<section class="panel" aria-labelledby="audits-title">
    <div class="panel-head"><h2 id="audits-title">Recent audits</h2><a class="small" href="<?= e(url('/admin/audits/')) ?>">SEO auditor</a></div>
    <?php $audits = audits_recent(5); ?>
    <?php if ($audits): ?>
        <div class="table-wrap"><table class="table-cards">
            <thead><tr><th>URL</th><th>HTTP</th><th>Score</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($audits as $a): ?>
                <tr>
                    <td class="primary"><a href="<?= e(url('/admin/audits/view.php?id=' . $a['id'])) ?>"><?= e(str_limit($a['url'], 70)) ?></a></td>
                    <td data-label="HTTP"><?= (int) $a['http_status'] ?: '—' ?></td>
                    <td data-label="Score"><span class="score score-<?= score_tone((int) $a['overall_score']) ?>"><?= (int) $a['overall_score'] ?></span></td>
                    <td data-label="When" class="muted nowrap"><?= e(time_ago($a['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php else: ?>
        <?= admin_empty('Audit a page to get an on-page SEO health check.', url('/admin/audits/'), 'Run an audit', 'shield', 'No audits yet') ?>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
