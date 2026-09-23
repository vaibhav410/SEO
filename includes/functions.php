<?php
/**
 * Small, dependency-free helpers used across the app.
 */

/** Escape for HTML text and attribute context. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Path part of base_url, e.g. "" or "/syscom-growthhub". */
function base_path(): string
{
    static $path = null;
    if ($path === null) {
        $path = rtrim((string) parse_url(config('app.base_url'), PHP_URL_PATH), '/');
    }
    return $path;
}

/** Root-relative URL for an app path: url('/blog') => /syscom-growthhub/blog */
function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    return base_path() . ($path === '/' ? '/' : $path);
}

/** Absolute URL for canonical tags, sitemap, Open Graph. */
function absolute_url(string $path = '/'): string
{
    $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', config('app.base_url'));
    return $origin . url($path);
}

/** Versioned asset URL so browsers can cache aggressively. */
function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? filemtime($file) : 0;
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function redirect(string $to, int $status = 302): void
{
    if (!preg_match('#^https?://#', $to)) {
        $to = url($to);
    }
    header('Location: ' . $to, true, $status);
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

/** Trimmed string input from POST/GET. */
function input(string $key, string $default = '', string $source = 'post'): string
{
    $bag = $source === 'get' ? $_GET : $_POST;
    $value = $bag[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function input_int(string $key, int $default = 0, string $source = 'get'): int
{
    $bag = $source === 'get' ? $_GET : $_POST;
    $value = filter_var($bag[$key] ?? null, FILTER_VALIDATE_INT);
    return $value === false || $value === null ? $default : $value;
}

/** Convert any title into a URL slug: "What is SSL?" => "what-is-ssl" */
function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text) ?: $text;
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-');
}

function is_valid_slug(string $slug): bool
{
    return strlen($slug) <= 120 && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

function str_limit(string $text, int $limit, string $end = '…'): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    $cut = mb_substr($text, 0, $limit - mb_strlen($end));
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > $limit * 0.6) {
        $cut = mb_substr($cut, 0, $space);
    }
    return rtrim($cut, " ,.;:-") . $end;
}

function format_date(?string $date, string $format = 'j M Y'): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '';
}

/** ISO-8601 for schema/sitemap. */
function iso_date(?string $date): string
{
    $ts = $date ? strtotime($date) : false;
    return $ts ? date('c', $ts) : date('c');
}

/** Words-per-minute reading time for article cards. */
function reading_time(string $text): int
{
    $words = str_word_count(strip_tags($text));
    return max(1, (int) ceil($words / 200));
}

/** Split a textarea into non-empty trimmed lines. */
function lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text)), 'strlen'));
}

/**
 * Pagination values for a list query.
 * @return array{page:int, per_page:int, offset:int, total:int, pages:int}
 */
function paginate(int $total, int $perPage, ?int $page = null): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = $page ?? input_int('page', 1);
    $page = min(max(1, $page), $pages);
    return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total, 'pages' => $pages];
}

/** Current query string with some keys replaced; used by pagination/filter links. */
function query_with(array $changes): string
{
    $query = array_filter(array_merge($_GET, $changes), fn($v) => $v !== null && $v !== '');
    return $query ? '?' . http_build_query($query) : '';
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Store a keyed hash of the IP, never the raw address. */
function ip_hash(): string
{
    return hash_hmac('sha256', client_ip(), (string) config('app.secret'));
}

/** Current request path relative to the app, without query string: "/blog/some-post". */
function current_path(): string
{
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $base = base_path();
    if ($base !== '' && strncmp($path, $base, strlen($base)) === 0) {
        $path = substr($path, strlen($base));
    }
    return '/' . ltrim($path, '/');
}

/** Render a PHP template with isolated variables and return the output. */
function render_partial(string $file, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Status badge for admin tables. */
function status_badge(string $status): string
{
    $map = [
        'published' => 'success', 'live' => 'success', 'active' => 'success', 'converted' => 'success', 'mapped' => 'success', 'healthy' => 'success', 'passed' => 'success', 'done' => 'success',
        'draft' => 'muted', 'paused' => 'muted', 'closed' => 'muted', 'inactive' => 'muted', 'archived' => 'muted',
        'new' => 'info', 'opportunity' => 'info', 'researching' => 'info', 'scheduled' => 'info',
        'pending' => 'warning', 'submitted' => 'warning', 'contacted' => 'warning', 'qualified' => 'warning', 'targeting' => 'warning',
        'rejected' => 'danger', 'spam' => 'danger', 'issue' => 'danger', 'needs attention' => 'danger', 'high' => 'danger',
        'dismissed' => 'muted', 'planned' => 'info', 'skipped' => 'muted', 'in_progress' => 'warning', 'warning' => 'warning', 'medium' => 'warning', 'low' => 'muted',
    ];
    $tone = $map[strtolower($status)] ?? 'muted';
    return '<span class="badge badge-' . $tone . '">' . e(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

/** Health tone for a 0-100 score. */
function score_tone(int $score): string
{
    return $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
}

/** Short relative time for tables: "3 days ago". */
function time_ago(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $diff = time() - strtotime($date);
    if ($diff < 60) {
        return 'just now';
    }
    foreach ([86400 * 30 => 'month', 86400 * 7 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'] as $secs => $unit) {
        if ($diff >= $secs) {
            $n = (int) floor($diff / $secs);
            return $n . ' ' . $unit . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}
