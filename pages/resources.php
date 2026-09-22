<?php
/**
 * Resource hub: groups solutions (landing pages), guides (articles) and services into one crawlable page,
 * which strengthens internal linking between related content.
 */
$solutions = landing_pages_published();
$posts = posts_published(12);
$services = services_published();

$seo = seo([
    'title'       => 'Resources: Hosting, Domain & Email Guides',
    'description' => 'All SYSCOM resources in one place: solution pages, step-by-step guides and service overviews for hosting, domains, business email and SSL.',
    'path'        => '/resources',
    'breadcrumbs' => ['Home' => '/', 'Resources' => '/resources'],
]);
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <?= breadcrumb_nav($seo['breadcrumbs']) ?>
        <h1>Resources to plan your website, email and hosting</h1>
        <p class="lead">Start with a solution overview, go deeper with a guide, or jump straight to a service.</p>
    </div>
</section>

<section class="section" aria-labelledby="solutions-title">
    <div class="container">
        <h2 id="solutions-title">Solutions</h2>
        <?php if ($solutions): ?>
            <div class="grid grid-3">
                <?php foreach ($solutions as $l): ?>
                    <article class="card">
                        <div class="card-icon"><?= icon('zap') ?></div>
                        <h3><a class="stretched" href="<?= e(url('/' . $l['slug'])) ?>"><?= e($l['title']) ?></a></h3>
                        <p><?= e($l['hero_subtitle']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">Solution pages are coming soon.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section section-alt" aria-labelledby="guides-title">
    <div class="container">
        <h2 id="guides-title">Guides</h2>
        <?php if ($posts): ?>
            <div class="grid grid-3">
                <?php foreach ($posts as $p): ?>
                    <?= post_card($p) ?>
                <?php endforeach; ?>
            </div>
            <p><a class="btn btn-outline" href="<?= e(url('/blog')) ?>">All articles</a></p>
        <?php else: ?>
            <p class="empty-state">Guides are coming soon.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section" aria-labelledby="services-list-title">
    <div class="container">
        <h2 id="services-list-title">Services</h2>
        <ul class="pill-list">
            <?php foreach ($services as $s): ?>
                <li><a href="<?= e(url('/services/' . $s['slug'])) ?>"><?= e($s['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
