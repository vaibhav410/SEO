<?php
require __DIR__ . '/_init.php';

// Every figure below comes from the database. Nothing is estimated or simulated.
$counts = db_one(
    "SELECT
        (SELECT COUNT(*) FROM posts p WHERE " . POST_PUBLIC_SQL . ") AS published_posts,
        (SELECT COUNT(*) FROM posts WHERE status = 'draft') AS draft_posts,
        (SELECT COUNT(*) FROM landing_pages WHERE status = 'published') AS landing_pages,
        (SELECT COUNT(*) FROM services WHERE status = 'published') AS services,
        (SELECT COUNT(*) FROM leads WHERE status = 'new') AS new_leads,
        (SELECT COUNT(*) FROM leads WHERE status IN ('new', 'contacted', 'qualified')) AS open_leads,
        (SELECT COUNT(*) FROM leads WHERE created_at >= NOW() - INTERVAL 30 DAY AND status <> 'spam') AS leads_30d,
        (SELECT COUNT(*) FROM internal_links WHERE status = 'active') AS link_rules"
);
$kw = keyword_summary();
$backlinks = backlink_counts();

// Average of the latest audit per URL, so re-auditing one page many times does not skew the figure.
$health = db_one(
    'SELECT ROUND(AVG(a.overall_score)) AS avg_score, COUNT(*) AS pages FROM seo_audits a
     JOIN (SELECT MAX(id) AS id FROM seo_audits GROUP BY url) latest ON latest.id = a.id'
);
$recentAudits = audits_recent(5);
$recentPosts = db_all('SELECT id, title, status, updated_at FROM posts ORDER BY updated_at DESC LIMIT 5');
$recentLeads = db_all("SELECT id, name, company, source_page, status, created_at FROM leads WHERE status <> 'spam' ORDER BY created_at DESC LIMIT 5");
$sources = leads_by_source(6);
$issues = content_health_issues();
$issueCounts = array_count_values(array_column($issues, 'severity'));

admin_header('Dashboard', 'dashboard');
?>
<div class="page-head">
    <div>
        <h1>Growth dashboard</h1>
        <p>Keyword → content → landing page → internal links → organic discovery → leads.</p>
    </div>
    <div class="actions">
        <a class="btn btn-outline" href="<?= e(url('/admin/audits/')) ?>">Run SEO audit</a>
        <a class="btn btn-primary" href="<?= e(url('/admin/posts/edit.php')) ?>">New article</a>
    </div>
</div>

<div class="stats">
    <div class="stat">
        <div class="stat-label">SEO health</div>
        <?php if ($health && $health['pages'] > 0): ?>
            <div class="stat-value"><?= (int) $health['avg_score'] ?><small>/100</small></div>
            <p class="stat-note">Internal score, latest audit of <?= (int) $health['pages'] ?> URL<?= $health['pages'] > 1 ? 's' : '' ?></p>
        <?php else: ?>
            <div class="stat-value">—</div>
            <p class="stat-note"><a href="<?= e(url('/admin/audits/')) ?>">Run your first audit</a></p>
        <?php endif; ?>
    </div>
    <div class="stat">
        <div class="stat-label">Content</div>
        <div class="stat-value"><?= (int) $counts['published_posts'] ?> <small>published</small></div>
        <p class="stat-note"><?= (int) $counts['draft_posts'] ?> draft<?= $counts['draft_posts'] == 1 ? '' : 's' ?> · <?= (int) $counts['services'] ?> services</p>
    </div>
    <div class="stat">
        <div class="stat-label">Keywords</div>
        <div class="stat-value"><?= (int) $kw['total'] ?> <small>tracked</small></div>
        <p class="stat-note"><?= (int) $kw['active'] ?> targeted · <?= (int) $kw['unmapped'] ?> unmapped</p>
    </div>
    <div class="stat">
        <div class="stat-label">Landing pages</div>
        <div class="stat-value"><?= (int) $counts['landing_pages'] ?> <small>live</small></div>
        <p class="stat-note"><?= (int) $counts['link_rules'] ?> internal link rules active</p>
    </div>
    <div class="stat">
        <div class="stat-label">Off-page</div>
        <div class="stat-value"><?= $backlinks['live'] ?> <small>live</small></div>
        <p class="stat-note"><?= $backlinks['submitted'] + $backlinks['pending'] ?> in progress · <?= $backlinks['opportunity'] ?> opportunities</p>
    </div>
    <div class="stat">
        <div class="stat-label">Leads</div>
        <div class="stat-value"><?= (int) $counts['open_leads'] ?> <small>open</small></div>
        <p class="stat-note"><?= (int) $counts['new_leads'] ?> new · <?= (int) $counts['leads_30d'] ?> in last 30 days</p>
    </div>
</div>

<div class="grid-2 grid-2-1">
    <section class="panel" aria-labelledby="issues-title">
        <div class="panel-head">
            <h2 id="issues-title">Content SEO issues</h2>
            <span class="small muted">
                <?= (int) ($issueCounts['error'] ?? 0) ?> errors · <?= (int) ($issueCounts['warning'] ?? 0) ?> warnings · <?= (int) ($issueCounts['info'] ?? 0) ?> tips
            </span>
        </div>
        <?php if ($issues): ?>
            <ul class="issue-list">
                <?php foreach (array_slice($issues, 0, 8) as $i): ?>
                    <li class="issue-<?= e($i['severity']) ?>">
                        <span class="issue-icon" aria-hidden="true"><?= $i['severity'] === 'info' ? 'i' : '!' ?></span>
                        <div>
                            <strong><?= e($i['type']) ?></strong> · <?= e($i['item']) ?><br>
                            <span class="muted"><?= e($i['message']) ?></span>
                            <?php if ($i['edit']): ?> <a href="<?= e(url($i['edit'])) ?>">Fix</a><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($issues) > 8): ?><p class="small muted">+ <?= count($issues) - 8 ?> more.</p><?php endif; ?>
        <?php else: ?>
            <p class="muted">No content issues found. Nice work.</p>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="sources-title">
        <div class="panel-head"><h2 id="sources-title">Leads by landing page</h2><a class="small" href="<?= e(url('/admin/leads/')) ?>">All leads</a></div>
        <?php if ($sources): ?>
            <?php $max = max(array_column($sources, 'total')); ?>
            <?php foreach ($sources as $s): ?>
                <div class="bar">
                    <span class="mono" title="<?= e($s['source_page']) ?>"><?= e(str_limit($s['source_page'], 22)) ?></span>
                    <meter min="0" max="<?= (int) $max ?>" value="<?= (int) $s['total'] ?>"><?= (int) $s['total'] ?></meter>
                    <strong><?= (int) $s['total'] ?></strong>
                </div>
            <?php endforeach; ?>
            <p class="small muted">Which pages turn organic visitors into enquiries.</p>
        <?php else: ?>
            <p class="muted">No leads yet. Forms on landing pages and the contact page record their source automatically.</p>
        <?php endif; ?>
    </section>
</div>

<div class="grid-2">
    <section class="panel" aria-labelledby="audits-title">
        <div class="panel-head"><h2 id="audits-title">Recent audits</h2><a class="small" href="<?= e(url('/admin/audits/')) ?>">Auditor</a></div>
        <?php if ($recentAudits): ?>
            <div class="table-wrap"><table>
                <thead><tr><th>URL</th><th>Score</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($recentAudits as $a): ?>
                    <tr>
                        <td><a href="<?= e(url('/admin/audits/view.php?id=' . $a['id'])) ?>"><?= e(str_limit($a['url'], 55)) ?></a></td>
                        <td><span class="score score-<?= score_tone((int) $a['overall_score']) ?>"><?= (int) $a['overall_score'] ?></span></td>
                        <td class="nowrap muted"><?= e(time_ago($a['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <?= admin_empty('No audits yet. Audit a page to get an on-page SEO health check.', url('/admin/audits/'), 'Run an audit') ?>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="content-title">
        <div class="panel-head"><h2 id="content-title">Recent content</h2><a class="small" href="<?= e(url('/admin/posts/')) ?>">Articles</a></div>
        <?php if ($recentPosts): ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Article</th><th>Status</th><th>Updated</th></tr></thead>
                <tbody>
                <?php foreach ($recentPosts as $p): ?>
                    <tr>
                        <td><a href="<?= e(url('/admin/posts/edit.php?id=' . $p['id'])) ?>"><?= e(str_limit($p['title'], 60)) ?></a></td>
                        <td><?= status_badge($p['status']) ?></td>
                        <td class="nowrap muted"><?= e(time_ago($p['updated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <?= admin_empty('No articles yet.', url('/admin/posts/edit.php'), 'Write the first article') ?>
        <?php endif; ?>
    </section>
</div>

<section class="panel" aria-labelledby="leads-title">
    <div class="panel-head"><h2 id="leads-title">Latest leads</h2><a class="small" href="<?= e(url('/admin/leads/')) ?>">Lead inbox</a></div>
    <?php if ($recentLeads): ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Name</th><th>Company</th><th>Source page</th><th>Status</th><th>Received</th></tr></thead>
            <tbody>
            <?php foreach ($recentLeads as $l): ?>
                <tr>
                    <td><a href="<?= e(url('/admin/leads/view.php?id=' . $l['id'])) ?>"><?= e($l['name']) ?></a></td>
                    <td><?= e($l['company'] ?: '—') ?></td>
                    <td class="mono"><?= e($l['source_page']) ?></td>
                    <td><?= status_badge($l['status']) ?></td>
                    <td class="nowrap muted"><?= e(time_ago($l['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php else: ?>
        <p class="muted">No leads yet.</p>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
