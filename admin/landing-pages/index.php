<?php
require __DIR__ . '/../_init.php';

if (is_post() && input('action') === 'delete') {
    $page = landing_find(input_int('id', 0, 'post'));
    if (!$page) {
        abort(404);
    }
    db_delete('landing_pages', (int) $page['id']);
    flash('success', 'Landing page “' . $page['title'] . '” deleted.');
    redirect('/admin/landing-pages/');
}

$pages = landing_admin_list();
admin_header('Landing pages', 'landing');
?>
<div class="page-head">
    <div><h1>Landing pages</h1><p>Keyword-focused pages at /{slug} built from a fixed template: hero, problem, solution, features, benefits, use cases, FAQ and a lead form.</p></div>
    <a class="btn btn-primary" href="<?= e(url('/admin/landing-pages/edit.php')) ?>">New landing page</a>
</div>
<div class="alert alert-info">Each landing page should serve a distinct search intent with genuinely different content. Near-duplicate pages for keyword variations (doorway pages) are against Google's spam policies.</div>
<?php if ($pages): ?>
<div class="table-wrap"><table>
    <thead><tr><th>Page</th><th>Primary keyword</th><th>SEO meta</th><th>Leads</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($pages as $p):
        $t = seo_length_status($p['meta_title'] ?: $p['title'], 25, SEO_TITLE_MAX);
        $d = seo_length_status((string) $p['meta_description'], 70, SEO_DESCRIPTION_MAX); ?>
        <tr>
            <td><a href="<?= e(url('/admin/landing-pages/edit.php?id=' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a><span class="sub">/<?= e($p['slug']) ?></span></td>
            <td><?= e($p['primary_keyword']) ?></td>
            <td class="nowrap"><span class="badge badge-<?= $t === 'ok' ? 'success' : 'warning' ?>">Title</span> <span class="badge badge-<?= $d === 'ok' ? 'success' : ($d === 'missing' ? 'danger' : 'warning') ?>">Desc</span></td>
            <td><a href="<?= e(url('/admin/leads/?source=' . rawurlencode('/' . $p['slug']))) ?>"><?= (int) $p['lead_count'] ?></a></td>
            <td><?= status_badge($p['status']) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/landing-pages/edit.php?id=' . $p['id'])) ?>">Edit</a>
                <?php if ($p['status'] === 'published'): ?><a href="<?= e(url('/' . $p['slug'])) ?>" target="_blank" rel="noopener">View</a><?php endif; ?>
                <?= action_button('', 'Delete', ['id' => $p['id'], 'action' => 'delete'], 'btn-link danger', 'Delete this landing page? Its FAQs will be deleted too.') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php else: ?>
    <?= admin_empty('No landing pages yet.', url('/admin/landing-pages/edit.php'), 'Create a landing page') ?>
<?php endif; ?>
<?php admin_footer(); ?>
