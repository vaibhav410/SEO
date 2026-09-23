<?php
/**
 * Admin activity timeline. Every meaningful change in the admin (and each new lead) is recorded
 * so the team can see who did what, and so each record can show its own history.
 */

const ACTIVITY_ICONS = [
    'post' => 'book', 'service' => 'server', 'landing' => 'zap', 'faq' => 'headset', 'keyword' => 'search',
    'link' => 'layers', 'audit' => 'shield', 'backlink' => 'globe', 'lead' => 'users', 'category' => 'book',
    'distribution' => 'trending', 'settings' => 'cpu', 'user' => 'users', 'opportunity' => 'zap', 'system' => 'cpu',
];

/**
 * @param int|null $userId null = the signed-in admin (if any); 0 = no user (e.g. a public visitor)
 */
function log_activity(string $action, string $type, ?int $id, string $label, ?int $userId = null): void
{
    if ($userId === null) {
        $userId = PHP_SAPI !== 'cli' && !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    }
    try {
        db_insert('activity_log', [
            'user_id' => $userId ?: null, 'action' => mb_substr($action, 0, 40), 'entity_type' => mb_substr($type, 0, 40),
            'entity_id' => $id, 'label' => mb_substr($label, 0, 255),
        ]);
    } catch (PDOException $e) {
        // The timeline is informational; never block the real action because logging failed.
        log_error('Activity log failed: ' . $e->getMessage());
    }
}

function activity_recent(int $limit = 12, ?string $type = null, ?int $id = null): array
{
    $where = '';
    $params = [];
    if ($type !== null) {
        $where = ' WHERE a.entity_type = ?' . ($id !== null ? ' AND a.entity_id = ?' : '');
        $params = $id !== null ? [$type, $id] : [$type];
    }
    return db_all('SELECT a.*, u.name AS user_name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id'
        . $where . ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . (int) $limit, $params);
}

/** Admin URL for an activity entry, when the record still exists. */
function activity_link(array $a): ?string
{
    if (!$a['entity_id']) {
        return null;
    }
    return match ($a['entity_type']) {
        'post' => '/admin/posts/edit.php?id=' . $a['entity_id'],
        'service' => '/admin/services/edit.php?id=' . $a['entity_id'],
        'landing' => '/admin/landing-pages/edit.php?id=' . $a['entity_id'],
        'keyword' => '/admin/keywords/view.php?id=' . $a['entity_id'],
        'lead' => '/admin/leads/view.php?id=' . $a['entity_id'],
        'backlink' => '/admin/backlinks/view.php?id=' . $a['entity_id'],
        'audit' => '/admin/audits/view.php?id=' . $a['entity_id'],
        'faq' => '/admin/faqs/edit.php?id=' . $a['entity_id'],
        'category' => '/admin/categories/?edit=' . $a['entity_id'],
        'distribution' => '/admin/distribution/?edit=' . $a['entity_id'],
        default => null,
    };
}

/** Render a vertical timeline. */
function activity_timeline(array $items, bool $showUser = true): string
{
    if (!$items) {
        return '<p class="muted small">No activity yet.</p>';
    }
    $html = '<ol class="timeline">';
    foreach ($items as $a) {
        $link = activity_link($a);
        $label = $link ? '<a href="' . e(url($link)) . '">' . e($a['label']) . '</a>' : e($a['label']);
        $who = $showUser ? ($a['user_name'] ? e($a['user_name']) : 'Website visitor') . ' · ' : '';
        $html .= '<li><span class="timeline-icon">' . icon(ACTIVITY_ICONS[$a['entity_type']] ?? 'zap', 'icon icon-xs') . '</span>'
            . '<div><p>' . $label . '</p><p class="muted small">' . $who
            . '<time datetime="' . e(iso_date($a['created_at'])) . '" title="' . e($a['created_at']) . '">' . e(time_ago($a['created_at'])) . '</time></p></div></li>';
    }
    return $html . '</ol>';
}
