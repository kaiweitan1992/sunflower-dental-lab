<?php
declare(strict_types=1);

namespace Sunflower;

/**
 * One-stop init for both the SPA shell and the API entry point.
 * Loads config, configures errors/timezone, starts a hardened session.
 */
final class Bootstrap
{
    public static function init(string $rootDir): void
    {
        // 1. Composer autoloader, with PSR-4 fallback if vendor/ is missing.
        $autoload = $rootDir . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        } else {
            spl_autoload_register(function (string $class) use ($rootDir): void {
                $prefix = 'Sunflower\\';
                if (!str_starts_with($class, $prefix)) {
                    return;
                }
                $rel = substr($class, strlen($prefix));
                $file = $rootDir . '/src/' . str_replace('\\', '/', $rel) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            });
        }

        // 2. Env vars
        Config::load($rootDir);

        // 3. Errors
        $debug = Config::bool('APP_DEBUG', false);
        error_reporting(E_ALL);
        ini_set('display_errors',  $debug ? '1' : '0');
        ini_set('log_errors', '1');

        // 4. Timezone (stored as UTC in DB, displayed in business TZ)
        date_default_timezone_set(Config::get('APP_TZ', 'UTC') ?: 'UTC');

        // 5. Hardened session
        self::startSession();
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = Config::bool('COOKIE_SECURE', false);

        // Use a DB-backed session handler so sessions work across
        // multiple App Platform containers (filesystem is per-container).
        session_set_save_handler(new \Sunflower\Session\DbSessionHandler(), true);

        session_name(Config::get('SESSION_NAME', 'sfdl_sess'));
        session_set_cookie_params([
            'lifetime' => Config::int('SESSION_LIFETIME', 43200),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) Config::int('SESSION_LIFETIME', 43200));

        session_start();
    }
}
