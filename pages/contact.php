<?php
if (is_post()) {
    lead_handle_submission('/contact');
}
$form = lead_form_state();
$mainSite = setting('main_site_url');
$email = setting('contact_email');
$phone = setting('contact_phone');
$address = setting('contact_address');

$seo = seo([
    'title'       => 'Contact SYSCOM: Hosting & Domain Enquiries',
    'description' => 'Ask SYSCOM about web hosting, domains, business email, VPS, dedicated servers or SSL. Send your requirements and our team will reply.',
    'path'        => '/contact',
    'breadcrumbs' => ['Home' => '/', 'Contact' => '/contact'],
]);
require APP_ROOT . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <?= breadcrumb_nav($seo['breadcrumbs']) ?>
        <h1>Talk to our team</h1>
        <p class="lead">Tell us what you are building and we will recommend the right domain, hosting and email setup.</p>
    </div>
</section>
<section class="section">
    <div class="container split">
        <div>
            <h2>What happens next</h2>
            <ol class="steps">
                <li><h3>Share your requirements</h3><p>A few lines about your website, traffic and email needs are enough.</p></li>
                <li><h3>Get a recommendation</h3><p>We suggest a setup that fits today and can grow with you.</p></li>
                <li><h3>Go live</h3><p>We help with setup or migration so the switch is smooth.</p></li>
            </ol>
            <?php if ($email || $phone || $address): ?>
                <h2>Other ways to reach us</h2>
                <ul class="check-list">
                    <?php if ($email): ?><li><?= icon('mail') ?> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
                    <?php if ($phone): ?><li><?= icon('headset') ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
                    <?php if ($address): ?><li><?= icon('globe') ?> <span><?= nl2br(e($address)) ?></span></li><?php endif; ?>
                </ul>
            <?php elseif ($mainSite): ?>
                <p>Existing customer? Use the <a href="<?= e(rtrim($mainSite, '/') . '/support/contact-us.php') ?>" rel="noopener">support contact page</a> on our main website.</p>
            <?php endif; ?>
        </div>
        <?= lead_form($form) ?>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
