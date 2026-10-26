<?php
/**
 * CARI-IPTV Artwork Upload Controller
 *
 * One AJAX endpoint that lets an admin upload their own cover art for VOD
 * titles. The file goes through exactly the same ImageService pipeline as
 * artwork downloaded from TMDB/Fanart (same 'vod' context, same sizes,
 * same WebP quality), so the apps see no difference between the two.
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\ImageService;

class ArtworkController
{
    /** entity => [table, allowed artwork types => column] */
    private const ENTITIES = [
        'movie'   => ['table' => 'movies',          'types' => ['poster' => 'poster_url', 'backdrop' => 'backdrop_url']],
        'series'  => ['table' => 'series',          'types' => ['poster' => 'poster_url', 'backdrop' => 'backdrop_url']],
        'season'  => ['table' => 'series_seasons',  'types' => ['poster' => 'poster_url']],
        'episode' => ['table' => 'series_episodes', 'types' => ['still' => 'still_url']],
    ];

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * POST /admin/artwork/upload  (multipart)
     *   _token, entity (movie|series|season|episode), id, type (poster|backdrop|still), file
     *
     * With `save=1` the processed path is written to the entity row at once
     * (used by the season and episode modals, which save field by field).
     * Without it the path is returned for the form to submit with the rest
     * of its fields, exactly as a pasted URL would be.
     */
    public function upload(): void
    {
        if (!Session::validateCsrf($_POST['_token'] ?? '')) {
            Response::json(['success' => false, 'message' => 'Invalid request'], 403);
            return;
        }

        $entity = $_POST['entity'] ?? '';
        $type   = $_POST['type'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);

        if (!isset(self::ENTITIES[$entity])) {
            Response::json(['success' => false, 'message' => 'Unknown entity'], 400);
            return;
        }
        $spec = self::ENTITIES[$entity];
        if (!isset($spec['types'][$type])) {
            Response::json(['success' => false, 'message' => 'Unknown artwork type for ' . $entity], 400);
            return;
        }
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::json(['success' => false, 'message' => 'No image file uploaded'], 400);
            return;
        }

        // Same storage key the downloader uses for this entity (see
        // MovieService/SeriesService::process*Images), so a later metadata
        // refresh and a manual upload overwrite each other cleanly.
        $seriesId = (int) ($_POST['series_id'] ?? 0);
        $entityKey = match ($entity) {
            'movie', 'series' => $id ?: ('new_' . time()),
            'season'          => $id ? "s{$seriesId}_season{$id}" : "s{$seriesId}_season_new_" . time(),
            'episode'         => $id ? "s{$seriesId}_ep{$id}"     : "s{$seriesId}_ep_new_" . time(),
        };

        $imageService = new ImageService();
        $result = $imageService->processUpload($_FILES['file'], 'vod', $entityKey, $type);

        if (!$result['success']) {
            Response::json(['success' => false, 'message' => $result['error'] ?? 'Image processing failed'], 422);
            return;
        }

        // Pick the variant the apps read for this type; stills use the backdrop ratio.
        $variantKey = $type === 'still' ? 'backdrop' : $type;
        $url = $result['variants'][$variantKey] ?? ($result['base_path'] . '_' . $variantKey . '.webp');

        if (!empty($_POST['save']) && $id > 0) {
            $this->db->update($spec['table'], [$spec['types'][$type] => $url], 'id = ?', [$id]);
        }

        // Cache-bust the preview; the stored path stays clean.
        Response::json([
            'success'     => true,
            'url'         => $url,
            'preview_url' => $url . '?v=' . time(),
            'variants'    => $result['variants'],
        ]);
    }
}
