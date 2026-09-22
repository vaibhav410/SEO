<?php
/**
 * Included at the top of every admin page: loads the app, enforces authentication and role,
 * and makes admin UI helpers available.
 *
 *   require __DIR__ . '/../_init.php';          // any signed-in admin/editor
 *   $ADMIN_ROLES = ['admin']; require ...;      // restrict to admins only
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once APP_ROOT . '/includes/admin_ui.php';
require_once APP_ROOT . '/modules/keywords/keywords.php';
require_once APP_ROOT . '/modules/backlinks/backlinks.php';
require_once APP_ROOT . '/modules/audit/url_guard.php';
require_once APP_ROOT . '/modules/audit/auditor.php';
require_once APP_ROOT . '/modules/seo/content_health.php';
require_once APP_ROOT . '/modules/ai/assistant.php';

if (!defined('ADMIN_PUBLIC')) {
    $user = require_admin($ADMIN_ROLES ?? ['admin', 'editor']);
    // Every state-changing admin request must carry a valid CSRF token.
    if (is_post()) {
        csrf_verify();
    }
}
