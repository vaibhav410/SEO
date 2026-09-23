<?php
require __DIR__ . '/../_init.php';

if (is_post() && input('action') === 'delete') {
    $page = landing_find(input_int('id', 0, 'post'));
    if (!$page) {
        abort(404);
    }
    db_delete('landing_pages', (int) $page['id']);
    log_activity('deleted', 'landing', null, 'Landing page “' . $page['title'] . '” deleted');
    flash('success', 'Landing page “' . $page['title'] . '” deleted.');
    redirect('/admin/landing-pages/');
}

$pages = landing_admin_list();
$inbound = internal_inbound_map();
$status = input('status', '', 'get');
$visible = array_filter($pages, fn($p) => $status === '' || $p['status'] === $status);

admin_header('Landing pages', 'landing', ['Landing pages' => null]);
echo page_header('Landing pages', 'Focused pages at /{slug} for commercial search intents: hero, problem, solution, features, benefits, use cases, FAQ and an enquiry form.',
    '<a class="btn btn-primary" href="' . e(url('/admin/landing-pages/edit.php')) . '">' . icon('zap', 'icon icon-sm') . ' New landing page</a>');
?>
<div class="alert alert-info"><?= icon('shield', 'icon icon-sm') ?><span>Each landing page must serve a distinct intent with genuinely different content. Near-duplicate pages for keyword variations (doorway pages) break Google's spam policies. The editor requires real problem/solution copy and at least three features.</span></div>

<nav class="tabs" aria-label="Status">
    <?php foreach (['' => 'All', 'published' => 'Published', 'draft' => 'Drafts'] as $s => $label): ?>
        <a href="<?= e(url('/admin/landing-pages/') . ($s ? '?status=' . $s : '')) ?>"<?= $status === $s ? ' aria-current="page"' : '' ?>><?= $label ?><span class="count"><?= count(array_filter($pages, fn($p) => $s === '' || $p['status'] === $s)) ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if ($visible): ?>
<div class="table-wrap"><table class="table-cards">
    <thead><tr><th>Page</th><th>Primary keyword</th><th>Intent</th><th>SEO status</th><th>Content status</th><th>Internal links</th><th>Leads</th><th>Last updated</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($visible as $p): $chk = content_checklist($p, 'landing'); $links = (int) ($inbound['/' . $p['slug']] ?? 0); ?>
        <tr>
            <td class="primary"><a href="<?= e(url('/admin/landing-pages/edit.php?id=' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a><span class="sub mono">/<?= e($p['slug']) ?></span></td>
            <td data-label="Keyword"><?= e($p['primary_keyword']) ?></td>
            <td data-label="Intent"><?= $p['intent'] ? e(ucfirst($p['intent'])) : '<span class="muted" data-tooltip="Add this keyword to the keyword plan">Not in plan</span>' ?></td>
            <td data-label="SEO"><?= checklist_badge($chk) ?></td>
            <td data-label="Content"><?= status_badge($p['status']) ?></td>
            <td data-label="Internal links"><?= $links ?: '<span class="badge badge-warning" data-tooltip="No article links here yet">0 inbound</span>' ?></td>
            <td data-label="Leads"><a href="<?= e(url('/admin/leads/?source=' . rawurlencode('/' . $p['slug']))) ?>"><?= (int) $p['lead_count'] ?></a></td>
            <td data-label="Updated" class="muted nowrap"><?= e(time_ago($p['updated_at'])) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/landing-pages/edit.php?id=' . $p['id'])) ?>">Edit</a>
                <?php if ($p['status'] === 'published'): ?><a href="<?= e(url('/' . $p['slug'])) ?>" target="_blank" rel="noopener">View</a><?php endif; ?>
                <?= action_button('', 'Delete', ['id' => $p['id'], 'action' => 'delete'], 'btn-link danger', 'Delete “' . $p['title'] . '”? Its FAQs will be deleted too.') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php else: ?>
    <?= admin_empty('Create a landing page for a commercial keyword in your plan.', url('/admin/landing-pages/edit.php'), 'New landing page', 'zap', 'No landing pages') ?>
<?php endif; ?>
<?php admin_footer(); ?>
