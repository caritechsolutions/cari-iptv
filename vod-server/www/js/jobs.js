/**
 * VOD Server Web GUI - Jobs Page
 */
var jobsPage = {
    interval: null,
    statusFilter: '',

    render() {
        return `
            <div class="page-header">
                <h1>Transcode Jobs</h1>
                <div class="page-header-actions">
                    <select class="form-control" style="width:auto" onchange="jobsPage.setFilter(this.value)">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="packaging">Packaging</option>
                        <option value="complete">Complete</option>
                        <option value="failed">Failed</option>
                        <option value="paused">Paused</option>
                    </select>
                    <button class="btn btn-primary btn-sm" onclick="jobsPage.showNewJobModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        New Job
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="jobsPage.refresh()">Refresh</button>
                </div>
            </div>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Content ID</th>
                                <th>Title</th>
                                <th>Source</th>
                                <th>Profile</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Step</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="jobs-list">
                            <tr><td colspan="10" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>`;
    },

    init() {
        this.refresh();
        this.interval = setInterval(() => this.refresh(), 3000);
    },

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    },

    setFilter(status) {
        this.statusFilter = status;
        this.refresh();
    },

    async refresh() {
        try {
            const params = this.statusFilter ? `?status=${this.statusFilter}` : '';
            const data = await App.get('/jobs' + params);
            const jobs = data.items || data.jobs || [];
            const tbody = document.getElementById('jobs-list');

            if (jobs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="10">
                    <div class="empty-state">
                        <h3>No jobs</h3>
                        <p>Submit a new transcode job to get started.</p>
                    </div>
                </td></tr>`;
                return;
            }

            tbody.innerHTML = jobs.map(j => {
                const sourceShort = j.source_path ? j.source_path.split('/').pop() : '-';
                const canCancel = ['pending', 'processing', 'packaging', 'downloading'].includes(j.status);

                return `<tr>
                    <td class="text-mono">#${j.id}</td>
                    <td class="text-mono text-sm">${App.esc(j.content_id)}</td>
                    <td>${App.esc(j.title || '-')}</td>
                    <td class="text-sm" title="${App.esc(j.source_path)}">${App.esc(sourceShort)}</td>
                    <td>${App.esc(j.profile)}</td>
                    <td><span class="status ${j.status}">${j.status}</span></td>
                    <td style="min-width:140px">
                        ${['processing', 'packaging', 'downloading'].includes(j.status) ? `
                            <div class="flex gap-1" style="align-items:center">
                                <div class="progress-bar" style="flex:1">
                                    <div class="progress-fill" style="width:${j.progress || 0}%"></div>
                                </div>
                                <span class="text-sm text-mono">${(j.progress || 0).toFixed(1)}%</span>
                            </div>
                        ` : j.status === 'complete' ? '<span class="text-success">100%</span>' : '-'}
                    </td>
                    <td class="text-sm text-muted">${App.esc(j.current_step || '-')}</td>
                    <td class="text-muted text-sm">${App.timeAgo(j.created_at)}</td>
                    <td>
                        ${canCancel ? `<button class="btn btn-danger btn-sm" onclick="jobsPage.cancelJob(${j.id})">Cancel</button>` : ''}
                        ${j.status === 'failed' ? `<button class="btn btn-outline btn-sm" title="${App.esc(j.error_msg || '')}" onclick="jobsPage.showError(${j.id})">Error</button>` : ''}
                    </td>
                </tr>`;
            }).join('');
        } catch (err) {
            /* Silent on poll failures */
        }
    },

    showNewJobModal() {
        App.showModal('New Transcode Job', `
            <div class="form-group">
                <label>Content ID *</label>
                <input type="text" class="form-control" id="job-content-id" placeholder="e.g. movie-123">
            </div>
            <div class="form-group">
                <label>Title</label>
                <input type="text" class="form-control" id="job-title" placeholder="Movie title (optional)">
            </div>
            <div class="form-group">
                <label>Source Path *</label>
                <input type="text" class="form-control" id="job-source" placeholder="/path/to/source.mp4 or URL">
            </div>
            <div class="form-group">
                <label>Source Type</label>
                <select class="form-control" id="job-source-type">
                    <option value="file">Local File</option>
                    <option value="url">Remote URL</option>
                </select>
            </div>
            <div class="form-group">
                <label>Profile</label>
                <select class="form-control" id="job-profile">
                    <option value="standard">Standard (H.264)</option>
                    <option value="high">High (HEVC)</option>
                    <option value="low">Low Bandwidth (H.264)</option>
                    <option value="hevc_4k">HEVC 4K</option>
                    <option value="av1">AV1</option>
                </select>
            </div>
            <div class="form-group">
                <label>Priority</label>
                <select class="form-control" id="job-priority">
                    <option value="1">1 - Highest</option>
                    <option value="3">3 - High</option>
                    <option value="5" selected>5 - Normal</option>
                    <option value="7">7 - Low</option>
                    <option value="10">10 - Lowest</option>
                </select>
            </div>
        `, `
            <button class="btn btn-outline" onclick="App.closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="jobsPage.submitJob()">Submit Job</button>
        `);
    },

    async submitJob() {
        const contentId = document.getElementById('job-content-id').value.trim();
        const sourcePath = document.getElementById('job-source').value.trim();

        if (!contentId || !sourcePath) {
            App.toast('Content ID and Source Path are required', 'warning');
            return;
        }

        try {
            await App.post('/jobs', {
                content_id: contentId,
                title: document.getElementById('job-title').value.trim(),
                source_path: sourcePath,
                source_type: document.getElementById('job-source-type').value,
                profile: document.getElementById('job-profile').value,
                priority: parseInt(document.getElementById('job-priority').value)
            });
            App.closeModal();
            App.toast('Job submitted', 'success');
            this.refresh();
        } catch (err) {
            App.toast('Failed to submit job: ' + err.message, 'error');
        }
    },

    async cancelJob(id) {
        if (!confirm(`Cancel job #${id}?`)) return;
        try {
            await App.del(`/jobs/${id}`);
            App.toast('Job cancelled', 'success');
            this.refresh();
        } catch (err) {
            App.toast('Failed to cancel: ' + err.message, 'error');
        }
    },

    async showError(id) {
        try {
            const data = await App.get(`/jobs/${id}`);
            const job = data.job || data;
            App.showModal(`Job #${id} Error`, `
                <div class="text-sm text-mono" style="white-space:pre-wrap;word-break:break-all;max-height:300px;overflow-y:auto;padding:12px;background:var(--bg-input);border-radius:var(--radius-sm);">${App.esc(job.error_msg || 'No error message')}</div>
            `);
        } catch (err) {
            App.toast('Failed to load error details', 'error');
        }
    }
};
