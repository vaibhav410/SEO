<?php
/**
 * POST /api/ai.php  (admin session + X-CSRF-Token header)
 *   {"kind": "meta", "title": "", "keyword": "", "content": ""}
 *   {"kind": "outline", "keyword": "", "intent": ""}
 * Returns draft suggestions only; nothing is saved.
 */
require __DIR__ . '/../admin/_init.php';

if (!is_post()) {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}
if (!ai_enabled()) {
    json_response(['ok' => false, 'error' => 'The AI assistant is not configured.'], 503);
}

// Simple per-session throttle to protect the API budget.
$_SESSION['ai_calls'] = array_filter($_SESSION['ai_calls'] ?? [], fn($t) => $t > time() - 60);
if (count($_SESSION['ai_calls']) >= 10) {
    json_response(['ok' => false, 'error' => 'Too many AI requests. Please wait a minute.'], 429);
}
$_SESSION['ai_calls'][] = time();

$in = json_decode((string) file_get_contents('php://input'), true);
$in = is_array($in) ? $in : [];
$str = fn(string $k, int $max) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max);

@set_time_limit(45);
$result = match ($in['kind'] ?? '') {
    'meta'    => $str('title', 200) === '' ? ['ok' => false, 'error' => 'Add a title first.'] : ai_suggest_meta($str('title', 200), $str('keyword', 150), $str('content', 20000)),
    'outline' => $str('keyword', 150) === '' ? ['ok' => false, 'error' => 'Missing keyword.'] : ai_suggest_outline($str('keyword', 150), $str('intent', 30)),
    default   => ['ok' => false, 'error' => 'Unknown request.'],
};
json_response(['ok' => $result['ok'], 'data' => $result['data'] ?? null, 'error' => $result['error'] ?? null], $result['ok'] ? 200 : 422);
