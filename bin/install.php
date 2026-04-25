#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Installer.
 *
 *   php bin/install.php                       # interactive
 *   php bin/install.php --user=admin --pass=secret123 --display="Admin"
 *   php bin/install.php --skip-seed           # schema + admin only
 *   php bin/install.php --schema-only         # tables only, no admin
 *
 * Idempotent: safe to re-run. Uses CREATE TABLE IF NOT EXISTS and
 * INSERT IGNORE so no data is overwritten.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

$root = dirname(__DIR__);
require $root . '/src/Bootstrap.php';

\Sunflower\Bootstrap::init($root);

use Sunflower\Db;

// ---------- Tiny CLI helpers ----------
$opts = getopt('', ['user:', 'pass:', 'display:', 'skip-seed', 'schema-only', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage:\n  php bin/install.php [--user=NAME --pass=PWD --display=\"Name\"] [--skip-seed] [--schema-only]\n");
    exit(0);
}

function out(string $msg, string $kind = 'info'): void
{
    $color = match ($kind) {
        'ok'    => "\033[32m",
        'warn'  => "\033[33m",
        'err'   => "\033[31m",
        'head'  => "\033[36m",
        default => "\033[0m",
    };
    fwrite(STDOUT, $color . $msg . "\033[0m\n");
}

function ask(string $prompt, ?string $default = null): string
{
    $hint = $default !== null ? " [$default]" : '';
    fwrite(STDOUT, $prompt . $hint . ': ');
    $line = trim((string) fgets(STDIN));
    return $line === '' && $default !== null ? $default : $line;
}

function askPassword(string $prompt): string
{
    fwrite(STDOUT, $prompt . ': ');
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
        $pw = trim((string) fgets(STDIN));
        system('stty echo');
        fwrite(STDOUT, "\n");
    } else {
        $pw = trim((string) fgets(STDIN));
    }
    return $pw;
}

// ---------- 1. Verify DB connection ----------
out('› Checking database connection...', 'head');
try {
    Db::pdo();
    out('  ✓ Connected.', 'ok');
} catch (\Throwable $e) {
    out('  ✗ Could not connect to MySQL.', 'err');
    out('    Check DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS in your .env', 'err');
    out('    (or App-Level Environment Variables on DigitalOcean App Platform).', 'err');
    out('    Error: ' . $e->getMessage(), 'err');
    exit(1);
}

// ---------- 2. Run schema ----------
out('› Creating tables (if missing)...', 'head');
$schema = file_get_contents($root . '/sql/schema.sql');
runMultiSql($schema);
out('  ✓ Schema applied.', 'ok');

// ---------- 3. Seed (unless --skip-seed) ----------
if (!isset($opts['skip-seed']) && !isset($opts['schema-only'])) {
    out('› Seeding categories, products and default settings...', 'head');
    $seed = file_get_contents($root . '/sql/seed.sql');
    runMultiSql($seed);
    $cats  = (int) Db::one('SELECT COUNT(*) c FROM categories')['c'];
    $prods = (int) Db::one('SELECT COUNT(*) c FROM products')['c'];
    out("  ✓ {$cats} categories, {$prods} products in DB.", 'ok');
}

// ---------- 4. Admin user ----------
if (!isset($opts['schema-only'])) {
    out('› Setting up admin user...', 'head');

    $existing = (int) Db::one('SELECT COUNT(*) c FROM users WHERE role = "admin"')['c'];
    if ($existing > 0) {
        out("  ⓘ {$existing} admin user(s) already exist; skipping.", 'warn');
        out("    To reset a password, run:  php bin/install.php --user=USERNAME --pass=NEWPWD", 'warn');
    }

    $username = $opts['user']    ?? ask('  Admin username', 'admin');
    $password = $opts['pass']    ?? askPassword('  Admin password (8+ chars)');
    $display  = $opts['display'] ?? ask('  Display name', 'Administrator');

    if (mb_strlen($password) < 8) {
        out('  ✗ Password must be at least 8 characters.', 'err');
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Upsert by username
    $row = Db::one('SELECT id FROM users WHERE username = :u', [':u' => $username]);
    if ($row) {
        Db::exec(
            'UPDATE users SET password_hash = :h, display_name = :d, role = "admin", is_active = 1 WHERE id = :id',
            [':h' => $hash, ':d' => $display, ':id' => $row['id']]
        );
        out("  ✓ Updated existing user '{$username}'.", 'ok');
    } else {
        Db::exec(
            'INSERT INTO users (username, password_hash, display_name, role) VALUES (:u, :h, :d, "admin")',
            [':u' => $username, ':h' => $hash, ':d' => $display]
        );
        out("  ✓ Created admin user '{$username}'.", 'ok');
    }
}

out('', 'info');
out('Done. You can now visit your site and sign in.', 'ok');
out('Local dev:    http://localhost:8000/login.php', 'info');
exit(0);

/**
 * Execute a script that may contain many statements.
 * MySQL's PDO can't run multi-queries safely with prepared
 * statements, so we split on `;` at end of line. Comments
 * starting with `--` are ignored.
 */
function runMultiSql(string $sql): void
{
    $clean = preg_replace('/^--.*$/m', '', $sql);
    $stmts = preg_split('/;\s*\n/', (string) $clean);
    foreach ($stmts as $s) {
        $s = trim($s);
        if ($s === '') continue;
        Db::pdo()->exec($s);
    }
}
