<?php
/**
 * CARI-IPTV Admin User Management Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;

class AdminUserController
{
    private Database $db;
    private AdminAuthService $auth;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
    }

    /**
     * List all admin users
     */
    public function index(): void
    {
        $users = $this->db->fetchAll(
            "SELECT id, username, email, first_name, last_name, avatar, role,
                    is_active, force_password_change, last_login, created_at
             FROM admin_users
             ORDER BY created_at DESC"
        );

        Response::view('admin/admins/index', [
            'pageTitle' => 'Admin Users',
            'users' => $users,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Show create admin user form
     */
    public function create(): void
    {
        $permissions = $this->getGroupedPermissions();

        Response::view('admin/admins/form', [
            'pageTitle' => 'Create Admin User',
            'adminUser' => null,
            'permissions' => $permissions,
            'userPermissions' => [],
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Store new admin user
     */
    public function store(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/admins/create');
            return;
        }

        $data = $this->validateUserData($_POST);

        if (!empty($data['errors'])) {
            Session::flash('error', implode(' ', $data['errors']));
            Response::redirect('/admin/admins/create');
            return;
        }

        // Check if username or email already exists
        $existing = $this->db->fetch(
            "SELECT id FROM admin_users WHERE username = ? OR email = ?",
            [$data['username'], $data['email']]
        );

        if ($existing) {
            Session::flash('error', 'Username or email already exists.');
            Response::redirect('/admin/admins/create');
            return;
        }

        // Create user
        $userId = $this->db->insert('admin_users', [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
            'is_active' => $data['is_active'] ? 1 : 0,
            'force_password_change' => $data['force_password_change'] ? 1 : 0,
        ]);

        // Save user permissions
        $this->saveUserPermissions($userId, $_POST['permissions'] ?? []);

        // Log activity
        $this->auth->logActivity(
            $this->auth->id(),
            'create',
            'admins',
            'admin_user',
            $userId
        );

        Session::flash('success', 'Admin user created successfully.');
        Response::redirect('/admin/admins');
    }

    /**
     * Show edit admin user form
     */
    public function edit(int $id): void
    {
        $adminUser = $this->db->fetch(
            "SELECT * FROM admin_users WHERE id = ?",
            [$id]
        );

        if (!$adminUser) {
            Session::flash('error', 'Admin user not found.');
            Response::redirect('/admin/admins');
            return;
        }

        $permissions = $this->getGroupedPermissions();
        $userPermissions = $this->getUserPermissions($id);

        Response::view('admin/admins/form', [
            'pageTitle' => 'Edit Admin User',
            'adminUser' => $adminUser,
            'permissions' => $permissions,
            'userPermissions' => $userPermissions,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Update admin user
     */
    public function update(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect("/admin/admins/{$id}/edit");
            return;
        }

        $adminUser = $this->db->fetch(
            "SELECT * FROM admin_users WHERE id = ?",
            [$id]
        );

        if (!$adminUser) {
            Session::flash('error', 'Admin user not found.');
            Response::redirect('/admin/admins');
            return;
        }

        $data = $this->validateUserData($_POST, $id);

        if (!empty($data['errors'])) {
            Session::flash('error', implode(' ', $data['errors']));
            Response::redirect("/admin/admins/{$id}/edit");
            return;
        }

        // Check if username or email already exists for other users
        $existing = $this->db->fetch(
            "SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND id != ?",
            [$data['username'], $data['email'], $id]
        );

        if ($existing) {
            Session::flash('error', 'Username or email already in use by another user.');
            Response::redirect("/admin/admins/{$id}/edit");
            return;
        }

        // Update user
        $updateData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
            'is_active' => $data['is_active'] ? 1 : 0,
            'force_password_change' => $data['force_password_change'] ? 1 : 0,
        ];

        // Update password if provided
        if (!empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            $updateData['password_changed_at'] = date('Y-m-d H:i:s');
        }

        $this->db->update('admin_users', $updateData, 'id = ?', [$id]);

        // Save user permissions
        $this->saveUserPermissions($id, $_POST['permissions'] ?? []);

        // Log activity
        $this->auth->logActivity(
            $this->auth->id(),
            'update',
            'admins',
            'admin_user',
            $id
        );

        Session::flash('success', 'Admin user updated successfully.');
        Response::redirect('/admin/admins');
    }

    /**
     * Delete admin user
     */
    public function delete(int $id): void
    {
        $currentUserId = $this->auth->id();

        // Prevent self-deletion
        if ($id === $currentUserId) {
            Session::flash('error', 'You cannot delete your own account.');
            Response::redirect('/admin/admins');
            return;
        }

        $adminUser = $this->db->fetch(
            "SELECT username FROM admin_users WHERE id = ?",
            [$id]
        );

        if (!$adminUser) {
            Session::flash('error', 'Admin user not found.');
            Response::redirect('/admin/admins');
            return;
        }

        // Delete user (cascade will handle related records)
        $this->db->delete('admin_users', 'id = ?', [$id]);

        // Log activity
        $this->auth->logActivity(
            $currentUserId,
            'delete',
            'admins',
            'admin_user',
            $id,
            ['username' => $adminUser['username']]
        );

        Session::flash('success', 'Admin user deleted successfully.');
        Response::redirect('/admin/admins');
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus(int $id): void
    {
        $currentUserId = $this->auth->id();

        if ($id === $currentUserId) {
            Session::flash('error', 'You cannot deactivate your own account.');
            Response::redirect('/admin/admins');
            return;
        }

        $adminUser = $this->db->fetch(
            "SELECT is_active FROM admin_users WHERE id = ?",
            [$id]
        );

        if (!$adminUser) {
            Session::flash('error', 'Admin user not found.');
            Response::redirect('/admin/admins');
            return;
        }

        $newStatus = $adminUser['is_active'] ? 0 : 1;
        $this->db->update('admin_users', ['is_active' => $newStatus], 'id = ?', [$id]);

        $statusText = $newStatus ? 'activated' : 'deactivated';
        Session::flash('success', "Admin user {$statusText} successfully.");
        Response::redirect('/admin/admins');
    }

    /**
     * Reset user password (admin action)
     */
    public function resetPassword(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request.');
            Response::redirect('/admin/admins');
            return;
        }

        $adminUser = $this->db->fetch(
            "SELECT username, email FROM admin_users WHERE id = ?",
            [$id]
        );

        if (!$adminUser) {
            Session::flash('error', 'Admin user not found.');
            Response::redirect('/admin/admins');
            return;
        }

        // Generate temporary password
        $tempPassword = $this->generateTempPassword();

        $this->db->update('admin_users', [
            'password_hash' => password_hash($tempPassword, PASSWORD_BCRYPT),
            'force_password_change' => 1,
            'password_changed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        // Log activity
        $this->auth->logActivity(
            $this->auth->id(),
            'password_reset',
            'admins',
            'admin_user',
            $id
        );

        Session::flash('success', "Password reset for {$adminUser['username']}. Temporary password: {$tempPassword}");
        Response::redirect('/admin/admins');
    }

    /**
     * Get all permissions grouped by module
     */
    private function getGroupedPermissions(): array
    {
        $permissions = $this->db->fetchAll(
            "SELECT * FROM admin_permissions ORDER BY module, name"
        );

        $grouped = [];
        foreach ($permissions as $perm) {
            $module = $perm['module'];
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $perm;
        }

        return $grouped;
    }

    /**
     * Get user's specific permissions
     */
    private function getUserPermissions(int $userId): array
    {
        $results = $this->db->fetchAll(
            "SELECT permission_id FROM admin_user_permissions WHERE admin_user_id = ? AND granted = 1",
            [$userId]
        );

        return array_column($results, 'permission_id');
    }

    /**
     * Save user permissions
     */
    private function saveUserPermissions(int $userId, array $permissionIds): void
    {
        // Clear existing permissions
        $this->db->delete('admin_user_permissions', 'admin_user_id = ?', [$userId]);

        // Insert new permissions
        foreach ($permissionIds as $permId) {
            $this->db->insert('admin_user_permissions', [
                'admin_user_id' => $userId,
                'permission_id' => (int) $permId,
                'granted' => 1,
            ]);
        }
    }

    /**
     * Validate user data
     */
    private function validateUserData(array $data, ?int $userId = null): array
    {
        $errors = [];
        $validated = [];

        // Username
        $validated['username'] = trim($data['username'] ?? '');
        if (empty($validated['username'])) {
            $errors[] = 'Username is required.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $validated['username'])) {
            $errors[] = 'Username must be 3-50 characters and contain only letters, numbers, and underscores.';
        }

        // Email
        $validated['email'] = trim($data['email'] ?? '');
        if (empty($validated['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($validated['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        // Password (required for new users, optional for updates)
        $validated['password'] = $data['password'] ?? '';
        if (!$userId && empty($validated['password'])) {
            $errors[] = 'Password is required.';
        } elseif (!empty($validated['password']) && strlen($validated['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        // First name
        $validated['first_name'] = trim($data['first_name'] ?? '');
        if (empty($validated['first_name'])) {
            $errors[] = 'First name is required.';
        }

        // Last name
        $validated['last_name'] = trim($data['last_name'] ?? '');

        // Role
        $validRoles = ['super_admin', 'admin', 'manager', 'support', 'viewer'];
        $validated['role'] = $data['role'] ?? 'viewer';
        if (!in_array($validated['role'], $validRoles)) {
            $validated['role'] = 'viewer';
        }

        // Is active
        $validated['is_active'] = isset($data['is_active']) && $data['is_active'] == '1';

        // Force password change
        $validated['force_password_change'] = isset($data['force_password_change']) && $data['force_password_change'] == '1';

        $validated['errors'] = $errors;
        return $validated;
    }

    /**
     * Generate temporary password
     */
    private function generateTempPassword(): string
    {
        return substr(str_shuffle('abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$'), 0, 12);
    }
}
