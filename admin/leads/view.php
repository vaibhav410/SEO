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
        log_activity('deleted', 'lead', null, 'Lead “' . $lead['name'] . '” deleted');
        flash('success', 'Lead deleted.');
        redirect('/admin/leads/');
    }
    [$data, $errors] = validate($_POST, ['status' => 'required|in:' . implode(',', LEAD_STATUSES), 'admin_notes' => 'max:5000']);
    if (!$errors) {
        db_update('leads', $id, $data);
        if ($data['status'] !== $lead['status']) {
            log_activity('status', 'lead', $id, $lead['name'] . ': ' . $lead['status'] . ' → ' . $data['status']);
        }
        if ((string) $data['admin_notes'] !== (string) $lead['admin_notes']) {
            log_activity('updated', 'lead', $id, $lead['name'] . ': notes updated');
        }
        flash('success', 'Lead updated.');
    } else {
        flash('error', reset($errors));
    }
    redirect('/admin/leads/view.php?id=' . $id);
}

$timeline = activity_recent(20, 'lead', $id);
$landing = content_at_path($lead['source_page']);
$stages = ['new', 'contacted', 'qualified', 'converted'];
$stageIndex = array_search($lead['status'], $stages, true);

admin_header('Lead: ' . $lead['name'], 'leads', ['Leads' => '/admin/leads/', $lead['name'] => null]);
echo page_header($lead['name'], ($lead['company'] ? $lead['company'] . ' · ' : '') . 'Received ' . format_date($lead['created_at'], 'j M Y, g:i a'),
    '<a class="btn btn-primary" href="mailto:' . e($lead['email']) . '">' . icon('mail', 'icon icon-sm') . ' Email</a>'
    . action_button('', 'Delete lead', ['action' => 'delete'], 'btn-danger', 'Delete this lead permanently? This cannot be undone.'));
?>
<section class="panel">
    <div class="panel-head"><h2>Pipeline</h2><?= status_badge($lead['status']) ?></div>
    <div class="flow">
        <?php foreach ($stages as $i => $s): ?>
            <div class="flow-node<?= $stageIndex !== false && $i <= $stageIndex ? ' is-good' : ' is-missing' ?>"><small>Stage <?= $i + 1 ?></small><strong><?= e(ucfirst($s)) ?></strong></div>
            <?php if ($i < count($stages) - 1): ?><span class="flow-arrow" aria-hidden="true">→</span><?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php if (in_array($lead['status'], ['closed', 'spam'], true)): ?><p class="small muted">This lead is <?= e($lead['status']) ?>.</p><?php endif; ?>
</section>

<div class="grid-2-1">
    <div>
        <section class="panel">
            <h2>Contact information</h2>
            <?= meta_list([
                'Email' => '<a href="mailto:' . e($lead['email']) . '">' . e($lead['email']) . '</a>',
                'Phone' => $lead['phone'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $lead['phone'])) . '">' . e($lead['phone']) . '</a>' : '—',
                'Company' => e($lead['company'] ?: '—'),
                'Requirement' => e($lead['interest'] ?: '—'),
            ]) ?>
        </section>
        <section class="panel">
            <h2>Message</h2>
            <div class="message-box"><?= nl2br(e($lead['message'])) ?></div>
        </section>
        <section class="panel">
            <h2>Source attribution</h2>
            <?= meta_list([
                'Source' => e(lead_source_label($lead)),
                'Source page' => '<a class="mono" href="' . e(url($lead['source_page'])) . '" target="_blank" rel="noopener">' . e($lead['source_page']) . '</a>' . ($landing ? ' <span class="muted small">(' . e($landing['type']) . ': ' . e($landing['title']) . ')</span>' : ''),
                'Keyword' => $lead['keyword'] ? e($lead['keyword']) : '<span class="muted">—</span>',
                'Campaign' => $lead['campaign'] ? e($lead['campaign']) : '<span class="muted">None (organic / direct)</span>',
            ]) ?>
            <p class="help">Source page and keyword are recorded by the server from the page the form was on, not from values the visitor could edit.</p>
        </section>
    </div>
    <div>
        <section class="panel">
            <h2>Update lead</h2>
            <form method="post">
                <?= csrf_field() ?>
                <?= form_select('status', 'Status', $lead['status'], array_combine(LEAD_STATUSES, array_map('ucfirst', LEAD_STATUSES))) ?>
                <?= form_textarea('admin_notes', 'Internal notes', (string) $lead['admin_notes'], [], ['rows' => 5, 'placeholder' => 'Call summary, next step, follow-up date…']) ?>
                <button class="btn btn-primary btn-block" type="submit">Save changes</button>
            </form>
        </section>
        <section class="panel">
            <h2>Timeline</h2>
            <?php if (!in_array('received', array_column($timeline, 'action'), true)) {
                // Leads imported or created before activity logging existed.
                $timeline[] = ['entity_type' => 'lead', 'entity_id' => null, 'action' => 'received', 'label' => 'Enquiry received from ' . $lead['source_page'], 'created_at' => $lead['created_at'], 'user_name' => null];
            } ?>
            <?= activity_timeline($timeline) ?>
        </section>
    </div>
</div>
<?php admin_footer(); ?>
