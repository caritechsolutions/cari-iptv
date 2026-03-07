<?php
/**
 * CARI-IPTV App Layout Service
 * Business logic for app layout builder (home screen management)
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class AppLayoutService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ========================================================================
    // SECTION TYPE REGISTRY
    // ========================================================================

    /**
     * Get available section types with metadata
     */
    public function getSectionTypes(): array
    {
        return [
            'hero_slideshow' => [
                'name' => 'Hero Slideshow',
                'description' => 'Full-width featured content billboard with auto-rotation',
                'icon' => 'lucide-image',
                'category' => 'featured',
                'max_per_layout' => 1,
                'supports_items' => true,
                'default_settings' => [
                    'auto_rotate' => true,
                    'interval' => 8,
                    'show_description' => true,
                    'show_play_button' => true,
                    'show_info_button' => true,
                    'height' => 'large',
                ],
            ],
            'content_row' => [
                'name' => 'Content Row',
                'description' => 'Horizontal scrollable rail of content cards',
                'icon' => 'lucide-rows-3',
                'category' => 'content',
                'max_per_layout' => 20,
                'supports_items' => true,
                'default_settings' => [
                    'source' => 'curated',
                    'content_type' => 'movie',
                    'card_style' => 'poster',
                    'max_items' => 20,
                    'auto_scroll' => false,
                    'category_id' => null,
                    'sort_by' => 'added',
                ],
            ],
            'live_now' => [
                'name' => 'Live Now',
                'description' => 'EPG-powered strip showing currently airing programmes',
                'icon' => 'lucide-radio',
                'category' => 'live',
                'max_per_layout' => 2,
                'supports_items' => false,
                'default_settings' => [
                    'max_channels' => 10,
                    'show_progress' => true,
                    'show_next' => true,
                    'category_id' => null,
                ],
            ],
            'epg_schedule' => [
                'name' => 'TV Guide',
                'description' => 'Mini programme guide grid',
                'icon' => 'lucide-calendar-clock',
                'category' => 'live',
                'max_per_layout' => 1,
                'supports_items' => false,
                'default_settings' => [
                    'hours_ahead' => 3,
                    'max_channels' => 8,
                    'category_id' => null,
                ],
            ],
            'banner' => [
                'name' => 'Promo Banner',
                'description' => 'Promotional image banner with optional link',
                'icon' => 'lucide-megaphone',
                'category' => 'promotional',
                'max_per_layout' => 5,
                'supports_items' => false,
                'default_settings' => [
                    'image_url' => '',
                    'link_url' => '',
                    'link_type' => 'url',
                    'aspect_ratio' => '21:9',
                ],
            ],
            'category_grid' => [
                'name' => 'Category Grid',
                'description' => 'Browse-by-genre grid with thumbnails',
                'icon' => 'lucide-grid-3x3',
                'category' => 'navigation',
                'max_per_layout' => 2,
                'supports_items' => false,
                'default_settings' => [
                    'content_type' => 'all',
                    'columns' => 4,
                    'max_items' => 12,
                    'show_count' => true,
                ],
            ],
            'channel_grid' => [
                'name' => 'Channel Grid',
                'description' => 'Featured channels in a grid layout',
                'icon' => 'lucide-tv',
                'category' => 'live',
                'max_per_layout' => 3,
                'supports_items' => true,
                'default_settings' => [
                    'source' => 'popular',
                    'columns' => 5,
                    'max_items' => 15,
                    'show_now_playing' => true,
                    'category_id' => null,
                ],
            ],
            'continue_watching' => [
                'name' => 'Continue Watching',
                'description' => 'Personalized resume row for the logged-in user',
                'icon' => 'lucide-play-circle',
                'category' => 'personalized',
                'max_per_layout' => 1,
                'supports_items' => false,
                'default_settings' => [
                    'max_items' => 10,
                    'card_style' => 'backdrop',
                    'show_progress' => true,
                ],
            ],
            'spotlight' => [
                'name' => 'Spotlight',
                'description' => 'Single featured content item with details',
                'icon' => 'lucide-star',
                'category' => 'featured',
                'max_per_layout' => 3,
                'supports_items' => true,
                'default_settings' => [
                    'style' => 'card',
                    'show_trailer' => true,
                    'show_description' => true,
                ],
            ],
            'text_divider' => [
                'name' => 'Section Divider',
                'description' => 'Heading text or separator between sections',
                'icon' => 'lucide-minus',
                'category' => 'utility',
                'max_per_layout' => 10,
                'supports_items' => false,
                'default_settings' => [
                    'text' => '',
                    'style' => 'heading',
                    'alignment' => 'left',
                ],
            ],
            'packages_list' => [
                'name' => 'Packages List',
                'description' => 'Display available subscription packages with pricing',
                'icon' => 'lucide-credit-card',
                'category' => 'subscription',
                'max_per_layout' => 1,
                'supports_items' => false,
                'default_settings' => [
                    'layout' => 'cards',
                    'show_features' => true,
                    'show_pricing' => true,
                    'highlight_featured' => true,
                    'filter_platform' => true,
                    'filter_geo' => true,
                ],
            ],
            'recommended_for_you' => [
                'name' => 'Recommended For You',
                'description' => 'AI-powered personalized content recommendations based on viewing history',
                'icon' => 'lucide-sparkles',
                'category' => 'personalized',
                'max_per_layout' => 1,
                'supports_items' => false,
                'default_settings' => [
                    'card_style' => 'poster',
                    'max_items' => 15,
                    'show_match_percent' => true,
                ],
            ],
            'because_you_watched' => [
                'name' => 'Because You Watched',
                'description' => 'Personalized rows of similar content based on recently watched items',
                'icon' => 'lucide-history',
                'category' => 'personalized',
                'max_per_layout' => 3,
                'supports_items' => false,
                'default_settings' => [
                    'card_style' => 'poster',
                    'max_items' => 10,
                    'max_sets' => 3,
                    'show_match_percent' => false,
                ],
            ],
            'trending_now' => [
                'name' => 'Trending Now',
                'description' => 'Currently popular content across all viewers',
                'icon' => 'lucide-trending-up',
                'category' => 'personalized',
                'max_per_layout' => 1,
                'supports_items' => false,
                'default_settings' => [
                    'card_style' => 'backdrop',
                    'max_items' => 15,
                ],
            ],
            'top_picks' => [
                'name' => 'Top Picks For You',
                'description' => 'Highest rated content matching your taste profile',
                'icon' => 'lucide-award',
                'category' => 'personalized',
                'max_per_layout' => 1,
                'supports_items' => false,
                'default_settings' => [
                    'card_style' => 'poster',
                    'max_items' => 15,
                    'show_match_percent' => true,
                ],
            ],
            'hidden_gems' => [
                'name' => 'Hidden Gems',
                'description' => 'Highly rated content that deserves more attention',
                'icon' => 'lucide-gem',
                'category' => 'personalized',
                'max_per_layout' => 1,
                'supports_items' => false,
                'default_settings' => [
                    'card_style' => 'poster',
                    'max_items' => 10,
                ],
            ],
        ];
    }

    // ========================================================================
    // LAYOUT CRUD
    // ========================================================================

    /**
     * Get all layouts with optional filters
     */
    public function getLayouts(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['platform'])) {
            $where[] = 'l.platform = ?';
            $params[] = $filters['platform'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'l.status = ?';
            $params[] = $filters['status'];
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT l.*,
                    (SELECT COUNT(*) FROM app_layout_sections s WHERE s.layout_id = l.id) as section_count,
                    u1.first_name as created_by_name,
                    u2.first_name as updated_by_name
             FROM app_layouts l
             LEFT JOIN admin_users u1 ON l.created_by = u1.id
             LEFT JOIN admin_users u2 ON l.updated_by = u2.id
             WHERE {$whereClause}
             ORDER BY l.platform, l.is_default DESC, l.updated_at DESC",
            $params
        );
    }

    /**
     * Get a single layout by ID
     */
    public function getLayout(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT l.*,
                    u1.first_name as created_by_name,
                    u2.first_name as updated_by_name
             FROM app_layouts l
             LEFT JOIN admin_users u1 ON l.created_by = u1.id
             LEFT JOIN admin_users u2 ON l.updated_by = u2.id
             WHERE l.id = ?",
            [$id]
        );
    }

    /**
     * Create a new layout
     */
    public function createLayout(array $data): int
    {
        return $this->db->insert('app_layouts', [
            'name' => $data['name'],
            'platform' => $data['platform'],
            'status' => $data['status'] ?? 'draft',
            'is_default' => $data['is_default'] ?? 0,
            'schedule_start' => $data['schedule_start'] ?? null,
            'schedule_end' => $data['schedule_end'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
        ]);
    }

    /**
     * Update a layout
     */
    public function updateLayout(int $id, array $data): void
    {
        $fields = [];
        if (isset($data['name'])) $fields['name'] = $data['name'];
        if (isset($data['platform'])) $fields['platform'] = $data['platform'];
        if (isset($data['status'])) $fields['status'] = $data['status'];
        if (array_key_exists('is_default', $data)) $fields['is_default'] = $data['is_default'];
        if (array_key_exists('schedule_start', $data)) $fields['schedule_start'] = $data['schedule_start'];
        if (array_key_exists('schedule_end', $data)) $fields['schedule_end'] = $data['schedule_end'];
        if (isset($data['updated_by'])) $fields['updated_by'] = $data['updated_by'];

        if (!empty($fields)) {
            $this->db->update('app_layouts', $fields, 'id = ?', [$id]);
        }
    }

    /**
     * Delete a layout and all its sections/items (cascade)
     */
    public function deleteLayout(int $id): void
    {
        $this->db->delete('app_layouts', 'id = ?', [$id]);
    }

    /**
     * Duplicate a layout with all sections and items
     */
    public function duplicateLayout(int $id, int $userId): ?int
    {
        $layout = $this->getLayout($id);
        if (!$layout) return null;

        $newId = $this->createLayout([
            'name' => $layout['name'] . ' (Copy)',
            'platform' => $layout['platform'],
            'status' => 'draft',
            'is_default' => 0,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $sections = $this->getSections($id);
        foreach ($sections as $section) {
            $newSectionId = $this->addSection($newId, [
                'section_type' => $section['section_type'],
                'title' => $section['title'],
                'settings' => $section['settings'],
                'sort_order' => $section['sort_order'],
                'is_active' => $section['is_active'],
            ]);

            $items = $this->getItems($section['id']);
            foreach ($items as $item) {
                $this->addItem($newSectionId, [
                    'content_type' => $item['content_type'],
                    'content_id' => $item['content_id'],
                    'settings' => $item['settings'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => $item['is_active'],
                ]);
            }
        }

        return $newId;
    }

    /**
     * Publish a layout (set default for platform, archive previous)
     */
    public function publishLayout(int $id, int $userId): bool
    {
        $layout = $this->getLayout($id);
        if (!$layout) return false;

        $this->db->beginTransaction();
        try {
            // Unset current default for this platform
            $this->db->execute(
                "UPDATE app_layouts SET is_default = 0 WHERE platform = ? AND is_default = 1",
                [$layout['platform']]
            );

            // Publish this one and set as default
            $this->db->update('app_layouts', [
                'status' => 'published',
                'is_default' => 1,
                'updated_by' => $userId,
            ], 'id = ?', [$id]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Unpublish a layout (set back to draft)
     */
    public function unpublishLayout(int $id, int $userId): bool
    {
        $layout = $this->getLayout($id);
        if (!$layout) return false;

        $wasDefault = (bool) $layout['is_default'];

        $this->db->update('app_layouts', [
            'status' => 'draft',
            'is_default' => 0,
            'updated_by' => $userId,
        ], 'id = ?', [$id]);

        // If this was the default, promote the next published layout
        if ($wasDefault) {
            $next = $this->db->fetch(
                "SELECT id FROM app_layouts WHERE platform = ? AND status = 'published' AND id != ? ORDER BY updated_at DESC LIMIT 1",
                [$layout['platform'], $id]
            );
            if ($next) {
                $this->db->update('app_layouts', ['is_default' => 1], 'id = ?', [$next['id']]);
            }
        }

        return true;
    }

    // ========================================================================
    // SECTION CRUD
    // ========================================================================

    /**
     * Get all sections for a layout, ordered
     */
    public function getSections(int $layoutId): array
    {
        $sections = $this->db->fetchAll(
            "SELECT * FROM app_layout_sections WHERE layout_id = ? ORDER BY sort_order ASC",
            [$layoutId]
        );

        foreach ($sections as &$section) {
            $section['settings'] = json_decode($section['settings'], true) ?? [];
            $section['item_count'] = (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM app_layout_items WHERE section_id = ?",
                [$section['id']]
            );
        }

        return $sections;
    }

    /**
     * Get a single section
     */
    public function getSection(int $id): ?array
    {
        $section = $this->db->fetch(
            "SELECT * FROM app_layout_sections WHERE id = ?",
            [$id]
        );

        if ($section) {
            $section['settings'] = json_decode($section['settings'], true) ?? [];
        }

        return $section;
    }

    /**
     * Add a section to a layout
     */
    public function addSection(int $layoutId, array $data): int
    {
        $sortOrder = $data['sort_order'] ?? $this->getNextSortOrder($layoutId);
        $settings = $data['settings'] ?? [];

        if (is_array($settings)) {
            $settings = json_encode($settings);
        }

        return $this->db->insert('app_layout_sections', [
            'layout_id' => $layoutId,
            'section_type' => $data['section_type'],
            'title' => $data['title'] ?? null,
            'settings' => $settings,
            'sort_order' => $sortOrder,
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    /**
     * Update a section
     */
    public function updateSection(int $id, array $data): void
    {
        $fields = [];
        if (isset($data['title'])) $fields['title'] = $data['title'];
        if (array_key_exists('is_active', $data)) $fields['is_active'] = $data['is_active'];
        if (isset($data['sort_order'])) $fields['sort_order'] = $data['sort_order'];

        if (isset($data['settings'])) {
            $fields['settings'] = is_array($data['settings'])
                ? json_encode($data['settings'])
                : $data['settings'];
        }

        if (!empty($fields)) {
            $this->db->update('app_layout_sections', $fields, 'id = ?', [$id]);
        }
    }

    /**
     * Delete a section
     */
    public function deleteSection(int $id): void
    {
        $this->db->delete('app_layout_sections', 'id = ?', [$id]);
    }

    /**
     * Reorder sections for a layout
     */
    public function reorderSections(int $layoutId, array $sectionIds): void
    {
        foreach ($sectionIds as $order => $sectionId) {
            $this->db->update(
                'app_layout_sections',
                ['sort_order' => $order],
                'id = ? AND layout_id = ?',
                [(int) $sectionId, $layoutId]
            );
        }
    }

    // ========================================================================
    // ITEM CRUD
    // ========================================================================

    /**
     * Get items for a section
     */
    public function getItems(int $sectionId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM app_layout_items WHERE section_id = ? ORDER BY sort_order ASC",
            [$sectionId]
        );
    }

    /**
     * Add an item to a section
     */
    public function addItem(int $sectionId, array $data): int
    {
        $settings = $data['settings'] ?? null;
        if (is_array($settings)) {
            $settings = json_encode($settings);
        }

        $sortOrder = $data['sort_order'] ?? $this->getNextItemSortOrder($sectionId);

        return $this->db->insert('app_layout_items', [
            'section_id' => $sectionId,
            'content_type' => $data['content_type'],
            'content_id' => $data['content_id'] ?? null,
            'settings' => $settings,
            'sort_order' => $sortOrder,
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    /**
     * Remove an item
     */
    public function removeItem(int $id): void
    {
        $this->db->delete('app_layout_items', 'id = ?', [$id]);
    }

    /**
     * Reorder items in a section
     */
    public function reorderItems(int $sectionId, array $itemIds): void
    {
        foreach ($itemIds as $order => $itemId) {
            $this->db->update(
                'app_layout_items',
                ['sort_order' => $order],
                'id = ? AND section_id = ?',
                [(int) $itemId, $sectionId]
            );
        }
    }

    // ========================================================================
    // CONTENT SEARCH (for picker in builder)
    // ========================================================================

    /**
     * Search content for the content picker
     */
    public function searchContent(string $type, string $query = '', int $limit = 20): array
    {
        $results = [];
        $limit = (int) $limit;

        switch ($type) {
            case 'movie':
                $sql = "SELECT id, title as name, year, poster_url as image,
                               CONCAT(COALESCE(vote_average, ''), '/10') as meta
                        FROM movies WHERE status = 'published'";
                $params = [];
                if ($query) {
                    $sql .= " AND (title LIKE ? OR original_title LIKE ?)";
                    $params[] = "%{$query}%";
                    $params[] = "%{$query}%";
                }
                $sql .= " ORDER BY title LIMIT {$limit}";
                $results = $this->db->fetchAll($sql, $params);
                break;

            case 'series':
                $sql = "SELECT id, title as name, year, poster_url as image
                        FROM series WHERE status = 'published'";
                $params = [];
                if ($query) {
                    $sql .= " AND (title LIKE ? OR original_title LIKE ?)";
                    $params[] = "%{$query}%";
                    $params[] = "%{$query}%";
                }
                $sql .= " ORDER BY title LIMIT {$limit}";
                $results = $this->db->fetchAll($sql, $params);
                break;

            case 'channel':
                $sql = "SELECT id, name, channel_number as meta, logo_url as image
                        FROM channels WHERE 1=1";
                $params = [];
                if ($query) {
                    $sql .= " AND (name LIKE ? OR key_code LIKE ?)";
                    $params[] = "%{$query}%";
                    $params[] = "%{$query}%";
                }
                $sql .= " ORDER BY name LIMIT {$limit}";
                $results = $this->db->fetchAll($sql, $params);
                break;

            case 'category':
                $sql = "SELECT id, name, type as meta
                        FROM categories WHERE is_active = 1";
                $params = [];
                if ($query) {
                    $sql .= " AND name LIKE ?";
                    $params[] = "%{$query}%";
                }
                $sql .= " ORDER BY name LIMIT {$limit}";
                $results = $this->db->fetchAll($sql, $params);
                break;
        }

        return $results;
    }

    /**
     * Resolve content details for items (enrich items with actual data)
     */
    public function resolveItems(array $items): array
    {
        foreach ($items as &$item) {
            if (empty($item['content_id'])) continue;

            switch ($item['content_type']) {
                case 'movie':
                    $item['content'] = $this->db->fetch(
                        "SELECT id, title, year, poster_url, backdrop_url, synopsis as overview, vote_average
                         FROM movies WHERE id = ?",
                        [$item['content_id']]
                    );
                    break;
                case 'series':
                    $item['content'] = $this->db->fetch(
                        "SELECT id, title, year, poster_url, backdrop_url, synopsis as overview, vote_average
                         FROM series WHERE id = ?",
                        [$item['content_id']]
                    );
                    break;
                case 'channel':
                    $item['content'] = $this->db->fetch(
                        "SELECT id, name, logo_url, channel_number
                         FROM channels WHERE id = ?",
                        [$item['content_id']]
                    );
                    break;
                case 'category':
                    $item['content'] = $this->db->fetch(
                        "SELECT id, name, type FROM categories WHERE id = ?",
                        [$item['content_id']]
                    );
                    break;
            }
        }

        return $items;
    }

    /**
     * Get auto-populated items for sections with non-curated sources.
     * Used by content_row and channel_grid when source != 'curated'.
     */
    public function getAutoPopulatedItems(string $sectionType, array $settings): array
    {
        try {
            return $this->doGetAutoPopulatedItems($sectionType, $settings);
        } catch (\Exception $e) {
            error_log('[AppLayoutService] getAutoPopulatedItems error: ' . $e->getMessage());
            return [];
        }
    }

    private function doGetAutoPopulatedItems(string $sectionType, array $settings): array
    {
        $source = $settings['source'] ?? 'curated';
        if ($source === 'curated') {
            return [];
        }

        $maxItems = max(1, (int) ($settings['max_items'] ?? 20));
        $contentType = $settings['content_type'] ?? 'movie';
        $categoryId = !empty($settings['category_id']) ? (int) $settings['category_id'] : null;

        if ($sectionType === 'channel_grid') {
            return $this->getAutoChannels($source, $maxItems, $categoryId);
        }

        // content_row
        $items = [];
        if ($contentType === 'mixed') {
            $half = (int) ceil($maxItems / 2);
            $movies = $this->getAutoMovies($source, $half, $categoryId);
            $series = $this->getAutoSeries($source, $half, $categoryId);
            $items = array_merge($movies, $series);
            usort($items, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));
            $items = array_slice($items, 0, $maxItems);
        } elseif ($contentType === 'series') {
            $items = $this->getAutoSeries($source, $maxItems, $categoryId);
        } else {
            $items = $this->getAutoMovies($source, $maxItems, $categoryId);
        }

        // Transform into the same format as manually added items
        $result = [];
        foreach ($items as $i => $row) {
            $type = $row['_type'] ?? 'movie';
            unset($row['_type'], $row['created_at']);
            $result[] = [
                'id' => 0,
                'content_type' => $type,
                'content_id' => $row['id'],
                'settings' => '{}',
                'sort_order' => $i,
                'content' => $row,
                '_auto' => true,
            ];
        }

        return $result;
    }

    private function getAutoMovies(string $source, int $limit, ?int $categoryId): array
    {
        $where = "status = 'published'";
        $params = [];
        $order = 'created_at DESC';

        if ($categoryId) {
            $where .= " AND category_id = ?";
            $params[] = $categoryId;
        }

        switch ($source) {
            case 'latest':
                $order = 'created_at DESC';
                break;
            case 'popular':
                $order = 'views DESC, created_at DESC';
                break;
            case 'top_rated':
                $order = 'vote_average DESC, created_at DESC';
                break;
            case 'featured':
                $where .= " AND is_featured = 1";
                $order = 'updated_at DESC';
                break;
            case 'category':
                if (!$categoryId) return [];
                $order = 'title ASC';
                break;
        }

        $rows = $this->db->fetchAll(
            "SELECT id, title, year, poster_url, backdrop_url, synopsis as overview,
                    vote_average, created_at
             FROM movies WHERE {$where}
             ORDER BY {$order} LIMIT {$limit}",
            $params
        );

        foreach ($rows as &$r) $r['_type'] = 'movie';
        return $rows;
    }

    private function getAutoSeries(string $source, int $limit, ?int $categoryId): array
    {
        $where = "status = 'published'";
        $params = [];
        $order = 'created_at DESC';

        if ($categoryId) {
            $where .= " AND category_id = ?";
            $params[] = $categoryId;
        }

        switch ($source) {
            case 'latest':
                $order = 'created_at DESC';
                break;
            case 'popular':
                $order = 'views DESC, created_at DESC';
                break;
            case 'top_rated':
                $order = 'vote_average DESC, created_at DESC';
                break;
            case 'featured':
                $where .= " AND is_featured = 1";
                $order = 'updated_at DESC';
                break;
            case 'category':
                if (!$categoryId) return [];
                $order = 'title ASC';
                break;
        }

        $rows = $this->db->fetchAll(
            "SELECT id, title, year, poster_url, backdrop_url, synopsis as overview,
                    vote_average, created_at
             FROM series WHERE {$where}
             ORDER BY {$order} LIMIT {$limit}",
            $params
        );

        foreach ($rows as &$r) $r['_type'] = 'series';
        return $rows;
    }

    private function getAutoChannels(string $source, int $limit, ?int $categoryId): array
    {
        $where = "1=1";
        $params = [];
        $order = 'c.channel_number ASC, c.name ASC';
        $join = '';

        if ($categoryId) {
            $join = " JOIN channel_categories cc ON c.id = cc.channel_id AND cc.category_id = ?";
            $params[] = $categoryId;
        }

        switch ($source) {
            case 'popular':
                $order = 'c.channel_number ASC, c.name ASC';
                break;
            case 'category':
                if (!$categoryId) return [];
                $order = 'c.channel_number ASC, c.name ASC';
                break;
        }

        try {
            $rows = $this->db->fetchAll(
                "SELECT c.id, c.name, c.logo_url, c.channel_number
                 FROM channels c {$join}
                 WHERE {$where}
                 ORDER BY {$order} LIMIT {$limit}",
                $params
            );
        } catch (\Exception $e) {
            error_log('[AppLayoutService] getAutoChannels error: ' . $e->getMessage());
            return [];
        }

        $result = [];
        foreach ($rows as $i => $row) {
            $result[] = [
                'id' => 0,
                'content_type' => 'channel',
                'content_id' => $row['id'],
                'settings' => '{}',
                'sort_order' => $i,
                'content' => $row,
                '_auto' => true,
            ];
        }

        return $result;
    }

    // ========================================================================
    // PAGES
    // ========================================================================

    /**
     * Get available page types with metadata
     */
    public function getPageTypes(): array
    {
        return [
            'home' => ['name' => 'Home', 'icon' => 'lucide-home', 'description' => 'Main landing page', 'has_layout' => true],
            'movies' => ['name' => 'Movies', 'icon' => 'lucide-film', 'description' => 'Movie browsing & listing', 'has_layout' => true],
            'series' => ['name' => 'TV Shows', 'icon' => 'lucide-clapperboard', 'description' => 'TV series browsing & listing', 'has_layout' => true],
            'live_tv' => ['name' => 'Live TV', 'icon' => 'lucide-radio', 'description' => 'Live channel guide & player', 'has_layout' => true],
            'categories' => ['name' => 'Categories', 'icon' => 'lucide-grid-3x3', 'description' => 'Browse by genre/category', 'has_layout' => true],
            'search' => ['name' => 'Search', 'icon' => 'lucide-search', 'description' => 'Content search', 'has_layout' => false],
            'watchlist' => ['name' => 'My List', 'icon' => 'lucide-bookmark', 'description' => 'User watchlist/favourites', 'has_layout' => false],
            'settings' => ['name' => 'Settings', 'icon' => 'lucide-settings', 'description' => 'App settings & preferences', 'has_layout' => false],
            'player' => ['name' => 'Player', 'icon' => 'lucide-play', 'description' => 'Media player page', 'has_layout' => false],
            'details' => ['name' => 'Details', 'icon' => 'lucide-info', 'description' => 'Content detail view', 'has_layout' => false],
            'custom' => ['name' => 'Custom Page', 'icon' => 'lucide-file-plus', 'description' => 'Custom user-defined page', 'has_layout' => true],
            'subscription' => ['name' => 'Subscribe', 'icon' => 'lucide-credit-card', 'description' => 'Package subscription & payment', 'has_layout' => true],
            'profile' => ['name' => 'Profile', 'icon' => 'lucide-user', 'description' => 'User profile & settings', 'has_layout' => false],
        ];
    }

    /**
     * Get pages for a platform
     */
    public function getPages(string $platform): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, l.name as layout_name, l.status as layout_status
             FROM app_pages p
             LEFT JOIN app_layouts l ON p.layout_id = l.id
             WHERE p.platform = ?
             ORDER BY p.sort_order ASC",
            [$platform]
        );
    }

    /**
     * Get a single page
     */
    public function getPage(int $id): ?array
    {
        $page = $this->db->fetch(
            "SELECT p.*, l.name as layout_name, l.status as layout_status
             FROM app_pages p
             LEFT JOIN app_layouts l ON p.layout_id = l.id
             WHERE p.id = ?",
            [$id]
        );

        if ($page && $page['settings']) {
            $page['settings'] = json_decode($page['settings'], true) ?? [];
        }

        return $page;
    }

    /**
     * Create a page
     */
    public function createPage(array $data): int
    {
        $settings = $data['settings'] ?? null;
        if (is_array($settings)) {
            $settings = json_encode($settings);
        }

        return $this->db->insert('app_pages', [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'page_type' => $data['page_type'],
            'platform' => $data['platform'],
            'layout_id' => $data['layout_id'] ?? null,
            'icon' => $data['icon'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'is_system' => $data['is_system'] ?? 0,
            'sort_order' => $data['sort_order'] ?? $this->getNextPageSortOrder($data['platform']),
            'settings' => $settings,
        ]);
    }

    /**
     * Update a page
     */
    public function updatePage(int $id, array $data): void
    {
        $fields = [];
        if (isset($data['name'])) $fields['name'] = $data['name'];
        if (isset($data['slug'])) $fields['slug'] = $data['slug'];
        if (isset($data['icon'])) $fields['icon'] = $data['icon'];
        if (array_key_exists('layout_id', $data)) $fields['layout_id'] = $data['layout_id'];
        if (array_key_exists('is_active', $data)) $fields['is_active'] = (int) $data['is_active'];
        if (isset($data['sort_order'])) $fields['sort_order'] = $data['sort_order'];

        if (isset($data['settings'])) {
            $fields['settings'] = is_array($data['settings'])
                ? json_encode($data['settings'])
                : $data['settings'];
        }

        if (!empty($fields)) {
            $this->db->update('app_pages', $fields, 'id = ?', [$id]);
        }
    }

    /**
     * Delete a page (only non-system pages)
     */
    public function deletePage(int $id): bool
    {
        $page = $this->getPage($id);
        if (!$page || $page['is_system']) return false;

        $this->db->delete('app_pages', 'id = ?', [$id]);
        return true;
    }

    /**
     * Reorder pages
     */
    public function reorderPages(string $platform, array $pageIds): void
    {
        foreach ($pageIds as $order => $pageId) {
            $this->db->update(
                'app_pages',
                ['sort_order' => $order],
                'id = ? AND platform = ?',
                [(int) $pageId, $platform]
            );
        }
    }

    // ========================================================================
    // NAVIGATION
    // ========================================================================

    /**
     * Get navigation menu for a platform and position
     */
    public function getNavigation(string $platform, string $position = 'main'): ?array
    {
        $nav = $this->db->fetch(
            "SELECT * FROM app_navigation WHERE platform = ? AND position = ?",
            [$platform, $position]
        );

        if ($nav) {
            $nav['settings'] = json_decode($nav['settings'] ?? '{}', true) ?? [];
            $nav['items'] = $this->getNavigationItems($nav['id']);
        }

        return $nav;
    }

    /**
     * Get navigation by ID
     */
    public function getNavigationById(int $id): ?array
    {
        $nav = $this->db->fetch(
            "SELECT * FROM app_navigation WHERE id = ?",
            [$id]
        );

        if ($nav) {
            $nav['settings'] = json_decode($nav['settings'] ?? '{}', true) ?? [];
            $nav['items'] = $this->getNavigationItems($nav['id']);
        }

        return $nav;
    }

    /**
     * Get all navigation menus for a platform
     */
    public function getNavigationMenus(string $platform): array
    {
        $menus = $this->db->fetchAll(
            "SELECT * FROM app_navigation WHERE platform = ? ORDER BY position",
            [$platform]
        );

        foreach ($menus as &$menu) {
            $menu['settings'] = json_decode($menu['settings'] ?? '{}', true) ?? [];
            $menu['items'] = $this->getNavigationItems($menu['id']);
        }

        return $menus;
    }

    /**
     * Create or update a navigation menu
     */
    public function saveNavigation(string $platform, string $position, array $data): int
    {
        $existing = $this->db->fetch(
            "SELECT id FROM app_navigation WHERE platform = ? AND position = ?",
            [$platform, $position]
        );

        $settings = $data['settings'] ?? [];
        if (is_array($settings)) {
            $settings = json_encode($settings);
        }

        if ($existing) {
            $this->db->update('app_navigation', [
                'name' => $data['name'] ?? 'Main Navigation',
                'settings' => $settings,
                'is_active' => $data['is_active'] ?? 1,
            ], 'id = ?', [$existing['id']]);
            return (int) $existing['id'];
        }

        return $this->db->insert('app_navigation', [
            'name' => $data['name'] ?? 'Main Navigation',
            'platform' => $platform,
            'position' => $position,
            'settings' => $settings,
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    /**
     * Get navigation items
     */
    public function getNavigationItems(int $navigationId): array
    {
        $items = $this->db->fetchAll(
            "SELECT ni.*, p.name as page_name, p.slug as page_slug, p.page_type, p.is_active as page_active
             FROM app_navigation_items ni
             LEFT JOIN app_pages p ON ni.page_id = p.id
             WHERE ni.navigation_id = ?
             ORDER BY ni.sort_order ASC",
            [$navigationId]
        );

        foreach ($items as &$item) {
            $item['settings'] = json_decode($item['settings'] ?? '{}', true) ?? [];
        }

        return $items;
    }

    /**
     * Add a navigation item
     */
    public function addNavigationItem(int $navigationId, array $data): int
    {
        $settings = $data['settings'] ?? null;
        if (is_array($settings)) {
            $settings = json_encode($settings);
        }

        $sortOrder = $data['sort_order'] ?? $this->getNextNavItemSortOrder($navigationId);

        return $this->db->insert('app_navigation_items', [
            'navigation_id' => $navigationId,
            'page_id' => $data['page_id'] ?? null,
            'label' => $data['label'],
            'icon' => $data['icon'] ?? null,
            'url' => $data['url'] ?? null,
            'target' => $data['target'] ?? 'page',
            'sort_order' => $sortOrder,
            'is_active' => $data['is_active'] ?? 1,
            'settings' => $settings,
        ]);
    }

    /**
     * Update a navigation item
     */
    public function updateNavigationItem(int $id, array $data): void
    {
        $fields = [];
        if (isset($data['label'])) $fields['label'] = $data['label'];
        if (isset($data['icon'])) $fields['icon'] = $data['icon'];
        if (array_key_exists('page_id', $data)) $fields['page_id'] = $data['page_id'];
        if (array_key_exists('url', $data)) $fields['url'] = $data['url'];
        if (isset($data['target'])) $fields['target'] = $data['target'];
        if (array_key_exists('is_active', $data)) $fields['is_active'] = (int) $data['is_active'];

        if (isset($data['settings'])) {
            $fields['settings'] = is_array($data['settings'])
                ? json_encode($data['settings'])
                : $data['settings'];
        }

        if (!empty($fields)) {
            $this->db->update('app_navigation_items', $fields, 'id = ?', [$id]);
        }
    }

    /**
     * Remove a navigation item
     */
    public function removeNavigationItem(int $id): void
    {
        $this->db->delete('app_navigation_items', 'id = ?', [$id]);
    }

    /**
     * Reorder navigation items
     */
    public function reorderNavigationItems(int $navigationId, array $itemIds): void
    {
        foreach ($itemIds as $order => $itemId) {
            $this->db->update(
                'app_navigation_items',
                ['sort_order' => $order],
                'id = ? AND navigation_id = ?',
                [(int) $itemId, $navigationId]
            );
        }
    }

    // ========================================================================
    // STATISTICS
    // ========================================================================

    /**
     * Get layout statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_layouts' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM app_layouts"),
            'published' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM app_layouts WHERE status = 'published'"),
            'drafts' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM app_layouts WHERE status = 'draft'"),
            'platforms' => $this->db->fetchAll(
                "SELECT platform, COUNT(*) as count,
                        SUM(status = 'published') as published
                 FROM app_layouts GROUP BY platform"
            ),
        ];
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    private function getNextSortOrder(int $layoutId): int
    {
        $max = $this->db->fetchColumn(
            "SELECT COALESCE(MAX(sort_order), -1) FROM app_layout_sections WHERE layout_id = ?",
            [$layoutId]
        );
        return ((int) $max) + 1;
    }

    private function getNextItemSortOrder(int $sectionId): int
    {
        $max = $this->db->fetchColumn(
            "SELECT COALESCE(MAX(sort_order), -1) FROM app_layout_items WHERE section_id = ?",
            [$sectionId]
        );
        return ((int) $max) + 1;
    }

    private function getNextPageSortOrder(string $platform): int
    {
        $max = $this->db->fetchColumn(
            "SELECT COALESCE(MAX(sort_order), -1) FROM app_pages WHERE platform = ?",
            [$platform]
        );
        return ((int) $max) + 1;
    }

    private function getNextNavItemSortOrder(int $navigationId): int
    {
        $max = $this->db->fetchColumn(
            "SELECT COALESCE(MAX(sort_order), -1) FROM app_navigation_items WHERE navigation_id = ?",
            [$navigationId]
        );
        return ((int) $max) + 1;
    }
}
