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
        redirect('/admin/audits/view.php?id=' . $result['id']);
    }
    $error = $result['error'];
}

$total = (int) db_value('SELECT COUNT(*) FROM seo_audits');
$pager = paginate($total, 20);
$audits = audits_recent($pager['per_page'], $pager['offset']);

// Suggest this site's own key pages for quick audits.
$quick = ['/', '/services', '/blog'];
foreach (array_slice(landing_pages_published(), 0, 3) as $l) {
    $quick[] = '/' . $l['slug'];
}

admin_header('SEO auditor', 'audits');
?>
<div class="page-head">
    <div><h1>SEO auditor</h1><p>Fetches a page and checks titles, descriptions, headings, canonical, robots, Open Graph, structured data, image alt text, links and mobile basics.</p></div>
</div>

<section class="panel">
    <form id="audit-form" method="post" data-api="<?= e(url('/api/audit.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="audit-url">Page URL</label>
            <div class="actions">
                <input type="url" id="audit-url" name="url" value="<?= e($url) ?>" placeholder="https://syscom.co.in/" required maxlength="2000" class="grow">
                <button class="btn btn-primary" type="submit">Run audit</button>
            </div>
            <p class="help">Public http/https pages only. Private networks, localhost and cloud metadata addresses are blocked<?= config('audit.allow_self') ? ' (this site\'s own origin is allowed in this environment)' : '' ?>.</p>
        </div>
        <div id="audit-loading" class="loading" role="status"><span class="spinner" aria-hidden="true"></span> Fetching and analysing the page…</div>
        <div id="audit-error" class="alert alert-error" role="alert"<?= $error ? '' : ' hidden' ?>><?= e($error) ?></div>
    </form>
    <p class="small muted">Quick audit:
        <?php foreach ($quick as $q): ?>
            <a href="<?= e(url('/admin/audits/?url=' . rawurlencode(absolute_url($q)))) ?>"><?= e($q) ?></a> ·
        <?php endforeach; ?>
        <a href="<?= e(url('/admin/audits/?url=' . rawurlencode(setting('main_site_url', 'https://syscom.co.in') . '/'))) ?>">syscom.co.in</a>
    </p>
</section>

<p class="small muted">The SEO health score is an internal checklist score for on-page best practice. It is not a Google metric and does not predict rankings.</p>

<?php if ($audits): ?>
<div class="table-wrap"><table>
    <thead><tr><th>URL</th><th>HTTP</th><th>Score</th><th>Response</th><th>When</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($audits as $a): ?>
        <tr>
            <td><a href="<?= e(url('/admin/audits/view.php?id=' . $a['id'])) ?>"><?= e(str_limit($a['url'], 70)) ?></a>
                <?php if ($a['final_url'] && $a['final_url'] !== $a['url']): ?><span class="sub">→ <?= e(str_limit($a['final_url'], 70)) ?></span><?php endif; ?></td>
            <td><?= $a['http_status'] ? '<span class="badge badge-' . ($a['http_status'] < 400 ? 'success' : 'danger') . '">' . (int) $a['http_status'] . '</span>' : '—' ?></td>
            <td><span class="score score-<?= score_tone((int) $a['overall_score']) ?>"><?= (int) $a['overall_score'] ?></span></td>
            <td class="muted"><?= $a['response_ms'] ? number_format((int) $a['response_ms']) . ' ms' : '—' ?></td>
            <td class="nowrap muted"><?= e(time_ago($a['created_at'])) ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/audits/view.php?id=' . $a['id'])) ?>">Report</a>
                <?= action_button('', 'Delete', ['id' => $a['id'], 'action' => 'delete'], 'btn-link danger', 'Delete this audit?') ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?= admin_pagination($pager) ?>
<?php else: ?>
    <?= admin_empty('No audits yet. Enter a URL above to run the first one.') ?>
<?php endif; ?>
<?php admin_footer(); ?>
