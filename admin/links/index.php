<?php
/**
 * Internal linking rules: add, edit, pause and preview on one screen.
 */
require __DIR__ . '/../_init.php';

$editId = input_int('edit') ?: null;
$editing = $editId ? db_one('SELECT * FROM internal_links WHERE id = ?', [$editId]) : null;
$errors = [];
$values = $editing ?? ['keyword' => '', 'target_url' => '', 'priority' => 5, 'status' => 'active'];

if (is_post()) {
    $action = input('action');
    $id = input_int('id', 0, 'post') ?: null;
    if ($action === 'delete' && $id) {
        db_delete('internal_links', $id);
        flash('success', 'Link rule deleted.');
        redirect('/admin/links/');
    }
    if ($action === 'toggle' && $id) {
        db_query("UPDATE internal_links SET status = IF(status = 'active', 'paused', 'active') WHERE id = ?", [$id]);
        flash('success', 'Link rule updated.');
        redirect('/admin/links/');
    }
    [$data, $errors] = internal_link_validate($_POST, $id);
    if (!$errors) {
        $id ? db_update('internal_links', $id, $data) : db_insert('internal_links', $data);
        flash('success', 'Link rule saved.');
        redirect('/admin/links/');
    }
    $values = array_merge($values, $data);
    $editId = $id;
}

$rules = internal_links_admin_list();
$max = (int) setting('internal_links_max', '5');

// Preview: which rules fire on each published article right now.
$preview = [];
foreach (db_all('SELECT p.title, p.slug, p.content FROM posts p WHERE ' . POST_PUBLIC_SQL . ' ORDER BY p.published_at DESC LIMIT 6') as $p) {
    $preview[] = ['title' => $p['title'], 'slug' => $p['slug'], 'links' => apply_internal_links(render_markdown($p['content']), '/blog/' . $p['slug'], $max)['links']];
}

admin_header('Internal links', 'links');
?>
<div class="page-head">
    <div><h1>Internal linking</h1><p>Phrase → URL rules applied automatically when articles, services and landing pages render.</p></div>
</div>
<div class="alert alert-info">Only the <strong>first</strong> natural mention of each phrase is linked, never inside headings or existing links, never to the page itself, and at most <strong><?= $max ?></strong> automatic links per page (change in Settings).</div>

<div class="grid-2 grid-2-1">
    <section class="panel">
        <h2>Rules</h2>
        <?php if ($rules): ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Phrase</th><th>Links to</th><th>Priority</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($rules as $r): ?>
                <tr>
                    <td><strong><?= e($r['keyword']) ?></strong></td>
                    <td class="mono"><?= e($r['target_url']) ?></td>
                    <td><?= (int) $r['priority'] ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><div class="row-actions">
                        <a href="<?= e(url('/admin/links/?edit=' . $r['id'])) ?>#rule-form">Edit</a>
                        <?= action_button('', $r['status'] === 'active' ? 'Pause' : 'Activate', ['id' => $r['id'], 'action' => 'toggle']) ?>
                        <?= action_button('', 'Delete', ['id' => $r['id'], 'action' => 'delete'], 'btn-link danger', 'Delete this link rule?') ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php else: ?>
            <p class="muted">No rules yet.</p>
        <?php endif; ?>
    </section>

    <section class="panel" id="rule-form">
        <h2><?= $editId ? 'Edit rule' : 'Add rule' ?></h2>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <?php if ($editId): ?><input type="hidden" name="id" value="<?= (int) $editId ?>"><?php endif; ?>
            <?= form_input('keyword', 'Phrase', $values['keyword'], $errors, ['required' => true, 'maxlength' => 150, 'help' => 'Matched case-insensitively as a whole phrase.']) ?>
            <?= form_input('target_url', 'Target URL', $values['target_url'], $errors, ['required' => true, 'placeholder' => '/services/web-hosting']) ?>
            <div class="form-row">
                <?= form_input('priority', 'Priority (1–10)', (string) $values['priority'], $errors, ['type' => 'number', 'min' => 1, 'max' => 10]) ?>
                <?= form_select('status', 'Status', $values['status'], ['active' => 'Active', 'paused' => 'Paused'], $errors) ?>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Save rule</button>
                <?php if ($editId): ?><a href="<?= e(url('/admin/links/')) ?>">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>
</div>

<section class="panel">
    <h2>Live preview</h2>
    <p class="small muted">Links the engine adds to recent articles with the current rules.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>Article</th><th>Automatic links</th></tr></thead>
        <tbody>
        <?php foreach ($preview as $p): ?>
            <tr>
                <td><a href="<?= e(url('/blog/' . $p['slug'])) ?>" target="_blank" rel="noopener"><?= e($p['title']) ?></a></td>
                <td><?= $p['links'] ? e(implode(', ', $p['links'])) : '<span class="muted">None</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php admin_footer(); ?>
