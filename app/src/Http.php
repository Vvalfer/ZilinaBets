<?php

declare(strict_types=1);

namespace CasinoApp;

/**
 * Tiny request/response helpers for the JSON API.
 */
final class Http
{
    /** Decode the JSON request body into an array (empty array if none/invalid). */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /** Emit a JSON response with the given HTTP status and stop. */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Emit a JSON error envelope and stop. */
    public static function error(string $message, int $status = 400, string $code = 'error'): void
    {
        self::json(['error' => $code, 'message' => $message], $status);
    }
}
