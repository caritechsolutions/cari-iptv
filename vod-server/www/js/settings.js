/**
 * VOD Server Web GUI - Settings Page
 */
var settingsPage = {

    render() {
        return `
            <div class="page-header">
                <h1>Settings</h1>
            </div>

            <div class="grid-2">
                <!-- Server Settings -->
                <div class="card">
                    <div class="card-header"><h3>Server</h3></div>
                    <div class="form-group">
                        <label>Node Name</label>
                        <input type="text" class="form-control" id="set-node-name" disabled>
                    </div>
                    <div class="form-group">
                        <label>API Port</label>
                        <input type="text" class="form-control" id="set-port" disabled>
                    </div>
                    <div class="form-group">
                        <label>API Key</label>
                        <div class="flex gap-2">
                            <input type="password" class="form-control text-mono" id="set-api-key" readonly>
                            <button class="btn btn-outline btn-sm" onclick="settingsPage.toggleApiKey()">Show</button>
                            <button class="btn btn-outline btn-sm" onclick="settingsPage.copyApiKey()">Copy</button>
                        </div>
                    </div>
                    <p class="text-sm text-muted mt-2">Server settings are configured in vod-server.conf. Restart required for changes.</p>
                </div>

                <!-- SSL/TLS Settings -->
                <div class="card">
                    <div class="card-header"><h3>SSL/TLS</h3></div>
                    <div id="ssl-status">
                        <div class="spinner"></div>
                    </div>
                </div>

                <!-- Storage Settings -->
                <div class="card">
                    <div class="card-header"><h3>Storage</h3></div>
                    <div class="form-group">
                        <label>Library Path</label>
                        <div class="flex gap-2">
                            <input type="text" class="form-control text-mono" id="set-library-path" disabled style="flex:1">
                            <button class="btn btn-outline btn-sm" onclick="settingsPage.browsePath('set-library-path')">Browse</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Temp Path</label>
                        <div class="flex gap-2">
                            <input type="text" class="form-control text-mono" id="set-temp-path" disabled style="flex:1">
                            <button class="btn btn-outline btn-sm" onclick="settingsPage.browsePath('set-temp-path')">Browse</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Min Free Space (GB)</label>
                        <input type="number" class="form-control" id="set-min-space" disabled>
                    </div>
                    <p class="text-sm text-muted mt-2">Storage paths are set in vod-server.conf. Restart required.</p>
                </div>

                <!-- Transcoding Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3>Transcoding</h3>
                    </div>
                    <div class="form-group">
                        <label>Max Concurrent Jobs</label>
                        <input type="number" class="form-control" id="set-max-jobs" min="1" max="16">
                    </div>
                    <div class="form-group">
                        <label>Default Profile</label>
                        <select class="form-control" id="set-default-profile">
                            <option value="">Loading...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>HW Acceleration</label>
                        <select class="form-control" id="set-hwaccel">
                            <option value="none">None (Software)</option>
                            <option value="nvenc">NVIDIA NVENC</option>
                            <option value="vaapi">VAAPI (Intel/AMD)</option>
                            <option value="qsv">Intel Quick Sync</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Segment Duration (seconds)</label>
                        <input type="number" class="form-control" id="set-segment-dur" min="2" max="10">
                    </div>
                    <button class="btn btn-primary btn-sm mt-2" onclick="settingsPage.saveTranscoding()">Save Transcoding Settings</button>
                </div>

                <!-- Thumbnails -->
                <div class="card">
                    <div class="card-header"><h3>Thumbnails</h3></div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="set-thumb-enabled"> Generate thumbnail sprites
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Interval (seconds)</label>
                        <input type="number" class="form-control" id="set-thumb-interval" min="1" max="60">
                    </div>
                    <div class="form-group">
                        <label>Size</label>
                        <div class="flex gap-2">
                            <input type="number" class="form-control" id="set-thumb-w" placeholder="Width" min="80" max="320">
                            <span style="line-height:2.5">x</span>
                            <input type="number" class="form-control" id="set-thumb-h" placeholder="Height" min="45" max="180">
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm mt-2" onclick="settingsPage.saveThumbnails()">Save Thumbnail Settings</button>
                </div>

                <!-- Cluster -->
                <div class="card">
                    <div class="card-header"><h3>Cluster</h3></div>
                    <div class="form-group">
                        <label>Health Check Interval (seconds)</label>
                        <input type="number" class="form-control" id="set-health-interval" min="10" max="300">
                    </div>
                    <div class="form-group">
                        <label>Offline Threshold (missed checks)</label>
                        <input type="number" class="form-control" id="set-offline-threshold" min="1" max="10">
                    </div>
                    <div class="form-group">
                        <label>Max Concurrent Migrations</label>
                        <input type="number" class="form-control" id="set-max-migrations" min="1" max="5">
                    </div>
                    <button class="btn btn-primary btn-sm mt-2" onclick="settingsPage.saveCluster()">Save Cluster Settings</button>
                </div>
            </div>

            <!-- Tools Section -->
            <div class="card mt-3">
                <div class="card-header"><h3>Tools</h3></div>
                <div class="flex gap-2">
                    <button class="btn btn-outline" onclick="settingsPage.checkTools()">Check FFmpeg / MP4Box</button>
                    <button class="btn btn-warning" onclick="settingsPage.clearTemp()">Clear Temp Files</button>
                </div>
                <div id="tools-output" class="mt-3 text-sm text-mono" style="display:none;white-space:pre-wrap;padding:12px;background:var(--bg-input);border-radius:var(--radius-sm);max-height:200px;overflow-y:auto;"></div>
            </div>`;
    },

    async init() {
        try {
            /* Fetch both status and config in parallel */
            const [status, config] = await Promise.all([
                App.get('/status'),
                App.get('/config')
            ]);

            /* Server */
            const srv = config.server || {};
            const cls = config.cluster || {};
            document.getElementById('set-node-name').value = cls.node_name || status.node_name || '';
            document.getElementById('set-port').value = srv.port || status.port || '';

            /* API Key - use the injected key from the server */
            document.getElementById('set-api-key').value = window.VOD_API_KEY || '';

            /* SSL - load detailed status */
            this.loadSSLStatus();

            /* Storage */
            const stor = config.storage || {};
            document.getElementById('set-library-path').value = stor.library_path || '';
            document.getElementById('set-temp-path').value = stor.temp_path || '';
            document.getElementById('set-min-space').value = stor.min_free_space_gb || 10;

            /* Transcoding */
            const tc = config.transcoding || {};
            document.getElementById('set-max-jobs').value = tc.max_concurrent_jobs || 2;
            document.getElementById('set-hwaccel').value = tc.hwaccel || 'none';
            document.getElementById('set-segment-dur').value = tc.segment_duration || 6;

            /* Load profile list into default profile dropdown */
            this.loadProfileDropdown(tc.default_profile || 'standard');

            /* Thumbnails */
            const th = config.thumbnails || {};
            document.getElementById('set-thumb-enabled').checked = th.enabled !== false;
            document.getElementById('set-thumb-interval').value = th.interval || 10;
            document.getElementById('set-thumb-w').value = th.width || 160;
            document.getElementById('set-thumb-h').value = th.height || 90;

            /* Cluster */
            document.getElementById('set-health-interval').value = cls.health_check_interval || 30;
            document.getElementById('set-offline-threshold').value = cls.offline_threshold || 3;
            document.getElementById('set-max-migrations').value = cls.max_concurrent_migrations || 1;

        } catch (err) {
            App.toast('Failed to load settings: ' + err.message, 'error');
        }
    },

    stop() {},

    /* --- Profile Dropdown --- */

    async loadProfileDropdown(selected) {
        const select = document.getElementById('set-default-profile');
        try {
            const data = await App.get('/profiles');
            const profiles = data.profiles || [];
            if (profiles.length === 0) {
                select.innerHTML = '<option value="standard">standard</option>';
            } else {
                select.innerHTML = profiles.map(p =>
                    `<option value="${App.esc(p.name)}" ${p.name === selected ? 'selected' : ''}>${App.esc(p.name)} (${App.esc(p.codec || '?')})</option>`
                ).join('');
            }
        } catch (err) {
            select.innerHTML = `<option value="${App.esc(selected)}">${App.esc(selected)}</option>`;
        }
    },

    /* --- SSL Section --- */

    async loadSSLStatus() {
        const container = document.getElementById('ssl-status');
        try {
            const ssl = await App.get('/ssl/status');
            this._sslData = ssl;

            let certHtml = '';
            if (ssl.certificate) {
                const c = ssl.certificate;
                certHtml = `
                    <div class="mt-2" style="padding:8px;background:var(--bg-input);border-radius:var(--radius-sm)">
                        <div class="text-sm mb-1"><strong>Certificate Details</strong></div>
                        <table class="text-sm">
                            ${c.subject ? `<tr><td class="text-muted" style="padding-right:12px">Subject</td><td>${App.esc(c.subject)}</td></tr>` : ''}
                            ${c.issuer ? `<tr><td class="text-muted">Issuer</td><td>${App.esc(c.issuer)}</td></tr>` : ''}
                            ${c.not_before ? `<tr><td class="text-muted">Valid From</td><td>${App.esc(c.not_before)}</td></tr>` : ''}
                            ${c.not_after ? `<tr><td class="text-muted">Expires</td><td>${App.esc(c.not_after)}</td></tr>` : ''}
                        </table>
                        ${c.self_signed ? '<div class="text-sm text-warning mt-1">Self-signed certificate</div>' : ''}
                    </div>`;
            }

            container.innerHTML = `
                <div class="flex-between mb-2">
                    <span>Status</span>
                    <span class="status ${ssl.ready ? 'online' : 'offline'}">${ssl.ready ? 'Active' : ssl.enabled ? 'Enabled (no cert)' : 'Disabled'}</span>
                </div>
                ${ssl.cert_file ? `<div class="text-sm text-muted mb-1">Cert: ${App.esc(ssl.cert_file)}</div>` : ''}
                ${ssl.key_file ? `<div class="text-sm text-muted mb-2">Key: ${App.esc(ssl.key_file)}</div>` : ''}
                ${certHtml}
                <div class="mt-3">
                    <div class="text-sm text-muted mb-2">Generate or obtain a certificate:</div>
                    <div class="flex gap-2 flex-wrap">
                        <button class="btn btn-primary btn-sm" onclick="settingsPage.showLetsEncryptModal()">
                            Let's Encrypt
                        </button>
                        <button class="btn btn-outline btn-sm" onclick="settingsPage.showUploadCertModal()">
                            Upload Certificate
                        </button>
                        <button class="btn btn-outline btn-sm" onclick="settingsPage.generateSelfSigned()">
                            Self-Signed
                        </button>
                    </div>
                    ${!ssl.certbot_available ? '<div class="text-sm text-warning mt-2">certbot not installed. Install: <code>apt install certbot</code> or <code>snap install certbot --classic</code></div>' : ''}
                </div>
                ${ssl.acme?.enabled ? `
                <div class="mt-2" style="padding:8px;background:var(--bg-input);border-radius:var(--radius-sm)">
                    <div class="text-sm mb-1"><strong>ACME HTTP Listener</strong> <span class="status online">Active</span></div>
                    <div class="text-sm text-muted">Port ${ssl.acme.http_port} &rarr; serves challenges &amp; redirects to HTTPS${ssl.acme.https_port !== 443 ? ` port ${ssl.acme.https_port}` : ''}</div>
                    ${ssl.acme.domain ? `<div class="text-sm text-muted">Domain: ${App.esc(ssl.acme.domain)}</div>` : ''}
                    <div class="text-sm text-muted">Webroot: ${App.esc(ssl.acme.webroot)}</div>
                </div>
                ` : ''}
                <p class="text-sm text-muted mt-2">SSL must be enabled in vod-server.conf. Restart required after certificate changes.</p>
            `;
        } catch (err) {
            container.innerHTML = `
                <div class="flex-between mb-2">
                    <span>Status</span>
                    <span class="status offline">Unknown</span>
                </div>
                <p class="text-sm text-muted">Could not load SSL status.</p>
            `;
        }
    },

    showLetsEncryptModal() {
        const acme = this._sslData?.acme;
        const acmeEnabled = acme?.enabled;

        App.showModal("Let's Encrypt SSL Certificate", `
            <p class="text-sm text-muted mb-3">
                Get a free SSL certificate. ${acmeEnabled
                    ? 'ACME HTTP listener is active — you can use webroot mode (no Cloudflare needed) or Cloudflare DNS for behind-NAT setups.'
                    : 'Uses Cloudflare DNS validation so it works behind NAT/firewalls.'}
                SSL will be auto-enabled and the server will restart automatically.
            </p>
            ${acmeEnabled ? `
            <div class="form-group">
                <label>Validation Method</label>
                <select class="form-control" id="le-method" onchange="settingsPage.toggleLeMethod()">
                    <option value="webroot">HTTP-01 (Webroot) — ACME listener on port ${acme.http_port || 80}</option>
                    <option value="cloudflare">DNS-01 (Cloudflare) — works behind NAT</option>
                </select>
            </div>
            ` : ''}
            <div class="form-group">
                <label>Domain Name *</label>
                <input type="text" class="form-control" id="le-domain" placeholder="e.g. vod1.example.com"
                    ${acme?.domain ? `value="${App.esc(acme.domain)}"` : ''}>
            </div>
            <div class="form-group" id="le-cf-group" ${acmeEnabled ? 'style="display:none"' : ''}>
                <label>Cloudflare API Token ${acmeEnabled ? '' : '*'}</label>
                <input type="password" class="form-control" id="le-cf-token" placeholder="Paste your Cloudflare API token">
                <small class="text-muted">Create at <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" style="color:var(--primary)">Cloudflare Dashboard</a> &rarr; API Tokens &rarr; Create Token &rarr; "Edit zone DNS" template. Token is saved securely for auto-renewal.</small>
            </div>
            <div class="form-group">
                <label>Email (optional)</label>
                <input type="email" class="form-control" id="le-email" placeholder="For renewal notices">
            </div>
            <div id="le-status" class="mt-2" style="display:none"></div>
        `, `
            <button class="btn btn-outline" onclick="App.closeModal()">Cancel</button>
            <button class="btn btn-primary" id="le-submit" onclick="settingsPage.requestLetsEncrypt()">Get Certificate</button>
        `);
    },

    toggleLeMethod() {
        const method = document.getElementById('le-method')?.value;
        const cfGroup = document.getElementById('le-cf-group');
        if (cfGroup) {
            cfGroup.style.display = method === 'cloudflare' ? '' : 'none';
        }
    },

    async requestLetsEncrypt() {
        const domain = document.getElementById('le-domain').value.trim();
        const cfToken = document.getElementById('le-cf-token').value.trim();
        const email = document.getElementById('le-email').value.trim();
        const statusEl = document.getElementById('le-status');
        const btn = document.getElementById('le-submit');
        const method = document.getElementById('le-method')?.value || 'cloudflare';

        if (!domain) { App.toast('Domain name is required', 'warning'); return; }
        if (method === 'cloudflare' && !cfToken) { App.toast('Cloudflare API token is required', 'warning'); return; }

        statusEl.style.display = 'block';
        statusEl.className = 'mt-2 text-sm';
        const modeText = method === 'webroot' ? 'webroot (HTTP-01)' : 'Cloudflare DNS';
        statusEl.innerHTML = `<div class="spinner" style="width:16px;height:16px;display:inline-block;vertical-align:middle"></div> Requesting certificate via ${modeText}... this may take 1-2 minutes.`;
        btn.disabled = true;

        try {
            const payload = { domain, email };
            if (method === 'cloudflare' && cfToken) {
                payload.cloudflare_token = cfToken;
            }
            const result = await App.post('/ssl/letsencrypt', payload);
            if (result.success) {
                statusEl.className = 'mt-2 text-sm text-success';
                statusEl.textContent = result.message || 'Certificate obtained! Server restarting...';
                App.toast('SSL enabled! Server restarting...', 'success');
                /* Server is restarting — wait a moment then reload */
                setTimeout(() => {
                    App.closeModal();
                    /* Try HTTPS URL if we're on HTTP */
                    const loc = window.location;
                    if (loc.protocol === 'http:') {
                        statusEl.textContent = 'Switching to HTTPS...';
                        setTimeout(() => {
                            window.location.href = 'https://' + loc.hostname + ':' + loc.port + loc.pathname;
                        }, 5000);
                    } else {
                        setTimeout(() => window.location.reload(), 5000);
                    }
                }, 3000);
            } else {
                statusEl.className = 'mt-2 text-sm text-danger';
                statusEl.innerHTML = `<strong>Failed:</strong> ${App.esc(result.error || 'Unknown error')}` +
                    (result.output ? `<pre style="margin-top:8px;max-height:200px;overflow:auto;font-size:11px;padding:8px;background:var(--bg-input);border-radius:4px">${App.esc(result.output)}</pre>` : '');
            }
        } catch (err) {
            statusEl.className = 'mt-2 text-sm text-danger';
            statusEl.textContent = 'Request failed: ' + err.message;
        }
        btn.disabled = false;
    },

    showUploadCertModal() {
        App.showModal("Upload SSL Certificate", `
            <p class="text-sm text-muted mb-3">
                Paste your certificate and private key in PEM format. You can copy these from Nginx Proxy Manager,
                another server, or any certificate provider.
            </p>
            <div class="form-group">
                <label>Certificate (PEM) *</label>
                <textarea class="form-control" id="cert-pem" rows="6" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----" style="font-family:monospace;font-size:11px"></textarea>
            </div>
            <div class="form-group">
                <label>Private Key (PEM) *</label>
                <textarea class="form-control" id="key-pem" rows="6" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----" style="font-family:monospace;font-size:11px"></textarea>
            </div>
            <div id="upload-cert-status" class="mt-2" style="display:none"></div>
        `, `
            <button class="btn btn-outline" onclick="App.closeModal()">Cancel</button>
            <button class="btn btn-primary" id="upload-cert-btn" onclick="settingsPage.uploadCert()">Upload Certificate</button>
        `);
    },

    async uploadCert() {
        const cert = document.getElementById('cert-pem').value.trim();
        const key = document.getElementById('key-pem').value.trim();
        const statusEl = document.getElementById('upload-cert-status');
        const btn = document.getElementById('upload-cert-btn');

        if (!cert || !key) {
            App.toast('Both certificate and private key are required', 'warning');
            return;
        }

        statusEl.style.display = 'block';
        statusEl.className = 'mt-2 text-sm';
        statusEl.textContent = 'Uploading...';
        btn.disabled = true;

        try {
            const result = await App.post('/ssl/upload', { cert, key });
            if (result.success) {
                statusEl.className = 'mt-2 text-sm text-success';
                statusEl.textContent = result.message || 'Certificate uploaded!';
                App.toast('Certificate uploaded!', 'success');
                setTimeout(() => {
                    App.closeModal();
                    this.loadSSLStatus();
                }, 2000);
            } else {
                statusEl.className = 'mt-2 text-sm text-danger';
                statusEl.textContent = 'Failed: ' + (result.error || 'Unknown error');
            }
        } catch (err) {
            statusEl.className = 'mt-2 text-sm text-danger';
            statusEl.textContent = 'Failed: ' + err.message;
        }
        btn.disabled = false;
    },

    async generateSelfSigned() {
        if (!confirm('Generate a self-signed certificate? This will overwrite any existing certificate.')) return;
        try {
            const result = await App.post('/ssl/self-signed', {});
            if (result.success) {
                App.toast(result.message || 'Self-signed certificate generated', 'success');
                this.loadSSLStatus();
            } else {
                App.toast('Failed: ' + (result.error || 'Unknown error'), 'error');
            }
        } catch (err) {
            App.toast('Failed: ' + err.message, 'error');
        }
    },

    /* --- API Key --- */

    toggleApiKey() {
        const input = document.getElementById('set-api-key');
        const btn = input.parentElement.querySelector('button');
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = 'Hide';
        } else {
            input.type = 'password';
            btn.textContent = 'Show';
        }
    },

    copyApiKey() {
        const key = window.VOD_API_KEY || '';
        if (!key) {
            App.toast('No API key configured', 'warning');
            return;
        }
        navigator.clipboard.writeText(key).then(
            () => App.toast('API key copied to clipboard', 'success'),
            () => {
                /* Fallback for non-HTTPS */
                const input = document.getElementById('set-api-key');
                input.type = 'text';
                input.select();
                document.execCommand('copy');
                input.type = 'password';
                App.toast('API key copied', 'success');
            }
        );
    },

    /* --- Directory Browse --- */

    async browsePath(inputId) {
        const input = document.getElementById(inputId);
        const startPath = input.value || '/';
        this._browseTarget = inputId;
        this._browseCurrent = startPath;
        await this.showBrowseModal(startPath);
    },

    async showBrowseModal(path) {
        try {
            const data = await App.get('/browse?path=' + encodeURIComponent(path));
            const dirs = data.directories || [];

            /* Sort alphabetically */
            dirs.sort((a, b) => a.name.localeCompare(b.name));

            /* Build parent path */
            const parentPath = path === '/' ? null : path.replace(/\/[^/]+\/?$/, '') || '/';

            const listHtml = dirs.length === 0
                ? '<div class="text-center text-muted" style="padding:16px">No subdirectories</div>'
                : dirs.map(d => `
                    <div class="browse-item" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:8px"
                         onclick="settingsPage.browseInto('${App.esc(d.path).replace(/'/g, "\\'")}')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        <span>${App.esc(d.name)}</span>
                    </div>
                `).join('');

            App.showModal('Browse Directory', `
                <div class="text-mono text-sm mb-2" style="padding:8px;background:var(--bg-input);border-radius:var(--radius-sm)">${App.esc(path)}</div>
                ${parentPath !== null ? `
                    <div class="browse-item" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:8px"
                         onclick="settingsPage.browseInto('${App.esc(parentPath).replace(/'/g, "\\'")}')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        <span>..</span>
                    </div>
                ` : ''}
                <div style="max-height:300px;overflow-y:auto;border:1px solid var(--border-color);border-radius:var(--radius-sm)">
                    ${listHtml}
                </div>
            `, `
                <button class="btn btn-outline" onclick="App.closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="settingsPage.selectBrowsePath('${App.esc(path).replace(/'/g, "\\'")}')">Select This Directory</button>
            `);
        } catch (err) {
            App.toast('Cannot browse: ' + err.message, 'error');
        }
    },

    browseInto(path) {
        this.showBrowseModal(path);
    },

    selectBrowsePath(path) {
        if (this._browseTarget) {
            document.getElementById(this._browseTarget).value = path;
        }
        App.closeModal();
    },

    /* --- Save handlers --- */

    async saveTranscoding() {
        try {
            await App.post('/config', {
                max_concurrent_jobs: parseInt(document.getElementById('set-max-jobs').value),
                default_profile: document.getElementById('set-default-profile').value,
                hwaccel: document.getElementById('set-hwaccel').value,
                segment_duration: parseInt(document.getElementById('set-segment-dur').value)
            });
            App.toast('Transcoding settings saved', 'success');
        } catch (err) {
            App.toast('Failed to save: ' + err.message, 'error');
        }
    },

    async saveThumbnails() {
        try {
            await App.post('/config', {
                thumbnails_enabled: document.getElementById('set-thumb-enabled').checked,
                thumb_interval: parseInt(document.getElementById('set-thumb-interval').value),
                thumb_width: parseInt(document.getElementById('set-thumb-w').value),
                thumb_height: parseInt(document.getElementById('set-thumb-h').value)
            });
            App.toast('Thumbnail settings saved', 'success');
        } catch (err) {
            App.toast('Failed to save: ' + err.message, 'error');
        }
    },

    async saveCluster() {
        try {
            await App.post('/config', {
                health_check_interval: parseInt(document.getElementById('set-health-interval').value),
                offline_threshold: parseInt(document.getElementById('set-offline-threshold').value),
                max_concurrent_migrations: parseInt(document.getElementById('set-max-migrations').value)
            });
            App.toast('Cluster settings saved', 'success');
        } catch (err) {
            App.toast('Failed to save: ' + err.message, 'error');
        }
    },

    /* --- Tools --- */

    async checkTools() {
        const output = document.getElementById('tools-output');
        output.style.display = 'block';
        output.textContent = 'Checking tools...\n';
        try {
            const [status, config] = await Promise.all([
                App.get('/status'),
                App.get('/config')
            ]);
            const tc = config.transcoding || {};
            output.textContent = '';
            output.textContent += `FFmpeg:  ${status.ffmpeg_available ? 'Available' : 'NOT FOUND'}`;
            if (tc.ffmpeg_path) output.textContent += `  (${tc.ffmpeg_path})`;
            output.textContent += '\n';
            output.textContent += `FFprobe: ${status.ffprobe_available ? 'Available' : 'NOT FOUND'}`;
            if (tc.ffprobe_path) output.textContent += `  (${tc.ffprobe_path})`;
            output.textContent += '\n';
            output.textContent += `MP4Box:  ${status.mp4box_available ? 'Available' : 'NOT FOUND'}\n`;
        } catch (err) {
            output.textContent += `Error: ${err.message}\n`;
        }
    },

    async clearTemp() {
        if (!confirm('Clear all temporary transcoding files?')) return;
        try {
            await App.post('/config', { action: 'clear_temp' });
            App.toast('Temp files cleared', 'success');
        } catch (err) {
            App.toast('Failed: ' + err.message, 'error');
        }
    }
};
