<?php
/**
 * Keyword targeting plan and keyword -> page coverage checks.
 *
 * No search volumes are stored or displayed: numbers must come from a real research source
 * (Google Keyword Planner, Search Console) and can be recorded in notes with that source.
 */

const KEYWORD_INTENTS = ['informational', 'navigational', 'commercial', 'transactional'];
const KEYWORD_PRIORITIES = ['high', 'medium', 'low'];
const KEYWORD_STATUSES = ['researching', 'targeting', 'mapped', 'paused'];
const KEYWORD_CONTENT_TYPES = ['guide' => 'Blog guide', 'landing_page' => 'Landing page', 'service_page' => 'Service page', 'home' => 'Home page', 'faq' => 'FAQ'];
const KEYWORD_SORTS = ['keyword' => 'k.keyword', 'intent' => 'k.intent', 'priority' => "FIELD(k.priority, 'high', 'medium', 'low')", 'status' => 'k.status', 'updated' => 'k.updated_at'];

function keywords_admin_list(array $filters, string $orderBy = " ORDER BY FIELD(k.priority, 'high', 'medium', 'low') ASC, k.keyword"): array
{
    [$where, $params] = keywords_filter($filters);
    return db_all("SELECT k.*, s.name AS service_name FROM keywords k LEFT JOIN services s ON s.id = k.service_id{$where}{$orderBy}", $params);
}

/** Suggested content type for a keyword based on its intent. */
function keyword_recommended_type(string $intent): string
{
    return ['informational' => 'guide', 'navigational' => 'home', 'commercial' => 'landing_page', 'transactional' => 'service_page'][$intent] ?? 'guide';
}

function keywords_filter(array $f): array
{
    $clauses = [];
    $params = [];
    foreach (['intent' => KEYWORD_INTENTS, 'priority' => KEYWORD_PRIORITIES, 'status' => KEYWORD_STATUSES] as $col => $allowed) {
        if (!empty($f[$col]) && in_array($f[$col], $allowed, true)) {
            $clauses[] = "k.$col = ?";
            $params[] = $f[$col];
        }
    }
    if (!empty($f['q'])) {
        $clauses[] = 'k.keyword LIKE ?';
        $params[] = '%' . addcslashes($f['q'], '%_\\') . '%';
    }
    return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
}

function keyword_find(int $id): ?array
{
    return db_one('SELECT * FROM keywords WHERE id = ?', [$id]);
}

/** @return array{0: array, 1: array} */
function keyword_validate(array $input, ?int $id = null): array
{
    $input['keyword'] = mb_strtolower(preg_replace('/\s+/', ' ', trim((string) ($input['keyword'] ?? ''))));
    [$data, $errors] = validate($input, [
        'keyword'    => 'required|min:2|max:150',
        'intent'     => 'required|in:' . implode(',', KEYWORD_INTENTS),
        'priority'   => 'required|in:' . implode(',', KEYWORD_PRIORITIES),
        'target_url' => 'path_or_url|max:255',
        'status'     => 'required|in:' . implode(',', KEYWORD_STATUSES),
        'content_type' => 'in:' . implode(',', array_keys(KEYWORD_CONTENT_TYPES)),
        'service_id' => 'int',
        'notes'      => 'max:2000',
    ], ['target_url' => 'Target URL', 'content_type' => 'content type', 'service_id' => 'service']);
    $data['service_id'] = $data['service_id'] ? (int) $data['service_id'] : null;
    if ($data['service_id'] && !service_find($data['service_id'])) {
        $errors['service_id'] = 'Choose a valid service.';
    }
    if (!isset($errors['keyword']) && db_value('SELECT 1 FROM keywords WHERE keyword = ? AND id <> ?', [$data['keyword'], $id ?? 0])) {
        $errors['keyword'] = 'This keyword is already in the plan.';
    }
    if ($data['status'] === 'mapped' && !$data['target_url']) {
        $errors['target_url'] = 'A mapped keyword needs a target URL.';
    }
    return [$data, $errors];
}

/**
 * Resolve an app path to the published content that lives there.
 * @return array|null ['type' => , 'title' => , 'meta_title' => , 'meta_description' => , 'body' => , 'edit' => ]
 */
function content_at_path(?string $path): ?array
{
    if (!$path || preg_match('#^https?://#i', $path)) {
        return null;
    }
    $path = '/' . trim(strtolower((string) parse_url($path, PHP_URL_PATH)), '/');

    if ($path === '/') {
        return ['type' => 'Home page', 'title' => setting('site_name') . ' ' . setting('site_tagline'),
            'meta_title' => setting('home_meta_title', 'Web Hosting, Domains & Business Email in India'), 'meta_description' => setting('site_description'),
            'body' => setting('site_description'), 'edit' => '/admin/settings/'];
    }
    if (preg_match('#^/services/([a-z0-9-]+)$#', $path, $m)) {
        $r = db_one("SELECT id, name AS title, meta_title, meta_description, CONCAT(description, ' ', content) AS body FROM services WHERE slug = ? AND status = 'published'", [$m[1]]);
        return $r ? $r + ['type' => 'Service', 'edit' => '/admin/services/edit.php?id=' . $r['id']] : null;
    }
    if (preg_match('#^/blog/([a-z0-9-]+)$#', $path, $m)) {
        $r = db_one("SELECT p.id, p.title, p.meta_title, p.meta_description, CONCAT(p.excerpt, ' ', p.content) AS body FROM posts p WHERE p.slug = ? AND " . POST_PUBLIC_SQL, [$m[1]]);
        return $r ? $r + ['type' => 'Article', 'edit' => '/admin/posts/edit.php?id=' . $r['id']] : null;
    }
    if (preg_match('#^/([a-z0-9-]+)$#', $path, $m)) {
        $r = db_one("SELECT id, title, meta_title, meta_description, CONCAT_WS(' ', hero_subtitle, problem, solution, features, benefits, use_cases, content) AS body FROM landing_pages WHERE slug = ? AND status = 'published'", [$m[1]]);
        return $r ? $r + ['type' => 'Landing page', 'edit' => '/admin/landing-pages/edit.php?id=' . $r['id']] : null;
    }
    $static = ['/services' => 'Services hub', '/blog' => 'Blog', '/resources' => 'Resources hub', '/faq' => 'FAQ', '/contact' => 'Contact'];
    return isset($static[$path]) ? ['type' => $static[$path], 'title' => $static[$path], 'meta_title' => '', 'meta_description' => '', 'body' => '', 'edit' => null] : null;
}

/**
 * On-page coverage for a keyword on its target page.
 * @return array{status: string, target: ?array, checks: array<string, bool>, score: int}
 */
function keyword_coverage(array $keyword): array
{
    if (!$keyword['target_url']) {
        return ['status' => 'unmapped', 'target' => null, 'checks' => [], 'score' => 0];
    }
    $target = content_at_path($keyword['target_url']);
    if (!$target) {
        return ['status' => preg_match('#^https?://#i', $keyword['target_url']) ? 'external' : 'missing', 'target' => null, 'checks' => [], 'score' => 0];
    }
    $kw = mb_strtolower($keyword['keyword']);
    $has = fn(?string $text) => keyword_in_text($kw, (string) $text);
    $checks = [
        'Title / H1'       => $has($target['title']),
        'Meta title'       => $has($target['meta_title'] ?: $target['title']),
        'Meta description' => $has($target['meta_description']),
        'Body content'     => $has($target['body']),
    ];
    $score = (int) round(100 * count(array_filter($checks)) / count($checks));
    return ['status' => 'ok', 'target' => $target, 'checks' => $checks, 'score' => $score];
}

/**
 * Loose match: every significant word of the keyword appears in the text (word order may differ,
 * which is how people naturally write). Avoids pushing editors toward exact-match stuffing.
 */
function keyword_in_text(string $keyword, string $text): bool
{
    $text = mb_strtolower(strip_tags($text));
    if ($text === '') {
        return false;
    }
    if (str_contains($text, $keyword)) {
        return true;
    }
    $stop = ['a', 'an', 'the', 'in', 'of', 'for', 'to', 'and', 'vs', 'is', 'how', 'what', 'best', 'on', 'with'];
    $words = array_diff(preg_split('/\s+/', $keyword), $stop);
    foreach ($words as $w) {
        $stem = mb_strlen($w) > 4 ? rtrim($w, 's') : $w;
        if (!preg_match('/\b' . preg_quote($stem, '/') . '/u', $text)) {
            return false;
        }
    }
    return (bool) $words;
}

/** Counts for the dashboard and keyword page summary. */
function keyword_summary(): array
{
    return db_one(
        "SELECT COUNT(*) AS total,
                SUM(status IN ('targeting', 'mapped')) AS active,
                SUM(target_url IS NULL OR target_url = '') AS unmapped,
                SUM(priority = 'high') AS high
         FROM keywords"
    ) ?? ['total' => 0, 'active' => 0, 'unmapped' => 0, 'high' => 0];
}
