<?php
require __DIR__ . '/../_init.php';

$id = input_int('id') ?: null;
$keyword = $id ? keyword_find($id) : null;
if ($id && !$keyword) {
    abort(404);
}
$errors = [];
$values = $keyword ?? ['keyword' => '', 'intent' => 'commercial', 'priority' => 'medium', 'target_url' => '', 'status' => 'researching', 'notes' => ''];

if (is_post()) {
    [$data, $errors] = keyword_validate($_POST, $id);
    if (!$errors) {
        $id ? db_update('keywords', $id, $data) : ($id = db_insert('keywords', $data));
        flash('success', 'Keyword saved.');
        redirect('/admin/keywords/edit.php?id=' . $id);
    }
    $values = array_merge($values, $data);
}
$coverage = $keyword ? keyword_coverage($keyword) : null;

// Suggest existing pages as targets.
$targets = ['/'];
foreach (services_published() as $s) { $targets[] = '/services/' . $s['slug']; }
foreach (landing_pages_published() as $l) { $targets[] = '/' . $l['slug']; }
foreach (db_all('SELECT p.slug FROM posts p WHERE ' . POST_PUBLIC_SQL) as $p) { $targets[] = '/blog/' . $p['slug']; }

admin_header($id ? 'Edit keyword' : 'Add keyword', 'keywords');
?>
<div class="page-head">
    <div><h1><?= $id ? 'Edit keyword' : 'Add keyword' ?></h1><p><a href="<?= e(url('/admin/keywords/')) ?>">&larr; Keyword plan</a></p></div>
</div>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields.</div><?php endif; ?>
<form method="post" class="form-layout" novalidate>
    <?= csrf_field() ?>
    <section class="panel">
        <?= form_input('keyword', 'Keyword', $values['keyword'], $errors, ['required' => true, 'maxlength' => 150]) ?>
        <div class="form-row three">
            <?= form_select('intent', 'Search intent', $values['intent'], array_combine(KEYWORD_INTENTS, array_map('ucfirst', KEYWORD_INTENTS)), $errors) ?>
            <?= form_select('priority', 'Priority', $values['priority'], array_combine(KEYWORD_PRIORITIES, array_map('ucfirst', KEYWORD_PRIORITIES)), $errors) ?>
            <?= form_select('status', 'Status', $values['status'], array_combine(KEYWORD_STATUSES, array_map('ucfirst', KEYWORD_STATUSES)), $errors) ?>
        </div>
        <?= form_input('target_url', 'Target URL', (string) $values['target_url'], $errors, ['list' => 'targets', 'placeholder' => '/services/web-hosting',
            'help' => 'The single page that should rank for this keyword. One keyword, one page, to avoid cannibalisation.']) ?>
        <datalist id="targets"><?php foreach ($targets as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
        <?= form_textarea('notes', 'Notes', (string) $values['notes'], $errors, ['rows' => 4, 'help' => 'Research notes, competitor observations, or verified search volume with its source and date.']) ?>
        <button class="btn btn-primary" type="submit">Save keyword</button>
    </section>
    <div>
        <section class="panel">
            <h2>Intent guide</h2>
            <ul class="checklist small">
                <li><strong>Informational</strong>&nbsp;– wants to learn → blog guide</li>
                <li><strong>Navigational</strong>&nbsp;– looking for SYSCOM → home / brand pages</li>
                <li><strong>Commercial</strong>&nbsp;– comparing options → landing / service page</li>
                <li><strong>Transactional</strong>&nbsp;– ready to buy → service page with clear CTA</li>
            </ul>
        </section>
        <?php if ($coverage && $coverage['status'] === 'ok'): ?>
            <section class="panel">
                <h2>On-page coverage: <?= $coverage['score'] ?>%</h2>
                <p class="small muted"><?= e($coverage['target']['type']) ?>: <?= e($coverage['target']['title']) ?></p>
                <ul class="checklist">
                    <?php foreach ($coverage['checks'] as $label => $ok): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= e($label) ?></li><?php endforeach; ?>
                </ul>
                <?php if ($coverage['target']['edit']): ?><p><a class="btn btn-sm btn-outline" href="<?= e(url($coverage['target']['edit'])) ?>">Edit target page</a></p><?php endif; ?>
            </section>
        <?php elseif ($coverage && $coverage['status'] === 'missing'): ?>
            <div class="alert alert-warning">The target URL is not a published page on this site.</div>
        <?php endif; ?>
        <?php if (ai_enabled() && $keyword): ?>
            <section class="panel">
                <h2>Content idea</h2>
                <p class="small muted">Draft an article outline for this keyword. A writer should expand and fact-check it.</p>
                <input type="hidden" id="kw-keyword" value="<?= e($keyword['keyword']) ?>"><input type="hidden" id="kw-intent" value="<?= e($keyword['intent']) ?>">
                <button type="button" class="btn btn-sm btn-outline" data-ai="outline" data-api="<?= e(url('/api/ai.php')) ?>" aria-controls="ai-outline"
                        data-fields='<?= e(json_encode(['keyword' => 'kw-keyword', 'intent' => 'kw-intent'])) ?>'>Suggest outline</button>
                <div id="ai-outline" class="ai-box" hidden aria-live="polite"></div>
            </section>
        <?php endif; ?>
    </div>
</form>
<?php admin_footer(); ?>
