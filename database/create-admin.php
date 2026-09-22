<?php
/**
 * Create an admin account, or reset the password of an existing one.
 *
 *   php database/create-admin.php "Full Name" email@example.com "StrongPassword!" [admin|editor]
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';

[, $name, $email, $password, $role] = array_pad($argv, 5, null);
$role = $role ?: 'admin';

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 10 || !in_array($role, ['admin', 'editor'], true)) {
    fwrite(STDERR, "Usage: php database/create-admin.php \"Full Name\" email@example.com \"password (10+ chars)\" [admin|editor]\n");
    exit(1);
}

db_query(
    'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = VALUES(role)',
    [$name, mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT), $role]
);
echo "Saved {$role} account for {$email}.\n";
