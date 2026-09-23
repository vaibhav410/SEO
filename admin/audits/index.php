<?php
require __DIR__ . '/../_init.php';

$error = '';
$url = input('url', '', 'get');
if (is_post()) {
    if (input('action') === 'delete') {
        db_delete('seo_audits', input_int('id', 0, 'post'));
        flash('success', 'Audit deleted.');
        redirect('/admin/audits/');
    }
    // Non-JavaScript fallback: run the audit synchronously.
    $url = input('url');
    $result = audit_run($url, (int) $user['id']);
    if ($result['id']) {
        log_activity('audited', 'audit', $result['id'], 'Audited ' . str_limit($url, 80));
        redirect('/admin/audits/view.php?id=' . $result['id']);
    }
    $error = $result['error'];
}

$search = mb_substr(input('q', '', 'get'), 0, 100);
$where = $search !== '' ? ' WHERE url LIKE ?' : '';
$params = $search !== '' ? ['%' . addcslashes($search, '%_\\') . '%'] : [];
$pager = paginate((int) db_value('SELECT COUNT(*) FROM seo_audits' . $where, $params), 15);
$audits = db_all('SELECT id, url, final_url, http_status, overall_score, response_ms, issues, created_at FROM seo_audits' . $where
    . ' ORDER BY created_at DESC, id DESC LIMIT ' . $pager['per_page'] . ' OFFSET ' . $pager['offset'], $params);

$quick = ['/', '/services', '/blog'];
foreach (array_slice(landing_pages_published(), 0, 2) as $l) {
    $quick[] = '/' . $l['slug'];
}

admin_header('SEO auditor', 'audits', ['SEO auditor' => null]);
echo page_header('SEO auditor', 'Fetch any public page and check its on-page SEO: titles, descriptions, headings, canonical, robots, OpenGraph, structured data, images, links and mobile basics.');
?>
<section class="panel">
    <form id="audit-form" method="post" data-api="<?= e(url('/api/audit.php')) ?>" novalidate data-no-loading>
        <?= csrf_field() ?>
        <div class="field">
            <label for="audit-url">Page URL</label>
            <div class="actions">
                <input type="url" id="audit-url" name="url" value="<?= e($url) ?>" placeholder="https://syscom.co.in/" required maxlength="2000" class="grow">
                <button class="btn btn-primary" type="submit"><?= icon('shield', 'icon icon-sm') ?> Run SEO audit</button>
            </div>
            <p class="help">Public http/https pages only. Localhost, private networks and cloud metadata addresses are blocked (SSRF protection)<?= config('audit.allow_self') ? '; this site\'s own origin is allowed in this environment' : '' ?>.</p>
        </div>
        <div id="audit-loading" class="loading" role="status"><span class="spinner" aria-hidden="true"></span> Fetching and analysing the page… this can take up to <?= (int) config('audit.timeout') ?> seconds.</div>
        <div id="audit-skeleton" hidden><?= skeleton(5) ?></div>
        <div id="audit-error" class="alert alert-error" role="alert"<?= $error ? '' : ' hidden' ?>><?= e($error) ?></div>
    </form>
    <p class="small muted">Quick audit:
        <?php foreach ($quick as $q): ?><a href="<?= e(url('/admin/audits/?url=' . rawurlencode(absolute_url($q)))) ?>"><?= e($q) ?></a> · <?php endforeach; ?>
        <a href="<?= e(url('/admin/audits/?url=' . rawurlencode(setting('main_site_url', 'https://syscom.co.in') . '/'))) ?>">syscom.co.in</a></p>
</section>

<div class="alert alert-info"><?= icon('shield', 'icon icon-sm') ?><span>The SEO health score is an <strong>internal diagnostic checklist</strong>. It is not a Google score and does not predict rankings.</span></div>

<section class="panel">
    <div class="panel-head"><h2>Audit history</h2>
        <form class="filters" method="get" role="search"><div class="field"><label for="q" class="visually-hidden">Filter by URL</label><input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Filter by URL…"></div></form>
    </div>
    <?php if ($audits): ?>
        <div class="table-wrap"><table class="table-cards">
            <thead><tr><th>URL</th><th>Timestamp</th><th>HTTP</th><th>Score</th><th>Summary</th><th>Response</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($audits as $a): $counts = array_count_values(array_column(json_decode($a['issues'], true) ?: [], 'status')); ?>
                <tr>
                    <td class="primary"><a href="<?= e(url('/admin/audits/view.php?id=' . $a['id'])) ?>"><?= e(str_limit($a['url'], 70)) ?></a>
                        <?php if ($a['final_url'] && $a['final_url'] !== $a['url']): ?><span class="sub">→ <?= e(str_limit($a['final_url'], 70)) ?></span><?php endif; ?></td>
                    <td data-label="When" class="nowrap muted" title="<?= e($a['created_at']) ?>"><?= e(format_date($a['created_at'], 'j M, g:i a')) ?></td>
                    <td data-label="HTTP"><?= $a['http_status'] ? '<span class="badge badge-' . ($a['http_status'] < 400 ? 'success' : 'danger') . '">' . (int) $a['http_status'] . '</span>' : '—' ?></td>
                    <td data-label="Score"><span class="score score-<?= score_tone((int) $a['overall_score']) ?>"><?= (int) $a['overall_score'] ?></span></td>
                    <td data-label="Summary" class="small nowrap"><?= (int) ($counts['pass'] ?? 0) ?> passed · <?= (int) ($counts['warn'] ?? 0) ?> warnings · <?= (int) ($counts['fail'] ?? 0) ?> issues</td>
                    <td data-label="Response" class="muted"><?= $a['response_ms'] ? number_format((int) $a['response_ms']) . ' ms' : '—' ?></td>
                    <td><div class="row-actions">
                        <a href="<?= e(url('/admin/audits/view.php?id=' . $a['id'])) ?>">Report</a>
                        <?= action_button('', 'Delete', ['id' => $a['id'], 'action' => 'delete'], 'btn-link danger', 'Delete this audit from the history?') ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="table-foot"><span><?= $pager['total'] ?> audit<?= $pager['total'] === 1 ? '' : 's' ?></span><?= admin_pagination($pager) ?></div>
    <?php else: ?>
        <?= admin_empty($search ? 'No audits match that URL.' : 'Enter a URL above to run the first audit.', '', '', 'shield', 'No audits yet') ?>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
