<?php
require __DIR__ . '/../_init.php';

$id = input_int('id') ?: null;
$post = $id ? post_find($id) : null;
if ($id && !$post) {
    abort(404);
}

$errors = [];
$defaults = ['title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'primary_keyword' => input('keyword', '', 'get'), 'meta_title' => '',
    'meta_description' => '', 'canonical_url' => '', 'og_title' => '', 'og_description' => '', 'schema_type' => 'Article',
    'featured_image' => null, 'featured_image_alt' => '', 'category_id' => '', 'service_id' => '', 'status' => 'draft', 'published_at' => null];
$values = $post ?? $defaults;

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
        $verb = !$id ? 'created' : ($data['status'] === 'published' && ($post['status'] ?? '') !== 'published' ? 'published' : 'updated');
        log_activity($verb, 'post', $savedId, 'Article “' . $data['title'] . '” ' . $verb);
        flash('success', $data['status'] === 'published' ? 'Article saved and published.' : 'Draft saved.');
        redirect('/admin/posts/edit.php?id=' . $savedId);
    }
    delete_upload($upload['path']);
    $values = array_merge($values, $data);
}

$services = ['' => '— None —'] + array_column(db_all('SELECT id, name FROM services ORDER BY sort_order'), 'name', 'id');
$categories = ['' => '— Uncategorised —'] + array_column(categories_all(), 'name', 'id');
$checklist = content_checklist($values, 'post');
$faqCount = $id ? (int) db_value('SELECT COUNT(*) FROM faqs WHERE post_id = ?', [$id]) : 0;
$scheduled = ($values['status'] ?? '') === 'published' && $values['published_at'] && strtotime($values['published_at']) > time();
$statusLabel = $post ? ($scheduled ? 'scheduled' : $post['status']) : 'new';

// Links the article will contain on the live site (manual + automatic).
$renderedLinks = [];
if ($id) {
    $html = apply_internal_links(render_markdown($values['content']), '/blog/' . $values['slug'], (int) setting('internal_links_max', '5'))['html'];
    preg_match_all('#<a href="([^"]+)"( class="auto-link")?[^>]*>(.*?)</a>#', $html, $m, PREG_SET_ORDER);
    foreach ($m as $link) {
        $renderedLinks[] = ['href' => html_entity_decode($link[1]), 'auto' => $link[2] !== '', 'text' => strip_tags($link[3])];
    }
}
$related = $post ? posts_related($post + ['service_id' => $values['service_id']], 4) : [];

admin_header($id ? 'Edit post' : 'New post', 'posts', ['Content' => '/admin/posts/', 'Blog posts' => '/admin/posts/', $id ? str_limit($values['title'], 40) : 'New post' => null]);
?>
<div class="page-head">
    <div>
        <h1><?= $id ? 'Edit post' : 'New post' ?></h1>
        <p><?= status_badge($statusLabel) ?>
            <?php if ($post && $post['status'] === 'published' && !$scheduled): ?> · <a href="<?= e(url('/blog/' . $post['slug'])) ?>" target="_blank" rel="noopener">View live <?= icon('external', 'icon icon-xs') ?></a><?php endif; ?>
            <?php if ($post): ?> · Last saved <?= e(time_ago($post['updated_at'])) ?><?php endif; ?></p>
    </div>
    <div class="actions"><?= checklist_badge($checklist) ?> <span class="small muted">SEO checklist</span></div>
</div>

<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields. Nothing was saved.</div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form-layout" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="status" value="<?= e($values['status']) ?>">
    <div>
        <section class="panel">
            <?= form_input('title', 'Title (H1)', $values['title'], $errors, ['required' => true, 'maxlength' => 200]) ?>
            <?= form_input('slug', 'URL slug', $values['slug'], $errors, ['maxlength' => 120, 'data-slug-source' => 'f-title', 'pattern' => '[a-z0-9]+(-[a-z0-9]+)*',
                'help' => 'Leave blank to generate from the title. Changing the slug of a published post changes its URL.']) ?>
            <?= form_textarea('excerpt', 'Excerpt', $values['excerpt'], $errors, ['maxlength' => 300, 'rows' => 3, 'required' => true, 'help' => 'Shown on blog cards and used as the fallback meta description.']) ?>
            <?= form_textarea('content', 'Content', $values['content'], $errors, ['class' => 'tall', 'required' => true,
                'help' => 'Formatting: <code>## Heading</code>, <code>### Subheading</code>, <code>- list</code>, <code>1. list</code>, <code>**bold**</code>, <code>[link](/services/web-hosting)</code>, <code>| tables |</code>. Internal link rules add links to services automatically.']) ?>
        </section>

        <section class="panel">
            <h2>Search appearance</h2>
            <div class="form-section">
                <div class="field">
                    <label for="f-primary_keyword">Target keyword</label>
                    <input type="text" id="f-primary_keyword" name="primary_keyword" value="<?= e($values['primary_keyword']) ?>" maxlength="150" list="keyword-list">
                    <datalist id="keyword-list"><?php foreach (db_all('SELECT keyword FROM keywords ORDER BY keyword') as $k): ?><option value="<?= e($k['keyword']) ?>"><?php endforeach; ?></datalist>
                    <p class="help">Pick from the keyword plan so two pages don't compete for the same search.</p>
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
                <?= form_input('canonical_url', 'Canonical URL', (string) $values['canonical_url'], $errors, ['type' => 'url', 'placeholder' => absolute_url('/blog/' . ($values['slug'] ?: 'your-slug')),
                    'help' => 'Leave empty for the default self-referencing canonical. Only set it if this content is republished from another URL.']) ?>
                <?php if (ai_enabled()): ?>
                    <button type="button" class="btn btn-sm btn-outline" data-ai="meta" data-api="<?= e(url('/api/ai.php')) ?>" aria-controls="ai-meta"
                            data-fields='<?= e(json_encode(['title' => 'f-title', 'keyword' => 'f-primary_keyword', 'content' => 'f-content'])) ?>'><?= icon('zap', 'icon icon-xs') ?> Suggest meta with AI</button>
                    <div id="ai-meta" class="ai-box" hidden aria-live="polite"></div>
                <?php endif; ?>
            </div>
            <div class="form-section">
                <p class="section-title">Social sharing (OpenGraph)</p>
                <?= form_input('og_title', 'OG title', (string) $values['og_title'], $errors, ['maxlength' => 100, 'help' => 'Optional. Defaults to the meta title.']) ?>
                <?= form_textarea('og_description', 'OG description', (string) $values['og_description'], $errors, ['maxlength' => 200, 'rows' => 2, 'help' => 'Optional. Defaults to the meta description.']) ?>
            </div>
            <div class="form-section">
                <p class="section-title">Structured data</p>
                <?= form_select('schema_type', 'Schema type', $values['schema_type'] ?? 'Article', array_combine(POST_SCHEMA_TYPES, POST_SCHEMA_TYPES), $errors) ?>
                <p class="help">BreadcrumbList is added automatically, and FAQPage when this post has FAQs.</p>
            </div>
        </section>
    </div>

    <div>
        <section class="panel">
            <h2>Publish</h2>
            <?= form_input('published_at', 'Publish date', $values['published_at'] ? date('Y-m-d\TH:i', strtotime($values['published_at'])) : '', $errors,
                ['type' => 'datetime-local', 'help' => 'Empty = publish now. A future date schedules the post.']) ?>
            <div class="form-row">
                <?= form_select('category_id', 'Category', (string) $values['category_id'], $categories, $errors) ?>
                <?= form_select('service_id', 'Related service', (string) $values['service_id'], $services, $errors) ?>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit" name="intent" value="publish"><?= ($post['status'] ?? '') === 'published' ? 'Update' : 'Publish' ?></button>
                <button class="btn btn-outline" type="submit" name="intent" value="draft"><?= ($post['status'] ?? '') === 'published' ? 'Unpublish to draft' : 'Save draft' ?></button>
            </div>
            <p class="security-note"><?= icon('lock', 'icon icon-xs') ?> Protected form · CSRF token · server-side validation</p>
        </section>

        <section class="panel">
            <h2>Search preview</h2>
            <div class="serp" data-serp='<?= e(json_encode(['title' => 'f-title', 'meta_title' => 'f-meta_title', 'meta_description' => 'f-meta_description', 'fallback_description' => 'f-excerpt', 'slug' => 'f-slug', 'base' => absolute_url('/blog/')])) ?>'>
                <div class="serp-url"></div><div class="serp-title"></div><div class="serp-desc"></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>SEO checklist</h2><?= checklist_badge($checklist) ?></div>
            <?= checklist_list($checklist) ?>
            <p class="section-title">Live while typing</p>
            <ul class="checklist" data-seo-checklist='<?= e(json_encode(['keyword' => 'f-primary_keyword', 'title' => 'f-title', 'meta_title' => 'f-meta_title', 'meta_description' => 'f-meta_description', 'content' => 'f-content'])) ?>'></ul>
            <p class="help">Internal editorial checklist, not a Google ranking score.</p>
        </section>

        <section class="panel">
            <h2>Featured image</h2>
            <?php if ($values['featured_image']): ?>
                <img class="current-image" src="<?= e(url('/' . $values['featured_image'])) ?>" alt="<?= e($values['featured_image_alt']) ?>">
                <label class="check"><input type="checkbox" name="remove_image" value="1"> Remove image</label>
            <?php endif; ?>
            <div class="field">
                <label for="f-featured_image">Upload image</label>
                <input type="file" id="f-featured_image" name="featured_image" accept="image/jpeg,image/png,image/webp"<?= field_aria($errors, 'featured_image') ?>>
                <p class="help">JPG, PNG or WebP, up to <?= round(config('uploads.max_bytes') / 1048576, 1) ?> MB. Checked, re-encoded and resized to 1600px.</p>
                <?= field_error($errors, 'featured_image') ?>
            </div>
            <?= form_input('featured_image_alt', 'Alt text', $values['featured_image_alt'], $errors, ['maxlength' => 200]) ?>
        </section>

        <?php if ($id): ?>
            <section class="panel">
                <h2>Internal links</h2>
                <?php if ($renderedLinks): ?>
                    <ul class="issue-list">
                        <?php foreach ($renderedLinks as $l): ?>
                            <li><div><span class="mono small"><?= e(str_replace(base_path(), '', $l['href'])) ?></span><br><span class="muted small">“<?= e($l['text']) ?>” · <?= $l['auto'] ? 'automatic rule' : 'written in content' ?></span></div></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted small">No internal links yet. Mention a service or add a link rule.</p>
                <?php endif; ?>
                <p><a class="small" href="<?= e(url('/admin/links/')) ?>">Manage link rules</a></p>
            </section>
            <section class="panel">
                <h2>Related content</h2>
                <?php if ($related): ?>
                    <ul class="issue-list"><?php foreach ($related as $r): ?><li><a href="<?= e(url('/admin/posts/edit.php?id=' . $r['id'])) ?>"><?= e($r['title']) ?></a></li><?php endforeach; ?></ul>
                <?php else: ?><p class="muted small">No related posts yet.</p><?php endif; ?>
            </section>
            <section class="panel">
                <h2>FAQs</h2>
                <p class="small muted"><?= $faqCount ?> FAQ<?= $faqCount === 1 ? '' : 's' ?> on this post (also output as FAQPage structured data).</p>
                <div class="actions"><a class="btn btn-sm btn-outline" href="<?= e(url('/admin/faqs/edit.php?owner=post:' . $id)) ?>">Add FAQ</a>
                    <a class="small" href="<?= e(url('/admin/faqs/?owner=post')) ?>">Manage FAQs</a></div>
            </section>
            <section class="panel">
                <h2>History</h2>
                <?= activity_timeline(activity_recent(6, 'post', $id)) ?>
            </section>
        <?php endif; ?>
    </div>
</form>
<?php admin_footer(); ?>
