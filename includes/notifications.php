<?php
/**
 * Admin notification centre. Notifications are derived from live data (new leads, weak audits,
 * failed link checks, content errors, scheduled posts) rather than stored messages, so they are
 * always current. "Mark all read" stores the time the user last looked.
 */

/** @return array<array{icon: string, title: string, detail: string, url: string, at: string, tone: string}> */
function notifications_for(array $user): array
{
    $items = [];
    foreach (db_all("SELECT id, name, source_page, created_at FROM leads WHERE status = 'new' ORDER BY created_at DESC LIMIT 5") as $l) {
        $items[] = ['icon' => 'users', 'title' => 'New lead: ' . $l['name'], 'detail' => 'From ' . $l['source_page'], 'url' => '/admin/leads/view.php?id=' . $l['id'], 'at' => $l['created_at'], 'tone' => 'info'];
    }
    foreach (db_all('SELECT id, url, overall_score, created_at FROM seo_audits WHERE overall_score < 50 AND created_at >= NOW() - INTERVAL 14 DAY ORDER BY created_at DESC LIMIT 3') as $a) {
        $items[] = ['icon' => 'shield', 'title' => 'Low audit score (' . (int) $a['overall_score'] . '/100)', 'detail' => str_limit($a['url'], 60), 'url' => '/admin/audits/view.php?id=' . $a['id'], 'at' => $a['created_at'], 'tone' => 'danger'];
    }
    foreach (db_all("SELECT id, platform, verification_message, last_checked_at FROM backlinks WHERE last_checked_at >= NOW() - INTERVAL 14 DAY AND status <> 'live' ORDER BY last_checked_at DESC LIMIT 3") as $b) {
        $items[] = ['icon' => 'globe', 'title' => 'Link not found: ' . $b['platform'], 'detail' => (string) $b['verification_message'], 'url' => '/admin/backlinks/view.php?id=' . $b['id'], 'at' => $b['last_checked_at'], 'tone' => 'warning'];
    }
    foreach (db_all("SELECT id, title, published_at, updated_at FROM posts WHERE status = 'published' AND published_at > NOW() AND published_at <= NOW() + INTERVAL 7 DAY") as $p) {
        $items[] = ['icon' => 'book', 'title' => 'Scheduled: ' . str_limit($p['title'], 50), 'detail' => 'Goes live ' . format_date($p['published_at'], 'j M, g:i a'), 'url' => '/admin/posts/edit.php?id=' . $p['id'], 'at' => $p['updated_at'], 'tone' => 'info'];
    }
    $errors = count(array_filter(content_health_issues(), fn($i) => $i['severity'] === 'error'));
    if ($errors > 0) {
        $items[] = ['icon' => 'zap', 'title' => $errors . ' content SEO error' . ($errors > 1 ? 's' : ''), 'detail' => 'Missing or duplicate meta tags, broken keyword mappings', 'url' => '/admin/opportunities/?type=technical', 'at' => (string) db_value('SELECT GREATEST(COALESCE((SELECT MAX(updated_at) FROM posts), 0), COALESCE((SELECT MAX(updated_at) FROM services), 0), COALESCE((SELECT MAX(updated_at) FROM landing_pages), 0))'), 'tone' => 'danger'];
    }
    usort($items, fn($a, $b) => strcmp($b['at'], $a['at']));
    return $items;
}

function notifications_unread(array $items, array $user): int
{
    $seen = (string) db_value('SELECT notifications_seen_at FROM users WHERE id = ?', [$user['id']]);
    return count(array_filter($items, fn($n) => $seen === '' || $n['at'] > $seen));
}
