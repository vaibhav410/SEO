<?php
require __DIR__ . '/../_init.php';

$id = input_int('id') ?: null;
$post = $id ? post_find($id) : null;
if ($id && !$post) {
    abort(404);
}

$errors = [];
$values = $post ?? ['title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'primary_keyword' => '', 'meta_title' => '',
    'meta_description' => '', 'featured_image' => null, 'featured_image_alt' => '', 'service_id' => '', 'status' => 'draft', 'published_at' => null];

if (is_post()) {
    // "Save draft" and "Publish" are two submit buttons on the same form.
    $_POST['status'] = input('intent') === 'publish' ? 'published' : (input('intent') === 'draft' ? 'draft' : input('status'));
    [$data, $errors] = post_validate($_POST, $id);

    $upload = handle_image_upload($_FILES['featured_image'] ?? []);
    if ($upload['error']) {
        $errors['featured_image'] = $upload['error'];
    }
    if ($upload['path'] && !$data['featured_image_alt']) {
        $errors['featured_image_alt'] = 'Describe the image for screen readers and search engines.';
    }

    if (!$errors) {
        if ($upload['path']) {
            $data['featured_image'] = $upload['path'];
            delete_upload($post['featured_image'] ?? null);
        } elseif (input('remove_image') === '1') {
            $data['featured_image'] = null;
            delete_upload($post['featured_image'] ?? null);
        }
        $savedId = post_save($data, $id, (int) $user['id']);
        flash('success', $data['status'] === 'published' ? 'Article saved and published.' : 'Draft saved.');
        redirect('/admin/posts/edit.php?id=' . $savedId);
    }
    delete_upload($upload['path']);
    $values = array_merge($values, $data);
}

$services = ['' => '— None —'] + array_column(db_all('SELECT id, name FROM services ORDER BY sort_order'), 'name', 'id');
$faqCount = $id ? (int) db_value('SELECT COUNT(*) FROM faqs WHERE post_id = ?', [$id]) : 0;

admin_header($id ? 'Edit article' : 'New article', 'posts');
?>
<div class="page-head">
    <div>
        <h1><?= $id ? 'Edit article' : 'New article' ?></h1>
        <p><a href="<?= e(url('/admin/posts/')) ?>">&larr; All articles</a>
            <?php if ($post && $post['status'] === 'published'): ?> · <a href="<?= e(url('/blog/' . $post['slug'])) ?>" target="_blank" rel="noopener">View live</a><?php endif; ?></p>
    </div>
    <?php if ($post): ?><?= status_badge($post['status']) ?><?php endif; ?>
</div>

<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields.</div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form-layout" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="status" value="<?= e($values['status']) ?>">
    <div>
        <section class="panel">
            <?= form_input('title', 'Title (H1)', $values['title'], $errors, ['required' => true, 'maxlength' => 200]) ?>
            <?= form_input('slug', 'URL slug', $values['slug'], $errors, ['maxlength' => 120, 'data-slug-source' => 'f-title', 'pattern' => '[a-z0-9]+(-[a-z0-9]+)*',
                'help' => 'Lowercase words separated by hyphens. Leave blank to generate from the title. Changing the slug of a published article changes its URL.']) ?>
            <?= form_textarea('excerpt', 'Excerpt', $values['excerpt'], $errors, ['maxlength' => 300, 'rows' => 3, 'required' => true, 'help' => 'Shown on blog cards and used as a fallback meta description.']) ?>
            <?= form_textarea('content', 'Content', $values['content'], $errors, ['class' => 'tall', 'required' => true,
                'help' => 'Formatting: <code>## Heading</code>, <code>### Subheading</code>, <code>- list</code>, <code>1. list</code>, <code>**bold**</code>, <code>*italic*</code>, <code>[link](/services/web-hosting)</code>, <code>| tables |</code>. Internal links to services are added automatically on the live page.']) ?>
        </section>

        <section class="panel">
            <h2>Search appearance</h2>
            <div class="field">
                <label for="f-primary_keyword">Primary keyword</label>
                <input type="text" id="f-primary_keyword" name="primary_keyword" value="<?= e($values['primary_keyword']) ?>" maxlength="150" list="keyword-list">
                <datalist id="keyword-list">
                    <?php foreach (db_all('SELECT keyword FROM keywords ORDER BY keyword') as $k): ?><option value="<?= e($k['keyword']) ?>"><?php endforeach; ?>
                </datalist>
                <p class="help">The main search phrase this article answers. Pick from the keyword plan to avoid two pages competing for the same term.</p>
            </div>
            <div class="field">
                <label for="f-meta_title">Meta title <?= seo_counter('meta_title', 30, 60) ?></label>
                <input type="text" id="f-meta_title" name="meta_title" value="<?= e($values['meta_title']) ?>" maxlength="70"<?= field_aria($errors, 'meta_title') ?>>
                <?= field_error($errors, 'meta_title') ?>
            </div>
            <div class="field">
                <label for="f-meta_description">Meta description <?= seo_counter('meta_description', 70, 160) ?></label>
                <textarea id="f-meta_description" name="meta_description" maxlength="170" rows="3"<?= field_aria($errors, 'meta_description') ?>><?= e($values['meta_description']) ?></textarea>
                <?= field_error($errors, 'meta_description') ?>
            </div>
            <?php if (ai_enabled()): ?>
                <button type="button" class="btn btn-sm btn-outline" data-ai="meta" data-api="<?= e(url('/api/ai.php')) ?>" aria-controls="ai-meta"
                        data-fields='<?= e(json_encode(['title' => 'f-title', 'keyword' => 'f-primary_keyword', 'content' => 'f-content'])) ?>'>Suggest meta with AI</button>
                <div id="ai-meta" class="ai-box" hidden aria-live="polite"></div>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="panel">
            <h2>Publish</h2>
            <?= form_input('published_at', 'Publish date', $values['published_at'] ? date('Y-m-d\TH:i', strtotime($values['published_at'])) : '', $errors,
                ['type' => 'datetime-local', 'help' => 'Leave empty to publish now. A future date schedules the article.']) ?>
            <?= form_select('service_id', 'Related service', (string) $values['service_id'], $services, $errors) ?>
            <div class="actions">
                <button class="btn btn-primary" type="submit" name="intent" value="publish"><?= ($post['status'] ?? '') === 'published' ? 'Update' : 'Publish' ?></button>
                <button class="btn btn-outline" type="submit" name="intent" value="draft"><?= ($post['status'] ?? '') === 'published' ? 'Unpublish & save draft' : 'Save draft' ?></button>
            </div>
        </section>

        <section class="panel">
            <h2>Search preview</h2>
            <div class="serp" data-serp='<?= e(json_encode(['title' => 'f-title', 'meta_title' => 'f-meta_title', 'meta_description' => 'f-meta_description', 'fallback_description' => 'f-excerpt', 'slug' => 'f-slug', 'base' => absolute_url('/blog/')])) ?>'>
                <div class="serp-url"></div><div class="serp-title"></div><div class="serp-desc"></div>
            </div>
            <h3 class="small">On-page checklist</h3>
            <ul class="checklist" data-seo-checklist='<?= e(json_encode(['keyword' => 'f-primary_keyword', 'title' => 'f-title', 'meta_title' => 'f-meta_title', 'meta_description' => 'f-meta_description', 'content' => 'f-content'])) ?>'></ul>
        </section>

        <section class="panel">
            <h2>Featured image</h2>
            <?php if ($values['featured_image']): ?>
                <img class="current-image" src="<?= e(url('/' . $values['featured_image'])) ?>" alt="<?= e($values['featured_image_alt']) ?>">
                <label class="small"><input type="checkbox" name="remove_image" value="1"> Remove image</label>
            <?php endif; ?>
            <div class="field">
                <label for="f-featured_image">Upload image</label>
                <input type="file" id="f-featured_image" name="featured_image" accept="image/jpeg,image/png,image/webp"<?= field_aria($errors, 'featured_image') ?>>
                <p class="help">JPG, PNG or WebP, up to <?= round(config('uploads.max_bytes') / 1048576, 1) ?> MB. Resized to 1600px wide.</p>
                <?= field_error($errors, 'featured_image') ?>
            </div>
            <?= form_input('featured_image_alt', 'Alt text', $values['featured_image_alt'], $errors, ['maxlength' => 200]) ?>
        </section>

        <?php if ($id): ?>
            <section class="panel">
                <h2>FAQs</h2>
                <p class="small muted"><?= $faqCount ?> FAQ<?= $faqCount === 1 ? '' : 's' ?> shown on this article (also used for FAQPage structured data).</p>
                <a class="btn btn-sm btn-outline" href="<?= e(url('/admin/faqs/edit.php?owner=post:' . $id)) ?>">Add FAQ</a>
                <a class="btn btn-sm btn-link" href="<?= e(url('/admin/faqs/?owner=post')) ?>">Manage FAQs</a>
            </section>
        <?php endif; ?>
    </div>
</form>
<?php admin_footer(); ?>
