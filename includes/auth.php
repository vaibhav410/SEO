<?php
/**
 * Admin authentication and authorization.
 */

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_MINUTES = 15;

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = $_SESSION['user_id'] ?? null;
    $user = $id ? db_one('SELECT id, name, email, role FROM users WHERE id = ?', [$id]) : null;
    return $user;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], ['admin', 'editor'], true);
}

/**
 * Guard for every admin page: logged in, not idle, and an allowed role.
 * $roles lets a page restrict itself further (e.g. settings = admin only).
 */
function require_admin(array $roles = ['admin', 'editor']): array
{
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');

    $idle = (int) config('session.idle_timeout');
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > $idle) {
        logout_user();
        start_secure_session();
        flash('info', 'You were signed out after a period of inactivity.');
    }

    $user = current_user();
    if (!$user && str_starts_with(current_path(), '/api/')) {
        json_response(['ok' => false, 'error' => 'Your session has expired. Please sign in again.'], 401);
    }
    if (!$user) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? url('/admin/');
        redirect('/admin/login.php');
    }
    if (!in_array($user['role'], $roles, true)) {
        abort(403, 'Your account does not have permission to view this page.');
    }
    $_SESSION['last_seen'] = time();
    return $user;
}

function too_many_login_attempts(string $email): bool
{
    $count = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE (ip_hash = ? OR email = ?) AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)',
        [ip_hash(), mb_strtolower($email)]
    );
    return $count >= LOGIN_MAX_ATTEMPTS;
}

/** @return array|null the user on success */
function attempt_login(string $email, string $password): ?array
{
    $email = mb_strtolower(trim($email));
    $user = db_one('SELECT id, name, email, role, password_hash FROM users WHERE email = ?', [$email]);

    // Verify against a dummy hash when the user is unknown so timing does not reveal valid emails.
    $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $valid = password_verify($password, $hash) && $user !== null;

    if (!$valid) {
        db_query('INSERT INTO login_attempts (ip_hash, email) VALUES (?, ?)', [ip_hash(), $email]);
        return null;
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    db_query('DELETE FROM login_attempts WHERE email = ? OR ip_hash = ?', [$email, ip_hash()]);
    db_query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_seen'] = time();
    unset($user['password_hash']);
    return $user;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
