<?php
/**
 * Shared PDO connection. One connection per request, created on first use.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = config('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int) $c['port'], $c['name'], $c['charset']);

    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ]);
    $pdo->exec("SET time_zone = '+05:30'");

    return $pdo;
}

/** Run a prepared statement and return it. */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_value(string $sql, array $params = [])
{
    $value = db_query($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

/**
 * Insert/update/delete helpers. Table and column names come from code (whitelisted in each module),
 * values are always bound parameters.
 */
function db_assert_identifier(string $name): string
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier: ' . $name);
    }
    return $name;
}

function db_insert(string $table, array $data): int
{
    $columns = array_map('db_assert_identifier', array_keys($data));
    $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', db_assert_identifier($table), implode(', ', $columns), implode(', ', array_fill(0, count($data), '?')));
    db_query($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function db_update(string $table, int $id, array $data): void
{
    $sets = implode(', ', array_map(fn($c) => db_assert_identifier($c) . ' = ?', array_keys($data)));
    db_query(sprintf('UPDATE %s SET %s WHERE id = ?', db_assert_identifier($table), $sets), [...array_values($data), $id]);
}

function db_delete(string $table, int $id): void
{
    db_query(sprintf('DELETE FROM %s WHERE id = ?', db_assert_identifier($table)), [$id]);
}

/** Is this slug already used by another row of the table? */
function db_slug_taken(string $table, string $slug, ?int $exceptId = null): bool
{
    return (bool) db_value(
        sprintf('SELECT 1 FROM %s WHERE slug = ? AND id <> ? LIMIT 1', db_assert_identifier($table)),
        [$slug, $exceptId ?? 0]
    );
}
