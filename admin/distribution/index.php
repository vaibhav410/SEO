<?php
require __DIR__ . '/../_init.php';

$editId = input_int('edit') ?: null;
$editing = $editId ? db_one('SELECT * FROM distribution_posts WHERE id = ?', [$editId]) : null;
$errors = [];
$values = $editing ?? ['platform' => 'linkedin', 'title' => '', 'post_id' => '', 'url' => '', 'status' => 'planned', 'published_at' => '', 'notes' => ''];

if (is_post()) {
    $id = input_int('id', 0, 'post') ?: null;
    if (input('action') === 'delete' && $id) {
        db_delete('distribution_posts', $id);
        log_activity('deleted', 'distribution', $id, 'Distribution item deleted');
        flash('success', 'Item deleted.');
        redirect('/admin/distribution/');
    }
    [$data, $errors] = distribution_validate($_POST);
    if (!$errors) {
        $isNew = !$id;
        $id ? db_update('distribution_posts', $id, $data) : ($id = db_insert('distribution_posts', $data));
        log_activity($data['status'] === 'published' ? 'published' : ($isNew ? 'created' : 'updated'), 'distribution', $id,
            DISTRIBUTION_PLATFORMS[$data['platform']] . ': ' . $data['title']);
        flash('success', 'Distribution item saved.');
        redirect('/admin/distribution/');
    }
    $values = array_merge($values, $data);
    $editId = $id;
}

$platform = input('platform', '', 'get');
$status = input('status', '', 'get');
$items = distribution_list($platform, $status);
$counts = distribution_counts();
$posts = ['' => '— Not linked to an article —'] + array_column(db_all('SELECT p.id, p.title FROM posts p WHERE ' . POST_PUBLIC_SQL . ' ORDER BY p.published_at DESC'), 'title', 'id');

admin_header('Organic distribution', 'distribution', ['Off-page' => '/admin/backlinks/', 'Organic distribution' => null]);
echo page_header('Organic distribution', 'Record where each article was shared so good content reaches people. Social activity helps discovery and can earn natural links; it is not a direct Google ranking factor.');
?>
<section class="kpis">
    <?php foreach (DISTRIBUTION_PLATFORMS as $key => $label): if ($key === 'other' && !$counts['other']['published'] && !$counts['other']['planned']) { continue; } ?>
        <a class="kpi" href="<?= e(url('/admin/distribution/?platform=' . $key)) ?>">
            <div class="kpi-top"><span class="kpi-label"><?= e($label) ?></span></div>
            <div class="kpi-value"><?= $counts[$key]['published'] ?><small>published</small></div>
            <p class="kpi-note"><?= $counts[$key]['planned'] ?> planned</p>
        </a>
    <?php endforeach; ?>
</section>

<div class="grid-2-1">
    <section>
        <form class="filters" method="get">
            <div class="field"><label for="platform">Platform</label><select id="platform" name="platform"><option value="">All</option>
                <?php foreach (DISTRIBUTION_PLATFORMS as $k => $l): ?><option value="<?= $k ?>"<?= $platform === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option>
                <?php foreach (DISTRIBUTION_STATUSES as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
            <button class="btn btn-outline" type="submit">Filter</button>
        </form>
        <?php if ($items): ?>
            <div class="table-wrap"><table class="table-cards">
                <thead><tr><th>Content</th><th>Platform</th><th>Published?</th><th>URL</th><th>Date</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($items as $d): ?>
                    <tr>
                        <td class="primary"><strong><?= e($d['title']) ?></strong><?php if ($d['post_title']): ?><span class="sub">Article: <a href="<?= e(url('/blog/' . $d['post_slug'])) ?>" target="_blank" rel="noopener"><?= e(str_limit($d['post_title'], 60)) ?></a></span><?php endif; ?></td>
                        <td data-label="Platform"><?= e(DISTRIBUTION_PLATFORMS[$d['platform']]) ?></td>
                        <td data-label="Status"><?= status_badge($d['status']) ?></td>
                        <td data-label="URL"><?= $d['url'] ? '<a href="' . e($d['url']) . '" target="_blank" rel="noopener noreferrer">Open ' . icon('external', 'icon icon-xs') . '</a>' : '<span class="muted">—</span>' ?></td>
                        <td data-label="Date" class="muted nowrap"><?= $d['published_at'] ? e(format_date($d['published_at'])) : '—' ?></td>
                        <td><div class="row-actions">
                            <a href="<?= e(url('/admin/distribution/?edit=' . $d['id'])) ?>#dist-form">Edit</a>
                            <?= action_button('', 'Delete', ['id' => $d['id'], 'action' => 'delete'], 'btn-link danger', 'Delete this distribution record?') ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <?= admin_empty('Plan where your next article will be shared.', '', '', 'trending', 'Nothing recorded yet') ?>
        <?php endif; ?>
    </section>

    <section class="panel" id="dist-form">
        <h2><?= $editId ? 'Edit item' : 'Plan or record a share' ?></h2>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <?php if ($editId): ?><input type="hidden" name="id" value="<?= (int) $editId ?>"><?php endif; ?>
            <?= form_select('platform', 'Platform', $values['platform'], DISTRIBUTION_PLATFORMS, $errors) ?>
            <?= form_input('title', 'What was shared', $values['title'], $errors, ['required' => true, 'maxlength' => 200, 'placeholder' => 'e.g. Carousel: how to choose web hosting']) ?>
            <?= form_select('post_id', 'Article', (string) $values['post_id'], $posts, $errors) ?>
            <div class="form-row">
                <?= form_select('status', 'Status', $values['status'], array_combine(DISTRIBUTION_STATUSES, array_map('ucfirst', DISTRIBUTION_STATUSES)), $errors) ?>
                <?= form_input('published_at', 'Date', (string) $values['published_at'], $errors, ['type' => 'date']) ?>
            </div>
            <?= form_input('url', 'Post URL', (string) $values['url'], $errors, ['type' => 'url', 'maxlength' => 500, 'help' => 'Required once published.']) ?>
            <?= form_textarea('notes', 'Notes', (string) $values['notes'], $errors, ['rows' => 3]) ?>
            <div class="actions"><button class="btn btn-primary" type="submit">Save</button><?php if ($editId): ?><a href="<?= e(url('/admin/distribution/')) ?>">Cancel</a><?php endif; ?></div>
        </form>
    </section>
</div>
<?php admin_footer(); ?>
