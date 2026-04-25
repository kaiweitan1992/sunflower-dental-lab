<?php
declare(strict_types=1);

namespace Sunflower;

/**
 * CSRF protection using a per-session token that the browser must
 * echo back via the X-CSRF-Token header on any unsafe verb.
 *
 * The token is exposed once at page load via a meta tag; the JS
 * fetch wrapper attaches it automatically.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function check(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }
        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected = $_SESSION['csrf'] ?? '';
        return $supplied !== '' && $expected !== '' && hash_equals($expected, $supplied);
    }

    public static function require(): void
    {
        if (!self::check()) {
            Http\Response::json(['error' => 'csrf_failed'], 419);
        }
    }
}
