<?php
/**
 * CARI-IPTV Response Helper
 */

namespace CariIPTV\Core;

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        self::json($response, $status);
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        self::redirect($referer);
    }

    public static function view(string $template, array $data = [], string $layout = null): void
    {
        // For admin layout, inject site settings
        if ($layout === 'admin') {
            $data = self::injectSiteSettings($data);
        }

        // Save layout name before extract/include can overwrite it
        $__layoutName = $layout;

        extract($data);

        $templatePath = dirname(__DIR__, 2) . '/templates/' . $template . '.php';

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: $template");
        }

        if ($__layoutName) {
            ob_start();
            include $templatePath;
            $content = ob_get_clean();

            $layoutPath = dirname(__DIR__, 2) . '/templates/layouts/' . $__layoutName . '.php';
            if (file_exists($layoutPath)) {
                include $layoutPath;
            } else {
                echo $content;
            }
        } else {
            include $templatePath;
        }
    }

    /**
     * Inject site settings into view data for admin layout
     */
    private static function injectSiteSettings(array $data): array
    {
        try {
            $settings = new \CariIPTV\Services\SettingsService();
            $data['siteName'] = $settings->get('site_name', 'CARI-IPTV', 'general');
            $data['siteLogo'] = $settings->get('site_logo', '', 'general');
        } catch (\Exception $e) {
            // Use defaults if settings can't be loaded
            $data['siteName'] = $data['siteName'] ?? 'CARI-IPTV';
            $data['siteLogo'] = $data['siteLogo'] ?? '';
        }
        return $data;
    }

    public static function download(string $filePath, string $fileName = null): void
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo 'File not found';
            exit;
        }

        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($filePath);
        exit;
    }

    public static function setHeader(string $name, string $value): void
    {
        header("$name: $value");
    }

    public static function setCookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = true
    ): void {
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax',
        ]);
    }

    public static function deleteCookie(string $name): void
    {
        self::setCookie($name, '', time() - 3600);
    }
}
