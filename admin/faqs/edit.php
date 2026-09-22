<?php
require __DIR__ . '/../_init.php';

$id = input_int('id') ?: null;
$faq = $id ? faq_find($id) : null;
if ($id && !$faq) {
    abort(404);
}
$errors = [];
$values = $faq ?? ['question' => '', 'answer' => '', 'sort_order' => 0, 'status' => 'published'];
$owner = $faq ? faq_owner_value($faq) : (preg_match('/^(general|(service|post|landing):\d+)$/', input('owner', '', 'get')) ? input('owner', '', 'get') : 'general');

if (is_post()) {
    [$data, $errors] = faq_validate($_POST);
    $owner = input('owner');
    if (!$errors) {
        $id ? db_update('faqs', $id, $data) : ($id = db_insert('faqs', $data));
        flash('success', 'FAQ saved.');
        redirect('/admin/faqs/edit.php?id=' . $id);
    }
    $values = array_merge($values, $data);
}

// Grouped choices for where the FAQ appears.
$owners = ['general' => 'General FAQ page'];
foreach (db_all('SELECT id, name FROM services ORDER BY sort_order') as $r) {
    $owners['service:' . $r['id']] = 'Service – ' . $r['name'];
}
foreach (db_all('SELECT id, title FROM landing_pages ORDER BY title') as $r) {
    $owners['landing:' . $r['id']] = 'Landing – ' . $r['title'];
}
foreach (db_all('SELECT id, title FROM posts ORDER BY updated_at DESC') as $r) {
    $owners['post:' . $r['id']] = 'Article – ' . str_limit($r['title'], 70);
}

admin_header($id ? 'Edit FAQ' : 'New FAQ', 'faqs');
?>
<div class="page-head">
    <div><h1><?= $id ? 'Edit FAQ' : 'New FAQ' ?></h1><p><a href="<?= e(url('/admin/faqs/')) ?>">&larr; All FAQs</a></p></div>
</div>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields.</div><?php endif; ?>
<form method="post" class="form-layout" novalidate>
    <?= csrf_field() ?>
    <section class="panel">
        <?= form_input('question', 'Question', $values['question'], $errors, ['required' => true, 'maxlength' => 255]) ?>
        <?= form_textarea('answer', 'Answer', $values['answer'], $errors, ['required' => true, 'maxlength' => 3000, 'rows' => 6,
            'help' => 'Answer directly and honestly. The text appears on the page and in FAQPage structured data, so both must match.']) ?>
    </section>
    <section class="panel">
        <?= form_select('owner', 'Show on', $owner, $owners, $errors) ?>
        <div class="form-row">
            <?= form_select('status', 'Status', $values['status'], ['published' => 'Published', 'draft' => 'Draft'], $errors) ?>
            <?= form_input('sort_order', 'Order', (string) $values['sort_order'], $errors, ['type' => 'number', 'min' => 0]) ?>
        </div>
        <button class="btn btn-primary" type="submit">Save FAQ</button>
    </section>
</form>
<?php admin_footer(); ?>
