<?php
/**
 * Site search: articles, services, landing pages and FAQs. Always noindex (search result pages
 * are thin, infinite and disallowed in robots.txt).
 */
$q = mb_substr(trim(input('q', '', 'get')), 0, 100);
$results = site_search($q, 12);
$type = input('type', '', 'get');
$groups = [];
foreach ($results as $r) {
    $groups[$r['type']][] = $r;
}
$visible = $type !== '' && isset($groups[$type]) ? $groups[$type] : $results;

$seo = seo([
    'title'       => $q !== '' ? 'Search: ' . $q : 'Search',
    'description' => 'Search SYSCOM guides, service pages, solutions and FAQs about web hosting, domains, business email and SSL certificates.',
    'path'        => '/search',
    'robots'      => 'noindex, follow',
    'breadcrumbs' => ['Home' => '/', 'Search' => '/search'],
]);
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero page-hero-compact">
    <div class="container">
        <?= breadcrumb_nav($seo['breadcrumbs']) ?>
        <h1><?= $q !== '' ? 'Results for “' . e($q) . '”' : 'Search SYSCOM' ?></h1>
        <form class="search-form search-form-lg" role="search" action="<?= e(url('/search')) ?>" method="get">
            <label for="site-search" class="visually-hidden">Search query</label>
            <input type="search" id="site-search" name="q" value="<?= e($q) ?>" placeholder="e.g. VPS hosting, SSL, domain transfer" maxlength="100" minlength="2" autofocus>
            <button class="btn btn-light" type="submit"><?= icon('search', 'icon icon-sm') ?> Search</button>
        </form>
    </div>
</section>

<section class="section">
    <div class="container narrow">
        <?php if ($q === '' || mb_strlen($q) < 2): ?>
            <div class="empty-state">
                <p>Search across our guides, services, solutions and FAQs.</p>
                <ul class="pill-list pill-center">
                    <?php foreach (['web hosting', 'VPS', 'SSL certificate', 'business email', 'domain transfer'] as $s): ?>
                        <li><a href="<?= e(url('/search?q=' . rawurlencode($s))) ?>"><?= e($s) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif (!$results): ?>
            <div class="empty-state">
                <h2>No relevant results found.</h2>
                <p>Try a broader term such as “hosting”, “email” or “domain”, or <a href="<?= e(url('/contact')) ?>">ask our team</a>.</p>
            </div>
        <?php else: ?>
            <p class="muted"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= e($q) ?>”</p>
            <nav class="category-nav" aria-label="Filter results">
                <a href="<?= e(url('/search') . query_with(['type' => null])) ?>"<?= $type === '' ? ' aria-current="page"' : '' ?>>All <span><?= count($results) ?></span></a>
                <?php foreach ($groups as $g => $items): ?>
                    <a href="<?= e(url('/search') . query_with(['type' => $g])) ?>"<?= $type === $g ? ' aria-current="page"' : '' ?>><?= e($g) ?>s <span><?= count($items) ?></span></a>
                <?php endforeach; ?>
            </nav>
            <ol class="search-results">
                <?php foreach ($visible as $r): ?>
                    <li>
                        <span class="result-type"><?= e($r['type']) ?></span>
                        <h2><a href="<?= e(url($r['url'])) ?>"><?= e($r['title']) ?></a></h2>
                        <p class="result-url"><?= e(absolute_url($r['url'])) ?></p>
                        <p><?= e(str_limit($r['excerpt'], 180)) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
