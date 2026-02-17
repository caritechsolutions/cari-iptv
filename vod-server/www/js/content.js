/**
 * VOD Server Web GUI - Content Page
 */
var contentPage = {
    interval: null,

    render() {
        return `
            <div class="page-header">
                <h1>Content Library</h1>
                <div class="page-header-actions">
                    <button class="btn btn-outline btn-sm" onclick="contentPage.refresh()">Refresh</button>
                </div>
            </div>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Content ID</th>
                                <th>Title</th>
                                <th>Codec</th>
                                <th>Renditions</th>
                                <th>Duration</th>
                                <th>Size</th>
                                <th>Thumbnails</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="content-list">
                            <tr><td colspan="9" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>`;
    },

    init() {
        this.refresh();
        this.interval = setInterval(() => this.refresh(), 15000);
    },

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    },

    async refresh() {
        try {
            const data = await App.get('/content');
            const items = data.items || data.content || [];
            const tbody = document.getElementById('content-list');

            if (items.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9">
                    <div class="empty-state">
                        <h3>No content yet</h3>
                        <p>Submit a transcode job to add content to the library.</p>
                    </div>
                </td></tr>`;
                return;
            }

            tbody.innerHTML = items.map(c => {
                let renditions = '-';
                try {
                    const r = typeof c.renditions === 'string' ? JSON.parse(c.renditions) : c.renditions;
                    if (Array.isArray(r)) {
                        renditions = r.map(x => x.resolution || x.label || '?').join(', ');
                    }
                } catch(e) {}

                return `<tr>
                    <td class="text-mono">${App.esc(c.content_id)}</td>
                    <td>${App.esc(c.title || '-')}</td>
                    <td>${App.esc(c.codec || '-')}</td>
                    <td class="text-sm">${renditions}</td>
                    <td>${App.formatDuration(c.duration)}</td>
                    <td>${App.formatBytes(c.size_bytes)}</td>
                    <td>${c.has_thumbnails ? '<span class="text-success">Yes</span>' : '<span class="text-muted">No</span>'}</td>
                    <td><span class="status ${c.status}">${c.status}</span></td>
                    <td>
                        <button class="btn btn-outline btn-sm" onclick="contentPage.viewDetail('${App.esc(c.content_id)}')">View</button>
                        <button class="btn btn-danger btn-sm" onclick="contentPage.deleteContent('${App.esc(c.content_id)}')">Delete</button>
                    </td>
                </tr>`;
            }).join('');
        } catch (err) {
            App.toast('Failed to load content: ' + err.message, 'error');
        }
    },

    async viewDetail(contentId) {
        try {
            const data = await App.get(`/content/${encodeURIComponent(contentId)}`);
            const c = data.content || data;

            let renditionsHtml = '-';
            try {
                const r = typeof c.renditions === 'string' ? JSON.parse(c.renditions) : c.renditions;
                if (Array.isArray(r)) {
                    renditionsHtml = r.map(x =>
                        `<div class="flex-between mb-2">
                            <span>${x.resolution || x.label}</span>
                            <span class="text-muted">${x.bitrate ? x.bitrate + 'k' : ''} ${x.path || ''}</span>
                        </div>`
                    ).join('');
                }
            } catch(e) {}

            App.showModal(`Content: ${App.esc(c.content_id)}`, `
                <table>
                    <tr><td class="text-muted" style="padding-right:16px">Title</td><td>${App.esc(c.title || '-')}</td></tr>
                    <tr><td class="text-muted">Path</td><td class="text-mono text-sm">${App.esc(c.path)}</td></tr>
                    <tr><td class="text-muted">Codec</td><td>${App.esc(c.codec || '-')}</td></tr>
                    <tr><td class="text-muted">Duration</td><td>${App.formatDuration(c.duration)}</td></tr>
                    <tr><td class="text-muted">Total Size</td><td>${App.formatBytes(c.size_bytes)}</td></tr>
                    <tr><td class="text-muted">HLS</td><td class="text-mono text-sm">${App.esc(c.hls_path || '-')}</td></tr>
                    <tr><td class="text-muted">DASH</td><td class="text-mono text-sm">${App.esc(c.dash_path || '-')}</td></tr>
                    <tr><td class="text-muted">Thumbnails</td><td>${c.has_thumbnails ? 'Yes' : 'No'}</td></tr>
                    <tr><td class="text-muted">Created</td><td>${c.created_at || '-'}</td></tr>
                </table>
                <div class="mt-3">
                    <strong class="text-sm text-muted">Renditions</strong>
                    <div class="mt-1">${renditionsHtml}</div>
                </div>
            `);
        } catch (err) {
            App.toast('Failed to load content details: ' + err.message, 'error');
        }
    },

    async deleteContent(contentId) {
        if (!confirm(`Delete content "${contentId}" and all its files?`)) return;
        try {
            await App.del(`/content/${encodeURIComponent(contentId)}`);
            App.toast('Content deleted', 'success');
            this.refresh();
        } catch (err) {
            App.toast('Failed to delete: ' + err.message, 'error');
        }
    }
};
