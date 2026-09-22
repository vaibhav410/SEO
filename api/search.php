<?php
/**
 * Public search suggestions: GET /api/search.php?q=term
 * Returns up to 8 published services, landing pages and articles. Read-only, prepared statements.
 */
define('NO_SESSION', true);
require __DIR__ . '/../includes/bootstrap.php';

header('X-Robots-Tag: noindex');
if (request_method() !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$q = mb_substr(trim(input('q', '', 'get')), 0, 100);
if (mb_strlen($q) < 2) {
    json_response(['ok' => true, 'results' => []]);
}
$like = '%' . addcslashes($q, '%_\\') . '%';

$results = [];
foreach (db_all("SELECT name, slug FROM services WHERE status = 'published' AND (name LIKE ? OR description LIKE ?) LIMIT 3", [$like, $like]) as $r) {
    $results[] = ['type' => 'Service', 'title' => $r['name'], 'url' => url('/services/' . $r['slug'])];
}
foreach (db_all("SELECT title, slug FROM landing_pages WHERE status = 'published' AND (title LIKE ? OR primary_keyword LIKE ?) LIMIT 2", [$like, $like]) as $r) {
    $results[] = ['type' => 'Solution', 'title' => $r['title'], 'url' => url('/' . $r['slug'])];
}
foreach (db_all('SELECT p.title, p.slug FROM posts p WHERE ' . POST_PUBLIC_SQL . ' AND (p.title LIKE ? OR p.excerpt LIKE ?) ORDER BY p.published_at DESC LIMIT 5', [$like, $like]) as $r) {
    $results[] = ['type' => 'Guide', 'title' => $r['title'], 'url' => url('/blog/' . $r['slug'])];
}

header('Cache-Control: public, max-age=300');
json_response(['ok' => true, 'results' => array_slice($results, 0, 8)]);
