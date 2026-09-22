<?php
require __DIR__ . '/../_init.php';

if (is_post()) {
    $id = input_int('id', 0, 'post');
    $action = input('action');
    $post = post_find($id);
    if (!$post) {
        abort(404);
    }
    if ($action === 'delete') {
        post_delete($id);
        flash('success', 'Article “' . $post['title'] . '” deleted.');
    } elseif (in_array($action, ['publish', 'unpublish'], true)) {
        $data = $action === 'publish'
            ? ['status' => 'published', 'published_at' => $post['published_at'] ?: date('Y-m-d H:i:s')]
            : ['status' => 'draft'];
        db_update('posts', $id, $data);
        flash('success', 'Article ' . ($action === 'publish' ? 'published.' : 'moved to drafts.'));
    }
    redirect('/admin/posts/' . query_with([]));
}

$status = input('status', '', 'get');
$search = mb_substr(input('q', '', 'get'), 0, 100);
$pager = paginate(posts_admin_count($status, $search), 20);
$posts = posts_admin_list($status, $search, $pager['per_page'], $pager['offset']);

admin_header('Articles', 'posts');
?>
<div class="page-head">
    <div><h1>Articles</h1><p>Blog content that targets informational keywords and links to services.</p></div>
    <a class="btn btn-primary" href="<?= e(url('/admin/posts/edit.php')) ?>">New article</a>
</div>

<form class="filters" method="get">
    <div class="field"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Title contains…"></div>
    <div class="field"><label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (['published' => 'Published', 'draft' => 'Draft'] as $v => $l): ?>
                <option value="<?= $v ?>"<?= $status === $v ? ' selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-outline" type="submit">Filter</button>
</form>

<?php if ($posts): ?>
<div class="table-wrap"><table>
    <thead><tr><th>Title</th><th>Keyword</th><th>SEO meta</th><th>Status</th><th>Updated</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p):
        $titleState = seo_length_status($p['meta_title'] ?: $p['title'], 25, SEO_TITLE_MAX);
        $descState = seo_length_status((string) $p['meta_description'], 70, SEO_DESCRIPTION_MAX); ?>
        <tr>
            <td><a href="<?= e(url('/admin/posts/edit.php?id=' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a><span class="sub">/blog/<?= e($p['slug']) ?></span></td>
            <td><?= e($p['primary_keyword'] ?: '—') ?></td>
            <td class="nowrap">
                <span class="badge badge-<?= $titleState === 'ok' ? 'success' : 'warning' ?>" title="Meta title <?= e($titleState) ?>">Title</span>
                <span class="badge badge-<?= $descState === 'ok' ? 'success' : ($descState === 'missing' ? 'danger' : 'warning') ?>" title="Meta description <?= e($descState) ?>">Desc</span>
            </td>
            <td><?= status_badge($p['status']) ?><?php if ($p['status'] === 'published' && strtotime((string) $p['published_at']) > time()): ?> <span class="badge badge-info">Scheduled</span><?php endif; ?></td>
            <td class="nowrap muted"><?= e(time_ago($p['updated_at'])) ?></td>
            <td>
                <div class="row-actions">
                    <a href="<?= e(url('/admin/posts/edit.php?id=' . $p['id'])) ?>">Edit</a>
                    <?php if ($p['status'] === 'published'): ?>
                        <a href="<?= e(url('/blog/' . $p['slug'])) ?>" target="_blank" rel="noopener">View</a>
                        <?= action_button('', 'Unpublish', ['id' => $p['id'], 'action' => 'unpublish']) ?>
                    <?php else: ?>
                        <?= action_button('', 'Publish', ['id' => $p['id'], 'action' => 'publish']) ?>
                    <?php endif; ?>
                    <?= action_button('', 'Delete', ['id' => $p['id'], 'action' => 'delete'], 'btn-link danger', 'Delete this article permanently? Its FAQs will be deleted too.') ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?= admin_pagination($pager) ?>
<?php else: ?>
    <?= admin_empty($search || $status ? 'No articles match these filters.' : 'No articles yet.', url('/admin/posts/edit.php'), 'Write an article') ?>
<?php endif; ?>
<?php admin_footer(); ?>
