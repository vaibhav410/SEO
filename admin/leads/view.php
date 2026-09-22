<?php
require __DIR__ . '/../_init.php';

$id = input_int('id');
$lead = db_one('SELECT * FROM leads WHERE id = ?', [$id]);
if (!$lead) {
    abort(404);
}

if (is_post()) {
    if (input('action') === 'delete') {
        db_delete('leads', $id);
        flash('success', 'Lead deleted.');
        redirect('/admin/leads/');
    }
    [$data, $errors] = validate($_POST, ['status' => 'required|in:' . implode(',', LEAD_STATUSES), 'admin_notes' => 'max:5000']);
    if (!$errors) {
        db_update('leads', $id, $data);
        flash('success', 'Lead updated.');
    } else {
        flash('error', reset($errors));
    }
    redirect('/admin/leads/view.php?id=' . $id);
}

admin_header('Lead: ' . $lead['name'], 'leads');
?>
<div class="page-head">
    <div><h1><?= e($lead['name']) ?></h1><p><a href="<?= e(url('/admin/leads/')) ?>">&larr; All leads</a></p></div>
    <?= status_badge($lead['status']) ?>
</div>
<div class="form-layout">
    <section class="panel">
        <dl class="meta">
            <dt>Email</dt><dd><a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a></dd>
            <dt>Phone</dt><dd><?= $lead['phone'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $lead['phone'])) . '">' . e($lead['phone']) . '</a>' : '—' ?></dd>
            <dt>Company</dt><dd><?= e($lead['company'] ?: '—') ?></dd>
            <dt>Interested in</dt><dd><?= e($lead['interest'] ?: '—') ?></dd>
            <dt>Source page</dt><dd class="mono"><a href="<?= e(url($lead['source_page'])) ?>" target="_blank" rel="noopener"><?= e($lead['source_page']) ?></a></dd>
            <dt>Received</dt><dd><?= e(format_date($lead['created_at'], 'j M Y, g:i a')) ?></dd>
        </dl>
        <h2>Message</h2>
        <div class="message-box"><?= e($lead['message']) ?></div>
    </section>
    <section class="panel">
        <form method="post">
            <?= csrf_field() ?>
            <?= form_select('status', 'Status', $lead['status'], array_combine(LEAD_STATUSES, array_map('ucfirst', LEAD_STATUSES))) ?>
            <?= form_textarea('admin_notes', 'Internal notes', (string) $lead['admin_notes'], [], ['rows' => 5]) ?>
            <button class="btn btn-primary" type="submit">Update lead</button>
        </form>
        <hr>
        <?= action_button('', 'Delete lead', ['action' => 'delete'], 'btn-danger btn-sm', 'Delete this lead permanently?') ?>
    </section>
</div>
<?php admin_footer(); ?>
