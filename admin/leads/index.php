<?php
require __DIR__ . '/../_init.php';

$status = input('status', '', 'get');
$search = mb_substr(input('q', '', 'get'), 0, 100);
$source = mb_substr(input('source', '', 'get'), 0, 255);

// CSV export of the current filter (spreadsheet-safe: formula characters are neutralised).
if (input('export', '', 'get') === 'csv') {
    $rows = leads_admin_list($status, $search, 10000, 0, $source);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Company', 'Interest', 'Source page', 'Status', 'Received']);
    foreach ($rows as $r) {
        fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v,
            [$r['id'], $r['name'], $r['email'], $r['phone'], $r['company'], $r['interest'], $r['source_page'], $r['status'], $r['created_at']]));
    }
    exit;
}

$pager = paginate(leads_admin_count($status, $search, $source), 25);
$leads = leads_admin_list($status, $search, $pager['per_page'], $pager['offset'], $source);

admin_header('Leads', 'leads');
?>
<div class="page-head">
    <div><h1>Leads</h1><p>Enquiries from the contact page and landing pages, with the page that generated them.</p></div>
    <a class="btn btn-outline" href="<?= e(query_with(['export' => 'csv', 'page' => null])) ?>">Export CSV</a>
</div>

<nav class="tabs" aria-label="Filter by status">
    <a href="<?= e(url('/admin/leads/')) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>>All</a>
    <?php foreach (LEAD_STATUSES as $s): ?>
        <a href="<?= e(url('/admin/leads/?status=' . $s)) ?>"<?= $status === $s ? ' aria-current="page"' : '' ?>><?= e(ucfirst($s)) ?></a>
    <?php endforeach; ?>
</nav>
<form class="filters" method="get">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="field"><label for="q">Search name, email or company</label><input type="search" id="q" name="q" value="<?= e($search) ?>"></div>
    <div class="field"><label for="source">Source page</label><input type="text" id="source" name="source" value="<?= e($source) ?>" placeholder="/web-hosting-india"></div>
    <button class="btn btn-outline" type="submit">Filter</button>
</form>

<?php if ($leads): ?>
<div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Company</th><th>Interest</th><th>Source page</th><th>Status</th><th>Received</th></tr></thead>
    <tbody>
    <?php foreach ($leads as $l): ?>
        <tr>
            <td><a href="<?= e(url('/admin/leads/view.php?id=' . $l['id'])) ?>"><strong><?= e($l['name']) ?></strong></a><span class="sub"><?= e($l['email']) ?></span></td>
            <td><?= e($l['company'] ?: '—') ?></td>
            <td><?= e($l['interest'] ?: '—') ?></td>
            <td class="mono"><?= e($l['source_page']) ?></td>
            <td><?= status_badge($l['status']) ?></td>
            <td class="nowrap muted" title="<?= e($l['created_at']) ?>"><?= e(time_ago($l['created_at'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?= admin_pagination($pager) ?>
<?php else: ?>
    <?= admin_empty('No leads match these filters.') ?>
<?php endif; ?>
<?php admin_footer(); ?>
