<?php
require __DIR__ . '/../_init.php';

if (is_post() && input('action') === 'delete') {
    $service = service_find(input_int('id', 0, 'post'));
    if (!$service) {
        abort(404);
    }
    db_delete('services', (int) $service['id']);
    log_activity('deleted', 'service', null, 'Service “' . $service['name'] . '” deleted');
    flash('success', 'Service “' . $service['name'] . '” deleted. Its FAQs were removed and related articles unlinked.');
    redirect('/admin/services/');
}

$services = services_admin_list();
admin_header('Services', 'services', ['Services' => null]);
echo page_header('Services', 'SYSCOM\'s commercial service pages at /services/{slug}: hosting, servers, domains, email and SSL.',
    '<a class="btn btn-primary" href="' . e(url('/admin/services/edit.php')) . '">' . icon('server', 'icon icon-sm') . ' New service</a>');
?>
<?php if ($services): ?>
<div class="table-wrap"><table class="table-cards">
    <thead><tr><th>Service</th><th>Primary keyword</th><th>Guides</th><th>FAQs</th><th>Leads</th><th>SEO status</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($services as $s): $chk = content_checklist($s, 'service'); ?>
        <tr>
            <td class="primary"><a href="<?= e(url('/admin/services/edit.php?id=' . $s['id'])) ?>"><strong><?= icon($s['icon'], 'icon icon-xs') ?> <?= e($s['name']) ?></strong></a><span class="sub mono">/services/<?= e($s['slug']) ?></span></td>
            <td data-label="Keyword"><?= $s['primary_keyword'] ? e($s['primary_keyword']) : '<span class="muted">—</span>' ?></td>
            <td data-label="Guides"><?= (int) $s['post_count'] ?: '<span class="badge badge-warning" data-tooltip="No article links readers to this service">0</span>' ?></td>
            <td data-label="FAQs"><?= (int) $s['faq_count'] ?></td>
            <td data-label="Leads"><?= (int) $s['lead_count'] ?></td>
            <td data-label="SEO"><?= checklist_badge($chk) ?></td>
            <td data-label="Status"><?= status_badge($s['status']) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/services/edit.php?id=' . $s['id'])) ?>">Edit</a>
                <?php if ($s['status'] === 'published'): ?><a href="<?= e(url('/services/' . $s['slug'])) ?>" target="_blank" rel="noopener">View</a><?php endif; ?>
                <?= action_button('', 'Delete', ['id' => $s['id'], 'action' => 'delete'], 'btn-link danger', 'Delete the “' . $s['name'] . '” service? Its FAQs will also be deleted.') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php else: ?>
    <?= admin_empty('Add SYSCOM\'s first service page.', url('/admin/services/edit.php'), 'New service', 'server', 'No services yet') ?>
<?php endif; ?>
<?php admin_footer(); ?>
