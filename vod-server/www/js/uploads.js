/**
 * VOD Server Web GUI - Uploads Page
 * Manage uploaded files (list, upload, delete)
 */
var uploadsPage = {
    interval: null,

    render() {
        return `
            <div class="page-header">
                <h1>Uploads</h1>
                <div class="page-header-actions">
                    <button class="btn btn-primary btn-sm" onclick="uploadsPage.showUploadModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        Upload File
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="uploadsPage.refresh()">Refresh</button>
                </div>
            </div>

            <div id="upload-stats" class="stats-grid" style="display:none">
                <div class="stat-card">
                    <span class="stat-label">Files</span>
                    <span class="stat-value" id="upload-file-count">0</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Total Size</span>
                    <span class="stat-value" id="upload-total-size">0 B</span>
                </div>
            </div>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Modified</th>
                                <th>Path</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="uploads-list">
                            <tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>
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
        try {
            const data = await App.get('/uploads');
            const files = data.files || [];
            const tbody = document.getElementById('uploads-list');
            const stats = document.getElementById('upload-stats');

            /* Update stats */
            document.getElementById('upload-file-count').textContent = files.length;
            document.getElementById('upload-total-size').textContent = App.formatBytes(data.total_size || 0);
            stats.style.display = files.length > 0 ? '' : 'none';

            if (files.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5">
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        <h3>No uploaded files</h3>
                        <p>Upload a video file to get started with transcoding.</p>
                    </div>
                </td></tr>`;
                return;
            }

            /* Sort by modified descending (newest first) */
            files.sort((a, b) => (b.modified || 0) - (a.modified || 0));

            tbody.innerHTML = files.map(f => {
                const modified = f.modified ? new Date(f.modified * 1000).toLocaleString() : '-';
                return `<tr>
                    <td>
                        <div class="flex gap-2" style="align-items:center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
                            <strong>${App.esc(f.name)}</strong>
                        </div>
                    </td>
                    <td class="text-mono">${App.formatBytes(f.size)}</td>
                    <td class="text-muted text-sm">${modified}</td>
                    <td class="text-mono text-sm text-muted" style="max-width:300px;overflow:hidden;text-overflow:ellipsis" title="${App.esc(f.path)}">${App.esc(f.path)}</td>
                    <td>
                        <div class="flex gap-1">
                            <button class="btn btn-outline btn-sm" onclick="uploadsPage.useInJob('${App.esc(f.path)}')" title="Create transcode job with this file">Use</button>
                            <button class="btn btn-danger btn-sm" onclick="uploadsPage.deleteFile('${App.esc(f.name)}')">Delete</button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        } catch (err) {
            const tbody = document.getElementById('uploads-list');
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="5">
                    <div class="empty-state">
                        <h3>Failed to load uploads</h3>
                        <p>${App.esc(err.message)}</p>
                    </div>
                </td></tr>`;
            }
        }
    },

    async deleteFile(filename) {
        if (!confirm(`Delete "${filename}"? This cannot be undone.`)) return;
        try {
            await App.del('/uploads?file=' + encodeURIComponent(filename));
            App.toast('File deleted', 'success');
            this.refresh();
        } catch (err) {
            App.toast('Failed to delete: ' + err.message, 'error');
        }
    },

    useInJob(filepath) {
        /* Navigate to jobs page and open the new job modal with the path pre-filled */
        App.navigate('jobs');
        setTimeout(() => {
            jobsPage.showNewJobModal();
            setTimeout(() => {
                const sourceInput = document.getElementById('job-source');
                if (sourceInput) sourceInput.value = filepath;
                /* Auto-generate a content ID from filename */
                const contentInput = document.getElementById('job-content-id');
                if (contentInput && !contentInput.value) {
                    const name = filepath.split('/').pop().replace(/\.[^.]+$/, '')
                        .replace(/[^a-zA-Z0-9]+/g, '-').toLowerCase();
                    contentInput.value = name;
                }
            }, 100);
        }, 200);
    },

    showUploadModal() {
        App.showModal('Upload Video File', `
            <div class="form-group">
                <label>Select File</label>
                <input type="file" class="form-control" id="upload-file-input"
                    accept="video/*,.mp4,.mkv,.avi,.mov,.wmv,.flv,.webm,.ts,.m2ts,.mxf"
                    style="padding:8px">
            </div>
            <div id="upload-progress-wrap" style="display:none">
                <div class="form-group">
                    <label>Uploading: <span id="upload-progress-text">0%</span></label>
                    <div class="progress-bar" style="height:10px;margin-top:4px">
                        <div class="progress-fill" id="upload-progress-bar" style="width:0%"></div>
                    </div>
                    <div class="text-sm text-muted mt-1" id="upload-speed-text"></div>
                </div>
            </div>
        `, `
            <button class="btn btn-outline" onclick="App.closeModal()">Cancel</button>
            <button class="btn btn-primary" id="upload-submit-btn" onclick="uploadsPage.doUpload()">Upload</button>
        `);
    },

    async doUpload() {
        const fileInput = document.getElementById('upload-file-input');
        if (!fileInput || !fileInput.files.length) {
            App.toast('Please select a file', 'warning');
            return;
        }

        const file = fileInput.files[0];
        const submitBtn = document.getElementById('upload-submit-btn');
        const progressWrap = document.getElementById('upload-progress-wrap');
        const progressBar = document.getElementById('upload-progress-bar');
        const progressText = document.getElementById('upload-progress-text');
        const speedText = document.getElementById('upload-speed-text');

        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';
        fileInput.disabled = true;
        progressWrap.style.display = '';

        const startTime = Date.now();

        try {
            const xhr = new XMLHttpRequest();

            await new Promise((resolve, reject) => {
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const pct = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = pct + '%';
                        progressText.textContent = pct + '%';

                        const elapsed = (Date.now() - startTime) / 1000;
                        if (elapsed > 0.5) {
                            const speed = e.loaded / elapsed;
                            const remaining = (e.total - e.loaded) / speed;
                            speedText.textContent = App.formatBytes(speed) + '/s — ' +
                                App.formatDuration(Math.ceil(remaining)) + ' remaining';
                        }
                    }
                });

                xhr.addEventListener('load', () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(JSON.parse(xhr.responseText));
                    } else {
                        try {
                            const err = JSON.parse(xhr.responseText);
                            reject(new Error(err.error || 'Upload failed'));
                        } catch (e) {
                            reject(new Error('Upload failed: HTTP ' + xhr.status));
                        }
                    }
                });

                xhr.addEventListener('error', () => reject(new Error('Network error')));
                xhr.addEventListener('abort', () => reject(new Error('Upload aborted')));

                const url = App.apiBase + '/upload?filename=' + encodeURIComponent(file.name);
                xhr.open('POST', url);
                xhr.setRequestHeader('Content-Type', 'application/octet-stream');
                if (window.VOD_API_KEY) {
                    xhr.setRequestHeader('X-API-Key', window.VOD_API_KEY);
                }
                xhr.send(file);
            });

            App.closeModal();
            App.toast('File uploaded: ' + file.name, 'success');
            this.refresh();
        } catch (err) {
            App.toast('Upload failed: ' + err.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Upload';
            fileInput.disabled = false;
        }
    }
};
