<?php
/**
 * Key/value site settings, loaded once per request.
 */

const SETTING_KEYS = [
    'site_name', 'site_tagline', 'site_description', 'contact_email', 'contact_phone',
    'contact_address', 'main_site_url', 'social_profiles', 'internal_links_max',
];

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = (string) $row['setting_value'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $value = settings_all()[$key] ?? '';
    return $value !== '' ? $value : $default;
}

function settings_save(array $values): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );
    foreach ($values as $key => $value) {
        if (in_array($key, SETTING_KEYS, true)) {
            $stmt->execute([$key, $value]);
        }
    }
    settings_all(true);
}
