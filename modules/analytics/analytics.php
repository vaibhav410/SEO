<?php
/**
 * Internal analytics: everything here is counted from this application's own database
 * (leads, content, keywords, off-page work, audits). Traffic, impressions and rankings need an
 * external source (Google Analytics / Search Console) and are shown as "not connected" instead of
 * being estimated.
 */

/** Count of rows in the last $days vs the $days before that. @return array{current: int, previous: int, change: ?float} */
function trend_count(string $table, string $dateColumn, int $days = 30, string $where = '1=1'): array
{
    db_assert_identifier($table);
    db_assert_identifier($dateColumn);
    $row = db_one("SELECT
        SUM($dateColumn >= NOW() - INTERVAL $days DAY) AS cur,
        SUM($dateColumn < NOW() - INTERVAL $days DAY AND $dateColumn >= NOW() - INTERVAL " . ($days * 2) . " DAY) AS prev
        FROM $table WHERE $where");
    $cur = (int) ($row['cur'] ?? 0);
    $prev = (int) ($row['prev'] ?? 0);
    return ['current' => $cur, 'previous' => $prev, 'change' => $prev > 0 ? round(100 * ($cur - $prev) / $prev, 1) : null];
}

/** Leads per ISO week for the last $weeks weeks (oldest first), zero-filled. */
function leads_weekly(int $weeks = 12): array
{
    $counts = [];
    foreach (db_all("SELECT YEARWEEK(created_at, 3) wk, COUNT(*) n FROM leads WHERE status <> 'spam' AND created_at >= NOW() - INTERVAL " . (int) $weeks . " WEEK GROUP BY wk") as $r) {
        $counts[(int) $r['wk']] = (int) $r['n'];
    }
    $series = [];
    for ($i = $weeks - 1; $i >= 0; $i--) {
        $ts = strtotime("-$i weeks");
        $key = (int) (date('o', $ts) . date('W', $ts));
        $series[] = ['label' => date('j M', strtotime('monday this week', $ts)), 'value' => $counts[$key] ?? 0];
    }
    return $series;
}

/** Articles published per month for the last $months months. */
function content_monthly(int $months = 6): array
{
    $counts = [];
    foreach (db_all("SELECT DATE_FORMAT(published_at, '%Y-%m') m, COUNT(*) n FROM posts p WHERE " . POST_PUBLIC_SQL . " AND published_at >= NOW() - INTERVAL " . (int) $months . " MONTH GROUP BY m") as $r) {
        $counts[$r['m']] = (int) $r['n'];
    }
    $series = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts = strtotime(date('Y-m-01') . " -$i months");
        $series[] = ['label' => date('M', $ts), 'value' => $counts[date('Y-m', $ts)] ?? 0];
    }
    return $series;
}

function keyword_distribution(): array
{
    $out = array_fill_keys(KEYWORD_INTENTS, 0);
    foreach (db_all('SELECT intent, COUNT(*) n FROM keywords GROUP BY intent') as $r) {
        $out[$r['intent']] = (int) $r['n'];
    }
    return $out;
}

function lead_funnel(): array
{
    $out = array_fill_keys(LEAD_STATUSES, 0);
    foreach (db_all('SELECT status, COUNT(*) n FROM leads GROUP BY status') as $r) {
        $out[$r['status']] = (int) $r['n'];
    }
    return $out;
}

/** Share of non-spam leads that converted. Null when there are no leads yet. */
function lead_conversion_rate(): ?float
{
    $row = db_one("SELECT SUM(status = 'converted') conv, SUM(status <> 'spam') total FROM leads");
    return (int) $row['total'] > 0 ? round(100 * (int) $row['conv'] / (int) $row['total'], 1) : null;
}

/** Keywords with a published target page / all active keywords. */
function keyword_coverage_rate(): ?float
{
    $rows = db_all("SELECT keyword, target_url FROM keywords WHERE status <> 'paused'");
    if (!$rows) {
        return null;
    }
    $covered = count(array_filter($rows, fn($k) => $k['target_url'] && content_at_path($k['target_url'])));
    return round(100 * $covered / count($rows), 1);
}

/** Per-article performance available without analytics: leads attributed and inbound links. */
function content_performance(int $limit = 8): array
{
    return db_all(
        "SELECT p.id, p.title, p.slug, p.published_at,
                (SELECT COUNT(*) FROM leads l WHERE l.source_page = CONCAT('/blog/', p.slug) AND l.status <> 'spam') AS leads,
                (SELECT COUNT(*) FROM distribution_posts d WHERE d.post_id = p.id AND d.status = 'published') AS shares
         FROM posts p WHERE " . POST_PUBLIC_SQL . " ORDER BY leads DESC, p.published_at DESC LIMIT " . (int) $limit
    );
}
