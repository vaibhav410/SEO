<?php
require __DIR__ . '/../_init.php';

if (is_post() && input('action') === 'delete') {
    $service = service_find(input_int('id', 0, 'post'));
    if (!$service) {
        abort(404);
    }
    db_delete('services', (int) $service['id']);
    flash('success', 'Service “' . $service['name'] . '” deleted. Its FAQs were removed and related articles unlinked.');
    redirect('/admin/services/');
}

$services = services_admin_list();
admin_header('Services', 'services');
?>
<div class="page-head">
    <div><h1>Services</h1><p>Service pages at /services/{slug}: the core commercial pages of the site.</p></div>
    <a class="btn btn-primary" href="<?= e(url('/admin/services/edit.php')) ?>">New service</a>
</div>
<?php if ($services): ?>
<div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Service</th><th>SEO meta</th><th>FAQs</th><th>Status</th><th>Updated</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($services as $s):
        $t = seo_length_status($s['meta_title'] ?: $s['name'], 25, SEO_TITLE_MAX);
        $d = seo_length_status((string) $s['meta_description'], 70, SEO_DESCRIPTION_MAX); ?>
        <tr>
            <td class="muted"><?= (int) $s['sort_order'] ?></td>
            <td><a href="<?= e(url('/admin/services/edit.php?id=' . $s['id'])) ?>"><strong><?= e($s['name']) ?></strong></a><span class="sub">/services/<?= e($s['slug']) ?></span></td>
            <td class="nowrap">
                <span class="badge badge-<?= $t === 'ok' ? 'success' : 'warning' ?>">Title</span>
                <span class="badge badge-<?= $d === 'ok' ? 'success' : ($d === 'missing' ? 'danger' : 'warning') ?>">Desc</span>
            </td>
            <td><a href="<?= e(url('/admin/faqs/?owner=service')) ?>"><?= (int) $s['faq_count'] ?></a></td>
            <td><?= status_badge($s['status']) ?></td>
            <td class="nowrap muted"><?= e(time_ago($s['updated_at'])) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/services/edit.php?id=' . $s['id'])) ?>">Edit</a>
                <?php if ($s['status'] === 'published'): ?><a href="<?= e(url('/services/' . $s['slug'])) ?>" target="_blank" rel="noopener">View</a><?php endif; ?>
                <?= action_button('', 'Delete', ['id' => $s['id'], 'action' => 'delete'], 'btn-link danger', 'Delete this service? Its FAQs will also be deleted.') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php else: ?>
    <?= admin_empty('No services yet.', url('/admin/services/edit.php'), 'Add a service') ?>
<?php endif; ?>
<?php admin_footer(); ?>
