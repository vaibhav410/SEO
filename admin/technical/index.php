<?php
require __DIR__ . '/../_init.php';

$cards = technical_overview();
$states = array_count_values(array_column($cards, 'status'));
$labels = ['healthy' => 'Healthy', 'warning' => 'Warning', 'attention' => 'Needs attention'];

admin_header('Technical SEO', 'technical', ['Technical SEO' => null]);
echo page_header('Technical SEO', 'Crawling, indexing and markup health across the site, derived from live configuration, content and audits.',
    '<a class="btn btn-outline" href="' . e(url('/sitemap.xml')) . '" target="_blank" rel="noopener">View sitemap.xml</a>'
    . '<a class="btn btn-outline" href="' . e(url('/robots.txt')) . '" target="_blank" rel="noopener">View robots.txt</a>');
echo tabs(['/admin/technical/' => 'Overview', '/admin/technical/schema.php' => 'Structured data', '/admin/technical/sitemap.php' => 'Sitemap', '/admin/technical/robots.php' => 'Robots.txt'], '/admin/technical/', 'Technical SEO sections');
?>
<section class="kpis">
    <?php foreach ($labels as $key => $label): ?>
        <div class="kpi"><div class="kpi-top"><span class="kpi-label"><?= e($label) ?></span></div>
            <div class="kpi-value"><?= (int) ($states[$key] ?? 0) ?></div><p class="kpi-note"><?= status_indicator($key) ?></p></div>
    <?php endforeach; ?>
</section>

<div class="tech-grid">
    <?php foreach ($cards as $card): ?>
        <article class="tech-card">
            <div class="panel-head"><h3><?= e($card['title']) ?></h3><?= status_indicator($card['status'], $labels[$card['status']]) ?></div>
            <p class="summary"><?= e($card['summary']) ?></p>
            <p><?= e($card['detail']) ?></p>
            <?php if ($card['link']): ?><a href="<?= e(url($card['link'])) ?>">Details →</a><?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php admin_footer(); ?>
