<?php
$search = mb_substr(input('q', '', 'get'), 0, 100);
$total = posts_published_count($search);
$pager = paginate($total, POSTS_PER_PAGE);
$posts = posts_published($pager['per_page'], $pager['offset'], $search);

// Requesting a page beyond the last one is a missing page, not a duplicate of the last page.
if (input_int('page', 1) > $pager['pages'] && $total > 0) {
    abort(404);
}

$seo = seo([
    'title'       => $search !== '' ? 'Search results for "' . $search . '"' : 'Hosting, Domain & Website Guides',
    'description' => 'Practical guides on web hosting, domain names, business email, SSL certificates and website security for Indian businesses.',
    'path'        => '/blog',
    'page'        => $search === '' ? $pager['page'] : 1,
    // Internal search result pages should not be indexed.
    'robots'      => $search !== '' ? 'noindex, follow' : 'index, follow',
    'breadcrumbs' => ['Home' => '/', 'Blog' => '/blog'],
]);
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <?= breadcrumb_nav($seo['breadcrumbs']) ?>
        <h1>Guides to hosting, domains and website security</h1>
        <p class="lead">Clear, practical advice to help you choose and run the right services for your business.</p>
        <form class="search-form" role="search" action="<?= e(url('/blog')) ?>" method="get">
            <label for="blog-search" class="visually-hidden">Search articles</label>
            <input type="search" id="blog-search" name="q" value="<?= e($search) ?>" placeholder="Search guides…" maxlength="100"
                   data-search-suggest="<?= e(url('/api/search.php')) ?>" autocomplete="off" aria-controls="search-suggestions">
            <button class="btn btn-light" type="submit"><?= icon('search', 'icon icon-sm') ?> Search</button>
        </form>
        <ul id="search-suggestions" class="search-suggest" aria-live="polite"></ul>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($search !== ''): ?>
            <p class="muted"><?= $total ?> result<?= $total === 1 ? '' : 's' ?> for “<?= e($search) ?>”. <a href="<?= e(url('/blog')) ?>">Clear search</a></p>
        <?php endif; ?>

        <?php if ($posts): ?>
            <div class="grid grid-3">
                <?php foreach ($posts as $p): ?>
                    <?= post_card($p) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p><?= $search !== '' ? 'No articles match your search. Try a broader term such as “hosting” or “email”.' : 'No articles have been published yet.' ?></p>
            </div>
        <?php endif; ?>

        <?php if ($pager['pages'] > 1): ?>
            <nav aria-label="Blog pages">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
                        <li>
                            <?php if ($i === $pager['page']): ?>
                                <span aria-current="page"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= e(url('/blog') . query_with(['page' => $i === 1 ? null : $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
