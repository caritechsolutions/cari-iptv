<div class="page-header">
    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">Manage your account settings and preferences.</p>
</div>

<div class="grid grid-2 mb-3">
    <!-- Avatar Section -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Profile Picture</h3>
        </div>
        <div class="card-body">
            <div class="avatar-section">
                <div class="avatar-preview">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" id="avatarImage">
                    <?php else: ?>
                        <div class="avatar-placeholder" id="avatarPlaceholder">
                            <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="avatar-actions">
                    <form action="/admin/profile/avatar" method="POST" enctype="multipart/form-data" class="avatar-form">
                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                        <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('avatarInput').click()">
                            <i class="lucide-upload"></i> Upload New
                        </button>
                    </form>

                    <?php if (!empty($user['avatar'])): ?>
                        <a href="/admin/profile/avatar/remove" class="btn btn-danger">
                            <i class="lucide-trash-2"></i> Remove
                        </a>
                    <?php endif; ?>
                </div>

                <p class="text-xs text-muted mt-2">
                    JPG, PNG, GIF or WebP. Max size 2MB.
                </p>
            </div>
        </div>
    </div>

    <!-- Account Info -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Account Information</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Username</span>
                    <span class="info-value"><?= htmlspecialchars($user['username']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Role</span>
                    <span class="info-value">
                        <span class="badge badge-primary"><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Login</span>
                    <span class="info-value">
                        <?= $user['last_login'] ? date('M j, Y g:i a', strtotime($user['last_login'])) : 'Never' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Basic Info Form -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Basic Information</h3>
    </div>
    <div class="card-body">
        <form action="/admin/profile/update" method="POST" class="form">
            <input type="hidden" name="_token" value="<?= $csrf ?>">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" class="form-input"
                           value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="form-input"
                           value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address *</label>
                <input type="email" id="email" name="email" class="form-input"
                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="lucide-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Password Change Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Change Password</h3>
    </div>
    <div class="card-body">
        <form action="/admin/profile/password" method="POST" class="form">
            <input type="hidden" name="_token" value="<?= $csrf ?>">

            <div class="form-group">
                <label class="form-label" for="current_password">Current Password *</label>
                <input type="password" id="current_password" name="current_password" class="form-input" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="new_password">New Password *</label>
                    <input type="password" id="new_password" name="new_password" class="form-input"
                           minlength="8" required>
                    <small class="form-help">Minimum 8 characters</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                           minlength="8" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-warning">
                    <i class="lucide-key"></i> Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.avatar-preview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    margin-bottom: 1rem;
    border: 3px solid #334155;
}

.avatar-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 600;
    color: white;
}

.avatar-actions {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.info-grid {
    display: grid;
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #334155;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    color: #94a3b8;
    font-size: 0.875rem;
}

.info-value {
    color: #f1f5f9;
    font-weight: 500;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }

    .grid-2 {
        grid-template-columns: 1fr;
    }
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #e2e8f0;
}

.form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 0.5rem;
    color: #f1f5f9;
    font-size: 0.9375rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-help {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #94a3b8;
}

.form-actions {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #334155;
}

.mt-2 {
    margin-top: 0.5rem;
}

.mb-3 {
    margin-bottom: 1.5rem;
}
</style>

<script>
// Auto-submit avatar form when file is selected
document.getElementById('avatarInput').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        // Validate file size
        if (this.files[0].size > 2 * 1024 * 1024) {
            alert('File is too large. Maximum size is 2MB.');
            this.value = '';
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.avatar-preview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Avatar preview">';
        };
        reader.readAsDataURL(this.files[0]);

        // Submit form
        this.closest('form').submit();
    }
});

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    if (this.value !== newPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value && confirmPassword.value !== this.value) {
        confirmPassword.setCustomValidity('Passwords do not match');
    } else {
        confirmPassword.setCustomValidity('');
    }
});
</script>
