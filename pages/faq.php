<?php
$general = faqs_for('general');
$groups = faqs_by_service();

// FAQPage schema covers every question shown on this page.
$all = $general;
foreach ($groups as $g) {
    $all = array_merge($all, $g['faqs']);
}

$seo = seo([
    'title'       => 'FAQ: Hosting, Domains, Email & SSL Questions',
    'description' => 'Answers to common questions about web hosting, domain registration and transfer, business email, VPS, dedicated servers and SSL certificates.',
    'path'        => '/faq',
    'breadcrumbs' => ['Home' => '/', 'FAQ' => '/faq'],
    'schema'      => [schema_faq($all)],
]);
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <?= breadcrumb_nav($seo['breadcrumbs']) ?>
        <h1>Frequently asked questions</h1>
        <p class="lead">Quick answers about our services. Can't find what you need? <a class="link-light" href="<?= e(url('/contact')) ?>">Ask our team</a>.</p>
    </div>
</section>
<section class="section">
    <div class="container narrow">
        <?php if (!$all): ?>
            <p class="empty-state">No FAQs have been published yet.</p>
        <?php endif; ?>
        <?php if ($general): ?>
            <div class="faq-group">
                <h2>General</h2>
                <?= faq_list($general) ?>
            </div>
        <?php endif; ?>
        <?php foreach ($groups as $slug => $g): ?>
            <div class="faq-group">
                <h2><a href="<?= e(url('/services/' . $slug)) ?>"><?= e($g['name']) ?></a></h2>
                <?= faq_list($g['faqs']) ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
