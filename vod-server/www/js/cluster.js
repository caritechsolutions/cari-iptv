/**
 * VOD Server Web GUI - Cluster Page
 */
var clusterPage = {
    interval: null,

    render() {
        return `
            <div class="page-header">
                <h1>Cluster</h1>
                <div class="page-header-actions">
                    <button class="btn btn-primary btn-sm" onclick="clusterPage.showAddPeerModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Add Peer
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="clusterPage.refresh()">Refresh</button>
                </div>
            </div>

            <!-- Peers -->
            <div class="card">
                <div class="card-header">
                    <h3>Peer Servers</h3>
                </div>
                <div id="peers-container">
                    <div class="spinner"></div>
                </div>
            </div>

            <!-- Migrations -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3>Content Migrations</h3>
                    <button class="btn btn-outline btn-sm" onclick="clusterPage.showMigrateModal()">New Migration</button>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Content</th>
                                <th>Source</th>
                                <th>Destination</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="migrations-list">
                            <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>`;
    },

    init() {
        this.refresh();
        this.interval = setInterval(() => this.refresh(), 10000);
    },

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    },

    async refresh() {
        await Promise.all([this.loadPeers(), this.loadMigrations()]);
    },

    async loadPeers() {
        try {
            const data = await App.get('/peers');
            const peers = data.peers || data || [];
            const container = document.getElementById('peers-container');

            if (peers.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h3>No peer servers</h3>
                        <p>Add a peer server to enable content migration and cluster monitoring.</p>
                    </div>`;
                return;
            }

            container.innerHTML = `<div class="stats-grid">${peers.map(p => {
                const diskPct = p.disk_total > 0 ? ((p.disk_used || (p.disk_total - p.disk_free)) / p.disk_total * 100) : 0;
                return `
                    <div class="stat-card" style="gap:8px">
                        <div class="flex-between">
                            <strong>${App.esc(p.name)}</strong>
                            <span class="status ${p.status}">${p.status}</span>
                        </div>
                        <div class="text-sm text-mono text-muted">${App.esc(p.url)}</div>
                        <div class="text-sm">
                            <span class="text-muted">CPU:</span> ${p.cpu_usage !== undefined ? p.cpu_usage.toFixed(1) + '%' : '-'}
                            &nbsp;&nbsp;
                            <span class="text-muted">Mem:</span> ${p.memory_usage !== undefined ? p.memory_usage.toFixed(1) + '%' : '-'}
                        </div>
                        <div class="disk-bar" style="height:12px">
                            <div class="disk-fill" style="width:${diskPct.toFixed(1)}%;background:${diskPct > 90 ? 'var(--danger)' : diskPct > 75 ? 'var(--warning)' : 'var(--success)'}"></div>
                        </div>
                        <div class="flex-between text-sm text-muted">
                            <span>${App.formatBytes(p.disk_free)} free</span>
                            <span>${p.content_count || 0} items</span>
                        </div>
                        <div class="flex-between mt-1">
                            <span class="text-sm text-muted">${p.version ? 'v' + p.version : ''} ${p.last_seen ? '- ' + App.timeAgo(p.last_seen) : ''}</span>
                            <div class="flex gap-1">
                                <button class="btn btn-outline btn-sm" onclick="clusterPage.browseContent(${p.id})">Browse</button>
                                <button class="btn btn-danger btn-sm" onclick="clusterPage.removePeer(${p.id})">Remove</button>
                            </div>
                        </div>
                    </div>`;
            }).join('')}</div>`;
        } catch (err) {
            document.getElementById('peers-container').innerHTML =
                `<div class="text-center text-muted">Failed to load peers</div>`;
        }
    },

    async loadMigrations() {
        try {
            const data = await App.get('/migrations');
            const migrations = data.items || data.migrations || [];
            const tbody = document.getElementById('migrations-list');

            if (migrations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No migrations</td></tr>';
                return;
            }

            tbody.innerHTML = migrations.map(m => `
                <tr>
                    <td class="text-mono">#${m.id}</td>
                    <td>${App.esc(m.title || m.content_id)}</td>
                    <td class="text-sm">${App.esc(m.source_peer)}</td>
                    <td class="text-sm">${App.esc(m.dest_peer)}</td>
                    <td><span class="status ${m.status}">${m.status}</span></td>
                    <td style="min-width:120px">
                        ${m.status === 'transferring' ? `
                            <div class="flex gap-1" style="align-items:center">
                                <div class="progress-bar" style="flex:1">
                                    <div class="progress-fill" style="width:${m.progress || 0}%"></div>
                                </div>
                                <span class="text-sm">${(m.progress || 0).toFixed(0)}%</span>
                            </div>
                        ` : m.status === 'complete' ? '100%' : '-'}
                    </td>
                    <td class="text-muted text-sm">${App.timeAgo(m.created_at)}</td>
                    <td>
                        ${m.status === 'pending' || m.status === 'transferring' ?
                            `<button class="btn btn-danger btn-sm" onclick="clusterPage.cancelMigration(${m.id})">Cancel</button>` : ''}
                    </td>
                </tr>
            `).join('');
        } catch (err) { /* silent */ }
    },

    showAddPeerModal() {
        App.showModal('Add Peer Server', `
            <div class="form-group">
                <label>Name *</label>
                <input type="text" class="form-control" id="peer-name" placeholder="e.g. VOD Node 2">
            </div>
            <div class="form-group">
                <label>URL *</label>
                <input type="text" class="form-control" id="peer-url" placeholder="https://192.168.1.100:8090">
            </div>
            <div class="form-group">
                <label>API Key</label>
                <input type="text" class="form-control" id="peer-api-key" placeholder="Remote server's API key">
            </div>
        `, `
            <button class="btn btn-outline" onclick="App.closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="clusterPage.addPeer()">Add Peer</button>
        `);
    },

    async addPeer() {
        const name = document.getElementById('peer-name').value.trim();
        const url = document.getElementById('peer-url').value.trim();
        if (!name || !url) {
            App.toast('Name and URL are required', 'warning');
            return;
        }
        try {
            await App.post('/peers', {
                name,
                url,
                api_key: document.getElementById('peer-api-key').value.trim()
            });
            App.closeModal();
            App.toast('Peer added', 'success');
            this.refresh();
        } catch (err) {
            App.toast('Failed to add peer: ' + err.message, 'error');
        }
    },

    async removePeer(id) {
        if (!confirm('Remove this peer server?')) return;
        try {
            await App.del(`/peers/${id}`);
            App.toast('Peer removed', 'success');
            this.refresh();
        } catch (err) {
            App.toast('Failed to remove peer: ' + err.message, 'error');
        }
    },

    async browseContent(peerId) {
        try {
            const data = await App.get(`/peers/${peerId}/content`);
            const items = data.content || data || [];

            App.showModal('Remote Content', items.length === 0
                ? '<div class="empty-state"><p>No content on this peer</p></div>'
                : `<div class="table-wrapper"><table>
                    <thead><tr><th>ID</th><th>Title</th><th>Size</th><th>Action</th></tr></thead>
                    <tbody>${items.map(c => `
                        <tr>
                            <td class="text-mono text-sm">${App.esc(c.content_id)}</td>
                            <td>${App.esc(c.title || '-')}</td>
                            <td>${App.formatBytes(c.size_bytes)}</td>
                            <td><button class="btn btn-primary btn-sm" onclick="clusterPage.migrateContent('${App.esc(c.content_id)}', '${App.esc(c.title || '')}', ${peerId})">Pull</button></td>
                        </tr>
                    `).join('')}</tbody>
                </table></div>`
            );
        } catch (err) {
            App.toast('Failed to browse remote content: ' + err.message, 'error');
        }
    },

    async migrateContent(contentId, title, peerId) {
        try {
            const peerData = await App.get('/peers');
            const peer = (peerData.peers || []).find(p => p.id === peerId);
            if (!peer) throw new Error('Peer not found');

            await App.post('/migrations', {
                content_id: contentId,
                title: title,
                source_peer: peer.url,
                dest_peer: 'local',
                delete_source: 0
            });
            App.closeModal();
            App.toast('Migration started', 'success');
            this.refresh();
        } catch (err) {
            App.toast('Failed to start migration: ' + err.message, 'error');
        }
    },

    showMigrateModal() {
        App.showModal('New Migration', `
            <div class="form-group">
                <label>Content ID *</label>
                <input type="text" class="form-control" id="mig-content-id" placeholder="Content ID to migrate">
            </div>
            <div class="form-group">
                <label>Title</label>
                <input type="text" class="form-control" id="mig-title" placeholder="Optional">
            </div>
            <div class="form-group">
                <label>Source Peer URL *</label>
                <input type="text" class="form-control" id="mig-source" placeholder="https://peer:8090 or 'local'">
            </div>
            <div class="form-group">
                <label>Destination</label>
                <input type="text" class="form-control" id="mig-dest" value="local" placeholder="'local' or peer URL">
            </div>
        `, `
            <button class="btn btn-outline" onclick="App.closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="clusterPage.submitMigration()">Start Migration</button>
        `);
    },

    async submitMigration() {
        const contentId = document.getElementById('mig-content-id').value.trim();
        const source = document.getElementById('mig-source').value.trim();
        if (!contentId || !source) {
            App.toast('Content ID and Source are required', 'warning');
            return;
        }
        try {
            await App.post('/migrations', {
                content_id: contentId,
                title: document.getElementById('mig-title').value.trim(),
                source_peer: source,
                dest_peer: document.getElementById('mig-dest').value.trim() || 'local',
                delete_source: 0
            });
            App.closeModal();
            App.toast('Migration created', 'success');
            this.refresh();
        } catch (err) {
            App.toast('Failed: ' + err.message, 'error');
        }
    },

    async cancelMigration(id) {
        if (!confirm(`Cancel migration #${id}?`)) return;
        try {
            await App.del(`/migrations/${id}`);
            App.toast('Migration cancelled', 'success');
            this.refresh();
        } catch (err) {
            App.toast('Failed: ' + err.message, 'error');
        }
    }
};
