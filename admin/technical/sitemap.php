<?php
require __DIR__ . '/../_init.php';

$entries = sitemap_entries();
$xml = sitemap_xml($entries);
$valid = simplexml_load_string($xml) !== false;
$type = input('type', '', 'get');
$types = array_count_values(array_column($entries, 'type'));
$visible = array_filter($entries, fn($e) => $type === '' || $e['type'] === $type);

if (is_post() && input('action') === 'regenerate') {
    // The sitemap is generated on every request, so "regenerate" re-validates and records the check.
    log_activity('verified', 'system', null, 'Sitemap validated: ' . count($entries) . ' URLs, ' . ($valid ? 'valid XML' : 'INVALID XML'));
    flash($valid ? 'success' : 'error', $valid ? 'Sitemap regenerated and validated: ' . count($entries) . ' URLs.' : 'The sitemap XML is invalid. Check recent content changes.');
    redirect('/admin/technical/sitemap.php');
}
$lastCheck = db_one("SELECT created_at FROM activity_log WHERE entity_type = 'system' AND label LIKE 'Sitemap validated%' ORDER BY id DESC LIMIT 1");

admin_header('Sitemap', 'sitemap', ['Technical SEO' => '/admin/technical/', 'Sitemap' => null]);
echo page_header('Sitemap manager', 'Every public, indexable URL. Drafts, scheduled posts, admin and search pages are excluded automatically.',
    '<form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="action" value="regenerate"><button class="btn btn-outline" type="submit">Generate &amp; validate</button></form>'
    . '<a class="btn btn-primary" href="' . e(url('/sitemap.xml')) . '" target="_blank" rel="noopener">View sitemap.xml</a>');
echo tabs(['/admin/technical/' => 'Overview', '/admin/technical/schema.php' => 'Structured data', '/admin/technical/sitemap.php' => 'Sitemap', '/admin/technical/robots.php' => 'Robots.txt'], '/admin/technical/sitemap.php', 'Technical SEO sections');
?>
<section class="kpis">
    <?= kpi_card(['label' => 'URLs', 'value' => (string) count($entries), 'icon' => 'layers']) ?>
    <?= kpi_card(['label' => 'XML status', 'value' => $valid ? 'Valid' : 'Invalid', 'icon' => 'check', 'note' => number_format(strlen($xml) / 1024, 1) . ' KB']) ?>
    <?= kpi_card(['label' => 'Generation', 'value' => 'Live', 'icon' => 'zap', 'note' => 'Rebuilt on every request · last validated ' . ($lastCheck ? time_ago($lastCheck['created_at']) : 'never')]) ?>
</section>

<nav class="pill-tabs" aria-label="URL type">
    <a href="<?= e(url('/admin/technical/sitemap.php')) ?>"<?= $type === '' ? ' aria-current="page"' : '' ?>>All (<?= count($entries) ?>)</a>
    <?php foreach ($types as $t => $n): ?><a href="<?= e(url('/admin/technical/sitemap.php?type=' . rawurlencode($t))) ?>"<?= $type === $t ? ' aria-current="page"' : '' ?>><?= e($t) ?> (<?= $n ?>)</a><?php endforeach; ?>
</nav>

<div class="table-wrap"><table class="table-cards">
    <thead><tr><th>URL</th><th>Type</th><th>Priority</th><th>Last modified</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($visible as $e): ?>
        <tr>
            <td class="primary"><a href="<?= e(url($e['path'])) ?>" target="_blank" rel="noopener" class="mono"><?= e(absolute_url($e['path'])) ?></a><span class="sub"><?= e($e['title']) ?></span></td>
            <td data-label="Type"><span class="badge badge-muted"><?= e($e['type']) ?></span></td>
            <td data-label="Priority" class="mono"><?= e($e['priority']) ?></td>
            <td data-label="Last modified" class="muted nowrap"><?= $e['lastmod'] ? e(format_date($e['lastmod'])) : '—' ?></td>
            <td data-label="Status"><?= status_indicator('healthy', 'Indexable') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<p class="small muted">Submit <code><?= e(absolute_url('/sitemap.xml')) ?></code> in Google Search Console after deployment. Google ignores the priority value; it is included for other crawlers.</p>
<?php admin_footer(); ?>
