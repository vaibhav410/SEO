<?php
/**
 * Loaded first by every entry point (public front controller, admin pages, API, CLI scripts).
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$GLOBALS['__config'] = require APP_ROOT . '/config/config.php';

function config(string $key, $default = null)
{
    $value = $GLOBALS['__config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

date_default_timezone_set(config('app.timezone'));
mb_internal_encoding('UTF-8');

require APP_ROOT . '/config/database.php';
require APP_ROOT . '/includes/functions.php';
require APP_ROOT . '/includes/errors.php';
require APP_ROOT . '/includes/security.php';
require APP_ROOT . '/includes/csrf.php';
require APP_ROOT . '/includes/flash.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/seo.php';
require APP_ROOT . '/includes/schema.php';
require APP_ROOT . '/includes/markdown.php';
require APP_ROOT . '/includes/validation.php';
require APP_ROOT . '/includes/uploads.php';
require APP_ROOT . '/includes/ui.php';
require APP_ROOT . '/includes/activity.php';
require APP_ROOT . '/modules/settings/settings.php';
require APP_ROOT . '/modules/services/services.php';
require APP_ROOT . '/modules/content/posts.php';
require APP_ROOT . '/modules/landing-pages/landing_pages.php';
require APP_ROOT . '/modules/faqs/faqs.php';
require APP_ROOT . '/modules/leads/leads.php';
require APP_ROOT . '/modules/linking/linker.php';
require APP_ROOT . '/modules/categories/categories.php';
require APP_ROOT . '/modules/search/search.php';
require APP_ROOT . '/modules/seo/technical.php';

register_error_handlers();

if (PHP_SAPI !== 'cli') {
    send_security_headers();
    if (!defined('NO_SESSION')) {
        start_secure_session();
    }
}
