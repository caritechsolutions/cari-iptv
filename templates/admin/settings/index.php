<div class="page-header">
    <h1 class="page-title">Settings</h1>
    <p class="page-subtitle">Configure your system settings, integrations, and preferences.</p>
</div>

<!-- Settings Tabs -->
<div class="settings-tabs">
    <button class="settings-tab active" data-tab="general">
        <i class="lucide-settings"></i> General
    </button>
    <button class="settings-tab" data-tab="email">
        <i class="lucide-mail"></i> Email
    </button>
    <button class="settings-tab" data-tab="integrations">
        <i class="lucide-plug"></i> Integrations
    </button>
    <button class="settings-tab" data-tab="ai">
        <i class="lucide-brain"></i> AI
    </button>
    <button class="settings-tab" data-tab="image">
        <i class="lucide-image"></i> Images
    </button>
</div>

<!-- General Tab -->
<div class="settings-tab-content active" id="tab-general">
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
                <form action="/admin/settings/general" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">

                    <div class="form-group">
                        <label class="form-label" for="site_name">Site Name</label>
                        <input type="text" id="site_name" name="site_name" class="form-input"
                               value="<?= htmlspecialchars($settings['general']['site_name'] ?? 'CARI-IPTV') ?>">
                        <small class="form-help">Name displayed in emails and UI</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Site Logo</label>
                        <div class="logo-upload-area">
                            <?php if (!empty($settings['general']['site_logo'])): ?>
                                <div class="current-logo">
                                    <img src="<?= htmlspecialchars($settings['general']['site_logo']) ?>" alt="Current Logo" class="logo-preview">
                                    <label class="checkbox-label remove-logo-label">
                                        <input type="checkbox" name="remove_logo" value="1">
                                        <span>Remove current logo</span>
                                    </label>
                                </div>
                            <?php endif; ?>
                            <div class="logo-input">
                                <input type="file" id="site_logo" name="site_logo" class="form-input" accept="image/*">
                                <small class="form-help">Recommended: PNG or SVG, max 200x60px, max 1MB</small>
                            </div>
                        </div>
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
    </div>
</div>

<!-- Email Tab -->
<div class="settings-tab-content" id="tab-email">
    <div class="settings-grid">
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
    </div>
</div>

<!-- Integrations Tab (Metadata APIs) -->
<div class="settings-tab-content" id="tab-integrations">
    <div class="settings-grid">
        <!-- Fanart.tv Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="lucide-palette"></i>
                    Fanart.tv
                </h3>
                <span class="status-badge <?= ($integrationStatus['metadata']['fanart_tv']['connected'] ?? false) ? 'connected' : 'disconnected' ?>">
                    <?= ($integrationStatus['metadata']['fanart_tv']['connected'] ?? false) ? 'Connected' : 'Not Connected' ?>
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">Fanart.tv provides high-quality TV network logos and artwork.</p>

                <form action="/admin/settings/metadata" method="POST" id="metadataForm">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">

                    <div class="form-group">
                        <label class="form-label" for="fanart_tv_api_key">API Key</label>
                        <div class="input-with-button">
                            <input type="password" id="fanart_tv_api_key" name="fanart_tv_api_key" class="form-input"
                                   placeholder="<?= !empty($settings['metadata']['fanart_tv_api_key']) ? '••••••••••••••••' : 'Enter API key' ?>"
                                   autocomplete="off">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="testFanartConnection()">
                                <i class="lucide-plug"></i> Test
                            </button>
                        </div>
                        <small class="form-help">
                            Get a free API key at <a href="https://fanart.tv/get-an-api-key/" target="_blank">fanart.tv/get-an-api-key</a>
                        </small>
                    </div>
            </div>
        </div>

        <!-- TMDB Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="lucide-film"></i>
                    TMDB (The Movie Database)
                </h3>
                <span class="status-badge <?= ($integrationStatus['metadata']['tmdb']['connected'] ?? false) ? 'connected' : 'disconnected' ?>">
                    <?= ($integrationStatus['metadata']['tmdb']['connected'] ?? false) ? 'Connected' : 'Not Connected' ?>
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">TMDB provides comprehensive movie and TV show metadata.</p>

                    <div class="form-group">
                        <label class="form-label" for="tmdb_api_key">API Key</label>
                        <div class="input-with-button">
                            <input type="password" id="tmdb_api_key" name="tmdb_api_key" class="form-input"
                                   placeholder="<?= !empty($settings['metadata']['tmdb_api_key']) ? '••••••••••••••••' : 'Enter API key' ?>"
                                   autocomplete="off">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="testTmdbConnection()">
                                <i class="lucide-plug"></i> Test
                            </button>
                        </div>
                        <small class="form-help">
                            Get a free API key at <a href="https://www.themoviedb.org/settings/api" target="_blank">themoviedb.org/settings/api</a>
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="auto_fetch_metadata" value="1"
                                   <?= !empty($settings['metadata']['auto_fetch_metadata']) ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Auto-fetch Metadata</strong>
                                <small>Automatically fetch metadata when adding new content</small>
                            </span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="lucide-save"></i> Save Metadata Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- YouTube Data API Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="lucide-youtube"></i>
                    YouTube Data API
                </h3>
                <span class="status-badge <?= ($integrationStatus['metadata']['youtube']['connected'] ?? false) ? 'connected' : 'disconnected' ?>">
                    <?= ($integrationStatus['metadata']['youtube']['connected'] ?? false) ? 'Connected' : 'Not Connected' ?>
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">YouTube Data API enables searching for trailers and royalty-free content.</p>

                <form action="/admin/settings/youtube" method="POST">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">

                    <div class="form-group">
                        <label class="form-label" for="youtube_api_key">API Key</label>
                        <div class="input-with-button">
                            <input type="password" id="youtube_api_key" name="youtube_api_key" class="form-input"
                                   placeholder="<?= !empty($settings['metadata']['youtube_api_key']) ? '••••••••••••••••' : 'Enter API key' ?>"
                                   autocomplete="off">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="testYoutubeConnection()">
                                <i class="lucide-plug"></i> Test
                            </button>
                        </div>
                        <small class="form-help">
                            Get a free API key at <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> (enable YouTube Data API v3)
                        </small>
                    </div>

                    <div class="info-box">
                        <i class="lucide-info"></i>
                        <div>
                            <strong>Free Tier Limits</strong>
                            <p>YouTube Data API provides 10,000 units/day for free. A search costs ~100 units.</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="lucide-save"></i> Save YouTube Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- AI Tab -->
<div class="settings-tab-content" id="tab-ai">
    <div class="settings-grid">
        <!-- AI Provider Selection -->
        <div class="card full-width">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="lucide-brain"></i>
                    AI Configuration
                </h3>
                <span class="status-badge <?= ($integrationStatus['ai']['available'] ?? false) ? 'connected' : 'disconnected' ?>">
                    <?= ($integrationStatus['ai']['available'] ?? false) ? ($integrationStatus['ai']['provider_name'] ?? 'Connected') : 'Not Connected' ?>
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">Configure AI for generating descriptions, analyzing content, and more.</p>

                <form action="/admin/settings/ai" method="POST" id="aiForm">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="ai_enabled" value="1"
                                   <?= !empty($settings['ai']['ai_enabled']) ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Enable AI Features</strong>
                                <small>Use AI for content generation and analysis</small>
                            </span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">AI Provider</label>
                        <div class="provider-cards">
                            <label class="provider-card <?= ($settings['ai']['provider'] ?? 'ollama') === 'ollama' ? 'selected' : '' ?>">
                                <input type="radio" name="ai_provider" value="ollama"
                                       <?= ($settings['ai']['provider'] ?? 'ollama') === 'ollama' ? 'checked' : '' ?>
                                       onchange="showProviderSettings('ollama')">
                                <div class="provider-info">
                                    <i class="lucide-server"></i>
                                    <strong>Ollama (Local)</strong>
                                    <small>Free, runs on your server</small>
                                </div>
                            </label>

                            <label class="provider-card <?= ($settings['ai']['provider'] ?? '') === 'openai' ? 'selected' : '' ?>">
                                <input type="radio" name="ai_provider" value="openai"
                                       <?= ($settings['ai']['provider'] ?? '') === 'openai' ? 'checked' : '' ?>
                                       onchange="showProviderSettings('openai')">
                                <div class="provider-info">
                                    <i class="lucide-sparkles"></i>
                                    <strong>OpenAI</strong>
                                    <small>GPT-4, cloud-based</small>
                                </div>
                            </label>

                            <label class="provider-card <?= ($settings['ai']['provider'] ?? '') === 'anthropic' ? 'selected' : '' ?>">
                                <input type="radio" name="ai_provider" value="anthropic"
                                       <?= ($settings['ai']['provider'] ?? '') === 'anthropic' ? 'checked' : '' ?>
                                       onchange="showProviderSettings('anthropic')">
                                <div class="provider-info">
                                    <i class="lucide-message-square"></i>
                                    <strong>Anthropic</strong>
                                    <small>Claude, cloud-based</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Ollama Settings -->
                    <div class="provider-settings" id="settings-ollama" style="<?= ($settings['ai']['provider'] ?? 'ollama') === 'ollama' ? '' : 'display: none;' ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="ollama_url">Ollama Server URL</label>
                                <div class="input-with-button">
                                    <input type="url" id="ollama_url" name="ollama_url" class="form-input"
                                           value="<?= htmlspecialchars($settings['ai']['ollama_url'] ?? 'http://localhost:11434') ?>">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="testOllamaConnection()">
                                        <i class="lucide-plug"></i> Test
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="ollama_model">Model</label>
                                <select id="ollama_model" name="ollama_model" class="form-input">
                                    <option value="llama3.2:1b" <?= ($settings['ai']['ollama_model'] ?? 'llama3.2:1b') === 'llama3.2:1b' ? 'selected' : '' ?>>llama3.2:1b (Fast)</option>
                                    <option value="llama3.2:3b" <?= ($settings['ai']['ollama_model'] ?? '') === 'llama3.2:3b' ? 'selected' : '' ?>>llama3.2:3b (Balanced)</option>
                                    <option value="llama3:8b" <?= ($settings['ai']['ollama_model'] ?? '') === 'llama3:8b' ? 'selected' : '' ?>>llama3:8b (Quality)</option>
                                    <option value="mistral:7b" <?= ($settings['ai']['ollama_model'] ?? '') === 'mistral:7b' ? 'selected' : '' ?>>mistral:7b</option>
                                    <option value="phi3:mini" <?= ($settings['ai']['ollama_model'] ?? '') === 'phi3:mini' ? 'selected' : '' ?>>phi3:mini</option>
                                </select>
                            </div>
                        </div>
                        <div class="info-box">
                            <i class="lucide-info"></i>
                            <div>
                                <strong>Ollama Setup</strong>
                                <p>Pull models with: <code>ollama pull llama3.2:1b</code></p>
                            </div>
                        </div>
                    </div>

                    <!-- OpenAI Settings -->
                    <div class="provider-settings" id="settings-openai" style="<?= ($settings['ai']['provider'] ?? '') === 'openai' ? '' : 'display: none;' ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="openai_api_key">OpenAI API Key</label>
                                <input type="password" id="openai_api_key" name="openai_api_key" class="form-input"
                                       placeholder="<?= !empty($settings['ai']['openai_api_key']) ? '••••••••••••••••' : 'sk-...' ?>"
                                       autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="openai_model">Model</label>
                                <select id="openai_model" name="openai_model" class="form-input">
                                    <option value="gpt-4o-mini" <?= ($settings['ai']['openai_model'] ?? 'gpt-4o-mini') === 'gpt-4o-mini' ? 'selected' : '' ?>>GPT-4o Mini (Fast, cheap)</option>
                                    <option value="gpt-4o" <?= ($settings['ai']['openai_model'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o (Best quality)</option>
                                    <option value="gpt-4-turbo" <?= ($settings['ai']['openai_model'] ?? '') === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Anthropic Settings -->
                    <div class="provider-settings" id="settings-anthropic" style="<?= ($settings['ai']['provider'] ?? '') === 'anthropic' ? '' : 'display: none;' ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="anthropic_api_key">Anthropic API Key</label>
                                <input type="password" id="anthropic_api_key" name="anthropic_api_key" class="form-input"
                                       placeholder="<?= !empty($settings['ai']['anthropic_api_key']) ? '••••••••••••••••' : 'sk-ant-...' ?>"
                                       autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="anthropic_model">Model</label>
                                <select id="anthropic_model" name="anthropic_model" class="form-input">
                                    <option value="claude-3-haiku-20240307" <?= ($settings['ai']['anthropic_model'] ?? 'claude-3-haiku-20240307') === 'claude-3-haiku-20240307' ? 'selected' : '' ?>>Claude 3 Haiku (Fast)</option>
                                    <option value="claude-3-sonnet-20240229" <?= ($settings['ai']['anthropic_model'] ?? '') === 'claude-3-sonnet-20240229' ? 'selected' : '' ?>>Claude 3 Sonnet</option>
                                    <option value="claude-3-opus-20240229" <?= ($settings['ai']['anthropic_model'] ?? '') === 'claude-3-opus-20240229' ? 'selected' : '' ?>>Claude 3 Opus (Best)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="testAIConnection()">
                            <i class="lucide-zap"></i> Test AI Connection
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="lucide-save"></i> Save AI Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Image Tab -->
<div class="settings-tab-content" id="tab-image">
    <div class="settings-grid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="lucide-image"></i>
                    Image Processing
                </h3>
                <span class="status-badge <?= ($integrationStatus['image']['webp_supported'] ?? false) ? 'connected' : 'disconnected' ?>">
                    <?= ($integrationStatus['image']['webp_supported'] ?? false) ? 'WebP Supported' : 'WebP Not Supported' ?>
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">Configure how images are processed and optimized.</p>

                <form action="/admin/settings/image" method="POST">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="auto_optimize" value="1"
                                   <?= ($settings['image']['auto_optimize'] ?? '1') ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Auto-optimize Images</strong>
                                <small>Automatically compress and convert images to WebP on upload</small>
                            </span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="keep_originals" value="1"
                                   <?= ($settings['image']['keep_originals'] ?? '1') ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Keep Original Files</strong>
                                <small>Store original uploads for future regeneration</small>
                            </span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="webp_quality">WebP Quality</label>
                        <div class="range-with-value">
                            <input type="range" id="webp_quality" name="webp_quality" class="form-range"
                                   min="50" max="100" step="5"
                                   value="<?= htmlspecialchars($settings['image']['webp_quality'] ?? '85') ?>"
                                   oninput="document.getElementById('quality_value').textContent = this.value + '%'">
                            <span id="quality_value" class="range-value"><?= htmlspecialchars($settings['image']['webp_quality'] ?? '85') ?>%</span>
                        </div>
                        <small class="form-help">Higher quality = larger file size. Recommended: 80-90%</small>
                    </div>

                    <div class="info-box">
                        <i class="lucide-info"></i>
                        <div>
                            <strong>Image Sizes Generated</strong>
                            <ul class="size-list">
                                <li><strong>Channels:</strong> 64x64 (thumb), 200x200 (medium), 400x400 (large), 500x296 (landscape)</li>
                                <li><strong>VOD:</strong> 150x225 (thumb), 342x513 (poster), 780x439 (backdrop)</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="lucide-save"></i> Save Image Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Test Result Modal -->
<div class="modal-overlay" id="testResultModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="testResultTitle">Test Result</h3>
            <button type="button" class="modal-close" onclick="closeTestModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="testResultContent"></div>
        </div>
    </div>
</div>

<style>
.settings-tabs {
    display: flex;
    gap: 0.25rem;
    background: var(--bg-card);
    padding: 0.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
    flex-wrap: wrap;
}

.settings-tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.settings-tab:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.settings-tab.active {
    background: var(--primary);
    color: white;
}

.settings-tab-content {
    display: none;
}

.settings-tab-content.active {
    display: block;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.settings-grid .full-width {
    grid-column: 1 / -1;
}

@media (max-width: 1200px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.card-title i {
    color: var(--primary-light);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.connected {
    background: rgba(34, 197, 94, 0.15);
    color: var(--success);
}

.status-badge.disconnected {
    background: rgba(239, 68, 68, 0.15);
    color: var(--danger);
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

.form-help a {
    color: var(--primary-light);
}

.input-with-button {
    display: flex;
    gap: 0.5rem;
}

.input-with-button .form-input {
    flex: 1;
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

.provider-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 768px) {
    .provider-cards {
        grid-template-columns: 1fr;
    }
}

.provider-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: var(--bg-hover);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.provider-card:hover {
    border-color: var(--primary);
}

.provider-card.selected {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.provider-card input {
    display: none;
}

.provider-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.provider-info i {
    font-size: 1.5rem;
    color: var(--primary-light);
    margin-bottom: 0.5rem;
}

.provider-info strong {
    color: var(--text-primary);
}

.provider-info small {
    color: var(--text-muted);
    font-size: 0.8rem;
}

.provider-settings {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.info-box {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 8px;
    margin-top: 1rem;
}

.info-box i {
    color: var(--info);
    flex-shrink: 0;
}

.info-box strong {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
}

.info-box p, .info-box ul {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0;
}

.info-box code {
    background: var(--bg-hover);
    padding: 0.125rem 0.375rem;
    border-radius: 4px;
    font-family: monospace;
}

.size-list {
    list-style: none;
    padding: 0;
    margin: 0.5rem 0 0;
}

.size-list li {
    margin-bottom: 0.25rem;
}

.range-with-value {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.form-range {
    flex: 1;
    height: 6px;
    background: var(--bg-hover);
    border-radius: 3px;
    outline: none;
    -webkit-appearance: none;
}

.form-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    background: var(--primary);
    border-radius: 50%;
    cursor: pointer;
}

.range-value {
    min-width: 50px;
    text-align: right;
    font-weight: 500;
    color: var(--text-primary);
}

.form-actions {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.mb-2 {
    margin-bottom: 1rem;
}

.logo-upload-area {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.current-logo {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-hover);
    border-radius: 8px;
}

.logo-preview {
    max-width: 120px;
    max-height: 60px;
    object-fit: contain;
    background: var(--bg-dark);
    padding: 0.5rem;
    border-radius: 4px;
}

.remove-logo-label {
    padding: 0.5rem !important;
    background: transparent !important;
}

.logo-input input[type="file"] {
    padding: 0.5rem;
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: var(--bg-card);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    width: 100%;
    max-width: 500px;
    max-height: 80vh;
    overflow: hidden;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
}

.modal-close:hover {
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
}

.test-success {
    color: var(--success);
}

.test-error {
    color: var(--danger);
}
</style>

<script>
// Tab switching
document.querySelectorAll('.settings-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const tabId = this.dataset.tab;

        // Update tabs
        document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        // Update content
        document.querySelectorAll('.settings-tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
    });
});

// Provider card selection
document.querySelectorAll('.provider-card input').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.provider-card').forEach(card => card.classList.remove('selected'));
        this.closest('.provider-card').classList.add('selected');
    });
});

function showProviderSettings(provider) {
    document.querySelectorAll('.provider-settings').forEach(el => el.style.display = 'none');
    document.getElementById('settings-' + provider).style.display = 'block';
}

// Test functions
function showTestResult(title, message, isSuccess) {
    document.getElementById('testResultTitle').textContent = title;
    document.getElementById('testResultContent').innerHTML =
        '<div class="' + (isSuccess ? 'test-success' : 'test-error') + '">' +
        '<i class="lucide-' + (isSuccess ? 'check-circle' : 'x-circle') + '"></i> ' +
        message + '</div>';
    document.getElementById('testResultModal').style.display = 'flex';
}

function closeTestModal() {
    document.getElementById('testResultModal').style.display = 'none';
}

function testOllamaConnection() {
    fetch('/admin/settings/test-ollama', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_token=<?= $csrf ?>'
    })
    .then(r => r.json())
    .then(data => {
        let message = data.message;
        if (data.models && data.models.length > 0) {
            message += '<br><br>Available models: ' + data.models.join(', ');
        }
        showTestResult('Ollama Connection', message, data.success);
    })
    .catch(() => showTestResult('Ollama Connection', 'Connection test failed', false));
}

function testAIConnection() {
    fetch('/admin/settings/test-ai', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_token=<?= $csrf ?>'
    })
    .then(r => r.json())
    .then(data => {
        let message = data.message;
        if (data.response) {
            message += '<br><br>Response: "' + data.response.substring(0, 100) + '..."';
        }
        showTestResult('AI Connection', message, data.success);
    })
    .catch(() => showTestResult('AI Connection', 'Connection test failed', false));
}

function testFanartConnection() {
    fetch('/admin/settings/test-fanart', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_token=<?= $csrf ?>'
    })
    .then(r => r.json())
    .then(data => showTestResult('Fanart.tv Connection', data.message, data.success))
    .catch(() => showTestResult('Fanart.tv Connection', 'Connection test failed', false));
}

function testTmdbConnection() {
    fetch('/admin/settings/test-tmdb', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_token=<?= $csrf ?>'
    })
    .then(r => r.json())
    .then(data => showTestResult('TMDB Connection', data.message, data.success))
    .catch(() => showTestResult('TMDB Connection', 'Connection test failed', false));
}

function testYoutubeConnection() {
    fetch('/admin/settings/test-youtube', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_token=<?= $csrf ?>'
    })
    .then(r => r.json())
    .then(data => showTestResult('YouTube Data API Connection', data.message, data.success))
    .catch(() => showTestResult('YouTube Data API Connection', 'Connection test failed', false));
}

// Close modal on overlay click
document.getElementById('testResultModal').addEventListener('click', function(e) {
    if (e.target === this) closeTestModal();
});
</script>
