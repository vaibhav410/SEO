<?php
require __DIR__ . '/../_init.php';

$editId = input_int('edit') ?: null;
$editing = $editId ? category_find($editId) : null;
$errors = [];
$values = $editing ?? ['name' => '', 'slug' => '', 'description' => '', 'meta_description' => '', 'sort_order' => 0];

if (is_post()) {
    $id = input_int('id', 0, 'post') ?: null;
    if (input('action') === 'delete' && $id) {
        $cat = category_find($id);
        db_delete('categories', $id);
        log_activity('deleted', 'category', $id, 'Category “' . ($cat['name'] ?? '') . '” deleted');
        flash('success', 'Category deleted. Its articles are now uncategorised.');
        redirect('/admin/categories/');
    }
    [$data, $errors] = category_validate($_POST, $id);
    if (!$errors) {
        $id ? db_update('categories', $id, $data) : ($id = db_insert('categories', $data));
        log_activity($editId || input_int('id', 0, 'post') ? 'updated' : 'created', 'category', $id, 'Category “' . $data['name'] . '” saved');
        flash('success', 'Category saved.');
        redirect('/admin/categories/');
    }
    $values = array_merge($values, $data);
    $editId = $id;
}
$categories = categories_all();

admin_header('Categories', 'categories', ['Content' => '/admin/posts/', 'Categories' => null]);
echo page_header('Categories', 'Group articles into topic hubs at /blog/category/{slug}. Each category page is indexable, so give it a useful description.');
?>
<div class="grid-2-1">
    <section class="panel">
        <?php if ($categories): ?>
            <div class="table-wrap"><table class="table-cards">
                <thead><tr><th>Category</th><th>Articles</th><th>Description</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td class="primary"><strong><?= e($c['name']) ?></strong><span class="sub mono">/blog/category/<?= e($c['slug']) ?></span></td>
                        <td data-label="Articles"><a href="<?= e(url('/admin/posts/?category=' . $c['id'])) ?>"><?= (int) $c['post_count'] ?></a></td>
                        <td data-label="Description" class="small muted"><?= e(str_limit($c['description'], 90)) ?></td>
                        <td><div class="row-actions">
                            <a href="<?= e(url('/admin/categories/?edit=' . $c['id'])) ?>#category-form">Edit</a>
                            <?php if ($c['post_count'] > 0): ?><a href="<?= e(url('/blog/category/' . $c['slug'])) ?>" target="_blank" rel="noopener">View</a><?php endif; ?>
                            <?= action_button('', 'Delete', ['id' => $c['id'], 'action' => 'delete'], 'btn-link danger', 'Delete this category? Articles in it will become uncategorised.') ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <?= admin_empty('Create a category to group related articles.', '', '', 'book', 'No categories yet') ?>
        <?php endif; ?>
    </section>
    <section class="panel" id="category-form">
        <h2><?= $editId ? 'Edit category' : 'New category' ?></h2>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <?php if ($editId): ?><input type="hidden" name="id" value="<?= (int) $editId ?>"><?php endif; ?>
            <?= form_input('name', 'Name', $values['name'], $errors, ['required' => true, 'maxlength' => 80]) ?>
            <?= form_input('slug', 'Slug', $values['slug'], $errors, ['maxlength' => 80, 'data-slug-source' => 'f-name', 'help' => 'Generated from the name if blank.']) ?>
            <?= form_textarea('description', 'Description (shown on the category page)', $values['description'], $errors, ['rows' => 3, 'maxlength' => 300]) ?>
            <div class="field">
                <label for="f-meta_description">Meta description <?= seo_counter('meta_description', 70, 160) ?></label>
                <textarea id="f-meta_description" name="meta_description" rows="3" maxlength="170"><?= e((string) $values['meta_description']) ?></textarea>
            </div>
            <?= form_input('sort_order', 'Order', (string) $values['sort_order'], $errors, ['type' => 'number', 'min' => 0]) ?>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Save category</button>
                <?php if ($editId): ?><a href="<?= e(url('/admin/categories/')) ?>">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>
</div>
<?php admin_footer(); ?>
