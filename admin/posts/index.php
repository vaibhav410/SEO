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
        log_activity('deleted', 'post', null, 'Article “' . $post['title'] . '” deleted');
        flash('success', 'Article “' . $post['title'] . '” deleted.');
    } elseif (in_array($action, ['publish', 'unpublish'], true)) {
        $data = $action === 'publish'
            ? ['status' => 'published', 'published_at' => $post['published_at'] ?: date('Y-m-d H:i:s')]
            : ['status' => 'draft'];
        db_update('posts', $id, $data);
        log_activity($action === 'publish' ? 'published' : 'unpublished', 'post', $id, 'Article “' . $post['title'] . '” ' . ($action === 'publish' ? 'published' : 'moved to drafts'));
        flash('success', 'Article ' . ($action === 'publish' ? 'published.' : 'moved to drafts.'));
    }
    redirect('/admin/posts/' . query_with([]));
}

$filters = ['status' => input('status', '', 'get'), 'q' => mb_substr(input('q', '', 'get'), 0, 100), 'category' => input_int('category')];
$pager = paginate(posts_admin_count($filters), 15);
$posts = posts_admin_list($filters, $pager['per_page'], $pager['offset'], sort_sql(POST_SORTS, 'updated'));
$counts = db_one("SELECT COUNT(*) total, SUM(status = 'published' AND published_at <= NOW()) published, SUM(status = 'draft') draft,
                  SUM(status = 'published' AND published_at > NOW()) scheduled FROM posts");
$categories = categories_all();

admin_header('Blog posts', 'posts', ['Content' => '/admin/posts/', 'Blog posts' => null]);
echo page_header('Blog posts', 'Guides that answer informational searches and link readers to the right service.',
    '<a class="btn btn-primary" href="' . e(url('/admin/posts/edit.php')) . '">' . icon('book', 'icon icon-sm') . ' New post</a>');
?>
<nav class="tabs" aria-label="Status">
    <?php foreach (['' => ['All', $counts['total']], 'published' => ['Published', $counts['published']], 'scheduled' => ['Scheduled', $counts['scheduled']], 'draft' => ['Drafts', $counts['draft']]] as $s => [$label, $n]): ?>
        <a href="<?= e(url('/admin/posts/') . query_with(['status' => $s ?: null, 'page' => null])) ?>"<?= $filters['status'] === $s ? ' aria-current="page"' : '' ?>><?= $label ?><span class="count"><?= (int) $n ?></span></a>
    <?php endforeach; ?>
</nav>

<form class="filters" method="get">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <div class="field"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Title or keyword…"></div>
    <div class="field"><label for="category">Category</label>
        <select id="category" name="category"><option value="">All categories</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $filters['category'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
    <button class="btn btn-outline" type="submit">Apply filters</button>
    <?php if ($filters['q'] || $filters['category']): ?><a class="btn btn-ghost" href="<?= e(url('/admin/posts/')) ?>">Clear</a><?php endif; ?>
</form>

<?php if ($posts): ?>
<div class="table-wrap"><table class="table-cards">
    <thead><tr>
        <?= th_sort('title', 'Title', 'updated') ?><?= th_sort('category', 'Category', 'updated') ?><th>Target keyword</th><?= th_sort('status', 'Status', 'updated') ?>
        <th>Author</th><?= th_sort('published', 'Published', 'updated') ?><th>SEO status</th><th><span class="visually-hidden">Actions</span></th>
    </tr></thead>
    <tbody>
    <?php foreach ($posts as $p):
        $checklist = content_checklist($p, 'post');
        $scheduled = $p['status'] === 'published' && strtotime((string) $p['published_at']) > time(); ?>
        <tr>
            <td class="primary"><a href="<?= e(url('/admin/posts/edit.php?id=' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a><span class="sub mono">/blog/<?= e($p['slug']) ?></span></td>
            <td data-label="Category"><?= $p['category_name'] ? e($p['category_name']) : '<span class="muted">—</span>' ?></td>
            <td data-label="Keyword"><?= $p['primary_keyword'] ? e($p['primary_keyword']) : '<span class="muted">Not set</span>' ?></td>
            <td data-label="Status"><?= $scheduled ? status_badge('scheduled') : status_badge($p['status']) ?></td>
            <td data-label="Author"><?= e($p['author'] ?? '—') ?></td>
            <td data-label="Published" class="nowrap muted"><?= $p['published_at'] ? e(format_date($p['published_at'])) : '—' ?></td>
            <td data-label="SEO"><?= checklist_badge($checklist) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/posts/edit.php?id=' . $p['id'])) ?>">Edit</a>
                <?php if ($p['status'] === 'published' && !$scheduled): ?>
                    <a href="<?= e(url('/blog/' . $p['slug'])) ?>" target="_blank" rel="noopener">View</a>
                    <?= action_button('', 'Unpublish', ['id' => $p['id'], 'action' => 'unpublish']) ?>
                <?php elseif ($p['status'] === 'draft'): ?>
                    <?= action_button('', 'Publish', ['id' => $p['id'], 'action' => 'publish']) ?>
                <?php endif; ?>
                <?= action_button('', 'Delete', ['id' => $p['id'], 'action' => 'delete'], 'btn-link danger', 'Delete “' . $p['title'] . '” permanently? Its FAQs will be deleted too.') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<div class="table-foot"><span>Showing <?= $pager['offset'] + 1 ?>–<?= $pager['offset'] + count($posts) ?> of <?= $pager['total'] ?></span><?= admin_pagination($pager) ?></div>
<?php else: ?>
    <?= admin_empty($filters['q'] || $filters['status'] || $filters['category'] ? 'No posts match these filters.' : 'Write your first guide to start earning informational search traffic.',
        url('/admin/posts/edit.php'), 'New post', 'book', 'No posts found') ?>
<?php endif; ?>
<?php admin_footer(); ?>
