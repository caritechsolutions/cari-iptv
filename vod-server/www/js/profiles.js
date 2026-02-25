/**
 * VOD Server Web GUI - Profiles Page
 * Create, edit, and delete transcode profiles.
 */
var profilesPage = {

    profiles: [],

    render() {
        return `
            <div class="page-header">
                <h1>Transcode Profiles</h1>
                <div class="page-header-actions">
                    <button class="btn btn-primary" onclick="profilesPage.showCreate()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        New Profile
                    </button>
                </div>
            </div>

            <div id="profiles-loading" class="empty-state"><div class="spinner"></div></div>
            <div id="profiles-grid" style="display:none"></div>`;
    },

    async init() {
        await this.loadProfiles();
    },

    stop() {},

    async loadProfiles() {
        const loading = document.getElementById('profiles-loading');
        const grid = document.getElementById('profiles-grid');

        try {
            const data = await App.get('/profiles');
            this.profiles = data.profiles || [];

            loading.style.display = 'none';
            grid.style.display = 'block';

            if (this.profiles.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                        </svg>
                        <h3>No Profiles</h3>
                        <p>Create a transcode profile to define video encoding settings.</p>
                        <button class="btn btn-primary mt-3" onclick="profilesPage.showCreate()">Create Profile</button>
                    </div>`;
                return;
            }

            grid.innerHTML = `
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px">
                    ${this.profiles.map(p => this.renderCard(p)).join('')}
                </div>`;

        } catch (err) {
            loading.style.display = 'none';
            grid.style.display = 'block';
            grid.innerHTML = `<div class="empty-state"><h3>Connection Error</h3><p>${App.esc(err.message)}</p></div>`;
        }
    },

    renderCard(p) {
        const renditions = (p.renditions || []).map(r =>
            `<div style="display:flex;justify-content:space-between;padding:3px 0">
                <span>${App.esc(r.label || (r.width + 'x' + r.height))}</span>
                <span class="text-muted">${r.bitrate_kbps ? r.bitrate_kbps + ' kbps' : ''}</span>
            </div>`
        ).join('');

        return `
            <div class="card" style="margin-bottom:0">
                <div class="card-header" style="margin-bottom:8px">
                    <h3 style="font-size:1rem">${App.esc(p.name)}</h3>
                    <span class="status ready" style="font-size:0.72rem">${App.esc(p.codec || 'unknown')}</span>
                </div>

                <div style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:12px">
                    <div class="flex-between mb-2">
                        <span class="text-muted">Preset</span> <span>${App.esc(p.preset || '-')}</span>
                    </div>
                    <div class="flex-between mb-2">
                        <span class="text-muted">CRF</span> <span>${p.crf != null ? p.crf : '-'}</span>
                    </div>
                    <div class="flex-between mb-2">
                        <span class="text-muted">Audio</span> <span>${App.esc(p.audio_codec || 'aac')} @ ${App.esc(p.audio_bitrate || '128k')}</span>
                    </div>
                </div>

                ${renditions ? `
                    <div style="border-top:1px solid var(--border);padding-top:10px;margin-bottom:12px">
                        <div class="text-sm text-muted mb-2" style="font-weight:600">Renditions (${(p.renditions||[]).length})</div>
                        <div style="font-size:0.85rem">${renditions}</div>
                    </div>
                ` : '<div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:12px">No renditions defined</div>'}

                <div style="display:flex;gap:8px;border-top:1px solid var(--border);padding-top:12px">
                    <button class="btn btn-outline btn-sm" onclick="profilesPage.showEdit('${App.esc(p.name)}')" style="flex:1">Edit</button>
                    <button class="btn btn-danger btn-sm" onclick="profilesPage.deleteProfile('${App.esc(p.name)}')" style="flex:1">Delete</button>
                </div>
            </div>`;
    },

    /* ---- Create / Edit Modal ---- */

    showCreate() {
        this._editName = null;
        this._showModal('New Profile', {});
    },

    showEdit(name) {
        const p = this.profiles.find(x => x.name === name);
        if (!p) return;
        this._editName = name;
        this._showModal('Edit Profile: ' + name, p);
    },

    _showModal(title, p) {
        const isEdit = !!this._editName;

        const codecOptions = [
            ['libx264', 'H.264 (libx264)'],
            ['libx265', 'HEVC / H.265 (libx265)'],
            ['libsvtav1', 'AV1 (libsvtav1)'],
            ['h264_nvenc', 'H.264 NVENC (GPU)'],
            ['hevc_nvenc', 'HEVC NVENC (GPU)'],
            ['h264_vaapi', 'H.264 VAAPI (GPU)'],
            ['hevc_vaapi', 'HEVC VAAPI (GPU)']
        ];

        const presetOptions = ['ultrafast','superfast','veryfast','faster','fast','medium','slow','slower','veryslow'];
        const audioBitrateOptions = ['64k','96k','128k','192k','256k','320k'];
        const resOptions = ['360p','480p','576p','720p','1080p','1440p','2160p'];

        const renditions = (p.renditions && p.renditions.length > 0)
            ? p.renditions
            : [{label:'360p',bitrate_kbps:400},{label:'720p',bitrate_kbps:2800},{label:'1080p',bitrate_kbps:5000}];

        const renditionRows = renditions.map((r, i) => this._renditionRowHtml(i, r.label, r.bitrate_kbps, resOptions)).join('');

        const bodyHtml = `
            <div class="form-group">
                <label>Profile Name</label>
                <input type="text" class="form-control" id="pf-name" value="${App.esc(p.name || '')}"
                    placeholder="e.g. standard_hd" pattern="[a-z0-9_]+"
                    title="Lowercase letters, numbers, underscores only"
                    ${isEdit ? 'readonly style="opacity:0.6"' : ''}>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Video Codec</label>
                    <select class="form-control" id="pf-codec">
                        ${codecOptions.map(([v,l]) => `<option value="${v}" ${v===(p.codec||'libx264')?'selected':''}>${l}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Preset</label>
                    <select class="form-control" id="pf-preset">
                        ${presetOptions.map(v => `<option value="${v}" ${v===(p.preset||'medium')?'selected':''}>${v}</option>`).join('')}
                    </select>
                </div>
            </div>

            <div class="grid-3">
                <div class="form-group">
                    <label>CRF (Quality)</label>
                    <input type="number" class="form-control" id="pf-crf" value="${p.crf != null ? p.crf : 23}" min="0" max="51">
                    <div class="text-sm text-muted mt-1">Lower = better quality</div>
                </div>
                <div class="form-group">
                    <label>Audio Codec</label>
                    <select class="form-control" id="pf-audio-codec">
                        <option value="aac" ${(p.audio_codec||'aac')==='aac'?'selected':''}>AAC</option>
                        <option value="libopus" ${p.audio_codec==='libopus'?'selected':''}>Opus</option>
                        <option value="copy" ${p.audio_codec==='copy'?'selected':''}>Copy (passthrough)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Audio Bitrate</label>
                    <select class="form-control" id="pf-audio-bitrate">
                        ${audioBitrateOptions.map(v => `<option value="${v}" ${v===(p.audio_bitrate||'128k')?'selected':''}>${v}</option>`).join('')}
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="flex-between">
                    Renditions (ABR Ladder)
                    <button class="btn btn-outline btn-sm" onclick="profilesPage.addRenditionRow()">+ Add</button>
                </label>
                <div id="pf-renditions">${renditionRows}</div>
            </div>`;

        const footerHtml = `
            <button class="btn btn-outline" onclick="App.closeModal()">Cancel</button>
            <button class="btn btn-primary" id="pf-save-btn" onclick="profilesPage.saveProfile()">
                ${isEdit ? 'Update Profile' : 'Create Profile'}
            </button>`;

        App.showModal(title, bodyHtml, footerHtml);
    },

    _renditionRowHtml(idx, label, bitrate, resOptions) {
        if (!resOptions) resOptions = ['360p','480p','576p','720p','1080p','1440p','2160p'];
        return `
            <div class="pf-rendition-row flex gap-2 mb-2" style="align-items:center">
                <select class="form-control" data-field="label" style="flex:1">
                    ${resOptions.map(v => `<option value="${v}" ${v===label?'selected':''}>${v}</option>`).join('')}
                </select>
                <div class="flex gap-1" style="align-items:center;flex:1">
                    <input type="number" class="form-control" data-field="bitrate" value="${bitrate||''}" placeholder="Bitrate" min="100" max="50000" style="flex:1">
                    <span class="text-sm text-muted">kbps</span>
                </div>
                <button class="btn btn-danger btn-sm btn-icon" onclick="this.closest('.pf-rendition-row').remove()" title="Remove">&times;</button>
            </div>`;
    },

    addRenditionRow() {
        const container = document.getElementById('pf-renditions');
        if (!container) return;
        const idx = container.querySelectorAll('.pf-rendition-row').length;
        container.insertAdjacentHTML('beforeend', this._renditionRowHtml(idx, '720p', '', null));
    },

    async saveProfile() {
        const isEdit = !!this._editName;
        const btn = document.getElementById('pf-save-btn');

        const name = document.getElementById('pf-name').value.trim();
        if (!name) { App.toast('Profile name is required', 'warning'); return; }

        /* Collect rendition rows as JSON array of objects (C API primary format) */
        const rows = document.querySelectorAll('.pf-rendition-row');
        const renditions = [];
        const resMap = {
            '360p':  [640, 360],   '480p':  [854, 480],   '576p':  [1024, 576],
            '720p':  [1280, 720],  '1080p': [1920, 1080], '1440p': [2560, 1440],
            '2160p': [3840, 2160]
        };
        for (const row of rows) {
            const label = row.querySelector('[data-field="label"]').value;
            const bitrate = parseInt(row.querySelector('[data-field="bitrate"]').value);
            if (label && bitrate > 0) {
                const dims = resMap[label] || [0, 0];
                renditions.push({ label: label, width: dims[0], height: dims[1], bitrate_kbps: bitrate });
            }
        }

        const payload = {
            name: name,
            codec: document.getElementById('pf-codec').value,
            preset: document.getElementById('pf-preset').value,
            crf: parseInt(document.getElementById('pf-crf').value),
            audio_codec: document.getElementById('pf-audio-codec').value,
            audio_bitrate: document.getElementById('pf-audio-bitrate').value,
            renditions: renditions
        };

        btn.disabled = true;
        btn.textContent = 'Saving...';

        try {
            if (isEdit) {
                await App.api('PUT', '/profiles/' + encodeURIComponent(this._editName), payload);
                App.toast('Profile updated', 'success');
            } else {
                await App.post('/profiles', payload);
                App.toast('Profile created', 'success');
            }
            App.closeModal();
            await this.loadProfiles();
        } catch (err) {
            App.toast('Failed: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = isEdit ? 'Update Profile' : 'Create Profile';
        }
    },

    async deleteProfile(name) {
        if (!confirm('Delete profile "' + name + '"?\n\nExisting jobs using this profile will not be affected.')) return;

        try {
            await App.del('/profiles/' + encodeURIComponent(name));
            App.toast('Profile "' + name + '" deleted', 'success');
            await this.loadProfiles();
        } catch (err) {
            App.toast('Failed: ' + err.message, 'error');
        }
    }
};
