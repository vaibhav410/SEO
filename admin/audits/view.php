<?php
require __DIR__ . '/../_init.php';

$audit = audit_find(input_int('id'));
if (!$audit) {
    abort(404);
}
$score = (int) $audit['overall_score'];
$stats = $audit['stats'];
$rows = audit_report_rows($audit);
$failedFetch = (int) $audit['http_status'] >= 400 || !isset($stats['words']);
$counts = array_count_values(array_column($rows, 'status'));
$history = db_all('SELECT id, overall_score, created_at FROM seo_audits WHERE url = ? ORDER BY created_at DESC LIMIT 6', [$audit['url']]);
$icons = ['pass' => '✓', 'warn' => '!', 'fail' => '✕'];
$labels = ['pass' => 'Passed', 'warn' => 'Warning', 'fail' => 'Issue'];
$redirects = array_values(array_filter($audit['issues'], fn($i) => $i['check'] === 'http'));

admin_header('Audit report', 'audits', ['SEO auditor' => '/admin/audits/', 'Report #' . $audit['id'] => null]);
?>
<div class="page-head">
    <div>
        <h1>Audit report</h1>
        <p><a href="<?= e($audit['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(str_limit($audit['url'], 90)) ?> <?= icon('external', 'icon icon-xs') ?></a> · <?= e(format_date($audit['created_at'], 'j M Y, g:i a')) ?></p>
    </div>
    <form method="post" action="<?= e(url('/admin/audits/')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="url" value="<?= e($audit['url']) ?>">
        <button class="btn btn-outline" type="submit">Re-run audit</button></form>
</div>

<div class="grid-2-1">
    <section class="panel">
        <div class="actions">
            <div class="score-ring ring-<?= score_tone($score) ?>" role="img" aria-label="SEO health <?= $score ?> out of 100"><div><?= $score ?><small>/ 100</small></div></div>
            <div>
                <h2>SEO health overview</h2>
                <p class="muted small">Internal on-page diagnostic score – not a Google score and not a ranking prediction.</p>
                <p><?= status_indicator('pass', ($counts['pass'] ?? 0) . ' passed') ?> &nbsp; <?= status_indicator('warn', ($counts['warn'] ?? 0) . ' warnings') ?> &nbsp; <?= status_indicator('fail', ($counts['fail'] ?? 0) . ' issues') ?></p>
                <p class="small muted">HTTP <?= (int) $audit['http_status'] ?> · <?= number_format((int) $audit['response_ms']) ?> ms<?= $audit['final_url'] !== $audit['url'] ? ' · redirected to ' . e($audit['final_url']) : '' ?></p>
            </div>
        </div>
    </section>
    <section class="panel">
        <h2>Score breakdown</h2>
        <?php foreach (AUDIT_WEIGHTS as $check => $weight): ?>
            <?= progress_row(AUDIT_LABELS[$check] . ' (' . $weight . '%)', (int) ($audit[$check . '_score'] ?? 0)) ?>
        <?php endforeach; ?>
    </section>
</div>

<?php if ($failedFetch): ?>
    <div class="alert alert-error"><span><?= e($audit['issues'][0]['message'] ?? 'The page could not be analysed.') ?></span></div>
<?php else: ?>
<section class="panel">
    <div class="panel-head"><h2>Detailed findings &amp; recommended fixes</h2></div>
    <div class="table-wrap"><table class="table-cards">
        <thead><tr><th>Check</th><th>Status</th><th>Finding</th><th>Recommended fix</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="primary"><strong><?= e($r['label']) ?></strong><?= $r['score'] !== null ? '<span class="sub">' . $r['score'] . '/100</span>' : '' ?></td>
                <td data-label="Status"><?= status_indicator($r['status'], $icons[$r['status']] . ' ' . $labels[$r['status']]) ?></td>
                <td data-label="Finding" class="small"><?= e($r['finding']) ?></td>
                <td data-label="Fix" class="small"><?= $r['fix'] ? e($r['fix']) : '<span class="muted">No action needed</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>

<div class="grid-2">
    <?php if (!$failedFetch): ?>
    <section class="panel">
        <h2>Page facts</h2>
        <?= meta_list([
            'Title' => e($stats['title'] ?: '—'),
            'Description' => e($stats['description'] ?: '—'),
            'Words' => number_format((int) $stats['words']),
            'Links' => (int) $stats['internal_links'] . ' internal · ' . (int) $stats['external_links'] . ' external · ' . (int) $stats['nofollow_links'] . ' nofollow',
            'Images' => (int) $stats['images'] . ' (' . (int) $stats['images_missing_alt'] . ' without alt)',
            'Headings' => e(implode(', ', array_map(fn($k, $v) => strtoupper($k) . '×' . $v, array_keys($stats['headings'] ?? []), $stats['headings'] ?? [])) ?: '—'),
            'Schema' => e(implode(', ', $stats['schema_types'] ?? []) ?: '—'),
            'HTML size' => number_format((int) ($stats['bytes'] ?? 0) / 1024, 1) . ' KB',
        ]) ?>
        <?php if ($redirects): ?><ul class="issue-list"><?php foreach ($redirects as $r): ?><li class="issue-<?= e($r['status']) ?>"><span class="issue-icon" aria-hidden="true">i</span><div class="small"><?= e($r['message']) ?></div></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <?php endif; ?>
    <section class="panel">
        <h2>Audit history for this URL</h2>
        <?php if (count($history) > 1): ?>
            <?= chart_bars(array_reverse(array_map(fn($h) => ['label' => format_date($h['created_at'], 'j M'), 'value' => (int) $h['overall_score']], $history)), 'Score history', 'points') ?>
        <?php endif; ?>
        <ul class="issue-list">
            <?php foreach ($history as $h): ?>
                <li><span class="score score-<?= score_tone((int) $h['overall_score']) ?>"><?= (int) $h['overall_score'] ?></span>
                    <a href="<?= e(url('/admin/audits/view.php?id=' . $h['id'])) ?>"><?= e(format_date($h['created_at'], 'j M Y, g:i a')) ?></a><?= (int) $h['id'] === (int) $audit['id'] ? ' <span class="muted small">(this report)</span>' : '' ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
<?php admin_footer(); ?>
