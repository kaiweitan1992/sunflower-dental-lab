<?php
declare(strict_types=1);

namespace Sunflower;

/**
 * Lightweight env-config loader. Reads from (in order):
 *   1. real environment variables (DO App Platform sets these)
 *   2. a local .env file in project root (development only)
 *
 * Never use this class to expose env vars to JS or to the browser.
 */
final class Config
{
    private static array $cache = [];
    private static bool  $loaded = false;

    public static function load(string $rootDir): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $envFile = $rootDir . '/.env';
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (!str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                // strip surrounding quotes
                if (strlen($v) >= 2) {
                    $f = $v[0];
                    $l = $v[strlen($v) - 1];
                    if (($f === '"' && $l === '"') || ($f === "'" && $l === "'")) {
                        $v = substr($v, 1, -1);
                    }
                }
                if (getenv($k) === false) {
                    putenv("$k=$v");
                    $_ENV[$k] = $v;
                }
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $v = getenv($key);
        if ($v === false || $v === '') {
            $v = $default;
        }
        return self::$cache[$key] = $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int) $v;
    }
}
