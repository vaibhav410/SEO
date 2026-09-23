<?php
/** POST: mark all notifications as read for the signed-in user. */
require __DIR__ . '/_init.php';

if (!is_post()) {
    redirect('/admin/dashboard.php');
}
db_update('users', (int) $user['id'], ['notifications_seen_at' => date('Y-m-d H:i:s')]);
flash('success', 'Notifications marked as read.');

// Return to the page the user was on, but only if it is inside the admin.
$back = (string) parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH);
$query = (string) parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_QUERY);
$prefix = base_path() . '/admin/';
if (str_starts_with($back, $prefix) && !str_contains($back, '..')) {
    header('Location: ' . $back . ($query !== '' ? '?' . $query : ''), true, 302);
    exit;
}
redirect('/admin/dashboard.php');
