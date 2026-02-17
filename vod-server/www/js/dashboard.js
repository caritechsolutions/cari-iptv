/**
 * VOD Server Web GUI - Dashboard Page
 */
var dashboardPage = {
    interval: null,

    render() {
        return `
            <div class="page-header">
                <h1>Dashboard</h1>
                <div class="page-header-actions">
                    <span id="dash-uptime" class="text-muted text-sm"></span>
                </div>
            </div>

            <div class="stats-grid" id="dash-stats">
                <div class="stat-card">
                    <span class="stat-label">Content Items</span>
                    <span class="stat-value" id="stat-content">-</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Active Jobs</span>
                    <span class="stat-value" id="stat-jobs">-</span>
                    <span class="stat-sub" id="stat-jobs-queued"></span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">CPU Usage</span>
                    <span class="stat-value" id="stat-cpu">-</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Memory</span>
                    <span class="stat-value" id="stat-memory">-</span>
                </div>
            </div>

            <div class="grid-2">
                <div class="card">
                    <div class="card-header">
                        <h3>Storage</h3>
                    </div>
                    <div class="disk-bar mb-2">
                        <div class="disk-fill" id="disk-fill" style="width:0%;background:var(--success)"></div>
                        <span class="disk-label" id="disk-label">-</span>
                    </div>
                    <div class="flex-between text-sm text-muted">
                        <span id="disk-used">-</span>
                        <span id="disk-total">-</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Server Info</h3>
                    </div>
                    <table>
                        <tbody>
                            <tr><td class="text-muted">Version</td><td id="info-version">-</td></tr>
                            <tr><td class="text-muted">Node Name</td><td id="info-node">-</td></tr>
                            <tr><td class="text-muted">FFmpeg</td><td id="info-ffmpeg">-</td></tr>
                            <tr><td class="text-muted">Uptime</td><td id="info-uptime">-</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3>Recent Jobs</h3>
                    <a href="/jobs" class="btn btn-outline btn-sm" onclick="event.preventDefault(); App.navigate('jobs')">View All</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Profile</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody id="recent-jobs">
                            <tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>`;
    },

    init() {
        this.refresh();
        this.interval = setInterval(() => this.refresh(), 5000);
    },

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    },

    async refresh() {
        try {
            const [status, jobs] = await Promise.all([
                App.get('/status'),
                App.get('/jobs?limit=5').catch(() => ({ jobs: [] }))
            ]);

            /* Stats */
            document.getElementById('stat-content').textContent = status.content_count || 0;
            document.getElementById('stat-jobs').textContent = status.active_jobs || 0;
            document.getElementById('stat-cpu').textContent =
                (status.cpu_usage !== undefined ? status.cpu_usage.toFixed(1) + '%' : '-');
            document.getElementById('stat-memory').textContent =
                (status.memory_usage !== undefined ? status.memory_usage.toFixed(1) + '%' : '-');

            const queued = document.getElementById('stat-jobs-queued');
            if (status.queued_jobs > 0) {
                queued.textContent = `${status.queued_jobs} queued`;
            } else {
                queued.textContent = '';
            }

            /* Disk */
            if (status.disk) {
                const pct = status.disk.usage_percent || 0;
                const fill = document.getElementById('disk-fill');
                fill.style.width = pct.toFixed(1) + '%';
                fill.style.background = pct > 90 ? 'var(--danger)' : pct > 75 ? 'var(--warning)' : 'var(--success)';
                document.getElementById('disk-label').textContent = pct.toFixed(1) + '%';
                document.getElementById('disk-used').textContent = 'Used: ' + App.formatBytes(status.disk.used_bytes);
                document.getElementById('disk-total').textContent = 'Total: ' + App.formatBytes(status.disk.total_bytes);
            }

            /* Server info */
            document.getElementById('info-version').textContent = status.version || '-';
            document.getElementById('info-node').textContent = status.node_name || '-';
            document.getElementById('info-ffmpeg').textContent = status.ffmpeg_available ? 'Available' : 'Not found';
            document.getElementById('info-uptime').textContent = status.uptime ? App.formatDuration(status.uptime) : '-';

            /* Recent jobs */
            const tbody = document.getElementById('recent-jobs');
            const jobList = jobs.jobs || jobs || [];
            if (jobList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No jobs yet</td></tr>';
            } else {
                tbody.innerHTML = jobList.map(j => `
                    <tr>
                        <td class="text-mono">#${j.id}</td>
                        <td>${App.esc(j.title || j.content_id)}</td>
                        <td>${App.esc(j.profile)}</td>
                        <td><span class="status ${j.status}">${j.status}</span></td>
                        <td>
                            ${j.status === 'processing' || j.status === 'packaging' ? `
                                <div class="progress-bar" style="width:120px;display:inline-block;vertical-align:middle">
                                    <div class="progress-fill" style="width:${j.progress || 0}%"></div>
                                </div>
                                <span class="text-sm text-muted">${(j.progress || 0).toFixed(1)}%</span>
                            ` : '-'}
                        </td>
                        <td class="text-muted">${App.timeAgo(j.created_at)}</td>
                    </tr>
                `).join('');
            }

        } catch (err) {
            /* Silently handle — sidebar already shows disconnected state */
        }
    }
};
