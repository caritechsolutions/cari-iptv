/*
 * drm.js - DRM Key Management page for VOD Server Web GUI
 *
 * Manages CENC encryption keys (ClearKey) with a migration path
 * to Widevine. Shows key status, allows generation/deletion,
 * and provides integration info for players.
 */

var drmPage = {
    interval: null,
    keys: [],
    config: null,

    render: function () {
        return '<div class="page-header">' +
            '<h1>DRM Key Management</h1>' +
            '<div class="header-actions">' +
                '<button class="btn btn-primary" onclick="drmPage.showGenerateModal()">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>' +
                    ' Generate Key' +
                '</button>' +
                '<button class="btn btn-outline" onclick="drmPage.refresh()">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>' +
                    ' Refresh' +
                '</button>' +
            '</div>' +
        '</div>' +

        '<div id="drm-status-cards" class="stats-grid" style="margin-bottom:24px"></div>' +

        '<div id="drm-keys-section">' +
            '<div class="card">' +
                '<div class="card-header">' +
                    '<h3>Encryption Keys</h3>' +
                '</div>' +
                '<div id="drm-keys-table">Loading...</div>' +
            '</div>' +
        '</div>';
    },

    init: function () {
        this.refresh();
        this.interval = setInterval(function () { drmPage.refresh(); }, 15000);
    },

    stop: function () {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    },

    refresh: function () {
        var self = this;

        Promise.all([
            App.get('/drm/keys'),
            App.get('/config')
        ]).then(function (results) {
            self.keys = results[0].items || [];
            self.config = results[1].drm || {};
            self.renderStatusCards(results[0]);
            self.renderKeysTable();
        }).catch(function () {
            /* silent on poll failure */
        });
    },

    renderStatusCards: function (data) {
        var enabled = this.config.enabled;
        var scheme = (this.config.scheme || 'cenc').toUpperCase();
        var autoGen = this.config.auto_generate;

        var html = '<div class="stat-card">' +
            '<span class="stat-label">DRM Status</span>' +
            '<span class="stat-value" style="color:' + (enabled ? 'var(--success)' : 'var(--text-muted)') + '">' +
                (enabled ? 'Enabled' : 'Disabled') +
            '</span>' +
        '</div>' +
        '<div class="stat-card">' +
            '<span class="stat-label">Encryption Scheme</span>' +
            '<span class="stat-value">' + App.esc(scheme) + '</span>' +
            '<span class="stat-sub">' + (scheme === 'CENC' ? 'Widevine/PlayReady compatible' : 'FairPlay compatible') + '</span>' +
        '</div>' +
        '<div class="stat-card">' +
            '<span class="stat-label">Total Keys</span>' +
            '<span class="stat-value">' + (data.total || 0) + '</span>' +
        '</div>' +
        '<div class="stat-card">' +
            '<span class="stat-label">Auto Generate</span>' +
            '<span class="stat-value" style="color:' + (autoGen ? 'var(--success)' : 'var(--text-muted)') + '">' +
                (autoGen ? 'On' : 'Off') +
            '</span>' +
            '<span class="stat-sub">For new transcode jobs</span>' +
        '</div>';

        document.getElementById('drm-status-cards').innerHTML = html;
    },

    renderKeysTable: function () {
        var keys = this.keys;

        if (keys.length === 0) {
            document.getElementById('drm-keys-table').innerHTML =
                '<div class="empty-state" style="padding:48px 24px">' +
                    '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>' +
                    '<h3>No DRM Keys</h3>' +
                    '<p>Generate keys for content items to enable encryption.</p>' +
                    '<button class="btn btn-primary" onclick="drmPage.showGenerateModal()" style="margin-top:12px">Generate Key</button>' +
                '</div>';
            return;
        }

        var html = '<div class="table-responsive"><table>' +
            '<thead><tr>' +
                '<th>Content ID</th>' +
                '<th>Key ID (KID)</th>' +
                '<th>Scheme</th>' +
                '<th>Created</th>' +
                '<th>Actions</th>' +
            '</tr></thead><tbody>';

        for (var i = 0; i < keys.length; i++) {
            var k = keys[i];
            html += '<tr>' +
                '<td><code>' + App.esc(k.content_id) + '</code></td>' +
                '<td><code style="font-size:0.8em">' + App.esc(k.kid || k.kid_hex) + '</code></td>' +
                '<td><span class="status complete">' + App.esc(k.scheme || 'cenc') + '</span></td>' +
                '<td>' + App.esc(k.created_at || '') + '</td>' +
                '<td>' +
                    '<button class="btn btn-sm btn-outline" onclick="drmPage.viewKey(\'' + App.esc(k.content_id) + '\')" title="View Details">Details</button> ' +
                    '<button class="btn btn-sm btn-danger" onclick="drmPage.deleteKey(\'' + App.esc(k.content_id) + '\')" title="Delete Key">Delete</button>' +
                '</td>' +
            '</tr>';
        }

        html += '</tbody></table></div>';
        document.getElementById('drm-keys-table').innerHTML = html;
    },

    showGenerateModal: function () {
        var body = '<div class="form-group">' +
            '<label>Content ID</label>' +
            '<input type="text" id="drm-gen-content-id" class="form-control" placeholder="e.g. movie-123, episode-456" required>' +
        '</div>' +
        '<div class="form-group">' +
            '<label>Encryption Scheme</label>' +
            '<select id="drm-gen-scheme" class="form-control">' +
                '<option value="cenc" selected>CENC (AES-128-CTR) — Widevine/PlayReady</option>' +
                '<option value="cbcs">CBCS (AES-128-CBC) — FairPlay</option>' +
            '</select>' +
        '</div>' +
        '<p style="color:var(--text-muted);font-size:0.85em;margin-top:8px">' +
            'Generates a unique AES-128 encryption key and key ID (UUID v4). ' +
            'The key uses the same CENC standard as Widevine — switching DRM providers ' +
            'later only changes the license delivery, not the encrypted content.' +
        '</p>';

        var footer = '<button class="btn btn-outline" onclick="App.closeModal()">Cancel</button>' +
            '<button class="btn btn-primary" onclick="drmPage.generateKey()">Generate</button>';

        App.showModal('Generate DRM Key', body, footer);
    },

    generateKey: function () {
        var contentId = document.getElementById('drm-gen-content-id').value.trim();
        var scheme = document.getElementById('drm-gen-scheme').value;

        if (!contentId) {
            App.toast('Content ID is required', 'warning');
            return;
        }

        App.post('/drm/keys', { content_id: contentId, scheme: scheme })
            .then(function (data) {
                App.closeModal();
                App.toast('DRM key generated for ' + contentId, 'success');

                /* Show the key details (only shown once at creation) */
                drmPage.showKeyDetails(data);
                drmPage.refresh();
            })
            .catch(function (err) {
                App.toast(err.message || 'Failed to generate key', 'error');
            });
    },

    viewKey: function (contentId) {
        App.get('/drm/keys/' + encodeURIComponent(contentId))
            .then(function (data) {
                drmPage.showKeyDetails(data);
            })
            .catch(function (err) {
                App.toast(err.message || 'Failed to get key details', 'error');
            });
    },

    showKeyDetails: function (key) {
        var licenseUrl = (this.config.key_server_url || window.location.origin) +
                         '/api/drm/license?content_id=' + encodeURIComponent(key.content_id);
        var rawKeyUrl = (this.config.key_server_url || window.location.origin) +
                        '/api/drm/key/' + encodeURIComponent(key.content_id);

        var body = '<table class="detail-table">' +
            '<tr><td class="label">Content ID</td><td><code>' + App.esc(key.content_id) + '</code></td></tr>' +
            '<tr><td class="label">Key ID (KID)</td><td><code>' + App.esc(key.kid || '') + '</code></td></tr>' +
            '<tr><td class="label">Key ID (Hex)</td><td><code style="font-size:0.8em">' + App.esc(key.kid_hex || '') + '</code></td></tr>' +
            '<tr><td class="label">Key (Hex)</td><td><code style="font-size:0.8em">' + App.esc(key.key_hex || '') + '</code></td></tr>';

        if (key.key_b64url) {
            body += '<tr><td class="label">Key (Base64url)</td><td><code style="font-size:0.8em">' + App.esc(key.key_b64url) + '</code></td></tr>';
        }
        if (key.kid_b64url) {
            body += '<tr><td class="label">KID (Base64url)</td><td><code style="font-size:0.8em">' + App.esc(key.kid_b64url) + '</code></td></tr>';
        }

        body += '<tr><td class="label">Scheme</td><td><span class="status complete">' + App.esc(key.scheme || 'cenc') + '</span></td></tr>' +
            '<tr><td class="label">System ID</td><td><code style="font-size:0.8em">' + App.esc(key.system_id || '') + '</code></td></tr>' +
            '<tr><td class="label">Created</td><td>' + App.esc(key.created_at || '') + '</td></tr>' +
        '</table>' +

        '<div style="margin-top:16px;padding:12px;background:var(--bg-body);border-radius:8px">' +
            '<h4 style="margin:0 0 8px 0;color:var(--text-secondary)">Player Integration</h4>' +
            '<div class="form-group" style="margin-bottom:8px">' +
                '<label style="font-size:0.8em">ClearKey License URL (EME)</label>' +
                '<input type="text" class="form-control" value="' + App.esc(licenseUrl) + '" readonly style="font-size:0.8em">' +
            '</div>' +
            '<div class="form-group" style="margin-bottom:0">' +
                '<label style="font-size:0.8em">Raw Key URL (HLS #EXT-X-KEY)</label>' +
                '<input type="text" class="form-control" value="' + App.esc(rawKeyUrl) + '" readonly style="font-size:0.8em">' +
            '</div>' +
        '</div>';

        var footer = '<button class="btn btn-outline" onclick="App.closeModal()">Close</button>';
        App.showModal('DRM Key: ' + key.content_id, body, footer);
    },

    deleteKey: function (contentId) {
        if (!confirm('Delete DRM key for "' + contentId + '"?\n\nExisting encrypted content will no longer be playable without this key.')) {
            return;
        }

        App.del('/drm/keys/' + encodeURIComponent(contentId))
            .then(function () {
                App.toast('DRM key deleted for ' + contentId, 'success');
                drmPage.refresh();
            })
            .catch(function (err) {
                App.toast(err.message || 'Failed to delete key', 'error');
            });
    }
};
