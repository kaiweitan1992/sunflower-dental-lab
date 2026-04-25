<?php
declare(strict_types=1);

namespace Sunflower\Http;

final class Request
{
    private static ?array $jsonBody = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Path *inside* the API, without the /api.php prefix.
     * Handles both rewritten ("/api/products") and direct ("/api.php/products") URLs.
     */
    public static function path(): string
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = preg_replace('#^/api(?:\.php)?#', '', $path) ?? '';
        if ($path === '' || $path === false) {
            $path = '/';
        }
        return rtrim($path, '/') ?: '/';
    }

    public static function query(string $key, ?string $default = null): ?string
    {
        $v = $_GET[$key] ?? null;
        return is_string($v) ? $v : $default;
    }

    public static function json(): array
    {
        if (self::$jsonBody !== null) {
            return self::$jsonBody;
        }
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return self::$jsonBody = [];
        }
        $data = json_decode($raw, true);
        return self::$jsonBody = is_array($data) ? $data : [];
    }
}
