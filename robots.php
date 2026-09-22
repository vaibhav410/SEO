<?php
/**
 * robots.txt, served at /robots.txt via .htaccess. Generated so the Sitemap line is always the absolute URL.
 */
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$base = base_path();
echo "User-agent: *\n";
echo "Allow: {$base}/\n";
foreach (['/admin/', '/api/', '/config/', '/includes/', '/modules/', '/database/', '/storage/', '/tests/', '/blog?q='] as $private) {
    echo 'Disallow: ' . $base . $private . "\n";
}
echo "\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
