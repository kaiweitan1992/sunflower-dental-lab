<?php
declare(strict_types=1);

namespace Sunflower\Session;

use Sunflower\Db;

/**
 * MySQL-backed session handler so sessions are shared across
 * App Platform containers. Falls back gracefully — uses INSERT
 * ... ON DUPLICATE KEY UPDATE so concurrent writes don't error.
 */
final class DbSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string
    {
        $row = Db::one(
            'SELECT data FROM sessions WHERE id = :id AND expires_at > :now',
            [':id' => $id, ':now' => time()]
        );
        return $row['data'] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $lifetime = (int) ini_get('session.gc_maxlifetime') ?: 43200;
        Db::exec(
            'INSERT INTO sessions (id, data, expires_at, updated_at)
             VALUES (:id, :data, :exp, :now)
             ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at), updated_at = VALUES(updated_at)',
            [':id' => $id, ':data' => $data, ':exp' => time() + $lifetime, ':now' => time()]
        );
        return true;
    }

    public function destroy(string $id): bool
    {
        Db::exec('DELETE FROM sessions WHERE id = :id', [':id' => $id]);
        return true;
    }

    public function gc(int $maxLifetime): int|false
    {
        return Db::exec('DELETE FROM sessions WHERE expires_at < :now', [':now' => time()]);
    }

    public function validateId(string $id): bool
    {
        $row = Db::one(
            'SELECT 1 FROM sessions WHERE id = :id AND expires_at > :now LIMIT 1',
            [':id' => $id, ':now' => time()]
        );
        return $row !== null;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}