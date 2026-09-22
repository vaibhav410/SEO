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
require APP_ROOT . '/modules/settings/settings.php';

register_error_handlers();

if (PHP_SAPI !== 'cli') {
    send_security_headers();
    if (!defined('NO_SESSION')) {
        start_secure_session();
    }
}
