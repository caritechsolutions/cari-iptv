<div class="page-header">
    <h1 class="page-title"><?= $adminUser ? 'Edit Admin User' : 'Create Admin User' ?></h1>
    <p class="page-subtitle">
        <?= $adminUser ? 'Update admin user details and permissions.' : 'Add a new administrator to the system.' ?>
    </p>
</div>

<form action="<?= $adminUser ? "/admin/admins/{$adminUser['id']}/update" : '/admin/admins/store' ?>" method="POST" class="admin-form">
    <input type="hidden" name="_token" value="<?= $csrf ?>">

    <div class="grid grid-2 mb-3">
        <!-- User Details Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User Details</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" class="form-input"
                               value="<?= htmlspecialchars($adminUser['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-input"
                               value="<?= htmlspecialchars($adminUser['last_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="username">Username *</label>
                    <input type="text" id="username" name="username" class="form-input"
                           value="<?= htmlspecialchars($adminUser['username'] ?? '') ?>"
                           pattern="[a-zA-Z0-9_]{3,50}" required>
                    <small class="form-help">3-50 characters, letters, numbers, and underscores only</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-input"
                           value="<?= htmlspecialchars($adminUser['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        Password <?= $adminUser ? '(leave blank to keep current)' : '*' ?>
                    </label>
                    <input type="password" id="password" name="password" class="form-input"
                           minlength="8" <?= $adminUser ? '' : 'required' ?>>
                    <small class="form-help">Minimum 8 characters</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">Role *</label>
                    <select id="role" name="role" class="form-input">
                        <option value="viewer" <?= ($adminUser['role'] ?? '') === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                        <option value="support" <?= ($adminUser['role'] ?? '') === 'support' ? 'selected' : '' ?>>Support</option>
                        <option value="manager" <?= ($adminUser['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="admin" <?= ($adminUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="super_admin" <?= ($adminUser['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                    <small class="form-help">Super Admin has all permissions regardless of settings below</small>
                </div>
            </div>
        </div>

        <!-- Account Settings Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Account Settings</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1"
                               <?= ($adminUser['is_active'] ?? true) ? 'checked' : '' ?>>
                        <span class="checkbox-text">
                            <strong>Active Account</strong>
                            <small>User can log in and access the admin panel</small>
                        </span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="force_password_change" value="1"
                               <?= ($adminUser['force_password_change'] ?? false) ? 'checked' : '' ?>>
                        <span class="checkbox-text">
                            <strong>Force Password Change</strong>
                            <small>User must change password on next login</small>
                        </span>
                    </label>
                </div>

                <?php if ($adminUser): ?>
                    <div class="info-section">
                        <h4>Account Info</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Created</span>
                                <span class="info-value"><?= date('M j, Y g:i a', strtotime($adminUser['created_at'])) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Login</span>
                                <span class="info-value">
                                    <?= $adminUser['last_login'] ? date('M j, Y g:i a', strtotime($adminUser['last_login'])) : 'Never' ?>
                                </span>
                            </div>
                            <?php if ($adminUser['last_ip']): ?>
                                <div class="info-item">
                                    <span class="info-label">Last IP</span>
                                    <span class="info-value"><?= htmlspecialchars($adminUser['last_ip']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Permissions Card -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Page Permissions</h3>
            <div class="card-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllPermissions()">Select All</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="deselectAllPermissions()">Deselect All</button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted mb-2">Select which pages this user can access. Super Admin users have access to all pages regardless of these settings.</p>

            <div class="permissions-grid">
                <?php foreach ($permissions as $module => $modulePerms): ?>
                    <div class="permission-module">
                        <h4 class="module-title">
                            <i class="lucide-<?= getModuleIcon($module) ?>"></i>
                            <?= ucfirst($module) ?>
                        </h4>
                        <div class="permission-list">
                            <?php foreach ($modulePerms as $perm): ?>
                                <label class="permission-item">
                                    <input type="checkbox" name="permissions[]" value="<?= $perm['id'] ?>"
                                           <?= in_array($perm['id'], $userPermissions) ? 'checked' : '' ?>>
                                    <span class="permission-name"><?= htmlspecialchars($perm['name']) ?></span>
                                    <?php if ($perm['description']): ?>
                                        <span class="permission-desc"><?= htmlspecialchars($perm['description']) ?></span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="form-actions">
        <a href="/admin/admins" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="lucide-save"></i>
            <?= $adminUser ? 'Update User' : 'Create User' ?>
        </button>
    </div>
</form>

<?php
function getModuleIcon(string $module): string {
    return match($module) {
        'dashboard' => 'layout-dashboard',
        'analytics' => 'bar-chart-2',
        'channels' => 'tv',
        'vod' => 'film',
        'series' => 'clapperboard',
        'epg' => 'calendar',
        'categories' => 'folder',
        'subscribers' => 'users',
        'subscriptions' => 'credit-card',
        'packages' => 'package',
        'admins' => 'shield',
        'activity' => 'history',
        'settings' => 'settings',
        default => 'circle',
    };
}
?>

<style>
.admin-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.admin-form .form-group {
    margin-bottom: 1.25rem;
}

.admin-form .form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-secondary);
}

.admin-form .form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.9375rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.admin-form .form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.admin-form select.form-input {
    cursor: pointer;
}

.admin-form .form-help {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--bg-hover);
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s;
}

.checkbox-label:hover {
    background: var(--border-color);
}

.checkbox-label input[type="checkbox"] {
    margin-top: 0.25rem;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-text {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.checkbox-text strong {
    color: var(--text-primary);
}

.checkbox-text small {
    color: var(--text-muted);
    font-size: 0.8rem;
}

.info-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.info-section h4 {
    margin-bottom: 1rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.info-grid {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
}

.info-label {
    color: var(--text-muted);
}

.info-value {
    color: var(--text-primary);
}

.card-actions {
    display: flex;
    gap: 0.5rem;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.permission-module {
    background: var(--bg-hover);
    border-radius: 8px;
    padding: 1rem;
}

.module-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
    color: var(--text-primary);
    text-transform: capitalize;
}

.module-title i {
    color: var(--primary-light);
}

.permission-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.permission-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.5rem;
    background: var(--bg-card);
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
}

.permission-item:hover {
    background: var(--bg-dark);
}

.permission-item input[type="checkbox"] {
    margin-top: 0.125rem;
    cursor: pointer;
}

.permission-name {
    font-size: 0.875rem;
    color: var(--text-primary);
}

.permission-desc {
    display: block;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding-top: 1rem;
}

.mb-2 {
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .admin-form .form-row,
    .grid-2 {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function selectAllPermissions() {
    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = true);
}

function deselectAllPermissions() {
    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = false);
}
</script>
