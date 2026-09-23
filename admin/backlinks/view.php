<?php
require __DIR__ . '/../_init.php';

$l = backlink_find(input_int('id'));
if (!$l) {
    abort(404);
}
$verified = (bool) $l['last_checked_at'];

admin_header('Backlink: ' . $l['platform'], 'backlinks', ['Off-page' => '/admin/backlinks/', 'Backlinks' => '/admin/backlinks/', $l['platform'] => null]);
echo page_header($l['platform'], (BACKLINK_TYPES[$l['type']] ?? $l['type']) . ' · added ' . format_date($l['created_at']),
    '<a class="btn btn-outline" href="' . e(url('/admin/backlinks/edit.php?id=' . $l['id'])) . '">Edit</a>'
    . ($l['source_url'] ? action_button(url('/admin/backlinks/'), 'Verify link now', ['id' => $l['id'], 'action' => 'verify', 'back' => 'view'], 'btn-primary') : ''));
?>
<div class="grid-2-1">
    <div>
        <section class="panel">
            <div class="panel-head"><h2>Link details</h2><?= status_badge($l['status']) ?></div>
            <?= meta_list([
                'Source URL' => $l['source_url'] ? '<a href="' . e($l['source_url']) . '" target="_blank" rel="noopener noreferrer">' . e($l['source_url']) . '</a>' : '<span class="muted">Not known yet</span>',
                'Target URL' => '<a href="' . e($l['target_url']) . '" target="_blank" rel="noopener noreferrer">' . e($l['target_url']) . '</a>',
                'Anchor text' => e($l['anchor_text'] ?: '—'),
                'Relationship' => e(ucfirst($l['rel'])) . ' <span class="muted small">(follow / nofollow / ugc / sponsored)</span>',
                'Type' => e(BACKLINK_TYPES[$l['type']] ?? $l['type']),
            ]) ?>
        </section>
        <section class="panel">
            <h2>Pipeline</h2>
            <div class="flow">
                <?php $stages = ['opportunity', 'submitted', 'pending', 'live']; $idx = array_search($l['status'], $stages, true); ?>
                <?php foreach ($stages as $i => $s): ?>
                    <div class="flow-node<?= $idx !== false && $i <= $idx ? ' is-good' : ' is-missing' ?>"><small>Step <?= $i + 1 ?></small><strong><?= e(ucfirst($s)) ?></strong></div>
                    <?php if ($i < 3): ?><span class="flow-arrow" aria-hidden="true">→</span><?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php if ($l['status'] === 'rejected'): ?><p class="small"><?= status_badge('rejected') ?> This opportunity was rejected.</p><?php endif; ?>
        </section>
        <section class="panel">
            <h2>Notes</h2>
            <p class="small"><?= $l['notes'] ? nl2br(e($l['notes'])) : '<span class="muted">No notes.</span>' ?></p>
        </section>
    </div>
    <div>
        <section class="panel">
            <h2>Verification</h2>
            <?php if (!$verified): ?>
                <p><?= status_indicator('info', 'Not verified yet') ?></p>
                <p class="small muted"><?= $l['source_url'] ? 'Run a check to confirm the link exists on the source page.' : 'Add the source URL once the listing or article is published, then verify.' ?></p>
            <?php else: ?>
                <p><?= $l['status'] === 'live' ? status_indicator('pass', 'Link found on source page') : status_indicator('fail', 'Link not found') ?></p>
                <p class="small"><?= e($l['verification_message'] ?: '') ?></p>
                <p class="small muted">Last checked <?= e(format_date($l['last_checked_at'], 'j M Y, g:i a')) ?></p>
            <?php endif; ?>
            <p class="help">The checker fetches the public source page through the SSRF guard and looks for an &lt;a&gt; tag pointing to the target domain, recording its rel attribute.</p>
        </section>
        <section class="panel"><h2>History</h2><?= activity_timeline(activity_recent(8, 'backlink', (int) $l['id'])) ?></section>
    </div>
</div>
<?php admin_footer(); ?>
