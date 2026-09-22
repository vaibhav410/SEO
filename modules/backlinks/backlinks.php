<?php
/**
 * Off-page SEO tracker. Records outreach, directory listings, citations and mentions that the team
 * pursues manually. It never creates links automatically; the "verify" action only checks whether a
 * link that someone reports as live really exists on the source page.
 */

const BACKLINK_STATUSES = ['opportunity', 'submitted', 'pending', 'live', 'rejected'];
const BACKLINK_TYPES = [
    'directory' => 'Business directory', 'citation' => 'Local citation (NAP)', 'social' => 'Social profile',
    'guest_post' => 'Guest article', 'forum' => 'Forum / Q&A', 'pr' => 'PR / news mention',
    'partner' => 'Partner / supplier', 'other' => 'Other',
];
const BACKLINK_RELS = ['unknown', 'follow', 'nofollow', 'ugc', 'sponsored'];

function backlinks_admin_list(string $status, string $type): array
{
    $clauses = [];
    $params = [];
    if (in_array($status, BACKLINK_STATUSES, true)) {
        $clauses[] = 'status = ?';
        $params[] = $status;
    }
    if (isset(BACKLINK_TYPES[$type])) {
        $clauses[] = 'type = ?';
        $params[] = $type;
    }
    $where = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
    return db_all("SELECT * FROM backlinks{$where} ORDER BY FIELD(status, 'live', 'pending', 'submitted', 'opportunity', 'rejected'), updated_at DESC", $params);
}

function backlink_find(int $id): ?array
{
    return db_one('SELECT * FROM backlinks WHERE id = ?', [$id]);
}

function backlink_counts(): array
{
    $counts = array_fill_keys(BACKLINK_STATUSES, 0);
    foreach (db_all('SELECT status, COUNT(*) AS n FROM backlinks GROUP BY status') as $row) {
        $counts[$row['status']] = (int) $row['n'];
    }
    return $counts;
}

/** @return array{0: array, 1: array} */
function backlink_validate(array $input): array
{
    [$data, $errors] = validate($input, [
        'platform'    => 'required|max:120',
        'type'        => 'required|in:' . implode(',', array_keys(BACKLINK_TYPES)),
        'source_url'  => 'url|max:500',
        'target_url'  => 'required|url|max:500',
        'anchor_text' => 'max:200',
        'rel'         => 'required|in:' . implode(',', BACKLINK_RELS),
        'status'      => 'required|in:' . implode(',', BACKLINK_STATUSES),
        'notes'       => 'max:3000',
    ], ['source_url' => 'Source URL', 'target_url' => 'Target URL']);
    if ($data['status'] === 'live' && !$data['source_url']) {
        $errors['source_url'] = 'A live link needs the source page URL so it can be verified.';
    }
    return [$data, $errors];
}

/**
 * Fetch the source page (with SSRF protection) and look for a link to the target's domain.
 * @return array{found: bool, rel: string, anchor: string, message: string}
 */
function backlink_verify(array $backlink): array
{
    if (!$backlink['source_url']) {
        return ['found' => false, 'rel' => 'unknown', 'anchor' => '', 'message' => 'Add the source URL first.'];
    }
    $fetch = audit_fetch($backlink['source_url']);
    if (!$fetch['ok'] || $fetch['status'] >= 400) {
        return ['found' => false, 'rel' => 'unknown', 'anchor' => '', 'message' => $fetch['error'] ?: 'Source page returned HTTP ' . $fetch['status'] . '.'];
    }

    $targetHost = preg_replace('/^www\./', '', strtolower((string) parse_url($backlink['target_url'], PHP_URL_HOST)));
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $fetch['html'], LIBXML_NONET);
    libxml_clear_errors();

    foreach ($doc->getElementsByTagName('a') as $a) {
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($a->getAttribute('href'), PHP_URL_HOST)));
        if ($host !== '' && $host === $targetHost) {
            $relAttr = strtolower($a->getAttribute('rel'));
            $rel = 'follow';
            foreach (['sponsored', 'ugc', 'nofollow'] as $r) {
                if (str_contains($relAttr, $r)) {
                    $rel = $r;
                    break;
                }
            }
            $anchor = str_limit(trim($a->textContent), 190);
            return ['found' => true, 'rel' => $rel, 'anchor' => $anchor, 'message' => "Link found ($rel) with anchor “{$anchor}”."];
        }
    }
    return ['found' => false, 'rel' => 'unknown', 'anchor' => '', 'message' => 'No link to ' . $targetHost . ' was found on the source page.'];
}
