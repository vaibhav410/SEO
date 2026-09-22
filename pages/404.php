<?php
/** Friendly 404 with helpful links. Always noindex. */
$seo = seo([
    'title'       => 'Page not found',
    'description' => 'The page you are looking for could not be found.',
    'robots'      => 'noindex, follow',
    'path'        => current_path(),
]);
$services = services_published();
require APP_ROOT . '/includes/header.php';
?>
<section class="error-page">
    <div class="container">
        <p class="error-code" aria-hidden="true">404</p>
        <h1>We couldn't find that page</h1>
        <p class="lead">The link may be outdated or the page may have moved. These pages might help:</p>
        <div class="btn-row">
            <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go to homepage</a>
            <a class="btn btn-outline" href="<?= e(url('/blog')) ?>">Browse guides</a>
            <a class="btn btn-outline" href="<?= e(url('/contact')) ?>">Contact us</a>
        </div>
        <?php if ($services): ?>
            <div class="error-links">
                <h2>Popular services</h2>
                <ul class="pill-list">
                    <?php foreach ($services as $s): ?>
                        <li><a href="<?= e(url('/services/' . $s['slug'])) ?>"><?= e($s['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
