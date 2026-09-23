<?php
/**
 * Content opportunity centre. Opportunities are computed from the site's own data (keyword plan,
 * published content, internal links, off-page pipeline, content health), never from invented search
 * volumes. Editors can mark each one in progress, done or dismissed.
 */

const OPPORTUNITY_TYPES = [
    'keyword' => 'Keyword opportunities', 'content_gap' => 'Content gaps', 'internal_link' => 'Internal link opportunities',
    'landing_page' => 'Landing page opportunities', 'backlink' => 'Backlink opportunities', 'technical' => 'Technical SEO issues',
];
const OPPORTUNITY_PRIORITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

/**
 * @return array<array{key: string, type: string, title: string, priority: string, reason: string, action: string, url: ?string, status: string}>
 */
function opportunities_all(): array
{
    $ops = [];
    $add = function (string $type, string $identity, string $title, string $priority, string $reason, string $action, ?string $url) use (&$ops) {
        $key = sha1($type . '|' . $identity);
        $ops[$key] = compact('key', 'type', 'title', 'priority', 'reason', 'action', 'url') + ['status' => 'open'];
    };

    // Keywords without a page, and commercial keywords served only by a generic page.
    foreach (db_all('SELECT * FROM keywords WHERE status <> \'paused\'') as $k) {
        if (!$k['target_url']) {
            $isInfo = $k['intent'] === 'informational';
            $add('keyword', 'unmapped:' . $k['keyword'], ($isInfo ? 'Write a guide for “' : 'Create or map a page for “') . $k['keyword'] . '”', $k['priority'],
                ucfirst($k['intent']) . '-intent keyword in the plan with no target page yet.',
                $isInfo ? 'Create article' : 'Create landing page or map to an existing service',
                $isInfo ? '/admin/posts/edit.php' : '/admin/landing-pages/edit.php?keyword=' . rawurlencode($k['keyword']));
        } elseif ($k['intent'] === 'commercial' && str_starts_with($k['target_url'], '/services/') && $k['priority'] !== 'low') {
            $add('landing_page', 'landing:' . $k['keyword'], 'Consider a focused landing page for “' . $k['keyword'] . '”', 'medium',
                'Commercial-intent keyword currently mapped to a general service page (' . $k['target_url'] . ').',
                'Create landing page with problem/solution, use cases and FAQs', '/admin/landing-pages/edit.php?keyword=' . rawurlencode($k['keyword']));
        }
    }

    // Services without any supporting guide.
    foreach (db_all("SELECT s.id, s.name, s.slug, s.primary_keyword, (SELECT COUNT(*) FROM posts p WHERE p.service_id = s.id AND " . POST_PUBLIC_SQL . ") AS guides
                     FROM services s WHERE s.status = 'published'") as $s) {
        if ((int) $s['guides'] === 0) {
            $add('content_gap', 'service-guide:' . $s['slug'], 'Create a ' . $s['name'] . ' guide or comparison article', 'high',
                'No published article supports the ' . $s['name'] . ' service page, so it receives no contextual internal links from the blog.',
                'Create article linked to ' . $s['name'], '/admin/posts/edit.php');
        }
    }
    foreach (db_all('SELECT c.name, c.slug, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id AND ' . POST_PUBLIC_SQL . ') n FROM categories c') as $c) {
        if ((int) $c['n'] < 2) {
            $add('content_gap', 'category:' . $c['slug'], 'Expand the “' . $c['name'] . '” category', 'low',
                'Only ' . (int) $c['n'] . ' published article(s) in this category; thin category pages help nobody.', 'Plan another article', '/admin/posts/edit.php');
        }
    }

    // Internal links: service and landing pages that no article links to.
    $inbound = internal_inbound_map();
    foreach (db_all("SELECT title, slug, primary_keyword FROM landing_pages WHERE status = 'published'") as $l) {
        if (empty($inbound['/' . $l['slug']])) {
            $add('internal_link', 'landing-inbound:' . $l['slug'], 'Link to /' . $l['slug'] . ' from related guides', 'medium',
                'No article links to this landing page, so readers and crawlers rarely reach it.',
                'Add an internal link rule for “' . $l['primary_keyword'] . '” or link it manually', '/admin/links/');
        }
    }
    foreach (services_published() as $s) {
        if (empty($inbound['/services/' . $s['slug']])) {
            $add('internal_link', 'service-inbound:' . $s['slug'], 'Link to ' . $s['name'] . ' from the blog', 'medium',
                'No published article links to /services/' . $s['slug'] . '.', 'Add a link rule or mention the service in a related guide', '/admin/links/');
        }
    }

    // Off-page follow-ups.
    foreach (db_all("SELECT id, platform, status, DATEDIFF(NOW(), updated_at) age FROM backlinks WHERE status IN ('opportunity', 'submitted', 'pending')") as $b) {
        if ($b['status'] === 'opportunity' && $b['age'] >= 7) {
            $add('backlink', 'bl-submit:' . $b['id'], 'Submit or pitch: ' . $b['platform'], 'medium',
                'Listed as an opportunity for ' . (int) $b['age'] . ' days without action.', 'Prepare the listing or pitch, then mark as submitted', '/admin/backlinks/view.php?id=' . $b['id']);
        } elseif ($b['status'] !== 'opportunity' && $b['age'] >= 21) {
            $add('backlink', 'bl-follow:' . $b['id'], 'Follow up with ' . $b['platform'], 'low',
                ucfirst($b['status']) . ' for ' . (int) $b['age'] . ' days.', 'Check status and verify the link if it went live', '/admin/backlinks/view.php?id=' . $b['id']);
        }
    }
    $types = array_column(db_all('SELECT DISTINCT type FROM backlinks'), 'type');
    if (!in_array('citation', $types, true)) {
        $add('backlink', 'no-citations', 'Start local citations (Google Business Profile, Bing Places)', 'high',
            'No citation records yet. Consistent name, address and phone listings help local and brand searches.', 'Add citation opportunities', '/admin/backlinks/edit.php');
    }

    // Technical issues from content health (errors and warnings only).
    foreach (content_health_issues() as $i) {
        if ($i['severity'] === 'info') {
            continue;
        }
        $add('technical', 'health:' . $i['type'] . ':' . $i['item'], $i['type'] . ': ' . str_limit($i['item'], 70), $i['severity'] === 'error' ? 'high' : 'medium',
            $i['message'], 'Fix in the editor', $i['edit']);
    }
    foreach (db_all('SELECT a.id, a.url, a.overall_score FROM seo_audits a JOIN (SELECT MAX(id) id FROM seo_audits GROUP BY url) l ON l.id = a.id WHERE a.overall_score < 70') as $a) {
        $add('technical', 'audit:' . $a['url'], 'Improve on-page SEO of ' . str_limit(preg_replace('#^https?://#', '', $a['url']), 50), $a['overall_score'] < 50 ? 'high' : 'medium',
            'Latest audit scored ' . (int) $a['overall_score'] . '/100 on the internal checklist.', 'Review audit findings', '/admin/audits/view.php?id=' . $a['id']);
    }

    // Apply saved states.
    if ($ops) {
        $placeholders = implode(',', array_fill(0, count($ops), '?'));
        foreach (db_all("SELECT opportunity_key, status FROM opportunity_states WHERE opportunity_key IN ($placeholders)", array_keys($ops)) as $s) {
            $ops[$s['opportunity_key']]['status'] = $s['status'];
        }
    }
    $ops = array_values($ops);
    usort($ops, fn($a, $b) => [$a['status'] === 'open' ? 0 : 1, OPPORTUNITY_PRIORITY_ORDER[$a['priority']] ?? 3] <=> [$b['status'] === 'open' ? 0 : 1, OPPORTUNITY_PRIORITY_ORDER[$b['priority']] ?? 3]);
    return $ops;
}

function opportunity_set_status(string $key, string $status, int $userId): void
{
    if (!preg_match('/^[a-f0-9]{40}$/', $key)) {
        return;
    }
    if ($status === 'open') {
        db_query('DELETE FROM opportunity_states WHERE opportunity_key = ?', [$key]);
        return;
    }
    if (in_array($status, ['in_progress', 'done', 'dismissed'], true)) {
        db_query('INSERT INTO opportunity_states (opportunity_key, status, updated_by) VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE status = VALUES(status), updated_by = VALUES(updated_by)', [$key, $status, $userId]);
    }
}
