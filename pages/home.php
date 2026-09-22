<?php
$services = services_published();
$posts = posts_published(3);
$solutions = landing_pages_published();
$faqs = faqs_for('general');

$seo = seo([
    'title'       => 'Web Hosting, Domains & Business Email in India',
    'description' => setting('site_description'),
    'path'        => '/',
    'schema'      => [schema_organization(), schema_website(), schema_faq($faqs)],
]);

$useCases = [
    ['server', 'Launch a company website', 'Register a domain, choose shared or WordPress hosting and publish a fast, secure site on HTTPS.'],
    ['mail', 'Look professional over email', 'Give every team member an address on your own domain, with Business Email, Titan or Google Workspace.'],
    ['layers', 'Run applications with full control', 'Deploy custom stacks on a KVM VPS or a dedicated server when shared hosting is no longer enough.'],
    ['users', 'Host websites for your clients', 'Agencies and freelancers can package hosting, domains and SSL under their own brand with reseller plans.'],
];
$reasons = [
    ['globe', 'Everything in one place', 'Domains, hosting, email and SSL managed together, so DNS, renewals and billing are not scattered across providers.'],
    ['trending', 'A clear upgrade path', 'Start small on shared hosting and move to WordPress hosting, VPS or dedicated servers without changing your domain.'],
    ['shield', 'Security built in', 'SSL certificates, malware scanning and backup add-ons help keep your website and your customers safe.'],
    ['headset', 'Help when you need it', 'A support team and knowledge base to help you choose, migrate and run your services.'],
];
require APP_ROOT . '/includes/header.php';
?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="eyebrow">Domains · Hosting · Email · SSL</span>
            <h1>Everything your business needs to get online and stay online</h1>
            <p class="lead">Register your domain, host your website on Linux, Windows or WordPress-optimised servers, and run professional email on your own domain, with a path to VPS and dedicated servers as you grow.</p>
            <div class="btn-row">
                <a class="btn btn-primary" href="<?= e(url('/contact')) ?>">Get a recommendation <?= icon('arrow', 'icon icon-sm') ?></a>
                <a class="btn btn-outline" href="<?= e(url('/services')) ?>">Explore services</a>
            </div>
            <ul class="hero-points">
                <li><?= icon('check') ?> Linux and Windows hosting plans</li>
                <li><?= icon('check') ?> .in, .co.in, .com and many more domain extensions</li>
                <li><?= icon('check') ?> SSL certificates for every website</li>
            </ul>
        </div>
        <div class="hero-panel">
            <h2>What are you looking for?</h2>
            <ul class="stack-list">
                <?php foreach (array_slice($services, 0, 5) as $s): ?>
                    <li><a href="<?= e(url('/services/' . $s['slug'])) ?>"><?= icon($s['icon']) ?> <?= e($s['name']) ?> <?= icon('arrow', 'icon icon-sm') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="services-title">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Services</span>
            <h2 id="services-title">Hosting and online services for every stage</h2>
            <p class="lead">From your first domain to dedicated infrastructure, pick the service that fits what your website needs today.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($services as $s): ?>
                <?= service_card($s) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-alt" aria-labelledby="usecases-title">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Use cases</span>
            <h2 id="usecases-title">How businesses use SYSCOM</h2>
        </div>
        <div class="grid grid-2">
            <?php foreach ($useCases as [$ic, $title, $text]): ?>
                <div class="card feature">
                    <div class="card-icon"><?= icon($ic) ?></div>
                    <div><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="why-title">
    <div class="container split">
        <div class="section-head">
            <span class="eyebrow">Why SYSCOM</span>
            <h2 id="why-title">A dependable foundation for your online presence</h2>
            <p class="lead">Your website and email are how customers find and trust you. We focus on the basics that keep them running smoothly.</p>
            <a class="btn btn-outline" href="<?= e(url('/resources')) ?>">Browse guides</a>
        </div>
        <div class="grid">
            <?php foreach ($reasons as [$ic, $title, $text]): ?>
                <div class="feature">
                    <div class="card-icon"><?= icon($ic) ?></div>
                    <div><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-alt" aria-labelledby="resources-title">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Resources</span>
            <h2 id="resources-title">Guides to help you choose</h2>
            <p class="lead">Practical, vendor-neutral advice on hosting, domains, email and website security.</p>
        </div>
        <?php if ($posts): ?>
            <div class="grid grid-3">
                <?php foreach ($posts as $p): ?>
                    <?= post_card($p) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">New guides are on the way.</p>
        <?php endif; ?>
        <?php if ($solutions): ?>
            <h3 class="section-sub">Popular solutions</h3>
            <ul class="pill-list">
                <?php foreach ($solutions as $l): ?>
                    <li><a href="<?= e(url('/' . $l['slug'])) ?>"><?= e($l['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p><a class="btn btn-outline" href="<?= e(url('/blog')) ?>">Read the blog <?= icon('arrow', 'icon icon-sm') ?></a></p>
    </div>
</section>

<?php if ($faqs): ?>
<section class="section" aria-labelledby="faq-title">
    <div class="container narrow">
        <div class="section-head center">
            <span class="eyebrow">FAQ</span>
            <h2 id="faq-title">Frequently asked questions</h2>
        </div>
        <?= faq_list($faqs) ?>
        <p class="center-text"><a href="<?= e(url('/faq')) ?>">See all FAQs</a></p>
    </div>
</section>
<?php endif; ?>

<section class="section section-alt">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>Not sure which plan fits?</h2>
                <p>Tell us about your website and email needs and we will recommend a setup.</p>
            </div>
            <a class="btn btn-light" href="<?= e(url('/contact')) ?>">Talk to our team</a>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
