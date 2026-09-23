<?php
require __DIR__ . '/../_init.php';

$id = input_int('id') ?: null;
$link = $id ? backlink_find($id) : null;
if ($id && !$link) {
    abort(404);
}
$errors = [];
$values = $link ?? ['platform' => '', 'type' => 'directory', 'source_url' => '', 'target_url' => setting('main_site_url', 'https://syscom.co.in') . '/',
    'anchor_text' => '', 'rel' => 'unknown', 'status' => 'opportunity', 'notes' => ''];

if (is_post()) {
    [$data, $errors] = backlink_validate($_POST);
    if (!$errors) {
        $isNew = !$id;
        $id ? db_update('backlinks', $id, $data) : ($id = db_insert('backlinks', $data));
        log_activity($isNew ? 'created' : 'status', 'backlink', $id, $data['platform'] . ': ' . ($isNew ? 'opportunity added' : 'marked ' . $data['status']));
        flash('success', 'Record saved.');
        redirect('/admin/backlinks/view.php?id=' . $id);
    }
    $values = array_merge($values, $data);
}

admin_header($id ? 'Edit off-page record' : 'New off-page record', 'backlinks', ['Off-page' => '/admin/backlinks/', 'Backlinks' => '/admin/backlinks/', $id ? 'Edit' : 'New' => null]);
?>
<div class="page-head">
    <div><h1><?= $id ? 'Edit record' : 'New off-page record' ?></h1><p><a href="<?= e(url('/admin/backlinks/')) ?>">&larr; Off-page tracker</a></p></div>
</div>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields.</div><?php endif; ?>
<form method="post" class="form-layout" novalidate>
    <?= csrf_field() ?>
    <section class="panel">
        <div class="form-row">
            <?= form_input('platform', 'Platform / website', $values['platform'], $errors, ['required' => true, 'maxlength' => 120]) ?>
            <?= form_select('type', 'Type', $values['type'], BACKLINK_TYPES, $errors) ?>
        </div>
        <?= form_input('source_url', 'Source URL (page that links to us)', (string) $values['source_url'], $errors, ['type' => 'url', 'maxlength' => 500]) ?>
        <?= form_input('target_url', 'Target URL (our page)', $values['target_url'], $errors, ['type' => 'url', 'required' => true, 'maxlength' => 500]) ?>
        <div class="form-row">
            <?= form_input('anchor_text', 'Anchor text', (string) $values['anchor_text'], $errors, ['maxlength' => 200, 'help' => 'Natural, varied anchors (brand name, URL, descriptive phrase) look organic. Avoid exact-match keywords everywhere.']) ?>
            <?= form_select('rel', 'Link type', $values['rel'], array_combine(BACKLINK_RELS, ['Unknown', 'Follow', 'Nofollow', 'UGC', 'Sponsored']), $errors) ?>
        </div>
        <?= form_textarea('notes', 'Notes', (string) $values['notes'], $errors, ['rows' => 4, 'help' => 'Contact person, submission date, follow-up reminders.']) ?>
    </section>
    <section class="panel">
        <?= form_select('status', 'Status', $values['status'], array_combine(BACKLINK_STATUSES, array_map('ucfirst', BACKLINK_STATUSES)), $errors) ?>
        <p class="small muted">Opportunity → Submitted → Pending → Live (or Rejected).</p>
        <button class="btn btn-primary" type="submit">Save</button>
        <?php if ($link && $link['last_checked_at']): ?><p class="small muted">Last verified <?= e(time_ago($link['last_checked_at'])) ?>.</p><?php endif; ?>
    </section>
</form>
<?php admin_footer(); ?>
