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
    'cta_text' => 'Talk to our team', 'canonical_url' => '', 'og_title' => '', 'og_description' => '', 'service_id' => '', 'status' => 'draft'];

if (is_post()) {
    [$data, $errors] = landing_validate($_POST, $id);
    if (!$errors) {
        $savedId = landing_save($data, $id);
        log_activity($id ? 'updated' : 'created', 'landing', $savedId, 'Landing page “' . $data['title'] . '” ' . ($id ? 'updated' : 'created'));
        flash('success', 'Landing page saved.');
        redirect('/admin/landing-pages/edit.php?id=' . $savedId);
    }
    $values = array_merge($values, $data);
}
$services = ['' => '— None —'] + array_column(db_all('SELECT id, name FROM services ORDER BY sort_order'), 'name', 'id');
$checklist = content_checklist($values, 'landing');
$faqs = $id ? db_all('SELECT id, question FROM faqs WHERE landing_page_id = ? ORDER BY sort_order', [$id]) : [];
$relatedPosts = !empty($values['service_id'])
    ? db_all('SELECT id, title FROM posts p WHERE p.service_id = ? AND ' . POST_PUBLIC_SQL . ' ORDER BY p.published_at DESC LIMIT 4', [(int) $values['service_id']]) : [];
$keywordRow = $values['primary_keyword'] ? db_one('SELECT id, intent, target_url FROM keywords WHERE keyword = ?', [mb_strtolower($values['primary_keyword'])]) : null;

admin_header($id ? 'Edit landing page' : 'New landing page', 'landing', ['Landing pages' => '/admin/landing-pages/', $id ? str_limit($values['title'], 40) : 'New' => null]);
?>
<div class="page-head">
    <div><h1><?= $id ? e($values['title']) : 'New landing page' ?></h1>
        <p><?= status_badge($page['status'] ?? 'new') ?>
        <?php if ($page && $page['status'] === 'published'): ?> · <a href="<?= e(url('/' . $page['slug'])) ?>" target="_blank" rel="noopener">View live <?= icon('external', 'icon icon-xs') ?></a><?php endif; ?></p></div>
    <div class="actions"><?= checklist_badge($checklist) ?> <span class="small muted">SEO checklist</span></div>
</div>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields. Nothing was saved.</div><?php endif; ?>

<form method="post" class="form-layout" novalidate>
    <?= csrf_field() ?>
    <div>
        <section class="panel">
            <p class="section-title">1 · Hero</p>
            <div class="form-row">
                <?= form_input('primary_keyword', 'Primary keyword', $values['primary_keyword'], $errors, ['required' => true, 'maxlength' => 150, 'list' => 'kw-list']) ?>
                <?= form_input('slug', 'URL slug', $values['slug'], $errors, ['maxlength' => 120, 'data-slug-source' => 'f-primary_keyword', 'help' => 'Served at /{slug}. Generated from the keyword if blank.']) ?>
            </div>
            <datalist id="kw-list"><?php foreach (db_all("SELECT keyword FROM keywords WHERE intent IN ('commercial','transactional') ORDER BY keyword") as $k): ?><option value="<?= e($k['keyword']) ?>"><?php endforeach; ?></datalist>
            <?= form_input('title', 'Headline (H1)', $values['title'], $errors, ['required' => true, 'maxlength' => 200]) ?>
            <?= form_textarea('hero_subtitle', 'Subheadline', $values['hero_subtitle'], $errors, ['rows' => 2, 'maxlength' => 300, 'required' => true]) ?>
        </section>
        <section class="panel">
            <p class="section-title">2 · Problem &amp; solution</p>
            <?= form_textarea('problem', 'The problem your visitor has', (string) $values['problem'], $errors, ['rows' => 4, 'required' => true]) ?>
            <?= form_textarea('solution', 'How SYSCOM solves it', (string) $values['solution'], $errors, ['rows' => 4, 'required' => true]) ?>
        </section>
        <section class="panel">
            <p class="section-title">3 · Features, benefits &amp; use cases</p>
            <?= form_textarea('features', 'Features (one per line, "Title: description")', (string) $values['features'], $errors, ['rows' => 6, 'help' => 'At least three, so the page is genuinely useful.']) ?>
            <div class="form-row">
                <?= form_textarea('benefits', 'Benefits (one per line)', (string) $values['benefits'], $errors, ['rows' => 5]) ?>
                <?= form_textarea('use_cases', 'Use cases ("Who: how it helps")', (string) $values['use_cases'], $errors, ['rows' => 5]) ?>
            </div>
        </section>
        <section class="panel">
            <p class="section-title">4 · Supporting content &amp; call to action</p>
            <?= form_textarea('content', 'Extra content (Markdown, optional)', (string) $values['content'], $errors, ['rows' => 8, 'help' => 'Setup steps, comparisons or links to guides. Pricing lives on syscom.co.in; link to it from the related service.']) ?>
            <?= form_input('cta_text', 'CTA / form heading', $values['cta_text'], $errors, ['maxlength' => 150, 'required' => true]) ?>
        </section>
    </div>
    <div>
        <section class="panel">
            <h2>Publish</h2>
            <?= form_select('status', 'Status', $values['status'], ['draft' => 'Draft', 'published' => 'Published'], $errors) ?>
            <?= form_select('service_id', 'Related service', (string) $values['service_id'], $services, $errors) ?>
            <button class="btn btn-primary btn-block" type="submit">Save landing page</button>
            <p class="security-note"><?= icon('lock', 'icon icon-xs') ?> Protected form · CSRF token · server-side validation</p>
        </section>
        <section class="panel">
            <h2>SEO metadata</h2>
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
            <?= form_input('canonical_url', 'Canonical URL', (string) $values['canonical_url'], $errors, ['type' => 'url', 'help' => 'Empty = self-referencing (recommended).']) ?>
            <?= form_input('og_title', 'OG title', (string) $values['og_title'], $errors, ['maxlength' => 100]) ?>
            <?= form_textarea('og_description', 'OG description', (string) $values['og_description'], $errors, ['maxlength' => 200, 'rows' => 2]) ?>
            <p class="help">Schema: BreadcrumbList + FAQPage (when FAQs exist), generated automatically.</p>
            <div class="serp" data-serp='<?= e(json_encode(['title' => 'f-title', 'meta_title' => 'f-meta_title', 'meta_description' => 'f-meta_description', 'fallback_description' => 'f-hero_subtitle', 'slug' => 'f-slug', 'base' => absolute_url('/')])) ?>'>
                <div class="serp-url"></div><div class="serp-title"></div><div class="serp-desc"></div>
            </div>
            <?php if (ai_enabled()): ?>
                <p><button type="button" class="btn btn-sm btn-outline" data-ai="meta" data-api="<?= e(url('/api/ai.php')) ?>" aria-controls="ai-meta"
                        data-fields='<?= e(json_encode(['title' => 'f-title', 'keyword' => 'f-primary_keyword', 'content' => 'f-solution'])) ?>'><?= icon('zap', 'icon icon-xs') ?> Suggest meta with AI</button></p>
                <div id="ai-meta" class="ai-box" hidden aria-live="polite"></div>
            <?php endif; ?>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>SEO checklist</h2><?= checklist_badge($checklist) ?></div>
            <?= checklist_list($checklist) ?>
            <?php if ($keywordRow): ?>
                <p class="small">Keyword plan: <?= e(ucfirst($keywordRow['intent'])) ?> intent → <span class="mono"><?= e($keywordRow['target_url'] ?: 'unmapped') ?></span>
                    <a href="<?= e(url('/admin/keywords/view.php?id=' . $keywordRow['id'])) ?>">Open</a></p>
            <?php elseif ($values['primary_keyword']): ?>
                <p class="small muted">This keyword is not in the plan yet. <a href="<?= e(url('/admin/keywords/edit.php')) ?>">Add it</a> to track coverage.</p>
            <?php endif; ?>
        </section>
        <?php if ($id): ?>
            <section class="panel">
                <div class="panel-head"><h2>FAQs</h2><a class="btn btn-sm btn-outline" href="<?= e(url('/admin/faqs/edit.php?owner=landing:' . $id)) ?>">Add FAQ</a></div>
                <?php if ($faqs): ?><ul class="issue-list"><?php foreach ($faqs as $f): ?><li><a href="<?= e(url('/admin/faqs/edit.php?id=' . $f['id'])) ?>"><?= e($f['question']) ?></a></li><?php endforeach; ?></ul>
                <?php else: ?><p class="muted small">No FAQs yet. Answer the questions buyers ask before enquiring.</p><?php endif; ?>
            </section>
            <section class="panel">
                <h2>Related articles</h2>
                <?php if ($relatedPosts): ?><ul class="issue-list"><?php foreach ($relatedPosts as $p): ?><li><a href="<?= e(url('/admin/posts/edit.php?id=' . $p['id'])) ?>"><?= e($p['title']) ?></a></li><?php endforeach; ?></ul>
                <?php else: ?><p class="muted small">Choose a related service to see supporting guides.</p><?php endif; ?>
            </section>
            <section class="panel"><h2>History</h2><?= activity_timeline(activity_recent(6, 'landing', $id)) ?></section>
        <?php endif; ?>
    </div>
</form>
<?php admin_footer(); ?>
