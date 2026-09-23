<?php
require __DIR__ . '/../_init.php';

$status = input('status', '', 'get');
$search = mb_substr(input('q', '', 'get'), 0, 100);
$source = mb_substr(input('source', '', 'get'), 0, 255);

// CSV export of the current filter (spreadsheet-safe: formula characters are neutralised).
if (input('export', '', 'get') === 'csv') {
    $rows = leads_admin_list($status, $search, 10000, 0, $source);
    log_activity('exported', 'lead', null, 'Exported ' . count($rows) . ' leads to CSV');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Company', 'Requirement', 'Source', 'Source page', 'Keyword', 'Campaign', 'Status', 'Received']);
    foreach ($rows as $r) {
        fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v,
            [$r['id'], $r['name'], $r['email'], $r['phone'], $r['company'], $r['interest'], lead_source_label($r), $r['source_page'], $r['keyword'], $r['campaign'], $r['status'], $r['created_at']]));
    }
    exit;
}

$pager = paginate(leads_admin_count($status, $search, $source), 20);
$leads = leads_admin_list($status, $search, $pager['per_page'], $pager['offset'], $source, sort_sql(LEAD_SORTS, 'created'));
$funnel = lead_funnel();
$sources = array_column(db_all("SELECT DISTINCT source_page FROM leads ORDER BY source_page"), 'source_page');

admin_header('Leads', 'leads', ['Leads' => null]);
echo page_header('Leads', 'Enquiries from the contact page and landing pages, attributed to the page and keyword that generated them.',
    '<a class="btn btn-outline" href="' . e(query_with(['export' => 'csv', 'page' => null])) . '">' . icon('external', 'icon icon-sm') . ' Export CSV</a>');
?>
<nav class="tabs" aria-label="Lead status">
    <a href="<?= e(url('/admin/leads/') . query_with(['status' => null, 'page' => null])) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>>All<span class="count"><?= array_sum($funnel) ?></span></a>
    <?php foreach (LEAD_STATUSES as $s): ?>
        <a href="<?= e(url('/admin/leads/') . query_with(['status' => $s, 'page' => null])) ?>"<?= $status === $s ? ' aria-current="page"' : '' ?>><?= e(ucfirst($s)) ?><span class="count"><?= $funnel[$s] ?></span></a>
    <?php endforeach; ?>
</nav>

<form class="filters" method="get">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="field"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, email, company or keyword"></div>
    <div class="field"><label for="source">Landing page</label>
        <select id="source" name="source"><option value="">All pages</option>
            <?php foreach ($sources as $sp): ?><option<?= $source === $sp ? ' selected' : '' ?>><?= e($sp) ?></option><?php endforeach; ?>
        </select></div>
    <button class="btn btn-outline" type="submit">Apply</button>
    <?php if ($search || $source): ?><a class="btn btn-ghost" href="<?= e(url('/admin/leads/') . ($status ? '?status=' . $status : '')) ?>">Clear</a><?php endif; ?>
</form>

<?php if ($leads): ?>
<div class="table-wrap"><table class="table-cards">
    <thead><tr><?= th_sort('name', 'Name', 'created') ?><th>Phone</th><th>Company</th><?= th_sort('source', 'Source', 'created') ?><th>Landing page</th><th>Keyword</th><?= th_sort('status', 'Status', 'created') ?><?= th_sort('created', 'Created', 'created') ?><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($leads as $l): ?>
        <tr>
            <td class="primary"><a href="<?= e(url('/admin/leads/view.php?id=' . $l['id'])) ?>"><strong><?= e($l['name']) ?></strong></a><span class="sub"><?= e($l['email']) ?></span></td>
            <td data-label="Phone"><?= e($l['phone'] ?: '—') ?></td>
            <td data-label="Company"><?= e($l['company'] ?: '—') ?></td>
            <td data-label="Source"><?= e(lead_source_label($l)) ?></td>
            <td data-label="Landing page" class="mono"><?= e($l['source_page']) ?></td>
            <td data-label="Keyword"><?= $l['keyword'] ? e($l['keyword']) : '<span class="muted">—</span>' ?></td>
            <td data-label="Status"><?= status_badge($l['status']) ?></td>
            <td data-label="Created" class="nowrap muted" title="<?= e($l['created_at']) ?>"><?= e(time_ago($l['created_at'])) ?></td>
            <td><a href="<?= e(url('/admin/leads/view.php?id=' . $l['id'])) ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<div class="table-foot"><span>Showing <?= $pager['offset'] + 1 ?>–<?= $pager['offset'] + count($leads) ?> of <?= $pager['total'] ?></span><?= admin_pagination($pager) ?></div>
<?php else: ?>
    <?= admin_empty($search || $source || $status ? 'No leads match these filters.' : 'Enquiries from the website will appear here with the page that generated them.', '', '', 'users', 'No leads found') ?>
<?php endif; ?>
<?php admin_footer(); ?>
