<?php
/**
 * POST /api/audit.php  {"url": "https://..."}  (admin session + X-CSRF-Token header)
 * Runs an SEO audit and returns the report URL.
 */
require __DIR__ . '/../admin/_init.php';

if (!is_post()) {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}
$body = json_decode((string) file_get_contents('php://input'), true);
$url = is_array($body) && is_string($body['url'] ?? null) ? trim($body['url']) : '';
if ($url === '') {
    json_response(['ok' => false, 'error' => 'Enter a URL to audit.'], 422);
}

@set_time_limit(60);
$result = audit_run($url, (int) $user['id']);
if (!$result['id']) {
    json_response(['ok' => false, 'error' => $result['error']], 422);
}
log_activity('audited', 'audit', $result['id'], 'Audited ' . str_limit($url, 80));
json_response(['ok' => true, 'id' => $result['id'], 'redirect' => url('/admin/audits/view.php?id=' . $result['id'])]);
