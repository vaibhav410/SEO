<?php
require __DIR__ . '/../_init.php';

$id = input_int('id') ?: null;
$page = $id ? landing_find($id) : null;
if ($id && !$page) {
    abort(404);
}
$errors = [];
$values = $page ?? ['title' => '', 'slug' => '', 'primary_keyword' => input('keyword', '', 'get'), 'meta_title' => '', 'meta_description' => '',
    'hero_subtitle' => '', 'problem' => '', 'solution' => '', 'features' => '', 'benefits' => '', 'use_cases' => '', 'content' => '',
    'cta_text' => 'Talk to our team', 'service_id' => '', 'status' => 'draft'];

if (is_post()) {
    [$data, $errors] = landing_validate($_POST, $id);
    if (!$errors) {
        $savedId = landing_save($data, $id);
        flash('success', 'Landing page saved.');
        redirect('/admin/landing-pages/edit.php?id=' . $savedId);
    }
    $values = array_merge($values, $data);
}
$services = ['' => '— None —'] + array_column(db_all('SELECT id, name FROM services ORDER BY sort_order'), 'name', 'id');

admin_header($id ? 'Edit landing page' : 'New landing page', 'landing');
?>
<div class="page-head">
    <div><h1><?= $id ? 'Edit landing page' : 'New landing page' ?></h1>
        <p><a href="<?= e(url('/admin/landing-pages/')) ?>">&larr; All landing pages</a>
        <?php if ($page && $page['status'] === 'published'): ?> · <a href="<?= e(url('/' . $page['slug'])) ?>" target="_blank" rel="noopener">View live</a><?php endif; ?></p></div>
</div>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields.</div><?php endif; ?>

<form method="post" class="form-layout" novalidate>
    <?= csrf_field() ?>
    <div>
        <section class="panel">
            <h2>Hero</h2>
            <div class="form-row">
                <?= form_input('primary_keyword', 'Primary keyword', $values['primary_keyword'], $errors, ['required' => true, 'maxlength' => 150]) ?>
                <?= form_input('slug', 'URL slug', $values['slug'], $errors, ['maxlength' => 120, 'data-slug-source' => 'f-primary_keyword', 'help' => 'Served at /{slug}. Generated from the keyword if blank.']) ?>
            </div>
            <?= form_input('title', 'Headline (H1)', $values['title'], $errors, ['required' => true, 'maxlength' => 200]) ?>
            <?= form_textarea('hero_subtitle', 'Subheadline', $values['hero_subtitle'], $errors, ['rows' => 2, 'maxlength' => 300, 'required' => true]) ?>
        </section>
        <section class="panel">
            <h2>Problem &amp; solution</h2>
            <?= form_textarea('problem', 'The problem your visitor has', $values['problem'], $errors, ['rows' => 4, 'required' => true]) ?>
            <?= form_textarea('solution', 'How SYSCOM solves it', $values['solution'], $errors, ['rows' => 4, 'required' => true]) ?>
        </section>
        <section class="panel">
            <h2>Features, benefits, use cases</h2>
            <?= form_textarea('features', 'Features (one per line, "Title: description")', $values['features'], $errors, ['rows' => 6, 'help' => 'At least three.']) ?>
            <?= form_textarea('benefits', 'Benefits (one per line)', $values['benefits'], $errors, ['rows' => 4]) ?>
            <?= form_textarea('use_cases', 'Use cases (one per line, "Who: how it helps")', $values['use_cases'], $errors, ['rows' => 4]) ?>
            <?= form_textarea('content', 'Extra content (optional, Markdown)', (string) $values['content'], $errors, ['rows' => 8, 'help' => 'Steps, comparisons or links to guides that make the page more useful.']) ?>
        </section>
    </div>
    <div>
        <section class="panel">
            <h2>Publish</h2>
            <?= form_select('status', 'Status', $values['status'], ['draft' => 'Draft', 'published' => 'Published'], $errors) ?>
            <?= form_select('service_id', 'Related service', (string) $values['service_id'], $services, $errors) ?>
            <?= form_input('cta_text', 'Call-to-action text', $values['cta_text'], $errors, ['maxlength' => 150, 'required' => true]) ?>
            <button class="btn btn-primary" type="submit">Save landing page</button>
        </section>
        <section class="panel">
            <h2>Search appearance</h2>
            <div class="field">
                <label for="f-meta_title">Meta title <?= seo_counter('meta_title', 30, 60) ?></label>
                <input type="text" id="f-meta_title" name="meta_title" value="<?= e($values['meta_title']) ?>" maxlength="70"<?= field_aria($errors, 'meta_title') ?>>
                <?= field_error($errors, 'meta_title') ?>
            </div>
            <div class="field">
                <label for="f-meta_description">Meta description <?= seo_counter('meta_description', 70, 160) ?></label>
                <textarea id="f-meta_description" name="meta_description" maxlength="170" rows="4"<?= field_aria($errors, 'meta_description') ?>><?= e($values['meta_description']) ?></textarea>
                <?= field_error($errors, 'meta_description') ?>
            </div>
            <div class="serp" data-serp='<?= e(json_encode(['title' => 'f-title', 'meta_title' => 'f-meta_title', 'meta_description' => 'f-meta_description', 'fallback_description' => 'f-hero_subtitle', 'slug' => 'f-slug', 'base' => absolute_url('/')])) ?>'>
                <div class="serp-url"></div><div class="serp-title"></div><div class="serp-desc"></div>
            </div>
            <?php if (ai_enabled()): ?>
                <p><button type="button" class="btn btn-sm btn-outline" data-ai="meta" data-api="<?= e(url('/api/ai.php')) ?>" aria-controls="ai-meta"
                        data-fields='<?= e(json_encode(['title' => 'f-title', 'keyword' => 'f-primary_keyword', 'content' => 'f-solution'])) ?>'>Suggest meta with AI</button></p>
                <div id="ai-meta" class="ai-box" hidden aria-live="polite"></div>
            <?php endif; ?>
        </section>
        <?php if ($id): ?>
            <section class="panel">
                <h2>FAQs</h2>
                <a class="btn btn-sm btn-outline" href="<?= e(url('/admin/faqs/edit.php?owner=landing:' . $id)) ?>">Add FAQ to this page</a>
            </section>
        <?php endif; ?>
    </div>
</form>
<?php admin_footer(); ?>
