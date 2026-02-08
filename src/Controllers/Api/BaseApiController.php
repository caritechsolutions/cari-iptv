<?php
/**
 * CARI-IPTV Base API Controller
 * Common helpers for all API controllers
 */

namespace CariIPTV\Controllers\Api;

class BaseApiController
{
    /**
     * Send a JSON success response with versioning headers
     */
    protected function ok(mixed $data, array $meta = []): void
    {
        $meta['server_time'] = gmdate('Y-m-d\TH:i:s\Z');
        $meta['api_version'] = '1.0';

        $response = [
            'data' => $data,
            'meta' => $meta,
        ];

        // Set ETag if version is provided
        if (!empty($meta['version'])) {
            $etag = '"' . $meta['version'] . '"';
            header('ETag: ' . $etag);

            // Check If-None-Match for 304
            $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
            if ($ifNoneMatch === $etag) {
                http_response_code(304);
                exit;
            }
        }

        $this->json($response, 200);
    }

    /**
     * Send a JSON error response
     */
    protected function error(string $message, int $status = 400, string $code = 'BAD_REQUEST'): void
    {
        $this->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    /**
     * Send a 404 error
     */
    protected function notFound(string $message = 'Resource not found'): void
    {
        $this->error($message, 404, 'NOT_FOUND');
    }

    /**
     * Output JSON with CORS headers
     */
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);

        // CORS headers for player access
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, If-None-Match');
        header('Access-Control-Expose-Headers: ETag, X-Content-Version');

        // Default cache: 60 seconds for content, players can override via manifest
        if ($status === 200) {
            header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
        } else {
            header('Cache-Control: no-cache');
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) {
            error_log('JSON encode failed: ' . json_last_error_msg() . ' — data keys: ' . implode(',', array_keys($data)));
            $json = json_encode(['error' => ['code' => 'ENCODE_ERROR', 'message' => 'Response encoding failed']]);
        }
        echo $json;
        exit;
    }

    /**
     * Get query parameter
     */
    protected function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get multiple query parameters with defaults
     */
    protected function queryFilters(array $defaults): array
    {
        $filters = [];
        foreach ($defaults as $key => $default) {
            $filters[$key] = $_GET[$key] ?? $default;
        }
        return $filters;
    }

    /**
     * Handle OPTIONS preflight requests
     */
    protected function handleCors(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, If-None-Match');
            header('Access-Control-Max-Age: 86400');
            http_response_code(204);
            exit;
        }
    }
}
