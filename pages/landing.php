<?php
/**
 * SEO landing page template: Hero > Problem > Solution > Features > Benefits > Use cases > Content > FAQ > CTA + form.
 * @var string $slug
 */
$page = landing_by_slug($slug);
if (!$page) {
    abort(404);
}
$path = '/' . $page['slug'];
if (is_post()) {
    lead_handle_submission($path, $page['primary_keyword']);
}

$faqs = faqs_for('landing', (int) $page['id']);
$features = titled_lines($page['features']);
$useCases = titled_lines($page['use_cases']);
$benefits = lines($page['benefits']);
$crumbs = ['Home' => '/'];
if ($page['service_slug']) {
    $crumbs[$page['service_name']] = '/services/' . $page['service_slug'];
}
$crumbs[$page['title']] = $path;

$seo = seo([
    'title'       => $page['meta_title'] ?: $page['title'],
    'description' => $page['meta_description'] ?: $page['hero_subtitle'],
    'path'        => $path,
    'canonical'   => (string) $page['canonical_url'],
    'og_title'    => (string) $page['og_title'],
    'og_description' => (string) $page['og_description'],
    'breadcrumbs' => $crumbs,
    'schema'      => [schema_faq($faqs)],
]);
$form = lead_form_state();
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <?= breadcrumb_nav($crumbs) ?>
        <h1><?= e($page['title']) ?></h1>
        <p class="lead"><?= e($page['hero_subtitle']) ?></p>
        <div class="btn-row">
            <a class="btn btn-light" href="#lead-form"><?= e($page['cta_text']) ?></a>
            <?php if ($page['service_slug']): ?>
                <a class="btn btn-ghost-light" href="<?= e(url('/services/' . $page['service_slug'])) ?>">About <?= e($page['service_name']) ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="problem-title">
    <div class="container split">
        <div>
            <span class="eyebrow">The challenge</span>
            <h2 id="problem-title">Why this matters</h2>
            <p><?= nl2br(e($page['problem'])) ?></p>
        </div>
        <div>
            <span class="eyebrow">Our approach</span>
            <h2>How SYSCOM helps</h2>
            <p><?= nl2br(e($page['solution'])) ?></p>
        </div>
    </div>
</section>

<section class="section section-alt" aria-labelledby="features-title">
    <div class="container">
        <div class="section-head"><span class="eyebrow">Features</span><h2 id="features-title">What you get</h2></div>
        <div class="grid grid-3">
            <?php foreach ($features as $f): ?>
                <div class="card">
                    <div class="card-icon"><?= icon('check') ?></div>
                    <h3><?= e($f['title']) ?></h3>
                    <?php if ($f['text']): ?><p><?= e($f['text']) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="benefits-title">
    <div class="container split">
        <div>
            <span class="eyebrow">Benefits</span>
            <h2 id="benefits-title">What it means for your business</h2>
            <ul class="check-list">
                <?php foreach ($benefits as $b): ?>
                    <li><?= icon('check') ?> <span><?= e($b) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($useCases): ?>
            <div>
                <span class="eyebrow">Use cases</span>
                <h2>Who it is for</h2>
                <div class="grid">
                    <?php foreach ($useCases as $u): ?>
                        <div class="feature"><div class="card-icon"><?= icon('users') ?></div><div><h3><?= e($u['title']) ?></h3><p><?= e($u['text']) ?></p></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (trim((string) $page['content']) !== ''): ?>
<section class="section section-alt">
    <div class="container narrow prose">
        <?= apply_internal_links(render_markdown($page['content']), $path, (int) setting('internal_links_max', '5'))['html'] ?>
    </div>
</section>
<?php endif; ?>

<section class="section" aria-labelledby="landing-cta">
    <div class="container split">
        <div>
            <?php if ($faqs): ?>
                <h2 id="landing-cta">Questions we often hear</h2>
                <?= faq_list($faqs) ?>
            <?php else: ?>
                <h2 id="landing-cta"><?= e($page['cta_text']) ?></h2>
                <p class="lead">Tell us about your requirements and we will get back to you with a recommendation.</p>
            <?php endif; ?>
        </div>
        <?= lead_form($form, $page['cta_text'], 'Send enquiry') ?>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
