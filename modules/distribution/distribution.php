<?php
/**
 * Organic distribution log: where each piece of content was shared. Social activity helps people
 * discover content and can earn natural links and mentions; it is not a direct Google ranking factor.
 */

const DISTRIBUTION_PLATFORMS = ['linkedin' => 'LinkedIn', 'x' => 'X / Twitter', 'facebook' => 'Facebook', 'reddit' => 'Reddit',
    'youtube' => 'YouTube', 'quora' => 'Quora', 'other' => 'Other'];
const DISTRIBUTION_STATUSES = ['planned', 'published', 'skipped'];

function distribution_list(string $platform = '', string $status = ''): array
{
    $clauses = [];
    $params = [];
    if (isset(DISTRIBUTION_PLATFORMS[$platform])) {
        $clauses[] = 'd.platform = ?';
        $params[] = $platform;
    }
    if (in_array($status, DISTRIBUTION_STATUSES, true)) {
        $clauses[] = 'd.status = ?';
        $params[] = $status;
    }
    $where = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
    return db_all("SELECT d.*, p.title AS post_title, p.slug AS post_slug FROM distribution_posts d
                   LEFT JOIN posts p ON p.id = d.post_id{$where}
                   ORDER BY FIELD(d.status, 'planned', 'published', 'skipped'), COALESCE(d.published_at, d.created_at) DESC", $params);
}

function distribution_counts(): array
{
    $counts = array_fill_keys(array_keys(DISTRIBUTION_PLATFORMS), ['published' => 0, 'planned' => 0]);
    foreach (db_all('SELECT platform, status, COUNT(*) n FROM distribution_posts GROUP BY platform, status') as $r) {
        if (isset($counts[$r['platform']][$r['status']])) {
            $counts[$r['platform']][$r['status']] = (int) $r['n'];
        }
    }
    return $counts;
}

/** @return array{0: array, 1: array} */
function distribution_validate(array $input): array
{
    [$data, $errors] = validate($input, [
        'platform'     => 'required|in:' . implode(',', array_keys(DISTRIBUTION_PLATFORMS)),
        'title'        => 'required|max:200',
        'post_id'      => 'int',
        'url'          => 'url|max:500',
        'status'       => 'required|in:' . implode(',', DISTRIBUTION_STATUSES),
        'published_at' => 'max:10',
        'notes'        => 'max:2000',
    ], ['url' => 'Post URL', 'post_id' => 'Article']);
    if ($data['status'] === 'published' && !$data['url']) {
        $errors['url'] = 'Add the URL of the published post so it can be checked later.';
    }
    if ($data['published_at'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['published_at'])) {
        $errors['published_at'] = 'Use the date picker (YYYY-MM-DD).';
    }
    if ($data['status'] === 'published' && !$data['published_at']) {
        $data['published_at'] = date('Y-m-d');
    }
    $data['post_id'] = $data['post_id'] ? (int) $data['post_id'] : null;
    return [$data, $errors];
}
