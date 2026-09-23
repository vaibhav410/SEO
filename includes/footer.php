<?php
/**
 * Public page footer with crawlable links to every hub and service.
 */
$footerServices = services_published();
$footerGuides = landing_pages_published();
$mainSite = setting('main_site_url');
?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="logo logo-light" href="<?= e(url('/')) ?>">
                <img src="<?= e(url('/' . (setting('logo_path') ?: 'assets/images/logo.svg'))) ?>" alt="" width="32" height="32">
                <span><?= e(setting('site_name', 'SYSCOM')) ?></span>
            </a>
            <p><?= e(setting('site_description')) ?></p>
        </div>
        <nav aria-label="Services">
            <h2 class="footer-title">Services</h2>
            <ul>
                <?php foreach ($footerServices as $s): ?>
                    <li><a href="<?= e(url('/services/' . $s['slug'])) ?>"><?= e($s['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <nav aria-label="Solutions">
            <h2 class="footer-title">Solutions</h2>
            <ul>
                <?php foreach ($footerGuides as $l): ?>
                    <li><a href="<?= e(url('/' . $l['slug'])) ?>"><?= e($l['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <nav aria-label="Company">
            <h2 class="footer-title">Company</h2>
            <ul>
                <li><a href="<?= e(url('/blog')) ?>">Blog</a></li>
                <li><a href="<?= e(url('/resources')) ?>">Resources</a></li>
                <li><a href="<?= e(url('/faq')) ?>">FAQ</a></li>
                <li><a href="<?= e(url('/contact')) ?>">Contact</a></li>
                <?php if ($mainSite): ?>
                    <li><a href="<?= e($mainSite) ?>" rel="noopener">Main website <?= icon('external', 'icon icon-xs') ?></a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> <?= e(setting('site_name', 'SYSCOM')) ?>. All rights reserved.</p>
        <p><a href="<?= e(url('/sitemap.xml')) ?>">Sitemap</a></p>
    </div>
</footer>
</body>
</html>
