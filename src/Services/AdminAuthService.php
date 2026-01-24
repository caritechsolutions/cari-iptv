<?php
/**
 * CARI-IPTV Admin Authentication Service
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;
use CariIPTV\Core\Session;

class AdminAuthService
{
    private Database $db;
    private array $config;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->config = require dirname(__DIR__) . '/Config/app.php';
    }

    /**
     * Attempt admin login
     */
    public function login(string $username, string $password, bool $remember = false): array
    {
        // Check for lockout
        if ($this->isLockedOut($username)) {
            $lockoutMinutes = ceil($this->config['security']['lockout_duration'] / 60);
            return [
                'success' => false,
                'message' => "Account temporarily locked. Try again in {$lockoutMinutes} minutes.",
            ];
        }

        // Find user by username or email
        $user = $this->db->fetch(
            "SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt($username);
            return [
                'success' => false,
                'message' => 'Invalid username or password.',
            ];
        }

        // Clear failed attempts
        $this->clearFailedAttempts($username);

        // Update last login info
        $this->db->update('admin_users', [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ], 'id = ?', [$user['id']]);

        // Create session
        Session::regenerate();
        Session::set('admin_id', $user['id']);
        Session::set('admin_username', $user['username']);
        Session::set('admin_role', $user['role']);
        Session::set('admin_name', trim($user['first_name'] . ' ' . $user['last_name']));
        Session::set('admin_login_time', time());

        // Load permissions
        $permissions = $this->getUserPermissions($user['id'], $user['role']);
        Session::set('admin_permissions', $permissions);

        // Log activity
        $this->logActivity($user['id'], 'login', 'auth', null, null, null, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        // Create admin session record
        $this->createSessionRecord($user['id']);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'role' => $user['role'],
                'avatar' => $user['avatar'],
            ],
        ];
    }

    /**
     * Logout admin user
     */
    public function logout(): void
    {
        $adminId = Session::get('admin_id');

        if ($adminId) {
            // Log activity
            $this->logActivity($adminId, 'logout', 'auth');

            // End session record
            $this->db->update(
                'admin_sessions',
                ['last_activity' => time()],
                'admin_user_id = ? AND id = ?',
                [$adminId, session_id()]
            );
        }

        Session::destroy();
    }

    /**
     * Check if admin is logged in
     */
    public function check(): bool
    {
        $adminId = Session::get('admin_id');
        $loginTime = Session::get('admin_login_time');

        if (!$adminId || !$loginTime) {
            return false;
        }

        // Check session timeout
        if (time() - $loginTime > $this->config['session']['lifetime']) {
            $this->logout();
            return false;
        }

        // Verify user still exists and is active
        $user = $this->db->fetch(
            "SELECT id, is_active FROM admin_users WHERE id = ?",
            [$adminId]
        );

        if (!$user || !$user['is_active']) {
            $this->logout();
            return false;
        }

        // Update activity time
        Session::set('admin_login_time', time());

        return true;
    }

    /**
     * Get current admin user
     */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        $adminId = Session::get('admin_id');

        return $this->db->fetch(
            "SELECT id, username, email, first_name, last_name, avatar, role,
                    last_login, created_at
             FROM admin_users WHERE id = ?",
            [$adminId]
        );
    }

    /**
     * Get user ID
     */
    public function id(): ?int
    {
        return Session::get('admin_id');
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(string $permission): bool
    {
        $role = Session::get('admin_role');

        // Super admin has all permissions
        if ($role === 'super_admin') {
            return true;
        }

        $permissions = Session::get('admin_permissions', []);
        return in_array($permission, $permissions);
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has role or higher
     */
    public function hasRole(string $role): bool
    {
        $currentRole = Session::get('admin_role');
        $roles = $this->config['admin_roles'];

        $currentLevel = $roles[$currentRole] ?? 0;
        $requiredLevel = $roles[$role] ?? PHP_INT_MAX;

        return $currentLevel >= $requiredLevel;
    }

    /**
     * Get user permissions from database
     */
    private function getUserPermissions(int $userId, string $role): array
    {
        $results = $this->db->fetchAll(
            "SELECT p.slug FROM admin_permissions p
             INNER JOIN admin_role_permissions rp ON p.id = rp.permission_id
             WHERE rp.role = ?",
            [$role]
        );

        return array_column($results, 'slug');
    }

    /**
     * Check if username is locked out
     */
    private function isLockedOut(string $username): bool
    {
        $maxAttempts = $this->config['security']['max_login_attempts'];
        $lockoutDuration = $this->config['security']['lockout_duration'];
        $since = date('Y-m-d H:i:s', time() - $lockoutDuration);

        $attempts = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM admin_activity_log
             WHERE action = 'login_failed'
             AND (old_values->>'$.username' = ? OR old_values->>'$.username' = ?)
             AND created_at > ?",
            [$username, $username, $since]
        );

        return $attempts >= $maxAttempts;
    }

    /**
     * Record a failed login attempt
     */
    private function recordFailedAttempt(string $username): void
    {
        $this->db->insert('admin_activity_log', [
            'admin_user_id' => null,
            'action' => 'login_failed',
            'module' => 'auth',
            'old_values' => json_encode(['username' => $username]),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    /**
     * Clear failed attempts for username
     */
    private function clearFailedAttempts(string $username): void
    {
        // We don't delete, just let them age out
    }

    /**
     * Create session record
     */
    private function createSessionRecord(int $userId): void
    {
        $sessionId = session_id();

        // Remove old session for this user if exists
        $this->db->delete('admin_sessions', 'id = ?', [$sessionId]);

        $this->db->insert('admin_sessions', [
            'id' => $sessionId,
            'admin_user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'last_activity' => time(),
        ]);
    }

    /**
     * Log admin activity
     */
    public function logActivity(
        ?int $userId,
        string $action,
        string $module,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $this->db->insert('admin_activity_log', [
            'admin_user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = $this->db->fetch(
            "SELECT password_hash FROM admin_users WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        if (strlen($newPassword) < $this->config['security']['password_min_length']) {
            return [
                'success' => false,
                'message' => 'Password must be at least ' . $this->config['security']['password_min_length'] . ' characters.',
            ];
        }

        $this->db->update('admin_users', [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            'password_changed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$userId]);

        $this->logActivity($userId, 'password_change', 'auth');

        return ['success' => true, 'message' => 'Password changed successfully.'];
    }
}
