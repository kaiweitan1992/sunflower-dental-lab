<?php
declare(strict_types=1);

namespace Sunflower;

/**
 * Session-based authentication. Passwords are stored as bcrypt hashes
 * via PHP's password_hash() / password_verify().
 *
 * Sessions are configured with secure cookies in production
 * (see Bootstrap::startSession).
 */
final class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $u = Db::one(
            'SELECT id, username, password_hash, display_name, role, is_active
             FROM users WHERE username = :u LIMIT 1',
            [':u' => $username]
        );
        if (!$u || !$u['is_active']) {
            return false;
        }
        if (!password_verify($password, $u['password_hash'])) {
            return false;
        }
        // Rehash if PHP's algorithm has improved.
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Db::exec(
                'UPDATE users SET password_hash = :h WHERE id = :id',
                [':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $u['id']]
            );
        }
        // Prevent session fixation: regenerate on login.
        session_regenerate_id(true);
        $_SESSION['uid']     = (int) $u['id'];
        $_SESSION['uname']   = $u['username'];
        $_SESSION['display'] = $u['display_name'];
        $_SESSION['role']    = $u['role'];
        $_SESSION['login_at'] = time();
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']
            );
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id'           => (int) $_SESSION['uid'],
            'username'     => $_SESSION['uname']   ?? '',
            'display_name' => $_SESSION['display'] ?? '',
            'role'         => $_SESSION['role']    ?? 'staff',
        ];
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Http\Response::json(['error' => 'unauthorized'], 401);
        }
    }
}
