<?php
/**
 * StudentFlow - Session + authentication
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

/* ---------------------------------------------------------------
 * Secure session start
 * ------------------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------------------------------------------------------------
 * Remember-me cookie support
 * ------------------------------------------------------------- */
function check_remember_cookie(): void
{
    if (!empty($_SESSION['uid']) || empty($_COOKIE['sf_remember'])) {
        return;
    }
    $raw = explode('|', (string) $_COOKIE['sf_remember'], 2);
    if (count($raw) !== 2 || !ctype_digit($raw[0])) {
        return;
    }
    $uid   = (int) $raw[0];
    $token = hash('sha256', $raw[1]);
    $stmt  = db()->prepare('SELECT id FROM users WHERE id = ? AND remember_token = ?');
    $stmt->execute([$uid, $token]);
    if ($stmt->fetch()) {
        $_SESSION['uid'] = $uid;
        session_regenerate_id(true);
    }
}
check_remember_cookie();

/* ---------------------------------------------------------------
 * Auth helpers
 * ------------------------------------------------------------- */
function is_logged_in(): bool
{
    return !empty($_SESSION['uid']);
}

function user_id(): int
{
    return (int) ($_SESSION['uid'] ?? 0);
}

/** Currently authenticated user row (cached). Returns null when logged out. */
function current_user(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    if (!is_logged_in()) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([user_id()]);
    $cache = $stmt->fetch() ?: null;
    return $cache;
}

/** Guard: unauthenticated visitors are redirected to the login page. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        $dest = basename($_SERVER['PHP_SELF'] ?? '');
        header('Location: login.php' . ($dest !== 'login.php' && $dest !== '' ? '?redirect=' . urlencode($dest) : ''));
        exit;
    }
}

/** Guard used by actions / API endpoints. */
function require_api_auth(): void
{
    if (!is_logged_in()) {
        respond(false, 'Authentication required.', [], 401);
    }
}

/* ---------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        respond(false, 'Security token expired. Please refresh the page and try again.', [], 403);
    }
}

/* ---------------------------------------------------------------
 * Login / logout
 * ------------------------------------------------------------- */
function attempt_login(string $email, string $password, bool $remember): array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return [false, 'Invalid email or password.'];
    }

    // Re-hash when needed (future proofing)
    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
        $up = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
        $up->execute([password_hash($password, PASSWORD_BCRYPT), $user['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    unset($_SESSION['csrf_token']); // fresh token for the new session

    if ($remember) {
        $raw = bin2hex(random_bytes(24));
        $up  = db()->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
        $up->execute([hash('sha256', $raw), $user['id']]);
        setcookie('sf_remember', $user['id'] . '|' . $raw, [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    sync_notifications((int) $user['id']);
    return [true, ''];
}

function logout_user(): void
{
    if (!empty($_SESSION['uid'])) {
        $stmt = db()->prepare('UPDATE users SET remember_token = NULL WHERE id = ?');
        $stmt->execute([user_id()]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    setcookie('sf_remember', '', time() - 3600, '/');
    session_destroy();
}
