<?php
require __DIR__ . '/../_init.php';

if (is_post() && input('action') === 'delete') {
    $kw = keyword_find(input_int('id', 0, 'post'));
    if (!$kw) {
        abort(404);
    }
    db_delete('keywords', (int) $kw['id']);
    log_activity('deleted', 'keyword', null, 'Keyword “' . $kw['keyword'] . '” removed from the plan');
    flash('success', 'Keyword “' . $kw['keyword'] . '” removed from the plan.');
    redirect('/admin/keywords/' . query_with([]));
}

$filters = ['intent' => input('intent', '', 'get'), 'priority' => input('priority', '', 'get'), 'status' => input('status', '', 'get'), 'q' => mb_substr(input('q', '', 'get'), 0, 100)];
$keywords = keywords_admin_list($filters, sort_sql(KEYWORD_SORTS, 'priority', 'asc'));
$summary = keyword_summary();
$all = keywords_admin_list([]);
$intentCounts = array_count_values(array_column($all, 'intent'));

// Keyword -> content mapping matrix (intent x content type).
$matrix = [];
foreach ($all as $k) {
    $matrix[$k['intent']][$k['content_type'] ?? 'unmapped'] = ($matrix[$k['intent']][$k['content_type'] ?? 'unmapped'] ?? 0) + 1;
}
$types = KEYWORD_CONTENT_TYPES + ['unmapped' => 'No page yet'];

admin_header('Keywords', 'keywords', ['Keywords' => null]);
echo page_header('Keyword manager', 'Plan each search phrase, map it to exactly one page and check that page actually covers it.',
    '<a class="btn btn-primary" href="' . e(url('/admin/keywords/edit.php')) . '">' . icon('search', 'icon icon-sm') . ' Add keyword</a>');
?>
<section class="kpis">
    <?= kpi_card(['label' => 'Tracked', 'value' => (string) $summary['total'], 'icon' => 'search']) ?>
    <?= kpi_card(['label' => 'Targeted / mapped', 'value' => (string) $summary['active'], 'icon' => 'check']) ?>
    <?= kpi_card(['label' => 'High priority', 'value' => (string) $summary['high'], 'icon' => 'trending']) ?>
    <?= kpi_card(['label' => 'No target page', 'value' => (string) $summary['unmapped'], 'icon' => 'zap', 'href' => '/admin/opportunities/?type=keyword']) ?>
    <?= kpi_card(['label' => 'Search volume', 'value' => null, 'icon' => 'layers', 'connect' => 'Data unavailable. Validate in Keyword Planner or Search Console.']) ?>
</section>

<section class="panel">
    <div class="panel-head"><h2>Keyword → content mapping</h2><span class="small muted">How keywords by search intent map to content types</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Intent</th><?php foreach ($types as $t => $label): ?><th class="right"><?= e($label) ?></th><?php endforeach; ?><th class="right">Total</th></tr></thead>
        <tbody>
        <?php foreach (KEYWORD_INTENTS as $intent): ?>
            <tr><td><strong><?= e(ucfirst($intent)) ?></strong><span class="sub">Best as: <?= e(KEYWORD_CONTENT_TYPES[keyword_recommended_type($intent)]) ?></span></td>
                <?php foreach ($types as $t => $label): $n = $matrix[$intent][$t] ?? 0; ?>
                    <td class="right"><?= $n ? ($t === 'unmapped' ? '<span class="badge badge-warning">' . $n . '</span>' : '<strong>' . $n . '</strong>') : '<span class="muted">·</span>' ?></td>
                <?php endforeach; ?>
                <td class="right"><?= (int) ($intentCounts[$intent] ?? 0) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<nav class="tabs" aria-label="Search intent">
    <a href="<?= e(url('/admin/keywords/') . query_with(['intent' => null])) ?>"<?= $filters['intent'] === '' ? ' aria-current="page"' : '' ?>>All<span class="count"><?= count($all) ?></span></a>
    <?php foreach (KEYWORD_INTENTS as $i): ?>
        <a href="<?= e(url('/admin/keywords/') . query_with(['intent' => $i])) ?>"<?= $filters['intent'] === $i ? ' aria-current="page"' : '' ?>><?= ucfirst($i) ?><span class="count"><?= (int) ($intentCounts[$i] ?? 0) ?></span></a>
    <?php endforeach; ?>
</nav>
<form class="filters" method="get">
    <input type="hidden" name="intent" value="<?= e($filters['intent']) ?>">
    <div class="field"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Keyword contains…"></div>
    <?php foreach (['priority' => KEYWORD_PRIORITIES, 'status' => KEYWORD_STATUSES] as $name => $opts): ?>
        <div class="field"><label for="<?= $name ?>"><?= ucfirst($name) ?></label>
            <select id="<?= $name ?>" name="<?= $name ?>"><option value="">All</option>
                <?php foreach ($opts as $o): ?><option value="<?= $o ?>"<?= $filters[$name] === $o ? ' selected' : '' ?>><?= ucfirst($o) ?></option><?php endforeach; ?>
            </select></div>
    <?php endforeach; ?>
    <button class="btn btn-outline" type="submit">Apply</button>
</form>

<?php if ($keywords): ?>
<div class="table-wrap"><table class="table-cards">
    <thead><tr>
        <?= th_sort('keyword', 'Keyword', 'priority') ?><?= th_sort('intent', 'Search intent', 'priority') ?><th>Target page</th><th>Content type</th>
        <?= th_sort('priority', 'Priority', 'priority') ?><th>Difficulty</th><th>Coverage</th><?= th_sort('status', 'Status', 'priority') ?><?= th_sort('updated', 'Last updated', 'priority') ?><th><span class="visually-hidden">Actions</span></th>
    </tr></thead>
    <tbody>
    <?php foreach ($keywords as $k): $cov = keyword_coverage($k); ?>
        <tr>
            <td class="primary"><a href="<?= e(url('/admin/keywords/view.php?id=' . $k['id'])) ?>"><strong><?= e($k['keyword']) ?></strong></a><?php if ($k['service_name']): ?><span class="sub"><?= e($k['service_name']) ?></span><?php endif; ?></td>
            <td data-label="Intent"><?= e(ucfirst($k['intent'])) ?></td>
            <td data-label="Target" class="mono"><?= $k['target_url'] ? e($k['target_url']) : '<span class="muted">—</span>' ?></td>
            <td data-label="Content type"><?= $k['content_type'] ? e(KEYWORD_CONTENT_TYPES[$k['content_type']]) : '<span class="muted">—</span>' ?></td>
            <td data-label="Priority"><?= status_badge($k['priority']) ?></td>
            <td data-label="Difficulty"><span class="muted small" data-tooltip="Connect a keyword research source to see difficulty">Data unavailable</span></td>
            <td data-label="Coverage">
                <?php if ($cov['status'] === 'ok'): ?><span class="score score-<?= score_tone($cov['score']) ?>" tabindex="0" data-tooltip="<?= e(implode(' · ', array_map(fn($c, $ok) => $c . ($ok ? ' ✓' : ' ✗'), array_keys($cov['checks']), $cov['checks']))) ?>"><?= $cov['score'] ?>%</span>
                <?php elseif ($cov['status'] === 'missing'): ?><span class="badge badge-danger">Page not found</span>
                <?php elseif ($cov['status'] === 'external'): ?><span class="badge badge-muted">External</span>
                <?php else: ?><a class="small" href="<?= e(url(($k['intent'] === 'informational' ? '/admin/posts/edit.php?keyword=' : '/admin/landing-pages/edit.php?keyword=') . rawurlencode($k['keyword']))) ?>">Create page</a><?php endif; ?>
            </td>
            <td data-label="Status"><?= status_badge($k['status']) ?></td>
            <td data-label="Updated" class="muted nowrap"><?= e(time_ago($k['updated_at'])) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/keywords/view.php?id=' . $k['id'])) ?>">View</a>
                <a href="<?= e(url('/admin/keywords/edit.php?id=' . $k['id'])) ?>">Edit</a>
                <?= action_button('', 'Delete', ['id' => $k['id'], 'action' => 'delete'], 'btn-link danger', 'Remove “' . $k['keyword'] . '” from the keyword plan?') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<p class="small muted">Coverage checks whether the keyword's words appear in the target page's title, meta title, meta description and body. Hover or focus a score for detail. Search volume and difficulty are never estimated.</p>
<?php else: ?>
    <?= admin_empty('No keywords match these filters.', url('/admin/keywords/edit.php'), 'Add keyword', 'search', 'No keywords found') ?>
<?php endif; ?>
<?php admin_footer(); ?>
