<?php
/**
 * Dynamic XML sitemap at /sitemap.xml: public hubs, published services, articles and landing pages only.
 * Drafts, scheduled posts, admin and search URLs are never included.
 */
define('NO_SESSION', true);
require __DIR__ . '/includes/bootstrap.php';

$urls = [];
$add = function (string $path, ?string $lastmod = null) use (&$urls) {
    $urls[] = ['loc' => absolute_url($path), 'lastmod' => $lastmod ? date('Y-m-d', strtotime($lastmod)) : null];
};

$latestPost = db_value('SELECT MAX(p.updated_at) FROM posts p WHERE ' . POST_PUBLIC_SQL);
$latestService = db_value("SELECT MAX(updated_at) FROM services WHERE status = 'published'");

$add('/', max((string) $latestPost, (string) $latestService) ?: null);
$add('/services', $latestService);
$add('/blog', $latestPost);
$add('/resources', $latestPost);
$add('/faq', db_value("SELECT MAX(updated_at) FROM faqs WHERE status = 'published'"));
$add('/contact');

foreach (db_all("SELECT slug, updated_at FROM services WHERE status = 'published' ORDER BY sort_order") as $s) {
    $add('/services/' . $s['slug'], $s['updated_at']);
}
foreach (db_all('SELECT p.slug, p.updated_at FROM posts p WHERE ' . POST_PUBLIC_SQL . ' ORDER BY p.published_at DESC') as $p) {
    $add('/blog/' . $p['slug'], $p['updated_at']);
}
foreach (db_all("SELECT slug, updated_at FROM landing_pages WHERE status = 'published' ORDER BY slug") as $l) {
    $add('/' . $l['slug'], $l['updated_at']);
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent(true);
$xml->startDocument('1.0', 'UTF-8');
$xml->startElement('urlset');
$xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
foreach ($urls as $u) {
    $xml->startElement('url');
    $xml->writeElement('loc', $u['loc']);
    if ($u['lastmod']) {
        $xml->writeElement('lastmod', $u['lastmod']);
    }
    $xml->endElement();
}
$xml->endElement();
echo $xml->outputMemory();
