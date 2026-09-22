<?php
/**
 * CLI installer: creates the database from schema.sql and loads the demo data.
 *
 *   php database/install.php            schema + demo data
 *   php database/install.php --no-seed  schema only
 *
 * Uses the connection details from config/config.php (+ config.local.php / env vars).
 * phpMyAdmin users can instead import schema.sql then seed.sql.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/seeder.php';

$c = config('db');
if (!preg_match('/^[A-Za-z0-9_]+$/', $c['name'])) {
    fwrite(STDERR, "Invalid database name in config.\n");
    exit(1);
}

$server = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], (int) $c['port']),
    $c['user'], $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$schema = str_replace('syscom_growthhub', $c['name'], file_get_contents(__DIR__ . '/schema.sql'));
foreach (sql_split($schema) as $statement) {
    $server->exec($statement);
}
echo "Schema created in `{$c['name']}`.\n";

if (in_array('--no-seed', $argv, true)) {
    exit(0);
}

$pdo = db();
$pdo->beginTransaction();
$counts = [];
foreach (seed_rows() as [$table, $row]) {
    $columns = array_keys($row);
    $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', array_fill(0, count($row), '?')));
    $pdo->prepare($sql)->execute(array_values($row));
    $counts[$table] = ($counts[$table] ?? 0) + 1;
}
$pdo->commit();

foreach ($counts as $table => $n) {
    printf("  %-15s %d rows\n", $table, $n);
}
echo "\nDemo admin: " . DEMO_ADMIN_EMAIL . ' / ' . DEMO_ADMIN_PASSWORD . "\n";
echo "Change this password (php database/create-admin.php) before deploying anywhere public.\n";
