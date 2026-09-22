<?php
$services = services_published();
$seo = seo([
    'title'       => 'Hosting, Domain, Email & SSL Services',
    'description' => 'Explore SYSCOM services: web hosting, WordPress hosting, VPS, dedicated servers, domain registration, business email, SSL certificates and reseller hosting.',
    'path'        => '/services',
    'breadcrumbs' => ['Home' => '/', 'Services' => '/services'],
]);
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <?= breadcrumb_nav($seo['breadcrumbs']) ?>
        <h1>Services for every stage of your online presence</h1>
        <p class="lead">Choose from domains, hosting, servers, email and security. Each service page explains who it is for and what to check before you buy.</p>
    </div>
</section>
<section class="section">
    <div class="container">
        <?php if ($services): ?>
            <div class="grid grid-3">
                <?php foreach ($services as $s): ?>
                    <?= service_card($s) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">Services will be listed here soon.</p>
        <?php endif; ?>
    </div>
</section>
<section class="section section-alt">
    <div class="container narrow">
        <div class="section-head">
            <h2>How to pick the right service</h2>
        </div>
        <ol class="steps">
            <li><h3>Secure your domain</h3><p>Your domain name is your address online and the basis for your email. <a href="<?= e(url('/blog/how-to-choose-a-domain-name')) ?>">How to choose a domain name</a>.</p></li>
            <li><h3>Choose where the website lives</h3><p>Shared or WordPress hosting for most business sites; VPS or dedicated servers for heavier workloads. <a href="<?= e(url('/blog/shared-vs-vps-vs-dedicated-hosting')) ?>">Compare hosting types</a>.</p></li>
            <li><h3>Set up email and security</h3><p>Add business email on your domain and an SSL certificate so every page loads over HTTPS.</p></li>
        </ol>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
