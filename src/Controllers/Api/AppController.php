<?php
/**
 * CARI-IPTV App API Controller
 * Public endpoints for app configuration: layouts, navigation, pages
 */

namespace CariIPTV\Controllers\Api;

use CariIPTV\Services\ContentApiService;

class AppController extends BaseApiController
{
    private ContentApiService $service;

    public function __construct()
    {
        $this->service = new ContentApiService();
    }

    /**
     * GET /api/v1/app/layout/{platform}
     * ?id=X — fetch specific layout by ID (from page link)
     * Returns the published default layout for a platform with all sections and resolved content
     */
    public function layout(string $platform): void
    {
        $allowed = ['web', 'mobile', 'tv', 'stb'];
        if (!in_array($platform, $allowed)) {
            $this->error('Invalid platform. Use: ' . implode(', ', $allowed), 400, 'INVALID_PLATFORM');
            return;
        }

        // If a specific layout ID is requested (from page → layout link), fetch it directly
        $layoutId = !empty($_GET['id']) ? (int) $_GET['id'] : null;
        if ($layoutId) {
            $layout = $this->service->getLayoutById($layoutId);
        } else {
            $layout = $this->service->getLayout($platform);
        }

        if (!$layout) {
            $msg = $layoutId
                ? "Layout #{$layoutId} not found"
                : "No default published layout for platform: {$platform}";
            $this->notFound($msg);
            return;
        }

        // Longer cache for layouts (5 min) - they change less frequently
        header('Cache-Control: public, max-age=300, stale-while-revalidate=600');

        $this->ok($layout, [
            'version' => md5($layout['updated_at'] ?? ''),
            'platform' => $platform,
        ]);
    }

    /**
     * GET /api/v1/app/navigation/{platform}
     * ?position=main|footer|sidebar|top
     * Returns navigation menu for a platform and position
     */
    public function navigation(string $platform): void
    {
        $allowed = ['web', 'mobile', 'tv', 'stb'];
        if (!in_array($platform, $allowed)) {
            $this->error('Invalid platform. Use: ' . implode(', ', $allowed), 400, 'INVALID_PLATFORM');
            return;
        }

        $position = $this->query('position', 'main');
        $nav = $this->service->getNavigation($platform, $position);

        if (!$nav) {
            $this->notFound("No navigation found for {$platform}/{$position}");
            return;
        }

        header('Cache-Control: public, max-age=300, stale-while-revalidate=600');

        $this->ok($nav, [
            'platform' => $platform,
            'position' => $position,
        ]);
    }

    /**
     * GET /api/v1/app/pages/{platform}
     * Returns all active pages for a platform
     */
    public function pages(string $platform): void
    {
        $allowed = ['web', 'mobile', 'tv', 'stb'];
        if (!in_array($platform, $allowed)) {
            $this->error('Invalid platform. Use: ' . implode(', ', $allowed), 400, 'INVALID_PLATFORM');
            return;
        }

        $pages = $this->service->getPages($platform);

        header('Cache-Control: public, max-age=300, stale-while-revalidate=600');

        $this->ok($pages, [
            'platform' => $platform,
            'total' => count($pages),
        ]);
    }

    /**
     * GET /api/v1/app/config/{platform}
     * Returns complete app configuration: navigation + pages + layout in one request.
     * Reduces initial load from 3 requests to 1.
     */
    public function config(string $platform): void
    {
        $allowed = ['web', 'mobile', 'tv', 'stb'];
        if (!in_array($platform, $allowed)) {
            $this->error('Invalid platform. Use: ' . implode(', ', $allowed), 400, 'INVALID_PLATFORM');
            return;
        }

        $config = [
            'navigation' => $this->service->getNavigation($platform, 'main'),
            'pages' => $this->service->getPages($platform),
            'layout' => $this->service->getLayout($platform),
        ];

        header('Cache-Control: public, max-age=300, stale-while-revalidate=600');

        $this->ok($config, [
            'platform' => $platform,
        ]);
    }
}
