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
                            <input type="password" class="form-control" id="set-api-key" disabled>
                            <button class="btn btn-outline btn-sm" onclick="settingsPage.toggleApiKey()">Show</button>
                        </div>
                    </div>
                    <p class="text-sm text-muted mt-2">Server settings are configured in vod-server.conf. Restart required for changes.</p>
                </div>

                <!-- SSL Settings -->
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
                        <input type="text" class="form-control text-mono" id="set-library-path" disabled>
                    </div>
                    <div class="form-group">
                        <label>Temp Path</label>
                        <input type="text" class="form-control text-mono" id="set-temp-path" disabled>
                    </div>
                    <div class="form-group">
                        <label>Min Free Space (GB)</label>
                        <input type="number" class="form-control" id="set-min-space" disabled>
                    </div>
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
                            <option value="standard">Standard (H.264)</option>
                            <option value="high">High (HEVC)</option>
                            <option value="low">Low Bandwidth</option>
                            <option value="hevc_4k">HEVC 4K</option>
                            <option value="av1">AV1</option>
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
                    <button class="btn btn-outline" onclick="settingsPage.checkFFmpeg()">Check FFmpeg</button>
                    <button class="btn btn-outline" onclick="settingsPage.checkMP4Box()">Check MP4Box</button>
                    <button class="btn btn-warning" onclick="settingsPage.clearTemp()">Clear Temp Files</button>
                </div>
                <div id="tools-output" class="mt-3 text-sm text-mono" style="display:none;white-space:pre-wrap;padding:12px;background:var(--bg-input);border-radius:var(--radius-sm);max-height:200px;overflow-y:auto;"></div>
            </div>`;
    },

    async init() {
        try {
            const data = await App.get('/status');
            const config = data.config || data;

            /* Server */
            document.getElementById('set-node-name').value = config.node_name || data.node_name || '';
            document.getElementById('set-port').value = config.port || data.port || '';
            document.getElementById('set-api-key').value = '********';

            /* SSL */
            document.getElementById('ssl-status').innerHTML = `
                <div class="flex-between mb-2">
                    <span>Status</span>
                    <span class="status ${data.ssl_enabled ? 'online' : 'offline'}">${data.ssl_enabled ? 'Enabled' : 'Disabled'}</span>
                </div>
                <p class="text-sm text-muted">SSL is configured in vod-server.conf. Set enabled=true and provide cert/key paths.</p>
            `;

            /* Storage */
            document.getElementById('set-library-path').value = config.library_path || '';
            document.getElementById('set-temp-path').value = config.temp_path || '';
            document.getElementById('set-min-space').value = config.min_free_space_gb || 10;

            /* Transcoding */
            document.getElementById('set-max-jobs').value = config.max_concurrent_jobs || 2;
            document.getElementById('set-default-profile').value = config.default_profile || 'standard';
            document.getElementById('set-hwaccel').value = config.hwaccel || 'none';
            document.getElementById('set-segment-dur').value = config.segment_duration || 6;

            /* Thumbnails */
            document.getElementById('set-thumb-enabled').checked = config.thumbnails_enabled !== false;
            document.getElementById('set-thumb-interval').value = config.thumb_interval || 10;
            document.getElementById('set-thumb-w').value = config.thumb_width || 160;
            document.getElementById('set-thumb-h').value = config.thumb_height || 90;

            /* Cluster */
            document.getElementById('set-health-interval').value = config.health_check_interval || 30;
            document.getElementById('set-offline-threshold').value = config.offline_threshold || 3;
            document.getElementById('set-max-migrations').value = config.max_concurrent_migrations || 1;

        } catch (err) {
            App.toast('Failed to load settings: ' + err.message, 'error');
        }
    },

    stop() {},

    toggleApiKey() {
        const input = document.getElementById('set-api-key');
        input.type = input.type === 'password' ? 'text' : 'password';
    },

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

    async checkFFmpeg() {
        const output = document.getElementById('tools-output');
        output.style.display = 'block';
        output.textContent = 'Checking FFmpeg...\n';
        try {
            const data = await App.get('/status');
            output.textContent += `FFmpeg: ${data.ffmpeg_available ? 'Available' : 'NOT FOUND'}\n`;
            output.textContent += `MP4Box: ${data.mp4box_available ? 'Available' : 'NOT FOUND'}\n`;
        } catch (err) {
            output.textContent += `Error: ${err.message}\n`;
        }
    },

    async checkMP4Box() {
        this.checkFFmpeg();
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
