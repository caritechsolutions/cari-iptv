<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Admin Users</h1>
        <p class="page-subtitle">Manage administrator accounts and permissions.</p>
    </div>
    <a href="/admin/admins/create" class="btn btn-primary">
        <i class="lucide-plus"></i> Add Admin User
    </a>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-muted" style="text-align: center; padding: 2rem;">
                                No admin users found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $adminUser): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <?php if (!empty($adminUser['avatar'])): ?>
                                            <img src="<?= htmlspecialchars($adminUser['avatar']) ?>" class="user-avatar-sm" alt="Avatar">
                                        <?php else: ?>
                                            <div class="user-avatar-sm user-avatar-placeholder">
                                                <?= strtoupper(substr($adminUser['first_name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="text-sm font-medium">
                                                <?= htmlspecialchars(($adminUser['first_name'] ?? '') . ' ' . ($adminUser['last_name'] ?? '')) ?>
                                            </div>
                                            <div class="text-xs text-muted"><?= htmlspecialchars($adminUser['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-sm"><?= htmlspecialchars($adminUser['username']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $adminUser['role'] === 'super_admin' ? 'primary' : ($adminUser['role'] === 'admin' ? 'info' : 'secondary') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $adminUser['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($adminUser['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                    <?php if ($adminUser['force_password_change']): ?>
                                        <span class="badge badge-warning" title="Must change password">
                                            <i class="lucide-key"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm text-muted">
                                    <?= $adminUser['last_login'] ? date('M j, Y g:i a', strtotime($adminUser['last_login'])) : 'Never' ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="/admin/admins/<?= $adminUser['id'] ?>/edit" class="btn btn-secondary btn-sm" title="Edit">
                                            <i class="lucide-edit"></i>
                                        </a>
                                        <?php if ($adminUser['id'] !== $user['id']): ?>
                                            <form action="/admin/admins/<?= $adminUser['id'] ?>/toggle-status" method="POST" style="display: inline;">
                                                <input type="hidden" name="_token" value="<?= $csrf ?>">
                                                <button type="submit" class="btn btn-<?= $adminUser['is_active'] ? 'warning' : 'success' ?> btn-sm"
                                                        title="<?= $adminUser['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                    <i class="lucide-<?= $adminUser['is_active'] ? 'user-x' : 'user-check' ?>"></i>
                                                </button>
                                            </form>
                                            <form action="/admin/admins/<?= $adminUser['id'] ?>/reset-password" method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Reset password for this user? A temporary password will be generated.');">
                                                <input type="hidden" name="_token" value="<?= $csrf ?>">
                                                <button type="submit" class="btn btn-info btn-sm" title="Reset Password">
                                                    <i class="lucide-key"></i>
                                                </button>
                                            </form>
                                            <form action="/admin/admins/<?= $adminUser['id'] ?>/delete" method="POST" style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete this user? This cannot be undone.');">
                                                <input type="hidden" name="_token" value="<?= $csrf ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                                    <i class="lucide-trash-2"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted text-xs">(You)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.user-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
}

.user-avatar-placeholder {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    font-size: 1rem;
}

.font-medium {
    font-weight: 500;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.btn-info {
    background: var(--info);
    color: white;
}

.btn-info:hover {
    background: #2563eb;
}

.badge-secondary {
    background: rgba(100, 116, 139, 0.15);
    color: var(--secondary);
}
</style>
