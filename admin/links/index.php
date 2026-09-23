<?php
/**
 * Internal link management: existing links, suggestions, missing opportunities and automatic rules.
 */
require __DIR__ . '/../_init.php';

$editId = input_int('edit') ?: null;
$editing = $editId ? db_one('SELECT * FROM internal_links WHERE id = ?', [$editId]) : null;
$errors = [];
$values = $editing ?? ['keyword' => '', 'target_url' => '', 'priority' => 5, 'status' => 'active'];

if (is_post()) {
    $action = input('action');
    $id = input_int('id', 0, 'post') ?: null;
    if ($action === 'delete' && $id) {
        $rule = db_one('SELECT keyword FROM internal_links WHERE id = ?', [$id]);
        db_delete('internal_links', $id);
        log_activity('deleted', 'link', $id, 'Link rule “' . ($rule['keyword'] ?? '') . '” deleted');
        flash('success', 'Link rule deleted.');
        redirect('/admin/links/');
    }
    if ($action === 'toggle' && $id) {
        db_query("UPDATE internal_links SET status = IF(status = 'active', 'paused', 'active') WHERE id = ?", [$id]);
        flash('success', 'Link rule updated.');
        redirect('/admin/links/');
    }
    [$data, $errors] = internal_link_validate($_POST, $id);
    if (!$errors) {
        $id ? db_update('internal_links', $id, $data) : ($id = db_insert('internal_links', $data));
        log_activity('updated', 'link', $id, 'Link rule “' . $data['keyword'] . '” → ' . $data['target_url']);
        flash('success', 'Link rule saved. It applies the next time pages render.');
        redirect('/admin/links/');
    }
    flash('error', reset($errors));
    $values = array_merge($values, $data);
    $editId = $id;
}

$rules = internal_links_admin_list();
$max = (int) setting('internal_links_max', '5');
$tab = input('tab', 'existing', 'get');

// Existing links on the live site, extracted from the rendered article HTML.
$existing = [];
$suggestions = [];
foreach (db_all('SELECT p.id, p.title, p.slug, p.content FROM posts p WHERE ' . POST_PUBLIC_SQL . ' ORDER BY p.published_at DESC') as $p) {
    $html = apply_internal_links(render_markdown($p['content']), '/blog/' . $p['slug'], $max)['html'];
    preg_match_all('#<a href="' . preg_quote(base_path(), '#') . '(/[^"]*)"( class="auto-link")?[^>]*>(.*?)</a>#', $html, $m, PREG_SET_ORDER);
    foreach ($m as $link) {
        $target = $link[1];
        $existing[] = ['source' => $p, 'target' => $target, 'anchor' => html_entity_decode(strip_tags($link[3])), 'auto' => $link[2] !== '',
            'relevance' => content_at_path($target) ? 'Target live' : 'Target missing'];
    }
    // Suggestions: keyword-plan phrases mentioned in the article whose target page is not linked yet.
    foreach (db_all("SELECT keyword, target_url FROM keywords WHERE target_url LIKE '/%' AND status IN ('targeting','mapped')") as $k) {
        if ($k['target_url'] === '/blog/' . $p['slug'] || str_contains($html, 'href="' . url($k['target_url']) . '"')) {
            continue;
        }
        if (keyword_in_text($k['keyword'], markdown_text($p['content'])) && content_at_path($k['target_url'])) {
            $suggestions[$p['slug'] . $k['target_url']] = ['source' => $p, 'target' => $k['target_url'], 'anchor' => $k['keyword']];
        }
    }
}
$inbound = internal_inbound_map();
$missing = [];
foreach (services_published() as $s) {
    if (empty($inbound['/services/' . $s['slug']])) { $missing[] = ['path' => '/services/' . $s['slug'], 'title' => $s['name'], 'type' => 'Service']; }
}
foreach (landing_pages_published() as $l) {
    if (empty($inbound['/' . $l['slug']])) { $missing[] = ['path' => '/' . $l['slug'], 'title' => $l['title'], 'type' => 'Landing page']; }
}

admin_header('Internal links', 'links', ['Internal links' => null]);
echo page_header('Internal linking', 'Connect guides to services and landing pages so readers and crawlers can follow the path from question to enquiry.');
?>
<section class="panel">
    <div class="panel-head"><h2>Content journey</h2><span class="small muted">The path internal links should create</span></div>
    <div class="flow">
        <div class="flow-node is-good"><small>1 · Content</small><strong>Blog guide</strong><span class="sub">Answers an informational search</span></div>
        <span class="flow-arrow" aria-hidden="true">→</span>
        <div class="flow-node is-good"><small>2 · Service page</small><strong>Contextual link</strong><span class="sub">Automatic rules or written links</span></div>
        <span class="flow-arrow" aria-hidden="true">→</span>
        <div class="flow-node is-good"><small>3 · Related article</small><strong>“Keep reading”</strong><span class="sub">Same service, shown automatically</span></div>
        <span class="flow-arrow" aria-hidden="true">→</span>
        <div class="flow-node is-good"><small>4 · Conversion page</small><strong>Landing page / contact</strong><span class="sub">Lead form records the source</span></div>
    </div>
</section>

<section class="kpis">
    <?= kpi_card(['label' => 'Links on live articles', 'value' => (string) count($existing), 'icon' => 'layers', 'note' => count(array_filter($existing, fn($e) => $e['auto'])) . ' automatic']) ?>
    <?= kpi_card(['label' => 'Suggested links', 'value' => (string) count($suggestions), 'icon' => 'zap']) ?>
    <?= kpi_card(['label' => 'Pages without inbound links', 'value' => (string) count($missing), 'icon' => 'search']) ?>
    <?= kpi_card(['label' => 'Active rules', 'value' => (string) count(array_filter($rules, fn($r) => $r['status'] === 'active')), 'icon' => 'check', 'note' => 'max ' . $max . ' automatic links per page']) ?>
</section>

<?= tabs(['/admin/links/?tab=existing' => 'Existing links <span class="count">' . count($existing) . '</span>', '/admin/links/?tab=suggested' => 'Suggested <span class="count">' . count($suggestions) . '</span>',
    '/admin/links/?tab=missing' => 'Missing opportunities <span class="count">' . count($missing) . '</span>', '/admin/links/?tab=rules' => 'Link rules <span class="count">' . count($rules) . '</span>'], '/admin/links/?tab=' . $tab, 'Link views') ?>

<?php if ($tab === 'existing'): ?>
    <div class="table-wrap"><table class="table-cards">
        <thead><tr><th>Source page</th><th>Target page</th><th>Anchor text</th><th>Relevance</th><th>Status</th><th><span class="visually-hidden">Action</span></th></tr></thead>
        <tbody>
        <?php foreach ($existing as $e): ?>
            <tr>
                <td class="primary"><a href="<?= e(url('/admin/posts/edit.php?id=' . $e['source']['id'])) ?>"><?= e(str_limit($e['source']['title'], 55)) ?></a></td>
                <td data-label="Target" class="mono"><?= e($e['target']) ?></td>
                <td data-label="Anchor">“<?= e($e['anchor']) ?>”</td>
                <td data-label="Relevance"><?= status_indicator($e['relevance'] === 'Target live' ? 'healthy' : 'attention', $e['relevance']) ?></td>
                <td data-label="Status"><?= $e['auto'] ? '<span class="badge badge-info">Automatic rule</span>' : '<span class="badge badge-muted">In content</span>' ?></td>
                <td><a href="<?= e(url('/blog/' . $e['source']['slug'])) ?>" target="_blank" rel="noopener">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php elseif ($tab === 'suggested'): ?>
    <?php if ($suggestions): ?>
        <div class="table-wrap"><table class="table-cards">
            <thead><tr><th>Source page</th><th>Suggested target</th><th>Anchor text</th><th>Why</th><th><span class="visually-hidden">Action</span></th></tr></thead>
            <tbody>
            <?php foreach ($suggestions as $s): ?>
                <tr>
                    <td class="primary"><?= e(str_limit($s['source']['title'], 55)) ?></td>
                    <td data-label="Target" class="mono"><?= e($s['target']) ?></td>
                    <td data-label="Anchor">“<?= e($s['anchor']) ?>”</td>
                    <td data-label="Why" class="small muted">Mentions a mapped keyword but does not link to its target page</td>
                    <td><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/posts/edit.php?id=' . $s['source']['id'])) ?>">Add link</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php else: ?>
        <?= admin_empty('Every mapped keyword mentioned in an article already links to its target page.', '', '', 'check', 'No suggestions') ?>
    <?php endif; ?>
<?php elseif ($tab === 'missing'): ?>
    <?php if ($missing): ?>
        <div class="opp-list">
            <?php foreach ($missing as $m): ?>
                <article class="opp">
                    <div><div class="opp-meta"><span class="badge badge-muted"><?= e($m['type']) ?></span></div>
                        <h3><?= e($m['title']) ?> <span class="mono small muted"><?= e($m['path']) ?></span></h3>
                        <p class="muted">No published article links here. Add a link rule so relevant mentions link automatically.</p></div>
                    <div class="actions"><a class="btn btn-primary btn-sm" href="<?= e(url('/admin/links/?tab=rules&target=' . rawurlencode($m['path']))) ?>#rule-form">Create rule</a></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?= admin_empty('Every service and landing page receives at least one link from the blog.', '', '', 'check', 'No gaps') ?>
    <?php endif; ?>
<?php else: ?>
    <div class="grid-2-1">
        <section class="panel">
            <?php if ($rules): ?>
            <div class="table-wrap"><table class="table-cards">
                <thead><tr><th>Phrase</th><th>Links to</th><th>Priority</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($rules as $r): ?>
                    <tr>
                        <td class="primary"><strong><?= e($r['keyword']) ?></strong></td>
                        <td data-label="Target" class="mono"><?= e($r['target_url']) ?></td>
                        <td data-label="Priority"><?= (int) $r['priority'] ?></td>
                        <td data-label="Status"><?= status_badge($r['status']) ?></td>
                        <td><div class="row-actions">
                            <a href="<?= e(url('/admin/links/?tab=rules&edit=' . $r['id'])) ?>#rule-form">Edit</a>
                            <?= action_button('', $r['status'] === 'active' ? 'Pause' : 'Activate', ['id' => $r['id'], 'action' => 'toggle']) ?>
                            <?= action_button('', 'Delete', ['id' => $r['id'], 'action' => 'delete'], 'btn-link danger', 'Delete the link rule “' . $r['keyword'] . '”?') ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php else: ?><p class="muted">No rules yet.</p><?php endif; ?>
            <p class="small muted">Only the first natural mention of a phrase is linked, never inside headings or existing links, never to the page itself, at most <?= $max ?> per page.</p>
        </section>
        <section class="panel" id="rule-form">
            <h2><?= $editId ? 'Edit rule' : 'Add rule' ?></h2>
            <form method="post" action="<?= e(url('/admin/links/?tab=rules')) ?>" novalidate>
                <?= csrf_field() ?>
                <?php if ($editId): ?><input type="hidden" name="id" value="<?= (int) $editId ?>"><?php endif; ?>
                <?= form_input('keyword', 'Phrase', $values['keyword'], $errors, ['required' => true, 'maxlength' => 150, 'help' => 'Matched case-insensitively as a whole phrase.']) ?>
                <?= form_input('target_url', 'Target URL', $values['target_url'] ?: input('target', '', 'get'), $errors, ['required' => true, 'placeholder' => '/services/web-hosting']) ?>
                <div class="form-row">
                    <?= form_input('priority', 'Priority (1–10)', (string) $values['priority'], $errors, ['type' => 'number', 'min' => 1, 'max' => 10]) ?>
                    <?= form_select('status', 'Status', $values['status'], ['active' => 'Active', 'paused' => 'Paused'], $errors) ?>
                </div>
                <div class="actions"><button class="btn btn-primary" type="submit">Save rule</button><?php if ($editId): ?><a href="<?= e(url('/admin/links/?tab=rules')) ?>">Cancel</a><?php endif; ?></div>
            </form>
        </section>
    </div>
<?php endif; ?>
<?php admin_footer(); ?>
