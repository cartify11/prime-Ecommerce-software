<?php
/**
 * Prime E Commerce Hub - Standard API Response Formatter
 */

class Response {
    public static function json(array $data, int $statusCode = 200): void {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(string $message, $data = null, array $extra = []): void {
        $response = array_merge([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ], $extra);
        self::json($response, 200);
    }

    public static function error(string $message, int $statusCode = 400, $data = null): void {
        self::json([
            'success' => false,
            'message' => $message,
            'data'    => $data
        ], $statusCode);
    }

    public static function unauthorized(string $message = 'Authentication required'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'You do not have permission to perform this action'): void {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Resource not found'): void {
        self::error($message, 404);
    }
}
