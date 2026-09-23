<?php
require __DIR__ . '/../_init.php';

if (is_post() && input('action') === 'delete') {
    $faq = faq_find(input_int('id', 0, 'post'));
    if (!$faq) {
        abort(404);
    }
    db_delete('faqs', (int) $faq['id']);
    log_activity('deleted', 'faq', null, 'FAQ “' . str_limit($faq['question'], 80) . '” deleted');
    flash('success', 'FAQ deleted.');
    redirect('/admin/faqs/' . query_with([]));
}

$owner = input('owner', '', 'get');
$faqs = faqs_admin_list($owner);
$tabs = ['' => 'All', 'general' => 'General', 'service' => 'Services', 'post' => 'Articles', 'landing' => 'Landing pages'];

admin_header('FAQs', 'faqs', ['Content' => '/admin/posts/', 'FAQs' => null]);
?>
<div class="page-head">
    <div><h1>FAQs</h1><p>Questions shown on the FAQ page, services, articles and landing pages. Published FAQs also generate FAQPage structured data on the page where they appear.</p></div>
    <a class="btn btn-primary" href="<?= e(url('/admin/faqs/edit.php')) ?>">New FAQ</a>
</div>
<nav class="pill-tabs" aria-label="Filter FAQs">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= e(url('/admin/faqs/') . ($key ? '?owner=' . $key : '')) ?>"<?= $owner === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($faqs): ?>
<div class="table-wrap"><table class="table-cards">
    <thead><tr><th>Question</th><th>Shown on</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($faqs as $f):
        $where = $f['service_name'] ? 'Service: ' . $f['service_name'] : ($f['post_title'] ? 'Article: ' . $f['post_title'] : ($f['landing_title'] ? 'Landing: ' . $f['landing_title'] : 'General FAQ page')); ?>
        <tr>
            <td class="primary"><a href="<?= e(url('/admin/faqs/edit.php?id=' . $f['id'])) ?>"><strong><?= e($f['question']) ?></strong></a><span class="sub"><?= e(str_limit($f['answer'], 120)) ?></span></td>
            <td data-label="Shown on"><?= e(str_limit($where, 60)) ?></td>
            <td data-label="Status"><?= status_badge($f['status']) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/faqs/edit.php?id=' . $f['id'])) ?>">Edit</a>
                <?= action_button('', 'Delete', ['id' => $f['id'], 'action' => 'delete'], 'btn-link danger', 'Delete this FAQ?') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php else: ?>
    <?= admin_empty('No FAQs here yet.', url('/admin/faqs/edit.php'), 'Add an FAQ') ?>
<?php endif; ?>
<?php admin_footer(); ?>
