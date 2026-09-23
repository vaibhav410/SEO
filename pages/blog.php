<?php
/**
 * Blog index (/blog) and category archives (/blog/category/{slug}).
 * @var string|null $categorySlug
 */
$category = null;
if (!empty($categorySlug)) {
    $category = category_by_slug($categorySlug);
    if (!$category) {
        abort(404);
    }
}
$search = mb_substr(input('q', '', 'get'), 0, 100);
$categoryId = $category ? (int) $category['id'] : null;
$total = posts_published_count($search, $categoryId);
$pager = paginate($total, POSTS_PER_PAGE);
$posts = posts_published($pager['per_page'], $pager['offset'], $search, $categoryId);
if ($category && $total === 0) {
    abort(404); // empty category pages are thin content
}
// Requesting a page beyond the last one is a missing page, not a duplicate of the last page.
if (input_int('page', 1) > $pager['pages'] && $total > 0) {
    abort(404);
}
// Feature the newest article on the first page of the unfiltered blog.
$featured = !$category && $search === '' && $pager['page'] === 1 && $posts ? array_shift($posts) : null;
$categories = array_filter(categories_all(), fn($c) => $c['post_count'] > 0);
$path = $category ? '/blog/category/' . $category['slug'] : '/blog';
$crumbs = ['Home' => '/', 'Blog' => '/blog'] + ($category ? [$category['name'] => $path] : []);

$seo = seo([
    'title'       => $search !== '' ? 'Search results for "' . $search . '"' : ($category ? $category['name'] . ' Guides for Indian Businesses' : 'Hosting, Domain & Website Guides'),
    'description' => $category ? ($category['meta_description'] ?: $category['description']) : 'Practical guides on web hosting, domain names, business email, SSL certificates and website security for Indian businesses.',
    'path'        => $path,
    'page'        => $search === '' ? $pager['page'] : 1,
    // Internal search result pages should not be indexed.
    'robots'      => $search !== '' ? 'noindex, follow' : 'index, follow',
    'breadcrumbs' => $crumbs,
]);
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <?= breadcrumb_nav($crumbs) ?>
        <h1><?= $category ? e($category['name']) . ' guides' : 'Guides to hosting, domains and website security' ?></h1>
        <p class="lead"><?= e($category ? $category['description'] : 'Clear, practical advice to help you choose and run the right services for your business.') ?></p>
        <form class="search-form" role="search" action="<?= e(url('/search')) ?>" method="get">
            <label for="blog-search" class="visually-hidden">Search the site</label>
            <input type="search" id="blog-search" name="q" value="<?= e($search) ?>" placeholder="Search guides, services and FAQs…" maxlength="100"
                   data-search-suggest="<?= e(url('/api/search.php')) ?>" autocomplete="off" aria-controls="search-suggestions">
            <button class="btn btn-light" type="submit"><?= icon('search', 'icon icon-sm') ?> Search</button>
        </form>
        <ul id="search-suggestions" class="search-suggest" aria-live="polite"></ul>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($categories): ?>
            <nav class="category-nav" aria-label="Categories">
                <a href="<?= e(url('/blog')) ?>"<?= !$category ? ' aria-current="page"' : '' ?>>All guides</a>
                <?php foreach ($categories as $c): ?>
                    <a href="<?= e(url('/blog/category/' . $c['slug'])) ?>"<?= $category && $category['id'] === $c['id'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?> <span><?= (int) $c['post_count'] ?></span></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if ($featured): ?>
            <article class="featured-post">
                <div class="featured-body">
                    <span class="eyebrow">Featured guide<?= $featured['category_name'] ? ' · ' . e($featured['category_name']) : '' ?></span>
                    <h2><a href="<?= e(url('/blog/' . $featured['slug'])) ?>" class="stretched"><?= e($featured['title']) ?></a></h2>
                    <p><?= e($featured['excerpt']) ?></p>
                    <p class="meta"><time datetime="<?= e(iso_date($featured['published_at'])) ?>"><?= e(format_date($featured['published_at'])) ?></time> · <?= reading_time($featured['content']) ?> min read</p>
                </div>
                <div class="featured-art" aria-hidden="true"><?= icon('book', 'icon featured-icon') ?></div>
            </article>
        <?php endif; ?>

        <h2><?= $search !== '' ? 'Search results' : ($category ? 'All ' . e($category['name']) . ' guides' : 'Latest guides') ?></h2>
        <?php if ($posts): ?>
            <div class="grid grid-3">
                <?php foreach ($posts as $p): ?>
                    <?= post_card($p) ?>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$featured): ?>
            <div class="empty-state"><p>No articles have been published yet.</p></div>
        <?php endif; ?>

        <?php if ($pager['pages'] > 1): ?>
            <nav aria-label="Blog pages">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
                        <li>
                            <?php if ($i === $pager['page']): ?>
                                <span aria-current="page"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= e(url($path) . query_with(['page' => $i === 1 ? null : $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="cta-band">
            <div><h2>Can't find what you need?</h2><p>Ask our team about hosting, domains, email or SSL for your business.</p></div>
            <a class="btn btn-light" href="<?= e(url('/contact')) ?>">Talk to SYSCOM</a>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
