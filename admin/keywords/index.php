<?php
require __DIR__ . '/../_init.php';

if (is_post() && input('action') === 'delete') {
    $kw = keyword_find(input_int('id', 0, 'post'));
    if (!$kw) {
        abort(404);
    }
    db_delete('keywords', (int) $kw['id']);
    flash('success', 'Keyword “' . $kw['keyword'] . '” removed from the plan.');
    redirect('/admin/keywords/' . query_with([]));
}

$filters = ['intent' => input('intent', '', 'get'), 'priority' => input('priority', '', 'get'), 'status' => input('status', '', 'get'), 'q' => mb_substr(input('q', '', 'get'), 0, 100)];
$keywords = keywords_admin_list($filters);
$summary = keyword_summary();

admin_header('Keywords', 'keywords');
?>
<div class="page-head">
    <div><h1>Keyword plan</h1><p>Map each search phrase to one page, then check that page actually covers it.</p></div>
    <a class="btn btn-primary" href="<?= e(url('/admin/keywords/edit.php')) ?>">Add keyword</a>
</div>

<div class="stats">
    <div class="stat"><div class="stat-label">Tracked</div><div class="stat-value"><?= (int) $summary['total'] ?></div></div>
    <div class="stat"><div class="stat-label">Targeted / mapped</div><div class="stat-value"><?= (int) $summary['active'] ?></div></div>
    <div class="stat"><div class="stat-label">High priority</div><div class="stat-value"><?= (int) $summary['high'] ?></div></div>
    <div class="stat"><div class="stat-label">No target page</div><div class="stat-value"><?= (int) $summary['unmapped'] ?></div></div>
</div>
<p class="small muted">Search volumes are deliberately not stored here. Validate demand in Google Keyword Planner or Search Console and record the figure and its source in the notes.</p>

<form class="filters" method="get">
    <div class="field"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($filters['q']) ?>"></div>
    <?php foreach (['intent' => KEYWORD_INTENTS, 'priority' => KEYWORD_PRIORITIES, 'status' => KEYWORD_STATUSES] as $name => $opts): ?>
        <div class="field"><label for="<?= $name ?>"><?= ucfirst($name) ?></label>
            <select id="<?= $name ?>" name="<?= $name ?>"><option value="">All</option>
                <?php foreach ($opts as $o): ?><option value="<?= $o ?>"<?= $filters[$name] === $o ? ' selected' : '' ?>><?= ucfirst($o) ?></option><?php endforeach; ?>
            </select></div>
    <?php endforeach; ?>
    <button class="btn btn-outline" type="submit">Filter</button>
</form>

<?php if ($keywords): ?>
<div class="table-wrap"><table>
    <thead><tr><th>Keyword</th><th>Intent</th><th>Priority</th><th>Target page</th><th>On-page coverage</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($keywords as $k): $cov = keyword_coverage($k); ?>
        <tr>
            <td><a href="<?= e(url('/admin/keywords/edit.php?id=' . $k['id'])) ?>"><strong><?= e($k['keyword']) ?></strong></a></td>
            <td><?= e(ucfirst($k['intent'])) ?></td>
            <td><span class="badge badge-<?= ['high' => 'danger', 'medium' => 'warning', 'low' => 'muted'][$k['priority']] ?>"><?= e(ucfirst($k['priority'])) ?></span></td>
            <td class="mono">
                <?php if ($k['target_url']): ?>
                    <?= e($k['target_url']) ?>
                    <?php if ($cov['target']): ?><span class="sub"><?= e($cov['target']['type']) ?></span><?php endif; ?>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($cov['status'] === 'ok'): ?>
                    <span class="score score-<?= score_tone($cov['score']) ?>" title="<?= e(implode(', ', array_map(fn($c, $ok) => $c . ($ok ? ' ✓' : ' ✗'), array_keys($cov['checks']), $cov['checks']))) ?>"><?= $cov['score'] ?>%</span>
                <?php elseif ($cov['status'] === 'missing'): ?>
                    <span class="badge badge-danger">Page not found</span>
                <?php elseif ($cov['status'] === 'external'): ?>
                    <span class="badge badge-muted">External</span>
                <?php else: ?>
                    <a class="small" href="<?= e(url('/admin/landing-pages/edit.php?keyword=' . rawurlencode($k['keyword']))) ?>">Create page</a>
                <?php endif; ?>
            </td>
            <td><?= status_badge($k['status']) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/keywords/edit.php?id=' . $k['id'])) ?>">Edit</a>
                <?= action_button('', 'Delete', ['id' => $k['id'], 'action' => 'delete'], 'btn-link danger', 'Remove this keyword from the plan?') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<p class="small muted">Coverage checks whether the keyword's words appear in the target page's title, meta title, meta description and body. Hover a score for detail.</p>
<?php else: ?>
    <?= admin_empty('No keywords match.', url('/admin/keywords/edit.php'), 'Add a keyword') ?>
<?php endif; ?>
<?php admin_footer(); ?>
