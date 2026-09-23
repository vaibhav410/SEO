<?php
/**
 * GET /api/admin-search.php?q=term  (admin session required)
 * Typeahead results for the global admin search.
 */
require __DIR__ . '/../admin/_init.php';

if (request_method() !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}
$q = mb_substr(input('q', '', 'get'), 0, 100);
$results = array_map(fn($r) => ['type' => $r['type'], 'title' => $r['title'], 'url' => url($r['url']), 'meta' => $r['meta']], admin_search($q, 4));
json_response(['ok' => true, 'results' => $results]);
