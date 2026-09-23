<?php
require __DIR__ . '/../_init.php';

$k = db_one('SELECT k.*, s.name AS service_name, s.slug AS service_slug FROM keywords k LEFT JOIN services s ON s.id = k.service_id WHERE k.id = ?', [input_int('id')]);
if (!$k) {
    abort(404);
}
$cov = keyword_coverage($k);
$recommended = keyword_recommended_type($k['intent']);

// Related keywords: share at least one significant word.
$stop = ['a', 'an', 'the', 'in', 'of', 'for', 'to', 'and', 'vs', 'is', 'how', 'what', 'best', 'india', 'on', 'with'];
$words = array_diff(explode(' ', $k['keyword']), $stop);
$related = array_filter(db_all('SELECT id, keyword, intent, target_url FROM keywords WHERE id <> ?', [$k['id']]),
    fn($r) => (bool) array_intersect($words, array_diff(explode(' ', $r['keyword']), $stop)));

// Internal link suggestions: published articles that mention the keyword but do not link to the target.
$suggestions = [];
if ($k['target_url'] && !preg_match('#^https?://#', $k['target_url'])) {
    foreach (db_all('SELECT p.id, p.title, p.slug, p.content FROM posts p WHERE ' . POST_PUBLIC_SQL) as $p) {
        if ('/blog/' . $p['slug'] === $k['target_url'] || !keyword_in_text($k['keyword'], markdown_text($p['content']))) {
            continue;
        }
        $html = apply_internal_links(render_markdown($p['content']), '/blog/' . $p['slug'], (int) setting('internal_links_max', '5'))['html'];
        if (!str_contains($html, 'href="' . url($k['target_url']) . '"')) {
            $suggestions[] = $p;
        }
    }
}
$hasRule = $k['target_url'] ? db_one('SELECT keyword, status FROM internal_links WHERE target_url = ? LIMIT 1', [$k['target_url']]) : null;
$leads = $k['target_url'] ? (int) db_value("SELECT COUNT(*) FROM leads WHERE (source_page = ? OR keyword = ?) AND status <> 'spam'", [$k['target_url'], $k['keyword']]) : 0;

$recommendation = match (true) {
    !$k['target_url'] => 'No page targets this keyword yet. Create a ' . strtolower(KEYWORD_CONTENT_TYPES[$recommended]) . ' that fully answers the ' . $k['intent'] . ' intent.',
    $cov['status'] === 'missing' => 'The target URL is not a published page. Publish it or remap the keyword.',
    $cov['status'] === 'ok' && $cov['score'] < 75 => 'The target page only partly covers this keyword. Use it naturally in the title, meta description and a heading.',
    $k['content_type'] && $k['content_type'] !== $recommended => 'This ' . $k['intent'] . ' keyword is mapped to a ' . strtolower(KEYWORD_CONTENT_TYPES[$k['content_type']]) . '; a ' . strtolower(KEYWORD_CONTENT_TYPES[$recommended]) . ' usually matches this intent better.',
    default => 'Well covered. Next: earn internal links from related guides and track real performance once Search Console is connected.',
};

admin_header('Keyword: ' . $k['keyword'], 'keywords', ['Keywords' => '/admin/keywords/', $k['keyword'] => null]);
echo page_header($k['keyword'], ucfirst($k['intent']) . ' intent · ' . ucfirst($k['priority']) . ' priority',
    '<a class="btn btn-outline" href="' . e(url('/admin/keywords/edit.php?id=' . $k['id'])) . '">Edit keyword</a>'
    . ($cov['target']['edit'] ?? null ? '<a class="btn btn-primary" href="' . e(url($cov['target']['edit'])) . '">Edit target page</a>' : ''));
?>
<section class="panel">
    <div class="panel-head"><h2>Keyword → page mapping</h2><?= status_badge($k['status']) ?></div>
    <div class="flow">
        <div class="flow-node is-good"><small>Keyword</small><strong><?= e($k['keyword']) ?></strong><span class="sub"><?= e(ucfirst($k['intent'])) ?> intent</span></div>
        <span class="flow-arrow" aria-hidden="true">→</span>
        <div class="flow-node<?= $cov['status'] === 'ok' ? ' is-good' : ' is-missing' ?>"><small>Target page</small>
            <strong><?= $cov['target'] ? e($cov['target']['title']) : ($k['target_url'] ? 'Not published' : 'Not mapped') ?></strong><span class="sub"><?= e($k['target_url'] ?: '—') ?></span></div>
        <span class="flow-arrow" aria-hidden="true">→</span>
        <div class="flow-node<?= $k['service_name'] ? ' is-good' : ' is-missing' ?>"><small>Primary service</small><strong><?= e($k['service_name'] ?: 'Not set') ?></strong>
            <span class="sub"><?= $k['service_slug'] ? '/services/' . e($k['service_slug']) : '' ?></span></div>
        <span class="flow-arrow" aria-hidden="true">→</span>
        <div class="flow-node<?= $leads ? ' is-good' : '' ?>"><small>Conversion</small><strong><?= $leads ?> lead<?= $leads === 1 ? '' : 's' ?></strong><span class="sub">attributed to this page/keyword</span></div>
    </div>
</section>

<div class="grid-2-1">
    <div>
        <section class="panel">
            <h2>Content recommendation</h2>
            <p><?= e($recommendation) ?></p>
            <?= meta_list([
                'Recommended content' => e(KEYWORD_CONTENT_TYPES[$recommended]),
                'Current content type' => $k['content_type'] ? e(KEYWORD_CONTENT_TYPES[$k['content_type']]) : '<span class="muted">—</span>',
                'Search volume' => '<span class="muted">Data unavailable — validate in Google Keyword Planner</span>',
                'Difficulty' => '<span class="muted">Data unavailable</span>',
                'Current ranking' => '<span class="muted">Connect Search Console to see real positions</span>',
            ]) ?>
            <?php if (!$k['target_url']): ?>
                <p><a class="btn btn-primary btn-sm" href="<?= e(url(($recommended === 'guide' ? '/admin/posts/edit.php?keyword=' : '/admin/landing-pages/edit.php?keyword=') . rawurlencode($k['keyword']))) ?>">Create <?= e(strtolower(KEYWORD_CONTENT_TYPES[$recommended])) ?></a></p>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Internal link suggestions</h2><a class="small" href="<?= e(url('/admin/links/')) ?>">Link rules</a></div>
            <?php if ($suggestions): ?>
                <p class="small muted">These published articles mention “<?= e($k['keyword']) ?>” but don't link to <?= e($k['target_url']) ?> yet.</p>
                <ul class="issue-list">
                    <?php foreach ($suggestions as $s): ?>
                        <li class="issue-info"><span class="issue-icon" aria-hidden="true">→</span><div><a href="<?= e(url('/admin/posts/edit.php?id=' . $s['id'])) ?>"><strong><?= e($s['title']) ?></strong></a><br><span class="small muted">Add a contextual link to <?= e($k['target_url']) ?></span></div></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!$hasRule): ?>
                    <form method="post" action="<?= e(url('/admin/links/')) ?>" class="inline-form"><?= csrf_field() ?>
                        <input type="hidden" name="keyword" value="<?= e($k['keyword']) ?>"><input type="hidden" name="target_url" value="<?= e($k['target_url']) ?>">
                        <input type="hidden" name="priority" value="5"><input type="hidden" name="status" value="active">
                        <button class="btn btn-outline btn-sm" type="submit">Create automatic link rule</button></form>
                <?php endif; ?>
            <?php elseif ($k['target_url']): ?>
                <p class="muted small">No unlinked mentions found in published articles.<?= $hasRule ? ' A link rule for this page is ' . e($hasRule['status']) . ' (“' . e($hasRule['keyword']) . '”).' : '' ?></p>
            <?php else: ?>
                <p class="muted small">Map the keyword to a page first.</p>
            <?php endif; ?>
        </section>
    </div>
    <div>
        <section class="panel">
            <h2>On-page coverage</h2>
            <?php if ($cov['status'] === 'ok'): ?>
                <p><span class="score score-<?= score_tone($cov['score']) ?>"><?= $cov['score'] ?>%</span> <span class="small muted">of checks pass</span></p>
                <ul class="checklist"><?php foreach ($cov['checks'] as $label => $ok): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= e($label) ?></li><?php endforeach; ?></ul>
            <?php else: ?>
                <p class="muted small"><?= $cov['status'] === 'unmapped' ? 'Not mapped to a page.' : ($cov['status'] === 'external' ? 'Mapped to an external URL.' : 'Target page not found.') ?></p>
            <?php endif; ?>
        </section>
        <section class="panel">
            <h2>Related keywords</h2>
            <?php if ($related): ?>
                <ul class="chips"><?php foreach ($related as $r): ?><li><a href="<?= e(url('/admin/keywords/view.php?id=' . $r['id'])) ?>"><?= e($r['keyword']) ?></a></li><?php endforeach; ?></ul>
            <?php else: ?><p class="muted small">No related keywords in the plan.</p><?php endif; ?>
        </section>
        <section class="panel">
            <h2>Notes</h2>
            <p class="small"><?= $k['notes'] ? nl2br(e($k['notes'])) : '<span class="muted">No notes.</span>' ?></p>
            <p class="small muted">Added <?= e(format_date($k['created_at'])) ?> · updated <?= e(time_ago($k['updated_at'])) ?></p>
        </section>
        <section class="panel"><h2>History</h2><?= activity_timeline(activity_recent(6, 'keyword', (int) $k['id'])) ?></section>
    </div>
</div>
<?php admin_footer(); ?>
