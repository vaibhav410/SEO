<?php
require __DIR__ . '/_init.php';

$q = mb_substr(input('q', '', 'get'), 0, 100);
$results = admin_search($q, 10);
$grouped = [];
foreach ($results as $r) {
    $grouped[$r['type']][] = $r;
}

admin_header('Search', '', ['Search' => null]);
echo page_header('Search', $q !== '' ? count($results) . ' result' . (count($results) === 1 ? '' : 's') . ' for “' . $q . '”' : 'Search across content, keywords, leads, backlinks and audits.');
?>
<form class="filters" method="get" role="search">
    <div class="field"><label for="q">Search everything</label><input type="search" id="q" name="q" value="<?= e($q) ?>" minlength="2" maxlength="100" autofocus></div>
    <button class="btn btn-primary" type="submit">Search</button>
</form>

<?php if ($q === '' || mb_strlen($q) < 2): ?>
    <?= admin_empty('Type at least two characters. Tip: press “/” anywhere in the admin to jump to search.', '', '', 'search', 'Search GrowthHub') ?>
<?php elseif (!$results): ?>
    <?= admin_empty('Nothing matched “' . $q . '”. Try a shorter term or check the spelling.', '', '', 'search', 'No results found') ?>
<?php else: ?>
    <div class="grid-2">
        <?php foreach ($grouped as $type => $rows): ?>
            <section class="panel">
                <div class="panel-head"><h2><?= e($type) ?>s</h2><span class="badge badge-muted"><?= count($rows) ?></span></div>
                <ul class="issue-list">
                    <?php foreach ($rows as $r): ?>
                        <li><div><a href="<?= e(url($r['url'])) ?>"><strong><?= e($r['title']) ?></strong></a><br><span class="muted small"><?= e($r['meta']) ?></span></div></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php admin_footer(); ?>
