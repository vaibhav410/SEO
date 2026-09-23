<?php
/**
 * Dynamic XML sitemap at /sitemap.xml: public hubs, published services, articles, landing pages and
 * categories only. Built by modules/seo/technical.php, the same code the admin Sitemap manager shows.
 */
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo sitemap_xml(sitemap_entries());
