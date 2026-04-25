<?php
declare(strict_types=1);

namespace Sunflower\Http;

final class Response
{
    /**
     * Send a JSON response and exit. Always terminates the request.
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
            foreach ($headers as $k => $v) {
                header("$k: $v");
            }
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(mixed $data = ['ok' => true]): never
    {
        self::json($data, 200);
    }

    public static function created(mixed $data): never
    {
        self::json($data, 201);
    }

    public static function notFound(string $msg = 'not_found'): never
    {
        self::json(['error' => $msg], 404);
    }

    public static function badRequest(string $msg = 'bad_request', array $details = []): never
    {
        self::json(['error' => $msg, 'details' => $details], 400);
    }

    public static function methodNotAllowed(string $allowed = 'GET'): never
    {
        self::json(['error' => 'method_not_allowed'], 405, ['Allow' => $allowed]);
    }
}
