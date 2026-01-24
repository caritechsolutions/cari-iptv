<?php
/**
 * CARI-IPTV Settings Service
 * Manages system configuration stored in database
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class SettingsService
{
    private Database $db;
    private static array $cache = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get a single setting value
     */
    public function get(string $key, mixed $default = null, string $group = 'general'): mixed
    {
        $cacheKey = "{$group}.{$key}";

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $setting = $this->db->fetch(
            "SELECT value, type FROM settings WHERE `group` = ? AND `key` = ?",
            [$group, $key]
        );

        if (!$setting) {
            return $default;
        }

        $value = $this->castValue($setting['value'], $setting['type']);
        self::$cache[$cacheKey] = $value;

        return $value;
    }

    /**
     * Set a single setting value
     */
    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        $stringValue = is_array($value) ? json_encode($value) : (string) $value;

        $existing = $this->db->fetch(
            "SELECT id FROM settings WHERE `group` = ? AND `key` = ?",
            [$group, $key]
        );

        if ($existing) {
            $this->db->update('settings', [
                'value' => $stringValue,
            ], '`group` = ? AND `key` = ?', [$group, $key]);
        } else {
            $type = is_array($value) ? 'json' : (is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string'));
            $this->db->insert('settings', [
                'group' => $group,
                'key' => $key,
                'value' => $stringValue,
                'type' => $type,
            ]);
        }

        // Clear cache
        $cacheKey = "{$group}.{$key}";
        unset(self::$cache[$cacheKey]);
    }

    /**
     * Get all settings in a group
     */
    public function getGroup(string $group): array
    {
        $settings = $this->db->fetchAll(
            "SELECT `key`, value, type FROM settings WHERE `group` = ?",
            [$group]
        );

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['key']] = $this->castValue($setting['value'], $setting['type']);
        }

        return $result;
    }

    /**
     * Set multiple settings at once
     */
    public function setMany(array $settings, string $group = 'general'): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value, $group);
        }
    }

    /**
     * Get all settings grouped
     */
    public function getAll(): array
    {
        $settings = $this->db->fetchAll(
            "SELECT `group`, `key`, value, type FROM settings ORDER BY `group`, `key`"
        );

        $result = [];
        foreach ($settings as $setting) {
            if (!isset($result[$setting['group']])) {
                $result[$setting['group']] = [];
            }
            $result[$setting['group']][$setting['key']] = $this->castValue($setting['value'], $setting['type']);
        }

        return $result;
    }

    /**
     * Cast value to proper type
     */
    private function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => (bool) (int) $value,
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Clear settings cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
