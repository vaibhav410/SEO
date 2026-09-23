<?php
/**
 * Application configuration.
 *
 * Defaults target a stock XAMPP install (root / empty password on 3306).
 * Override per machine in config/config.local.php (git-ignored) or with
 * environment variables (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, APP_URL, APP_DEBUG).
 */

$config = [
    'app' => [
        'name'     => 'SYSCOM GrowthHub',
        // Absolute public URL of the app, no trailing slash. Sub-folder installs are supported.
        'base_url' => 'http://localhost/syscom-growthhub',
        'debug'    => false,
        'timezone' => 'Asia/Kolkata',
        // Secret used to hash visitor IPs for rate limiting. Change in production.
        'secret'   => 'change-this-secret-in-config-local',
        // Set true only when running behind a reverse proxy that sets X-Forwarded-For / CF-Connecting-IP.
        'trust_proxy' => false,
    ],
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'syscom_growthhub',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name'         => 'syscom_sid',
        'idle_timeout' => 7200, // seconds of admin inactivity before logout
    ],
    'uploads' => [
        'max_bytes' => 2 * 1024 * 1024,
    ],
    'audit' => [
        'timeout'        => 12,
        'connect_timeout'=> 6,
        'max_bytes'      => 3 * 1024 * 1024,
        'max_redirects'  => 5,
        // Lets the auditor fetch this app's own origin even when it runs on localhost.
        // Only the exact base_url origin is allowed; all other private addresses stay blocked.
        'allow_self'     => false,
    ],
    // Optional AI writing assistant (OpenRouter). Disabled while api_key is empty.
    // Suggestions are drafts for a human editor; nothing is published automatically.
    'ai' => [
        'api_key'  => '',
        'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
        'model'    => 'openai/gpt-4o-mini',
        'timeout'  => 30,
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

$env = [
    'DB_HOST' => ['db', 'host'], 'DB_PORT' => ['db', 'port'], 'DB_NAME' => ['db', 'name'],
    'DB_USER' => ['db', 'user'], 'DB_PASS' => ['db', 'pass'],
    'APP_URL' => ['app', 'base_url'], 'APP_DEBUG' => ['app', 'debug'], 'APP_SECRET' => ['app', 'secret'], 'TRUST_PROXY' => ['app', 'trust_proxy'],
    'AUDIT_ALLOW_SELF' => ['audit', 'allow_self'],
    'OPENROUTER_API_KEY' => ['ai', 'api_key'], 'AI_MODEL' => ['ai', 'model'],
];
foreach ($env as $var => [$group, $key]) {
    $value = getenv($var);
    if ($value !== false) {
        $config[$group][$key] = in_array($key, ['debug', 'allow_self', 'trust_proxy'], true)
            ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
            : $value;
    }
}

$config['app']['base_url'] = rtrim($config['app']['base_url'], '/');

return $config;
