<?php
require __DIR__ . '/../_init.php';

$rows = array_map(fn($r) => $r + ['check' => schema_validate($r['node'])], schema_inventory());
$typeFilter = input('type', '', 'get');
$types = array_count_values(array_column($rows, 'type'));
$visible = array_filter($rows, fn($r) => $typeFilter === '' || $r['type'] === $typeFilter);
$summary = array_count_values(array_map(fn($r) => $r['check']['status'], $rows));
$preview = input('preview', '', 'get');

admin_header('Structured data', 'schema', ['Technical SEO' => '/admin/technical/', 'Structured data' => null]);
echo page_header('Structured data', 'JSON-LD blocks the public site outputs, checked locally for required and recommended properties.');
echo tabs(['/admin/technical/' => 'Overview', '/admin/technical/schema.php' => 'Structured data', '/admin/technical/sitemap.php' => 'Sitemap', '/admin/technical/robots.php' => 'Robots.txt'], '/admin/technical/schema.php', 'Technical SEO sections');
?>
<div class="alert alert-info"><span>This is a <strong>local completeness check</strong> against schema.org and Google's documented required properties. It is not Google's validator: confirm rich-result eligibility with
    <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google's Rich Results Test</a> after deploying.</span></div>

<section class="kpis">
    <?= kpi_card(['label' => 'Schema blocks', 'value' => (string) count($rows), 'icon' => 'layers']) ?>
    <?= kpi_card(['label' => 'Valid', 'value' => (string) ($summary['valid'] ?? 0), 'icon' => 'check']) ?>
    <?= kpi_card(['label' => 'Warnings', 'value' => (string) ($summary['warning'] ?? 0), 'icon' => 'zap', 'note' => 'Recommended properties missing']) ?>
    <?= kpi_card(['label' => 'Errors', 'value' => (string) ($summary['error'] ?? 0), 'icon' => 'shield', 'note' => 'Required properties missing']) ?>
</section>

<nav class="pill-tabs" aria-label="Schema type">
    <a href="<?= e(url('/admin/technical/schema.php')) ?>"<?= $typeFilter === '' ? ' aria-current="page"' : '' ?>>All (<?= count($rows) ?>)</a>
    <?php foreach ($types as $t => $n): ?><a href="<?= e(url('/admin/technical/schema.php?type=' . rawurlencode($t))) ?>"<?= $typeFilter === $t ? ' aria-current="page"' : '' ?>><?= e($t) ?> (<?= $n ?>)</a><?php endforeach; ?>
</nav>

<div class="table-wrap"><table class="table-cards">
    <thead><tr><th>Schema type</th><th>Page</th><th>Status</th><th>Validation</th><th>Last updated</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($visible as $i => $r): $chk = $r['check']; $id = md5($r['path'] . $r['type']); ?>
        <tr>
            <td class="primary"><strong><?= e($r['type']) ?></strong></td>
            <td data-label="Page"><a href="<?= e(url($r['path'])) ?>" target="_blank" rel="noopener"><?= e(str_limit($r['page'], 45)) ?></a><span class="sub mono"><?= e($r['path']) ?></span></td>
            <td data-label="Status"><?= status_indicator($chk['status']) ?></td>
            <td data-label="Validation" class="small"><?php
                if ($chk['missing']) { echo 'Missing required: ' . e(implode(', ', $chk['missing'])); }
                elseif ($chk['missing_recommended']) { echo '<span class="muted">Recommended: ' . e(implode(', ', $chk['missing_recommended'])) . '</span>'; }
                else { echo '<span class="muted">All required and recommended properties present</span>'; } ?></td>
            <td data-label="Updated" class="muted nowrap"><?= $r['updated'] ? e(time_ago($r['updated'])) : '—' ?></td>
            <td><div class="row-actions">
                <a href="<?= e(url('/admin/technical/schema.php') . query_with(['preview' => $id])) ?>#preview">JSON</a>
                <?php if ($r['edit']): ?><a href="<?= e(url($r['edit'])) ?>">Edit source</a><?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>

<?php foreach ($rows as $r): if (md5($r['path'] . $r['type']) !== $preview) { continue; } ?>
    <section class="panel" id="preview">
        <div class="panel-head"><h2><?= e($r['type']) ?> on <?= e($r['path']) ?></h2><button class="btn btn-outline btn-sm" type="button" data-copy="schema-json">Copy</button></div>
        <pre class="code-box" id="schema-json"><?= e(json_encode(['@context' => 'https://schema.org'] + $r['node'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
    </section>
<?php endforeach; ?>
<?php admin_footer(); ?>
