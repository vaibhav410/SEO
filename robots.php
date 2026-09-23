<?php
/**
 * robots.txt, served at /robots.txt via .htaccess. Built-in rules plus custom rules managed in
 * Admin → Technical SEO → Robots.txt; the Sitemap line is always the absolute URL.
 */
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo robots_txt();
