<?php
require __DIR__ . '/../_init.php';

$id = input_int('id') ?: null;
$service = $id ? service_find($id) : null;
if ($id && !$service) {
    abort(404);
}
$errors = [];
$values = $service ?? ['name' => '', 'slug' => '', 'icon' => 'server', 'description' => '', 'content' => '', 'features' => '',
    'primary_keyword' => '', 'external_url' => '', 'meta_title' => '', 'meta_description' => '', 'sort_order' => 0, 'status' => 'draft'];

if (is_post()) {
    [$data, $errors] = service_validate($_POST, $id);
    if (!$errors) {
        $savedId = service_save($data, $id);
        flash('success', 'Service saved.');
        redirect('/admin/services/edit.php?id=' . $savedId);
    }
    $values = array_merge($values, $data);
}

admin_header($id ? 'Edit service' : 'New service', 'services');
?>
<div class="page-head">
    <div><h1><?= $id ? 'Edit service' : 'New service' ?></h1><p><a href="<?= e(url('/admin/services/')) ?>">&larr; All services</a></p></div>
</div>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields.</div><?php endif; ?>

<form method="post" class="form-layout" novalidate>
    <?= csrf_field() ?>
    <div>
        <section class="panel">
            <div class="form-row">
                <?= form_input('name', 'Service name (H1)', $values['name'], $errors, ['required' => true, 'maxlength' => 150]) ?>
                <?= form_input('slug', 'URL slug', $values['slug'], $errors, ['maxlength' => 120, 'data-slug-source' => 'f-name', 'help' => '/services/{slug}']) ?>
            </div>
            <?= form_textarea('description', 'Short description', $values['description'], $errors, ['rows' => 2, 'maxlength' => 300, 'required' => true, 'help' => 'Shown on service cards and in the page hero.']) ?>
            <?= form_textarea('content', 'Page content', $values['content'], $errors, ['class' => 'tall', 'required' => true, 'help' => 'Markdown subset: ## headings, lists, **bold**, [links](/path), | tables |.']) ?>
            <?= form_textarea('features', "What's included (one per line)", $values['features'], $errors, ['rows' => 6]) ?>
        </section>
        <section class="panel">
            <h2>Search appearance</h2>
            <?= form_input('primary_keyword', 'Primary keyword', $values['primary_keyword'], $errors, ['maxlength' => 150]) ?>
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
                        data-fields='<?= e(json_encode(['title' => 'f-name', 'keyword' => 'f-primary_keyword', 'content' => 'f-content'])) ?>'>Suggest meta with AI</button>
                <div id="ai-meta" class="ai-box" hidden aria-live="polite"></div>
            <?php endif; ?>
        </section>
    </div>
    <div>
        <section class="panel">
            <h2>Publish</h2>
            <?= form_select('status', 'Status', $values['status'], ['draft' => 'Draft', 'published' => 'Published'], $errors) ?>
            <div class="form-row">
                <?= form_select('icon', 'Icon', $values['icon'], array_combine(SERVICE_ICONS, array_map('ucfirst', SERVICE_ICONS)), $errors) ?>
                <?= form_input('sort_order', 'Order', (string) $values['sort_order'], $errors, ['type' => 'number', 'min' => 0, 'max' => 999]) ?>
            </div>
            <?= form_input('external_url', 'Plans page on main site', (string) $values['external_url'], $errors, ['type' => 'url', 'help' => 'Optional link to pricing on syscom.co.in.']) ?>
            <button class="btn btn-primary" type="submit">Save service</button>
        </section>
        <section class="panel">
            <h2>Search preview</h2>
            <div class="serp" data-serp='<?= e(json_encode(['title' => 'f-name', 'meta_title' => 'f-meta_title', 'meta_description' => 'f-meta_description', 'fallback_description' => 'f-description', 'slug' => 'f-slug', 'base' => absolute_url('/services/')])) ?>'>
                <div class="serp-url"></div><div class="serp-title"></div><div class="serp-desc"></div>
            </div>
        </section>
        <?php if ($id): ?>
            <section class="panel">
                <h2>FAQs</h2>
                <a class="btn btn-sm btn-outline" href="<?= e(url('/admin/faqs/edit.php?owner=service:' . $id)) ?>">Add FAQ to this service</a>
            </section>
        <?php endif; ?>
    </div>
</form>
<?php admin_footer(); ?>
