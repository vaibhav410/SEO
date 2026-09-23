<?php
require __DIR__ . '/../_init.php';

if (is_post()) {
    $status = input('status');
    opportunity_set_status(input('key'), $status, (int) $user['id']);
    log_activity('status', 'opportunity', null, 'Opportunity “' . str_limit(input('title'), 80) . '” marked ' . str_replace('_', ' ', $status));
    flash('success', 'Opportunity updated.');
    redirect('/admin/opportunities/' . query_with([]));
}

$all = opportunities_all();
$type = input('type', '', 'get');
$view = input('view', 'open', 'get');
$visible = array_filter($all, function ($o) use ($type, $view) {
    $open = in_array($o['status'], ['open', 'in_progress'], true);
    return ($type === '' || $o['type'] === $type) && ($view === 'all' || ($view === 'open' ? $open : !$open));
});
$counts = [];
foreach ($all as $o) {
    if (in_array($o['status'], ['open', 'in_progress'], true)) {
        $counts[$o['type']] = ($counts[$o['type']] ?? 0) + 1;
    }
}
$priorityCounts = array_count_values(array_column(array_filter($all, fn($o) => $o['status'] === 'open'), 'priority'));

admin_header('Opportunities', 'opportunities', ['Opportunities' => null]);
echo page_header('Content opportunity centre', 'Growth opportunities found in your own keyword plan, content, links, off-page pipeline and audits. No invented search volumes.');
?>
<section class="kpis">
    <?= kpi_card(['label' => 'Open opportunities', 'value' => (string) array_sum($counts), 'icon' => 'zap']) ?>
    <?= kpi_card(['label' => 'High priority', 'value' => (string) ($priorityCounts['high'] ?? 0), 'icon' => 'trending']) ?>
    <?= kpi_card(['label' => 'Completed', 'value' => (string) count(array_filter($all, fn($o) => $o['status'] === 'done')), 'icon' => 'check']) ?>
</section>

<nav class="tabs" aria-label="Opportunity type">
    <a href="<?= e(url('/admin/opportunities/') . query_with(['type' => null])) ?>"<?= $type === '' ? ' aria-current="page"' : '' ?>>All<span class="count"><?= array_sum($counts) ?></span></a>
    <?php foreach (OPPORTUNITY_TYPES as $key => $label): ?>
        <a href="<?= e(url('/admin/opportunities/') . query_with(['type' => $key])) ?>"<?= $type === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><span class="count"><?= (int) ($counts[$key] ?? 0) ?></span></a>
    <?php endforeach; ?>
</nav>
<nav class="pill-tabs" aria-label="Status">
    <?php foreach (['open' => 'Open', 'closed' => 'Done & dismissed', 'all' => 'All'] as $v => $l): ?>
        <a href="<?= e(url('/admin/opportunities/') . query_with(['view' => $v === 'open' ? null : $v])) ?>"<?= $view === $v ? ' aria-current="page"' : '' ?>><?= e($l) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($visible): ?>
    <div class="opp-list">
        <?php foreach ($visible as $o): $closed = in_array($o['status'], ['done', 'dismissed'], true); ?>
            <article class="opp<?= $closed ? ' is-closed' : '' ?>">
                <div>
                    <div class="opp-meta">
                        <?= status_badge($o['priority']) ?>
                        <span class="badge badge-muted"><?= e(OPPORTUNITY_TYPES[$o['type']]) ?></span>
                        <?php if ($o['status'] !== 'open'): ?><?= status_badge($o['status']) ?><?php endif; ?>
                    </div>
                    <h3><?= e($o['title']) ?></h3>
                    <p class="muted"><strong>Reason:</strong> <?= e($o['reason']) ?></p>
                    <p><strong>Recommended action:</strong> <?= e($o['action']) ?></p>
                </div>
                <div class="actions">
                    <?php if ($o['url'] && !$closed): ?><a class="btn btn-primary btn-sm" href="<?= e(url($o['url'])) ?>">Take action</a><?php endif; ?>
                    <?php foreach ($closed ? ['open' => 'Reopen'] : array_filter(['in_progress' => $o['status'] === 'in_progress' ? null : 'In progress', 'done' => 'Mark done', 'dismissed' => 'Dismiss']) as $s => $label): ?>
                        <form method="post" class="inline-form"><?= csrf_field() ?>
                            <input type="hidden" name="key" value="<?= e($o['key']) ?>"><input type="hidden" name="status" value="<?= e($s) ?>"><input type="hidden" name="title" value="<?= e($o['title']) ?>">
                            <button class="btn btn-outline btn-sm" type="submit"><?= e($label) ?></button></form>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?= admin_empty($view === 'open' ? 'No open opportunities in this category. Nice work.' : 'Nothing here yet.', '', '', 'check', 'All clear') ?>
<?php endif; ?>
<?php admin_footer(); ?>
