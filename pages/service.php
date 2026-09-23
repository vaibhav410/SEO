<?php
/** @var string $slug */
$service = service_by_slug($slug);
if (!$service) {
    abort(404);
}
$faqs = faqs_for('service', (int) $service['id']);
$features = lines($service['features']);
$related = db_all(
    "SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.featured_image, p.featured_image_alt, p.published_at
     FROM posts p WHERE p.service_id = ? AND " . POST_PUBLIC_SQL . " ORDER BY p.published_at DESC LIMIT 3",
    [$service['id']]
);
$otherServices = array_filter(services_published(), fn($s) => $s['id'] !== $service['id']);

$seo = seo([
    'title'       => $service['meta_title'] ?: $service['name'],
    'description' => $service['meta_description'] ?: $service['description'],
    'path'        => '/services/' . $service['slug'],
    'canonical'   => (string) $service['canonical_url'],
    'og_title'    => (string) $service['og_title'],
    'og_description' => (string) $service['og_description'],
    'breadcrumbs' => ['Home' => '/', 'Services' => '/services', $service['name'] => '/services/' . $service['slug']],
    'schema'      => [schema_service($service), schema_faq($faqs)],
]);
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <?= breadcrumb_nav($seo['breadcrumbs']) ?>
        <h1><?= e($service['name']) ?></h1>
        <p class="lead"><?= e($service['description']) ?></p>
        <div class="btn-row">
            <a class="btn btn-light" href="<?= e(url('/contact')) ?>">Talk to our team</a>
            <?php if ($service['external_url']): ?>
                <a class="btn btn-ghost-light" href="<?= e($service['external_url']) ?>" rel="noopener">View plans &amp; pricing <?= icon('external', 'icon icon-xs') ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="container split-wide section">
    <article class="prose">
        <?= apply_internal_links(render_markdown($service['content']), '/services/' . $service['slug'], (int) setting('internal_links_max', '5'))['html'] ?>
    </article>
    <aside>
        <?php if ($features): ?>
            <div class="aside-card">
                <h2>What's included</h2>
                <ul class="check-list">
                    <?php foreach ($features as $f): ?>
                        <li><?= icon('check') ?> <span><?= e($f) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="aside-card">
            <h2>Need advice?</h2>
            <p>Tell us about your website and we will suggest the right <?= e(strtolower($service['name'])) ?> setup.</p>
            <a class="btn btn-primary btn-sm" href="<?= e(url('/contact')) ?>">Contact us</a>
        </div>
    </aside>
</div>

<?php if ($faqs): ?>
<section class="section section-alt" aria-labelledby="service-faq">
    <div class="container narrow">
        <h2 id="service-faq"><?= e($service['name']) ?> FAQs</h2>
        <?= faq_list($faqs) ?>
    </div>
</section>
<?php endif; ?>

<?php if ($related): ?>
<section class="section" aria-labelledby="related-guides">
    <div class="container">
        <h2 id="related-guides">Related guides</h2>
        <div class="grid grid-3">
            <?php foreach ($related as $p): ?>
                <?= post_card($p) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section section-alt" aria-labelledby="other-services">
    <div class="container">
        <h2 id="other-services">Other services</h2>
        <div class="grid grid-4">
            <?php foreach (array_slice($otherServices, 0, 4) as $s): ?>
                <?= service_card($s) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
