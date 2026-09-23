<?php
require __DIR__ . '/../_init.php';

$id = input_int('id') ?: null;
$service = $id ? service_find($id) : null;
if ($id && !$service) {
    abort(404);
}
$errors = [];
$values = $service ?? ['name' => '', 'slug' => '', 'icon' => 'server', 'description' => '', 'content' => '', 'features' => '', 'benefits' => '',
    'cta_text' => 'Talk to our team', 'primary_keyword' => '', 'external_url' => '', 'meta_title' => '', 'meta_description' => '',
    'canonical_url' => '', 'og_title' => '', 'og_description' => '', 'sort_order' => 0, 'status' => 'draft'];

if (is_post()) {
    [$data, $errors] = service_validate($_POST, $id);
    if (!$errors) {
        $savedId = service_save($data, $id);
        log_activity($id ? 'updated' : 'created', 'service', $savedId, 'Service “' . $data['name'] . '” ' . ($id ? 'updated' : 'created'));
        flash('success', 'Service saved.');
        redirect('/admin/services/edit.php?id=' . $savedId);
    }
    $values = array_merge($values, $data);
}
$checklist = content_checklist($values, 'service');
$relatedPosts = $id ? db_all('SELECT id, title, status FROM posts WHERE service_id = ? ORDER BY updated_at DESC', [$id]) : [];
$otherServices = $id ? db_all("SELECT id, name FROM services WHERE id <> ? AND status = 'published' ORDER BY sort_order LIMIT 6", [$id]) : [];
$faqs = $id ? db_all('SELECT id, question, status FROM faqs WHERE service_id = ? ORDER BY sort_order', [$id]) : [];

admin_header($id ? 'Edit service' : 'New service', 'services', ['Services' => '/admin/services/', $id ? $values['name'] : 'New service' => null]);
?>
<div class="page-head">
    <div><h1><?= $id ? e($values['name']) : 'New service' ?></h1>
        <p><?= status_badge($service['status'] ?? 'new') ?>
        <?php if ($service && $service['status'] === 'published'): ?> · <a href="<?= e(url('/services/' . $service['slug'])) ?>" target="_blank" rel="noopener">View live <?= icon('external', 'icon icon-xs') ?></a><?php endif; ?></p></div>
    <div class="actions"><?= checklist_badge($checklist) ?> <span class="small muted">SEO checklist</span></div>
</div>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields. Nothing was saved.</div><?php endif; ?>

<form method="post" class="form-layout" novalidate>
    <?= csrf_field() ?>
    <div>
        <section class="panel">
            <p class="section-title">Service</p>
            <div class="form-row">
                <?= form_input('name', 'Service name (H1)', $values['name'], $errors, ['required' => true, 'maxlength' => 150]) ?>
                <?= form_input('slug', 'URL slug', $values['slug'], $errors, ['maxlength' => 120, 'data-slug-source' => 'f-name', 'help' => '/services/{slug}']) ?>
            </div>
            <?= form_textarea('description', 'Short description', $values['description'], $errors, ['rows' => 2, 'maxlength' => 300, 'required' => true, 'help' => 'Shown on service cards and in the page hero.']) ?>
            <?= form_textarea('content', 'Page content', $values['content'], $errors, ['class' => 'tall', 'required' => true, 'help' => 'Markdown subset: ## headings, lists, **bold**, [links](/path), | tables |.']) ?>
            <div class="form-row">
                <?= form_textarea('features', "Features (one per line)", (string) $values['features'], $errors, ['rows' => 6]) ?>
                <?= form_textarea('benefits', 'Benefits (one per line)', (string) $values['benefits'], $errors, ['rows' => 6]) ?>
            </div>
            <div class="form-row">
                <?= form_input('cta_text', 'Call-to-action text', (string) $values['cta_text'], $errors, ['maxlength' => 150]) ?>
                <?= form_input('external_url', 'Plans & pricing page', (string) $values['external_url'], $errors, ['type' => 'url', 'help' => 'Link to the matching page on syscom.co.in.']) ?>
            </div>
        </section>
        <section class="panel">
            <h2>Search appearance</h2>
            <?= form_input('primary_keyword', 'Primary keyword', (string) $values['primary_keyword'], $errors, ['maxlength' => 150]) ?>
            <div class="field">
                <label for="f-meta_title">SEO title <?= seo_counter('meta_title', 30, 60) ?></label>
                <input type="text" id="f-meta_title" name="meta_title" value="<?= e($values['meta_title']) ?>" maxlength="70"<?= field_aria($errors, 'meta_title') ?>>
                <?= field_error($errors, 'meta_title') ?>
            </div>
            <div class="field">
                <label for="f-meta_description">Meta description <?= seo_counter('meta_description', 70, 160) ?></label>
                <textarea id="f-meta_description" name="meta_description" maxlength="170" rows="3"<?= field_aria($errors, 'meta_description') ?>><?= e($values['meta_description']) ?></textarea>
                <?= field_error($errors, 'meta_description') ?>
            </div>
            <?= form_input('canonical_url', 'Canonical URL', (string) $values['canonical_url'], $errors, ['type' => 'url', 'placeholder' => absolute_url('/services/' . ($values['slug'] ?: 'slug')), 'help' => 'Leave empty for the default self-referencing canonical.']) ?>
            <div class="form-row">
                <?= form_input('og_title', 'OG title', (string) $values['og_title'], $errors, ['maxlength' => 100]) ?>
                <?= form_input('og_description', 'OG description', (string) $values['og_description'], $errors, ['maxlength' => 200]) ?>
            </div>
            <p class="help">Schema: <strong>Service</strong> (provider: SYSCOM), BreadcrumbList, and FAQPage when FAQs exist. Generated automatically.</p>
            <?php if (ai_enabled()): ?>
                <button type="button" class="btn btn-sm btn-outline" data-ai="meta" data-api="<?= e(url('/api/ai.php')) ?>" aria-controls="ai-meta"
                        data-fields='<?= e(json_encode(['title' => 'f-name', 'keyword' => 'f-primary_keyword', 'content' => 'f-content'])) ?>'><?= icon('zap', 'icon icon-xs') ?> Suggest meta with AI</button>
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
            <button class="btn btn-primary btn-block" type="submit">Save service</button>
            <p class="security-note"><?= icon('lock', 'icon icon-xs') ?> Protected form · CSRF token · server-side validation</p>
        </section>
        <section class="panel">
            <h2>Search preview</h2>
            <div class="serp" data-serp='<?= e(json_encode(['title' => 'f-name', 'meta_title' => 'f-meta_title', 'meta_description' => 'f-meta_description', 'fallback_description' => 'f-description', 'slug' => 'f-slug', 'base' => absolute_url('/services/')])) ?>'>
                <div class="serp-url"></div><div class="serp-title"></div><div class="serp-desc"></div>
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>SEO checklist</h2><?= checklist_badge($checklist) ?></div>
            <?= checklist_list($checklist) ?>
        </section>
        <?php if ($id): ?>
            <section class="panel">
                <div class="panel-head"><h2>FAQs</h2><a class="btn btn-sm btn-outline" href="<?= e(url('/admin/faqs/edit.php?owner=service:' . $id)) ?>">Add FAQ</a></div>
                <?php if ($faqs): ?><ul class="issue-list"><?php foreach ($faqs as $f): ?><li><a href="<?= e(url('/admin/faqs/edit.php?id=' . $f['id'])) ?>"><?= e($f['question']) ?></a></li><?php endforeach; ?></ul>
                <?php else: ?><p class="muted small">No FAQs yet.</p><?php endif; ?>
            </section>
            <section class="panel">
                <h2>Related blog posts</h2>
                <?php if ($relatedPosts): ?><ul class="issue-list"><?php foreach ($relatedPosts as $p): ?><li><div><a href="<?= e(url('/admin/posts/edit.php?id=' . $p['id'])) ?>"><?= e($p['title']) ?></a> <?= status_badge($p['status']) ?></div></li><?php endforeach; ?></ul>
                <?php else: ?><p class="muted small">No articles support this service yet. <a href="<?= e(url('/admin/posts/edit.php')) ?>">Write one</a>.</p><?php endif; ?>
            </section>
            <section class="panel">
                <h2>Related services</h2>
                <ul class="chips"><?php foreach ($otherServices as $o): ?><li><a href="<?= e(url('/admin/services/edit.php?id=' . $o['id'])) ?>"><?= e($o['name']) ?></a></li><?php endforeach; ?></ul>
                <p class="help">Shown automatically under “Other services” on the public page.</p>
            </section>
            <section class="panel"><h2>History</h2><?= activity_timeline(activity_recent(6, 'service', $id)) ?></section>
        <?php endif; ?>
    </div>
</form>
<?php admin_footer(); ?>
