<?php
require __DIR__ . '/../_init.php';

if (is_post()) {
    $link = backlink_find(input_int('id', 0, 'post'));
    if (!$link) {
        abort(404);
    }
    if (input('action') === 'delete') {
        db_delete('backlinks', (int) $link['id']);
        flash('success', 'Record deleted.');
    } elseif (input('action') === 'verify') {
        $result = backlink_verify($link);
        $update = ['last_checked_at' => date('Y-m-d H:i:s')];
        if ($result['found']) {
            $update['rel'] = $result['rel'];
            $update['status'] = 'live';
            if (!$link['anchor_text']) {
                $update['anchor_text'] = $result['anchor'];
            }
        }
        db_update('backlinks', (int) $link['id'], $update);
        flash($result['found'] ? 'success' : 'error', $link['platform'] . ': ' . $result['message']);
    }
    redirect('/admin/backlinks/' . query_with([]));
}

$status = input('status', '', 'get');
$type = input('type', '', 'get');
$links = backlinks_admin_list($status, $type);
$counts = backlink_counts();

admin_header('Off-page SEO', 'backlinks');
?>
<div class="page-head">
    <div><h1>Off-page SEO tracker</h1><p>Directory listings, citations, social profiles, guest articles and PR, tracked from idea to live link.</p></div>
    <a class="btn btn-primary" href="<?= e(url('/admin/backlinks/edit.php')) ?>">Add record</a>
</div>
<div class="alert alert-info">This tracker records manual, relationship-based outreach. It does not create links automatically. Links bought, exchanged or auto-generated for ranking violate Google's spam policies. <strong>Verify</strong> fetches the source page and confirms the link really exists.</div>

<div class="stats">
    <?php foreach ($counts as $s => $n): ?>
        <a class="stat" href="<?= e(url('/admin/backlinks/?status=' . $s)) ?>"><div class="stat-label"><?= e(ucfirst($s)) ?></div><div class="stat-value"><?= $n ?></div></a>
    <?php endforeach; ?>
</div>

<form class="filters" method="get">
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option>
        <?php foreach (BACKLINK_STATUSES as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="type">Type</label><select id="type" name="type"><option value="">All</option>
        <?php foreach (BACKLINK_TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-outline" type="submit">Filter</button>
</form>

<?php if ($links): ?>
<div class="table-wrap"><table>
    <thead><tr><th>Platform</th><th>Type</th><th>Target</th><th>Anchor / rel</th><th>Status</th><th>Checked</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($links as $l): ?>
        <tr>
            <td><a href="<?= e(url('/admin/backlinks/edit.php?id=' . $l['id'])) ?>"><strong><?= e($l['platform']) ?></strong></a>
                <?php if ($l['source_url']): ?><span class="sub"><?= e(str_limit($l['source_url'], 60)) ?></span><?php endif; ?></td>
            <td><?= e(BACKLINK_TYPES[$l['type']] ?? $l['type']) ?></td>
            <td class="mono"><?= e(str_limit(preg_replace('#^https?://#', '', $l['target_url']), 45)) ?></td>
            <td><?= e($l['anchor_text'] ?: '—') ?><span class="sub"><?= e($l['rel']) ?></span></td>
            <td><?= status_badge($l['status']) ?></td>
            <td class="nowrap muted"><?= e(time_ago($l['last_checked_at'])) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/backlinks/edit.php?id=' . $l['id'])) ?>">Edit</a>
                <?php if ($l['source_url']): ?><?= action_button('', 'Verify', ['id' => $l['id'], 'action' => 'verify']) ?><?php endif; ?>
                <?= action_button('', 'Delete', ['id' => $l['id'], 'action' => 'delete'], 'btn-link danger', 'Delete this record?') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php else: ?>
    <?= admin_empty('No records match.', url('/admin/backlinks/edit.php'), 'Add a record') ?>
<?php endif; ?>
<?php admin_footer(); ?>
