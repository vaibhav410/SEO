<?php
require __DIR__ . '/../_init.php';

if (is_post()) {
    $link = backlink_find(input_int('id', 0, 'post'));
    if (!$link) {
        abort(404);
    }
    if (input('action') === 'delete') {
        db_delete('backlinks', (int) $link['id']);
        log_activity('deleted', 'backlink', null, 'Off-page record “' . $link['platform'] . '” deleted');
        flash('success', 'Record deleted.');
    } elseif (input('action') === 'verify') {
        $result = backlink_verify_and_save($link);
        flash($result['found'] ? 'success' : 'error', $link['platform'] . ': ' . $result['message']);
    }
    redirect(input('back') === 'view' ? '/admin/backlinks/view.php?id=' . $link['id'] : '/admin/backlinks/' . query_with([]));
}

// Sections group the backlink types the way outreach work is usually organised.
$sections = [
    '' => ['All', []],
    'directories' => ['Directory opportunities', ['directory']],
    'listings' => ['Business listings', ['citation']],
    'communities' => ['Relevant communities', ['forum']],
    'partners' => ['Partnerships', ['partner']],
    'content' => ['Guest / content & PR', ['guest_post', 'pr']],
    'social' => ['Social profiles', ['social']],
];
$section = isset($sections[input('section', '', 'get')]) ? input('section', '', 'get') : '';
$status = input('status', '', 'get');
$links = array_filter(backlinks_admin_list($status, ''), fn($l) => $section === '' || in_array($l['type'], $sections[$section][1], true));
$counts = backlink_counts();

admin_header('Backlinks', 'backlinks', ['Off-page' => '/admin/backlinks/', 'Backlinks' => null]);
echo page_header('Backlinks & off-page SEO', 'Track legitimate listings, citations, partnerships, guest articles and mentions from idea to verified live link.',
    '<a class="btn btn-primary" href="' . e(url('/admin/backlinks/edit.php')) . '">' . icon('globe', 'icon icon-sm') . ' Track opportunity</a>');
?>
<div class="alert alert-info"><?= icon('shield', 'icon icon-sm') ?><span>GrowthHub tracks manual, relationship-based outreach. It never creates links automatically. Buying, exchanging or auto-generating links for rankings breaks Google's spam policies. <strong>Verify</strong> fetches the source page (SSRF-protected) and confirms the link exists.</span></div>

<section class="kpis">
    <?php foreach ($counts as $s => $n): ?>
        <a class="kpi" href="<?= e(url('/admin/backlinks/') . query_with(['status' => $status === $s ? null : $s])) ?>"<?= $status === $s ? ' aria-current="true"' : '' ?>>
            <div class="kpi-top"><span class="kpi-label"><?= e(ucfirst($s)) ?></span><?= status_badge($s) ?></div><div class="kpi-value"><?= $n ?></div></a>
    <?php endforeach; ?>
</section>

<nav class="tabs" aria-label="Off-page sections">
    <?php foreach ($sections as $key => [$label, $types]): ?>
        <a href="<?= e(url('/admin/backlinks/') . query_with(['section' => $key ?: null])) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($status): ?><p class="small">Filtered by status <?= status_badge($status) ?> · <a href="<?= e(url('/admin/backlinks/') . query_with(['status' => null])) ?>">Clear</a></p><?php endif; ?>

<?php if ($links): ?>
<div class="table-wrap"><table class="table-cards">
    <thead><tr><th>Source</th><th>Target URL</th><th>Anchor</th><th>Type</th><th>Status</th><th>Last checked</th><th>Verification</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($links as $l): ?>
        <tr>
            <td class="primary"><a href="<?= e(url('/admin/backlinks/view.php?id=' . $l['id'])) ?>"><strong><?= e($l['platform']) ?></strong></a>
                <span class="sub"><?= $l['source_url'] ? e(str_limit(preg_replace('#^https?://#', '', $l['source_url']), 55)) : 'No source URL yet' ?></span></td>
            <td data-label="Target" class="mono"><?= e(str_limit(preg_replace('#^https?://#', '', $l['target_url']), 40)) ?></td>
            <td data-label="Anchor"><?= e($l['anchor_text'] ?: '—') ?></td>
            <td data-label="Type"><?= e(BACKLINK_TYPES[$l['type']] ?? $l['type']) ?><span class="sub"><?= e($l['rel']) ?></span></td>
            <td data-label="Status"><?= status_badge($l['status']) ?></td>
            <td data-label="Checked" class="muted nowrap"><?= e(time_ago($l['last_checked_at'])) ?></td>
            <td data-label="Verification"><?= !$l['last_checked_at'] ? '<span class="muted small">Not verified</span>'
                : ($l['status'] === 'live' ? status_indicator('pass', 'Link found') : status_indicator('fail', 'Not found')) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/backlinks/view.php?id=' . $l['id'])) ?>">View</a>
                <?php if ($l['source_url']): ?><?= action_button('', 'Verify', ['id' => $l['id'], 'action' => 'verify']) ?><?php endif; ?>
                <?= action_button('', 'Delete', ['id' => $l['id'], 'action' => 'delete'], 'btn-link danger', 'Delete the off-page record for “' . $l['platform'] . '”?') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php else: ?>
    <?= admin_empty('Nothing tracked in this section yet.', url('/admin/backlinks/edit.php'), 'Track opportunity', 'globe', 'No records') ?>
<?php endif; ?>
<?php admin_footer(); ?>
