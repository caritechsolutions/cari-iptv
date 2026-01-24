<div class="page-header">
    <h1 class="page-title">Settings</h1>
    <p class="page-subtitle">Configure your system settings and preferences.</p>
</div>

<div class="settings-grid">
    <!-- General Settings -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="lucide-settings"></i>
                General Settings
            </h3>
        </div>
        <div class="card-body">
            <form action="/admin/settings/general" method="POST">
                <input type="hidden" name="_token" value="<?= $csrf ?>">

                <div class="form-group">
                    <label class="form-label" for="site_name">Site Name</label>
                    <input type="text" id="site_name" name="site_name" class="form-input"
                           value="<?= htmlspecialchars($settings['general']['site_name'] ?? 'CARI-IPTV') ?>">
                    <small class="form-help">Name displayed in emails and UI</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="site_url">Site URL</label>
                    <input type="url" id="site_url" name="site_url" class="form-input"
                           placeholder="https://example.com"
                           value="<?= htmlspecialchars($settings['general']['site_url'] ?? '') ?>">
                    <small class="form-help">Base URL of your site (used in emails)</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="admin_email">Admin Email</label>
                    <input type="email" id="admin_email" name="admin_email" class="form-input"
                           placeholder="admin@example.com"
                           value="<?= htmlspecialchars($settings['general']['admin_email'] ?? '') ?>">
                    <small class="form-help">Primary admin email for system notifications</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="lucide-save"></i> Save General Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SMTP Settings -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="lucide-mail"></i>
                Email / SMTP Settings
            </h3>
        </div>
        <div class="card-body">
            <form action="/admin/settings/smtp" method="POST">
                <input type="hidden" name="_token" value="<?= $csrf ?>">

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="smtp_enabled" value="1"
                               <?= !empty($settings['smtp']['enabled']) ? 'checked' : '' ?>>
                        <span class="checkbox-text">
                            <strong>Enable SMTP</strong>
                            <small>Send emails via SMTP server</small>
                        </span>
                    </label>
                </div>

                <div class="smtp-fields">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="smtp_host">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" class="form-input"
                                   placeholder="smtp.example.com"
                                   value="<?= htmlspecialchars($settings['smtp']['host'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="smtp_port">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" class="form-input"
                                   placeholder="587"
                                   value="<?= htmlspecialchars($settings['smtp']['port'] ?? '587') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="smtp_encryption">Encryption</label>
                        <select id="smtp_encryption" name="smtp_encryption" class="form-input">
                            <option value="tls" <?= ($settings['smtp']['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Recommended)</option>
                            <option value="ssl" <?= ($settings['smtp']['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= ($settings['smtp']['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="smtp_username">Username</label>
                            <input type="text" id="smtp_username" name="smtp_username" class="form-input"
                                   placeholder="user@example.com"
                                   autocomplete="off"
                                   value="<?= htmlspecialchars($settings['smtp']['username'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="smtp_password">Password</label>
                            <input type="password" id="smtp_password" name="smtp_password" class="form-input"
                                   placeholder="<?= !empty($settings['smtp']['password']) ? '••••••••' : 'Enter password' ?>"
                                   autocomplete="new-password">
                            <small class="form-help">Leave blank to keep current password</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="smtp_from_email">From Email</label>
                            <input type="email" id="smtp_from_email" name="smtp_from_email" class="form-input"
                                   placeholder="noreply@example.com"
                                   value="<?= htmlspecialchars($settings['smtp']['from_email'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="smtp_from_name">From Name</label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name" class="form-input"
                                   placeholder="CARI-IPTV"
                                   value="<?= htmlspecialchars($settings['smtp']['from_name'] ?? 'CARI-IPTV') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="lucide-save"></i> Save SMTP Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Test Email -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="lucide-send"></i>
                Test Email
            </h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-2">Send a test email to verify your SMTP configuration.</p>

            <form action="/admin/settings/test-email" method="POST">
                <input type="hidden" name="_token" value="<?= $csrf ?>">

                <div class="form-group">
                    <label class="form-label" for="test_email">Email Address</label>
                    <input type="email" id="test_email" name="test_email" class="form-input"
                           placeholder="test@example.com"
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-secondary">
                        <i class="lucide-send"></i> Send Test Email
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SMTP Presets Info -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="lucide-info"></i>
                Common SMTP Settings
            </h3>
        </div>
        <div class="card-body">
            <div class="smtp-presets">
                <div class="preset">
                    <h4>Gmail</h4>
                    <ul>
                        <li><strong>Host:</strong> smtp.gmail.com</li>
                        <li><strong>Port:</strong> 587 (TLS) or 465 (SSL)</li>
                        <li><strong>Username:</strong> your-email@gmail.com</li>
                        <li><strong>Note:</strong> Use App Password, not account password</li>
                    </ul>
                </div>

                <div class="preset">
                    <h4>Microsoft 365</h4>
                    <ul>
                        <li><strong>Host:</strong> smtp.office365.com</li>
                        <li><strong>Port:</strong> 587</li>
                        <li><strong>Encryption:</strong> TLS</li>
                    </ul>
                </div>

                <div class="preset">
                    <h4>Amazon SES</h4>
                    <ul>
                        <li><strong>Host:</strong> email-smtp.[region].amazonaws.com</li>
                        <li><strong>Port:</strong> 587 or 465</li>
                        <li><strong>Username:</strong> SMTP credentials (not AWS keys)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

@media (max-width: 1200px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}

.card-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-title i {
    color: var(--primary-light);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 600px) {
    .form-row {
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
    color: var(--text-secondary);
}

.form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.9375rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-help {
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

.form-actions {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.mb-2 {
    margin-bottom: 1rem;
}

.smtp-presets {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.preset {
    padding: 1rem;
    background: var(--bg-hover);
    border-radius: 8px;
}

.preset h4 {
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.preset ul {
    margin: 0;
    padding-left: 1.25rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.preset li {
    margin-bottom: 0.25rem;
}

.preset strong {
    color: var(--text-muted);
}
</style>
