<?php
require __DIR__ . '/../_init.php';

$audit = audit_find(input_int('id'));
if (!$audit) {
    abort(404);
}
$score = (int) $audit['overall_score'];
$stats = $audit['stats'];
$order = ['fail' => 0, 'warn' => 1, 'info' => 2, 'pass' => 3];
$issues = $audit['issues'];
usort($issues, fn($a, $b) => ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9));
$counts = array_count_values(array_column($issues, 'status'));
$history = db_all('SELECT id, overall_score, created_at FROM seo_audits WHERE url = ? ORDER BY created_at DESC LIMIT 6', [$audit['url']]);
$icons = ['pass' => '✓', 'warn' => '!', 'fail' => '✕', 'info' => 'i'];

admin_header('Audit report', 'audits');
?>
<div class="page-head">
    <div>
        <h1>Audit report</h1>
        <p><a href="<?= e(url('/admin/audits/')) ?>">&larr; All audits</a> · <a href="<?= e($audit['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(str_limit($audit['url'], 90)) ?></a></p>
    </div>
    <div class="actions">
        <form method="post" action="<?= e(url('/admin/audits/')) ?>" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="url" value="<?= e($audit['url']) ?>">
            <button class="btn btn-outline" type="submit">Re-run audit</button>
        </form>
    </div>
</div>

<div class="grid-2 grid-2-1">
    <section class="panel">
        <div class="actions">
            <div class="score-ring ring-<?= score_tone($score) ?>" role="img" aria-label="SEO health <?= $score ?> out of 100"><div><?= $score ?><small>/ 100</small></div></div>
            <div>
                <h2>SEO health: <?= $score ?>/100</h2>
                <p class="muted small">Internal on-page checklist score – not a Google ranking metric.</p>
                <p><?= (int) ($counts['pass'] ?? 0) ?> passed · <?= (int) ($counts['warn'] ?? 0) ?> warnings · <?= (int) ($counts['fail'] ?? 0) ?> failed</p>
                <p class="small muted">HTTP <?= (int) $audit['http_status'] ?> · <?= number_format((int) $audit['response_ms']) ?> ms · <?= e(format_date($audit['created_at'], 'j M Y, g:i a')) ?></p>
            </div>
        </div>
    </section>
    <section class="panel">
        <h2>Score breakdown</h2>
        <?php foreach (AUDIT_WEIGHTS as $check => $weight): $s = (int) ($audit[$check . '_score'] ?? 0); ?>
            <div class="bar">
                <span><?= e(AUDIT_LABELS[$check]) ?> <span class="muted small">(<?= $weight ?>%)</span></span>
                <meter min="0" max="100" low="50" high="80" optimum="100" value="<?= $s ?>"><?= $s ?></meter>
                <strong><?= $s ?></strong>
            </div>
        <?php endforeach; ?>
    </section>
</div>

<div class="grid-2 grid-2-1">
    <section class="panel">
        <h2>Findings</h2>
        <ul class="issue-list">
            <?php foreach ($issues as $i): ?>
                <li class="issue-<?= e($i['status']) ?>">
                    <span class="issue-icon" aria-hidden="true"><?= $icons[$i['status']] ?? '•' ?></span>
                    <div><strong><?= e(AUDIT_LABELS[$i['check']] ?? $i['check']) ?></strong> <span class="visually-hidden">(<?= e($i['status']) ?>)</span><br><?= e($i['message']) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <div>
        <?php if ($stats && isset($stats['words'])): ?>
        <section class="panel">
            <h2>Page facts</h2>
            <dl class="meta">
                <dt>Title</dt><dd><?= e($stats['title'] ?: '—') ?></dd>
                <dt>Description</dt><dd><?= e($stats['description'] ?: '—') ?></dd>
                <dt>Words</dt><dd><?= number_format((int) $stats['words']) ?></dd>
                <dt>Links</dt><dd><?= (int) $stats['internal_links'] ?> internal · <?= (int) $stats['external_links'] ?> external · <?= (int) $stats['nofollow_links'] ?> nofollow</dd>
                <dt>Images</dt><dd><?= (int) $stats['images'] ?> (<?= (int) $stats['images_missing_alt'] ?> without alt)</dd>
                <dt>Headings</dt><dd><?= e(implode(', ', array_map(fn($k, $v) => strtoupper($k) . '×' . $v, array_keys($stats['headings'] ?? []), $stats['headings'] ?? [])) ?: '—') ?></dd>
                <dt>Schema</dt><dd><?= e(implode(', ', $stats['schema_types'] ?? []) ?: '—') ?></dd>
                <dt>HTML size</dt><dd><?= number_format((int) ($stats['bytes'] ?? 0) / 1024, 1) ?> KB</dd>
                <?php if ($audit['final_url'] !== $audit['url']): ?><dt>Final URL</dt><dd><?= e($audit['final_url']) ?></dd><?php endif; ?>
            </dl>
        </section>
        <?php endif; ?>
        <?php if (count($history) > 1): ?>
        <section class="panel">
            <h2>History for this URL</h2>
            <ul class="issue-list">
                <?php foreach ($history as $h): ?>
                    <li><span class="score score-<?= score_tone((int) $h['overall_score']) ?>"><?= (int) $h['overall_score'] ?></span>
                        <a href="<?= e(url('/admin/audits/view.php?id=' . $h['id'])) ?>"><?= e(format_date($h['created_at'], 'j M Y, g:i a')) ?></a><?= (int) $h['id'] === (int) $audit['id'] ? ' <span class="muted">(this)</span>' : '' ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>
</div>
<?php admin_footer(); ?>
