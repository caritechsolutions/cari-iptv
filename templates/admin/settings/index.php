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
    <button class="settings-tab" data-tab="ads">
        <i class="lucide-megaphone"></i> Advertising
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
                        <small class="form-help">Public URL of your site (used for emails, player API, and DRM license URLs)</small>
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

<!-- Integrations Tab (Metadata APIs + VOD Servers) -->
<div class="settings-tab-content" id="tab-integrations">
    <div class="settings-grid">
        <!-- VOD Servers -->
        <div class="card full-width">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="lucide-hard-drive"></i>
                    VOD Servers
                </h3>
                <button type="button" class="btn btn-sm btn-primary" onclick="vodShowAddServer()">
                    <i class="lucide-plus"></i> Add Server
                </button>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">Configure VOD transcoding servers. Content from Movies and TV Shows can be sent to these servers for processing.</p>

                <div id="vod-server-list">
                    <?php if (!empty($vodServers)): ?>
                        <?php foreach ($vodServers as $srv): ?>
                            <div class="vod-server-row" id="vod-srv-<?= $srv['id'] ?>">
                                <span class="vod-srv-indicator" style="background:<?= $srv['is_active'] ? 'var(--success)' : 'var(--danger)' ?>"></span>
                                <div class="vod-srv-info">
                                    <div class="vod-srv-name">
                                        <?= htmlspecialchars($srv['name']) ?>
                                        <?php if ($srv['is_default']): ?><span class="badge badge-primary" style="font-size:0.65rem;padding:0.1rem 0.4rem">default</span><?php endif; ?>
                                        <?php if (!$srv['is_active']): ?><span class="badge badge-danger" style="font-size:0.65rem;padding:0.1rem 0.4rem">inactive</span><?php endif; ?>
                                    </div>
                                    <div class="vod-srv-url"><?= htmlspecialchars($srv['url']) ?></div>
                                </div>
                                <div class="vod-srv-actions">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="vodTestServer(<?= $srv['id'] ?>)" title="Test Connection">
                                        <i class="lucide-plug"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="vodEditServer(<?= $srv['id'] ?>)" title="Edit">
                                        <i class="lucide-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="vodDeleteServer(<?= $srv['id'] ?>, '<?= htmlspecialchars(addslashes($srv['name'])) ?>')" title="Delete">
                                        <i class="lucide-trash-2"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="vod-empty-state" id="vod-empty">
                            <i class="lucide-hard-drive" style="font-size:2rem;color:var(--text-muted);margin-bottom:0.5rem"></i>
                            <p>No VOD servers configured yet.</p>
                            <button type="button" class="btn btn-sm btn-primary" onclick="vodShowAddServer()">
                                <i class="lucide-plus"></i> Add Your First Server
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

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

        <!-- OpenSubtitles Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="lucide-subtitles"></i>
                    OpenSubtitles
                </h3>
                <span class="status-badge <?= ($integrationStatus['subtitles']['configured'] ?? false) ? 'connected' : 'disconnected' ?>">
                    <?= ($integrationStatus['subtitles']['configured'] ?? false) ? 'Configured' : 'Not Configured' ?>
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">OpenSubtitles provides subtitles in 60+ languages for movies and TV shows. Used as the primary subtitle source with FFmpeg extraction as fallback.</p>

                <form action="/admin/settings/subtitles" method="POST">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">

                    <div class="form-group">
                        <label class="form-label" for="opensubtitles_api_key">API Key</label>
                        <div class="input-with-button">
                            <input type="password" id="opensubtitles_api_key" name="opensubtitles_api_key" class="form-input"
                                   placeholder="<?= !empty($settings['subtitles']['opensubtitles_api_key']) ? '••••••••••••••••' : 'Enter API key' ?>"
                                   autocomplete="off">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="testOpenSubtitlesConnection()">
                                <i class="lucide-plug"></i> Test
                            </button>
                        </div>
                        <small class="form-help">
                            Get a free API key at <a href="https://www.opensubtitles.com/en/consumers" target="_blank">opensubtitles.com/consumers</a>
                        </small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="opensubtitles_username">Username</label>
                            <input type="text" id="opensubtitles_username" name="opensubtitles_username" class="form-input"
                                   value="<?= htmlspecialchars($settings['subtitles']['opensubtitles_username'] ?? '') ?>"
                                   placeholder="OpenSubtitles username" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="opensubtitles_password">Password</label>
                            <input type="password" id="opensubtitles_password" name="opensubtitles_password" class="form-input"
                                   placeholder="<?= !empty($settings['subtitles']['opensubtitles_password']) ? '••••••••' : 'Password' ?>"
                                   autocomplete="off">
                        </div>
                    </div>
                    <small class="form-help" style="margin-top:-0.5rem;display:block;margin-bottom:1rem">Username and password are required for downloading subtitles. API key alone only allows searching.</small>

                    <div class="form-group">
                        <label class="form-label" for="preferred_languages">Preferred Languages</label>
                        <input type="text" id="preferred_languages" name="preferred_languages" class="form-input"
                               value="<?= htmlspecialchars($settings['subtitles']['preferred_languages'] ?? 'en') ?>"
                               placeholder="en,es,fr">
                        <small class="form-help">Comma-separated ISO 639-1 codes. These languages will be fetched by default when searching for subtitles.</small>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="auto_fetch_subtitles" value="1"
                                   <?= !empty($settings['subtitles']['auto_fetch_subtitles']) ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Auto-fetch Subtitles</strong>
                                <small>Automatically search for subtitles when importing movies from TMDB</small>
                            </span>
                        </label>
                    </div>

                    <div class="info-box">
                        <i class="lucide-info"></i>
                        <div>
                            <strong>Free Tier Limits</strong>
                            <p>OpenSubtitles free tier allows 20 subtitle downloads per day and 5 requests per second.</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="lucide-save"></i> Save Subtitle Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Embedded Subtitle Extraction Info -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="lucide-file-text"></i>
                    Embedded Subtitle Extraction
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">When transcoding via the VOD server, embedded subtitle tracks (SRT, ASS, WebVTT, etc.) can be automatically extracted and included in the HLS output.</p>

                <div class="info-box">
                    <i class="lucide-info"></i>
                    <div>
                        <strong>How It Works</strong>
                        <p>The VOD server uses FFmpeg to detect and extract text-based subtitle tracks from source files during transcoding. Extracted subtitles are converted to WebVTT format and added to the HLS master playlist.</p>
                        <p style="margin-top:0.5rem"><strong>Subtitle sources (in order):</strong></p>
                        <ol style="margin:0.25rem 0 0 1.5rem">
                            <li>OpenSubtitles API (configured above)</li>
                            <li>Embedded subtitle extraction during VOD transcode</li>
                            <li>Manual SRT/VTT upload on the movie edit page</li>
                        </ol>
                    </div>
                </div>

                <div class="info-box" style="margin-top:0.75rem">
                    <i class="lucide-settings"></i>
                    <div>
                        <strong>Configuration</strong>
                        <p>Subtitle extraction is enabled/disabled in the VOD Server settings. Go to <strong>VOD Server GUI &rarr; Settings &rarr; Subtitles</strong> to toggle auto-extraction during transcoding.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- VOD Server Add/Edit Modal -->
<div class="modal-overlay" id="vodServerModal" style="display:none">
    <div class="modal-content" style="max-width:550px">
        <div class="modal-header">
            <h3 id="vodModalTitle">Add VOD Server</h3>
            <button type="button" class="modal-close" onclick="vodCloseModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="vodServerForm" onsubmit="return vodSaveServer(event)">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="server_id" id="vod-edit-id" value="">

                <div class="form-group">
                    <label class="form-label">Server Name *</label>
                    <input type="text" class="form-input" name="name" id="vod-srv-name" placeholder="e.g. Primary VOD Server" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Server URL *</label>
                    <input type="url" class="form-input" name="url" id="vod-srv-url" placeholder="http://192.168.1.100:8090" required>
                    <small class="form-help">Full URL including port (default: 8090)</small>
                </div>

                <div class="form-group">
                    <label class="form-label">API Key</label>
                    <input type="password" class="form-input" name="api_key" id="vod-srv-apikey" placeholder="VOD Server API key" autocomplete="off">
                    <small class="form-help">Found in /etc/vod-server/vod-server.conf</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label" style="padding:0.75rem">
                            <input type="checkbox" name="is_default" id="vod-srv-default" value="1">
                            <span class="checkbox-text"><strong>Default server</strong></span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label" style="padding:0.75rem">
                            <input type="checkbox" name="is_active" id="vod-srv-active" value="1" checked>
                            <span class="checkbox-text"><strong>Active</strong></span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea class="form-input" name="notes" id="vod-srv-notes" rows="2" placeholder="Optional notes"></textarea>
                </div>

                <div id="vod-test-result" style="display:none;padding:0.75rem;border-radius:8px;font-size:0.85rem;margin-bottom:1rem"></div>

                <div class="form-actions" style="justify-content:space-between">
                    <button type="button" class="btn btn-secondary" onclick="vodTestFromForm()">
                        <i class="lucide-plug"></i> Test Connection
                    </button>
                    <button type="submit" class="btn btn-primary" id="vod-save-btn">
                        <i class="lucide-save"></i> Save Server
                    </button>
                </div>
            </form>
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
                                <div class="input-with-button">
                                    <select id="ollama_model" name="ollama_model" class="form-input">
                                        <option value="<?= htmlspecialchars($settings['ai']['ollama_model'] ?? 'llama3.2:1b') ?>" selected>
                                            <?= htmlspecialchars($settings['ai']['ollama_model'] ?? 'llama3.2:1b') ?> (current)
                                        </option>
                                    </select>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="refreshOllamaModels()" title="Refresh model list from Ollama">
                                        <i class="lucide-refresh-cw"></i>
                                    </button>
                                </div>
                                <small class="form-help">Click refresh or Test to load available models from Ollama</small>
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
                        <button type="button" class="btn btn-primary" onclick="saveAISettingsBtn(this)">
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

<!-- Advertising Tab -->
<div class="settings-tab-content" id="tab-ads">
    <div class="settings-grid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="lucide-image"></i>
                    Banner Overlay Settings
                </h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">Configure how banner overlay ads appear during live TV and VOD playback.</p>

                <form action="/admin/settings/ads" method="POST">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">

                    <div class="form-group">
                        <label class="form-label" for="banner_initial_delay">Initial Delay (seconds)</label>
                        <input type="number" id="banner_initial_delay" name="banner_initial_delay" class="form-control"
                               min="0" max="600" step="5"
                               value="<?= htmlspecialchars($settings['ads']['banner_initial_delay'] ?? '30') ?>">
                        <small class="form-help">How long to wait after playback starts before showing the first banner ad. (Default: 30s)</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="banner_display_duration">Display Duration (seconds)</label>
                        <input type="number" id="banner_display_duration" name="banner_display_duration" class="form-control"
                               min="5" max="120" step="5"
                               value="<?= htmlspecialchars($settings['ads']['banner_display_duration'] ?? '15') ?>">
                        <small class="form-help">How long the banner stays visible before auto-dismissing. (Default: 15s)</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="banner_repeat_interval">Repeat Interval (seconds)</label>
                        <input type="number" id="banner_repeat_interval" name="banner_repeat_interval" class="form-control"
                               min="0" max="3600" step="30"
                               value="<?= htmlspecialchars($settings['ads']['banner_repeat_interval'] ?? '300') ?>">
                        <small class="form-help">Time between banner appearances. Set to 0 to show only once. (Default: 300s / 5 minutes)</small>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="banner_enabled" value="1"
                                   <?= ($settings['ads']['banner_enabled'] ?? '1') ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Enable Banner Overlays</strong>
                                <small>Show banner overlay ads during playback</small>
                            </span>
                        </label>
                    </div>

                    <div class="form-divider"></div>

                    <h4 style="margin: 1rem 0 0.5rem; color: var(--text-primary);">
                        <i class="lucide-type"></i> Text Scroller Settings
                    </h4>

                    <div class="form-group">
                        <label class="form-label" for="scroller_initial_delay">Initial Delay (seconds)</label>
                        <input type="number" id="scroller_initial_delay" name="scroller_initial_delay" class="form-control"
                               min="0" max="600" step="5"
                               value="<?= htmlspecialchars($settings['ads']['scroller_initial_delay'] ?? '15') ?>">
                        <small class="form-help">How long to wait before showing the first text scroller. (Default: 15s)</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="scroller_repeat_interval">Repeat Interval (seconds)</label>
                        <input type="number" id="scroller_repeat_interval" name="scroller_repeat_interval" class="form-control"
                               min="0" max="3600" step="30"
                               value="<?= htmlspecialchars($settings['ads']['scroller_repeat_interval'] ?? '300') ?>">
                        <small class="form-help">Time between text scroller appearances. Set to 0 to show only once. (Default: 300s / 5 minutes)</small>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="scroller_enabled" value="1"
                                   <?= ($settings['ads']['scroller_enabled'] ?? '1') ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Enable Text Scrollers</strong>
                                <small>Show text scroller ads during playback</small>
                            </span>
                        </label>
                    </div>

                    <div class="info-box">
                        <i class="lucide-info"></i>
                        <div>
                            <strong>How Ad Rotation Works</strong>
                            <p style="margin: 0.25rem 0 0;">After the initial delay, the ad is shown for the display duration, then hidden. After the repeat interval passes, a fresh ad is fetched and shown again. This continues as long as the viewer is watching. Setting the repeat interval to 0 means the ad shows only once per session.</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="lucide-save"></i> Save Advertising Settings
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

/* VOD Server styles */
.vod-server-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 0.5rem;
    transition: background 0.15s;
}
.vod-server-row:hover { background: var(--bg-hover); }
.vod-srv-indicator { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.vod-srv-info { flex: 1; min-width: 0; }
.vod-srv-name { font-weight: 500; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
.vod-srv-url { font-size: 0.8rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.vod-srv-actions { display: flex; gap: 0.25rem; flex-shrink: 0; }
.vod-empty-state { text-align: center; padding: 2rem 1rem; color: var(--text-muted); }
</style>

<script>
// Tab switching
document.querySelectorAll('.settings-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const tabId = this.dataset.tab;
        switchSettingsTab(tabId);
    });
});

function switchSettingsTab(tabId) {
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.settings-tab-content').forEach(c => c.classList.remove('active'));
    var tabBtn = document.querySelector('.settings-tab[data-tab="' + tabId + '"]');
    if (tabBtn) tabBtn.classList.add('active');
    var tabContent = document.getElementById('tab-' + tabId);
    if (tabContent) tabContent.classList.add('active');
}

// Auto-select tab from URL ?tab= parameter
(function() {
    var params = new URLSearchParams(window.location.search);
    var tab = params.get('tab');
    if (tab) switchSettingsTab(tab);
})();

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

    // Update the provider badge in the card header
    var badge = document.querySelector('.card.full-width .status-badge');
    if (badge) {
        var names = {ollama: 'Ollama (Local)', openai: 'OpenAI', anthropic: 'Anthropic'};
        badge.textContent = (names[provider] || provider) + ' (unsaved)';
        badge.className = 'status-badge disconnected';
    }
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

function refreshOllamaModels() {
    fetch('/admin/settings/test-ollama', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_token=<?= $csrf ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.models && data.models.length > 0) {
            populateOllamaModels(data.models);
        } else {
            showTestResult('Ollama Models', data.message || 'Could not load models from Ollama.', false);
        }
    })
    .catch(() => showTestResult('Ollama Models', 'Connection failed', false));
}

function populateOllamaModels(models) {
    var select = document.getElementById('ollama_model');
    var currentValue = select.value;
    select.innerHTML = '';

    models.forEach(function(model) {
        var opt = document.createElement('option');
        opt.value = model;
        opt.textContent = model;
        if (model === currentValue) opt.selected = true;
        select.appendChild(opt);
    });

    // If current value not in list, add it at top
    if (currentValue && !models.includes(currentValue)) {
        var opt = document.createElement('option');
        opt.value = currentValue;
        opt.textContent = currentValue + ' (not installed)';
        opt.selected = true;
        select.insertBefore(opt, select.firstChild);
    }
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
            populateOllamaModels(data.models);
        }
        showTestResult('Ollama Connection', message, data.success);
    })
    .catch(() => showTestResult('Ollama Connection', 'Connection test failed', false));
}

function saveAISettings(callback) {
    var form = document.getElementById('aiForm');
    var formData = new FormData(form);

    fetch('/admin/settings/ai', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: new URLSearchParams(formData)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update the provider badge
            var badge = document.querySelector('.card.full-width .status-badge');
            if (badge && data.provider_name) {
                badge.textContent = data.provider_name;
                badge.className = 'status-badge connected';
            }
        }
        if (callback) callback(data);
    })
    .catch(err => {
        if (callback) callback({success: false, message: 'Failed to save settings: ' + err.message});
    });
}

function saveAISettingsBtn(btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="lucide-loader"></i> Saving...';

    saveAISettings(function(data) {
        if (data.success) {
            showTestResult('AI Settings', 'AI settings saved successfully. Active provider: <strong>' + (data.provider_name || 'Unknown') + '</strong>', true);
        } else {
            showTestResult('AI Settings', 'Failed to save: ' + (data.message || 'Unknown error'), false);
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="lucide-save"></i> Save AI Settings';
    });
}

function testAIConnection() {
    var btn = document.querySelector('[onclick="testAIConnection()"]');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="lucide-loader"></i> Saving & Testing...'; }

    // Save settings first, then test
    saveAISettings(function(saveResult) {
        if (!saveResult.success) {
            showTestResult('AI Settings', 'Failed to save: ' + (saveResult.message || 'Unknown error'), false);
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="lucide-zap"></i> Test AI Connection'; }
            return;
        }

        // Now test with the saved settings
        fetch('/admin/settings/test-ai', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: '_token=<?= $csrf ?>'
        })
        .then(r => r.json())
        .then(data => {
            let message = data.message;
            if (data.response) {
                message += '<br><br>Response: "' + data.response.substring(0, 200) + '"';
            }
            if (data.model) {
                message += '<br><small>Model: ' + data.model + '</small>';
            }
            showTestResult('AI Connection', message, data.success);
        })
        .catch(() => showTestResult('AI Connection', 'Connection test failed. Check server logs.', false))
        .finally(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="lucide-zap"></i> Test AI Connection'; }
        });
    });
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

function testOpenSubtitlesConnection() {
    fetch('/admin/settings/test-opensubtitles', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_token=<?= $csrf ?>'
    })
    .then(r => r.json())
    .then(data => showTestResult('OpenSubtitles Connection', data.message, data.success))
    .catch(() => showTestResult('OpenSubtitles Connection', 'Connection test failed', false));
}

// Close modal on overlay click
document.getElementById('testResultModal').addEventListener('click', function(e) {
    if (e.target === this) closeTestModal();
});

/* ===================== VOD Server Management ===================== */
var vodServersData = <?= json_encode($vodServers ?? []) ?>;
var vodCsrf = '<?= $csrf ?>';

function vodShowAddServer() {
    document.getElementById('vodModalTitle').textContent = 'Add VOD Server';
    document.getElementById('vod-edit-id').value = '';
    document.getElementById('vodServerForm').reset();
    document.getElementById('vod-srv-active').checked = true;
    document.getElementById('vod-srv-default').checked = vodServersData.length === 0;
    document.getElementById('vod-test-result').style.display = 'none';
    document.getElementById('vod-save-btn').innerHTML = '<i class="lucide-save"></i> Add Server';
    document.getElementById('vodServerModal').style.display = 'flex';
}

function vodEditServer(id) {
    var srv = vodServersData.find(function(s) { return s.id == id; });
    if (!srv) return;

    document.getElementById('vodModalTitle').textContent = 'Edit VOD Server';
    document.getElementById('vod-edit-id').value = id;
    document.getElementById('vod-srv-name').value = srv.name;
    document.getElementById('vod-srv-url').value = srv.url;
    document.getElementById('vod-srv-apikey').value = '';
    document.getElementById('vod-srv-default').checked = !!srv.is_default;
    document.getElementById('vod-srv-active').checked = srv.is_active != 0;
    document.getElementById('vod-srv-notes').value = srv.notes || '';
    document.getElementById('vod-test-result').style.display = 'none';
    document.getElementById('vod-save-btn').innerHTML = '<i class="lucide-save"></i> Update Server';
    document.getElementById('vodServerModal').style.display = 'flex';
}

function vodCloseModal() {
    document.getElementById('vodServerModal').style.display = 'none';
}

function vodSaveServer(e) {
    e.preventDefault();
    var editId = document.getElementById('vod-edit-id').value;
    var isEdit = editId && editId !== '';
    var btn = document.getElementById('vod-save-btn');
    btn.disabled = true;

    var body = new URLSearchParams(new FormData(document.getElementById('vodServerForm')));
    if (!document.getElementById('vod-srv-active').checked) {
        body.set('is_active', '0');
    }

    var url = isEdit
        ? '/admin/vod-server/servers/' + editId + '/update'
        : '/admin/vod-server/servers/add';

    fetch(url, { method: 'POST', body: body })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showTestResult('VOD Server', data.message || 'Server saved successfully!', true);
                vodCloseModal();
                // Reload to reflect changes
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                showTestResult('VOD Server', data.error || 'Failed to save server.', false);
            }
        })
        .catch(function(err) {
            showTestResult('VOD Server', 'Error: ' + err.message, false);
        })
        .finally(function() { btn.disabled = false; });

    return false;
}

function vodDeleteServer(id, name) {
    if (!confirm('Delete server "' + name + '"? This only removes it from the admin panel.')) return;

    fetch('/admin/vod-server/servers/' + id + '/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(vodCsrf)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var el = document.getElementById('vod-srv-' + id);
            if (el) el.remove();
            vodServersData = vodServersData.filter(function(s) { return s.id != id; });
            if (vodServersData.length === 0) {
                document.getElementById('vod-server-list').innerHTML =
                    '<div class="vod-empty-state" id="vod-empty">' +
                    '<i class="lucide-hard-drive" style="font-size:2rem;color:var(--text-muted);margin-bottom:0.5rem"></i>' +
                    '<p>No VOD servers configured yet.</p>' +
                    '<button type="button" class="btn btn-sm btn-primary" onclick="vodShowAddServer()"><i class="lucide-plus"></i> Add Your First Server</button>' +
                    '</div>';
            }
            showTestResult('VOD Server', 'Server removed.', true);
        } else {
            showTestResult('VOD Server', data.error || 'Failed to delete.', false);
        }
    })
    .catch(function(err) { showTestResult('VOD Server', err.message, false); });
}

function vodTestServer(id) {
    var srv = vodServersData.find(function(s) { return s.id == id; });
    if (!srv) return;

    fetch('/admin/vod-server/test-connection', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(vodCsrf) + '&url=' + encodeURIComponent(srv.url) + '&api_key=' + encodeURIComponent(srv.api_key || '')
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showTestResult('VOD Server: ' + srv.name,
                'Connected! Server: <strong>' + (data.node_name || '') + '</strong> v' + (data.version || '') +
                ' | Content: ' + (data.content_count || 0) + ' | Jobs: ' + (data.active_jobs || 0), true);
        } else {
            showTestResult('VOD Server: ' + srv.name, 'Connection failed: ' + (data.error || 'Unknown error'), false);
        }
    })
    .catch(function(err) { showTestResult('VOD Server', err.message, false); });
}

function vodTestFromForm() {
    var url = document.getElementById('vod-srv-url').value;
    var apiKey = document.getElementById('vod-srv-apikey').value;
    var resultDiv = document.getElementById('vod-test-result');

    if (!url) { alert('Enter a server URL first.'); return; }

    resultDiv.style.display = 'block';
    resultDiv.style.background = 'rgba(99, 102, 241, 0.1)';
    resultDiv.style.color = 'var(--text-secondary)';
    resultDiv.textContent = 'Testing connection...';

    fetch('/admin/vod-server/test-connection', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(vodCsrf) + '&url=' + encodeURIComponent(url) + '&api_key=' + encodeURIComponent(apiKey)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            resultDiv.style.background = 'rgba(34, 197, 94, 0.1)';
            resultDiv.style.color = 'var(--success)';
            resultDiv.innerHTML = 'Connected! Server: <strong>' + (data.node_name || '') + '</strong> v' + (data.version || '') +
                ' | Content: ' + (data.content_count || 0) + ' items | Jobs: ' + (data.active_jobs || 0);
        } else {
            resultDiv.style.background = 'rgba(239, 68, 68, 0.1)';
            resultDiv.style.color = 'var(--danger)';
            resultDiv.textContent = 'Failed: ' + (data.error || 'Unknown error');
        }
    })
    .catch(function(err) {
        resultDiv.style.background = 'rgba(239, 68, 68, 0.1)';
        resultDiv.style.color = 'var(--danger)';
        resultDiv.textContent = 'Error: ' + err.message;
    });
}

document.getElementById('vodServerModal').addEventListener('click', function(e) {
    if (e.target === this) vodCloseModal();
});
</script>
