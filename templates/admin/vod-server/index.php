<?php $pageTitle = 'VOD Server'; ?>

<style>
    .vod-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--border-color); margin-bottom: 1.5rem; }
    .vod-tab { padding: 0.75rem 1.25rem; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; color: var(--text-muted); font-weight: 500; font-size: 0.9rem; transition: all 0.2s; }
    .vod-tab:hover { color: var(--text-primary); }
    .vod-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
    .vod-panel { display: none; }
    .vod-panel.active { display: block; }
    .status-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .status-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 1.25rem; }
    .status-card .label { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem; }
    .status-card .value { font-size: 1.5rem; font-weight: 600; color: var(--text-primary); }
    .status-card .sub { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }
    .conn-status { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
    .conn-status.online { background: rgba(34, 197, 94, 0.15); color: var(--success); }
    .conn-status.offline { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
    .conn-status.unconfigured { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
    .conn-dot { width: 8px; height: 8px; border-radius: 50%; }
    .conn-status.online .conn-dot { background: var(--success); }
    .conn-status.offline .conn-dot { background: var(--danger); }
    .conn-status.unconfigured .conn-dot { background: var(--warning); }
    .disk-bar { height: 8px; background: var(--bg-hover); border-radius: 4px; overflow: hidden; margin-top: 0.5rem; }
    .disk-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
    .job-form .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .job-form .form-group { margin-bottom: 1rem; }
    .job-form label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--text-secondary); margin-bottom: 0.35rem; }
    .progress-cell { min-width: 140px; }
    .progress-bar-sm { height: 6px; background: var(--bg-hover); border-radius: 3px; overflow: hidden; display: inline-block; width: 100px; vertical-align: middle; }
    .progress-bar-sm .fill { height: 100%; border-radius: 3px; background: var(--primary); transition: width 0.3s; }
    .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
    .empty-state h3 { color: var(--text-secondary); margin-bottom: 0.5rem; }
    .badge-sm { font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: 10px; font-weight: 500; }
    .badge-ready { background: rgba(34, 197, 94, 0.15); color: var(--success); }
    .badge-pending { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
    .badge-processing { background: rgba(99, 102, 241, 0.15); color: var(--primary); }
    .badge-complete { background: rgba(34, 197, 94, 0.15); color: var(--success); }
    .badge-failed { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
    .badge-cancelled { background: rgba(148, 163, 184, 0.15); color: var(--text-muted); }
    .badge-packaging { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
    .badge-downloading { background: rgba(6, 182, 212, 0.15); color: #06b6d4; }
    .badge-transcoded { background: rgba(34, 197, 94, 0.15); color: var(--success); }
    .rendition-tags { display: flex; gap: 0.25rem; flex-wrap: wrap; }
    .rendition-tag { font-size: 0.65rem; padding: 0.1rem 0.4rem; background: rgba(99, 102, 241, 0.1); color: var(--primary); border-radius: 3px; }
    .browse-modal-list { max-height: 400px; overflow-y: auto; }
    .browse-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); cursor: pointer; border: 1px solid transparent; }
    .browse-item:hover { background: var(--bg-hover); }
    .browse-item.selected { border-color: var(--primary); background: rgba(99, 102, 241, 0.08); }
    .browse-item i { color: var(--text-muted); }
    .browse-item .name { flex: 1; font-size: 0.9rem; }
    .browse-item .size { font-size: 0.75rem; color: var(--text-muted); }
    .browse-path { font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted); padding: 0.5rem 0.75rem; background: var(--bg-dark); border-radius: var(--radius-sm); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
    .browse-path span { cursor: pointer; color: var(--primary); }
    .browse-path span:hover { text-decoration: underline; }
    .info-table td { padding: 0.4rem 0; }
    .info-table td:first-child { color: var(--text-muted); padding-right: 1.5rem; white-space: nowrap; font-size: 0.85rem; }
    .info-table td:last-child { font-size: 0.85rem; }

    /* Server selector */
    .server-selector { position: relative; }
    .server-selector-btn {
        display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.85rem;
        background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px;
        color: var(--text-primary); font-size: 0.85rem; cursor: pointer; transition: all 0.2s;
        min-width: 180px;
    }
    .server-selector-btn:hover { border-color: var(--primary); }
    .server-selector-btn .server-name { flex: 1; text-align: left; }
    .server-selector-btn .server-indicator { width: 8px; height: 8px; border-radius: 50%; background: var(--text-muted); }
    .server-selector-btn .server-indicator.active { background: var(--success); }
    .server-selector-btn .server-indicator.inactive { background: var(--danger); }
    .server-dropdown {
        position: absolute; top: calc(100% + 4px); right: 0; min-width: 280px;
        background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px;
        box-shadow: var(--shadow-lg); display: none; z-index: 200; overflow: hidden;
    }
    .server-dropdown.open { display: block; }
    .server-dropdown-item {
        display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 1rem;
        cursor: pointer; transition: all 0.15s; font-size: 0.85rem; color: var(--text-secondary);
    }
    .server-dropdown-item:hover { background: var(--bg-hover); color: var(--text-primary); }
    .server-dropdown-item.selected { background: rgba(99, 102, 241, 0.1); color: var(--primary-light); }
    .server-dropdown-item .name { flex: 1; }
    .server-dropdown-item .url { font-size: 0.7rem; color: var(--text-muted); }
    .server-dropdown-item .indicator { width: 8px; height: 8px; border-radius: 50%; }
    .server-dropdown-divider { border-top: 1px solid var(--border-color); margin: 0; }
    .server-dropdown-action { display: flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1rem; cursor: pointer; color: var(--primary-light); font-size: 0.85rem; transition: all 0.15s; }
    .server-dropdown-action:hover { background: var(--bg-hover); }

    /* Server modal form */
    .server-form .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .server-form .form-group { margin-bottom: 1rem; }
    .server-form label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--text-secondary); margin-bottom: 0.35rem; }
    .server-list-item {
        display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;
        border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 0.5rem;
    }
    .server-list-item .info { flex: 1; }
    .server-list-item .info .name { font-weight: 500; color: var(--text-primary); }
    .server-list-item .info .url { font-size: 0.8rem; color: var(--text-muted); }
    .server-list-item .actions { display: flex; gap: 0.25rem; }
    .badge-default { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); font-size: 0.7rem; padding: 0.1rem 0.4rem; border-radius: 3px; margin-left: 0.5rem; }
    .badge-inactive { background: rgba(239, 68, 68, 0.15); color: var(--danger); font-size: 0.7rem; padding: 0.1rem 0.4rem; border-radius: 3px; margin-left: 0.5rem; }

    /* Modal overlay */
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; }
    .modal { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); }
    .modal-header h2 { font-size: 1.1rem; font-weight: 600; }
    .modal-body { padding: 1.25rem; }
    .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; }

    @media (max-width: 768px) {
        .job-form .form-row, .server-form .form-row { grid-template-columns: 1fr; }
        .status-grid { grid-template-columns: 1fr 1fr; }
        .server-selector-btn { min-width: 140px; }
    }
</style>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">VOD Server</h1>
        <p class="page-subtitle">Manage transcoding, content library, and server connections.</p>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center">
        <span id="conn-badge" class="conn-status unconfigured">
            <span class="conn-dot"></span>
            <span id="conn-text">Checking...</span>
        </span>

        <!-- Server Selector Dropdown -->
        <div class="server-selector" id="server-selector">
            <button type="button" class="server-selector-btn" onclick="VodPage.toggleServerDropdown()">
                <span class="server-indicator" id="server-indicator"></span>
                <span class="server-name" id="server-selector-name">Select Server</span>
                <i class="lucide-chevron-down" style="font-size:0.85rem;color:var(--text-muted)"></i>
            </button>
            <div class="server-dropdown" id="server-dropdown">
                <div id="server-dropdown-list"></div>
                <div class="server-dropdown-divider"></div>
                <div class="server-dropdown-action" onclick="VodPage.showManageServers()">
                    <i class="lucide-settings" style="font-size:0.9rem"></i>
                    Manage Servers
                </div>
                <div class="server-dropdown-action" onclick="VodPage.showAddServer()">
                    <i class="lucide-plus" style="font-size:0.9rem"></i>
                    Add Server
                </div>
            </div>
        </div>
    </div>
</div>

<div class="vod-tabs">
    <div class="vod-tab active" data-tab="dashboard" onclick="VodPage.switchTab('dashboard')">Dashboard</div>
    <div class="vod-tab" data-tab="transcode" onclick="VodPage.switchTab('transcode')">Transcode</div>
    <div class="vod-tab" data-tab="jobs" onclick="VodPage.switchTab('jobs')">Jobs</div>
    <div class="vod-tab" data-tab="content" onclick="VodPage.switchTab('content')">Content Library</div>
</div>

<!-- Dashboard Panel -->
<div id="panel-dashboard" class="vod-panel active">
    <div id="dashboard-loading" class="empty-state">
        <p>Loading server status...</p>
    </div>
    <div id="dashboard-content" style="display:none">
        <div class="status-grid" id="status-cards"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="card">
                <div class="card-header"><h3>Server Info</h3></div>
                <div class="card-body">
                    <table class="info-table" style="width:100%">
                        <tr><td>Version</td><td id="info-version">-</td></tr>
                        <tr><td>Node Name</td><td id="info-node">-</td></tr>
                        <tr><td>Uptime</td><td id="info-uptime">-</td></tr>
                        <tr><td>FFmpeg</td><td id="info-ffmpeg">-</td></tr>
                        <tr><td>FFprobe</td><td id="info-ffprobe">-</td></tr>
                        <tr><td>MP4Box</td><td id="info-mp4box">-</td></tr>
                        <tr><td>SSL</td><td id="info-ssl">-</td></tr>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3>Storage</h3></div>
                <div class="card-body">
                    <table class="info-table" style="width:100%">
                        <tr><td>Total</td><td id="storage-total">-</td></tr>
                        <tr><td>Used</td><td id="storage-used">-</td></tr>
                        <tr><td>Free</td><td id="storage-free">-</td></tr>
                    </table>
                    <div class="disk-bar" style="margin-top:0.75rem">
                        <div class="disk-fill" id="disk-fill" style="width:0%;background:var(--success)"></div>
                    </div>
                    <div style="text-align:right;margin-top:0.25rem;font-size:0.75rem;color:var(--text-muted)" id="disk-pct">-</div>
                </div>
            </div>
        </div>
    </div>
    <div id="dashboard-no-server" style="display:none">
        <div class="empty-state">
            <i class="lucide-hard-drive" style="font-size:3rem;margin-bottom:1rem;color:var(--text-muted)"></i>
            <h3>No VOD Server Configured</h3>
            <p>Add a VOD server to get started with transcoding and content management.</p>
            <button class="btn btn-primary" style="margin-top:1rem" onclick="VodPage.showAddServer()">
                <i class="lucide-plus"></i> Add Server
            </button>
        </div>
    </div>
</div>

<!-- Transcode Panel -->
<div id="panel-transcode" class="vod-panel">
    <div class="card">
        <div class="card-header"><h3>Submit Transcode Job</h3></div>
        <div class="card-body">
            <form id="transcode-form" class="job-form" onsubmit="return VodPage.submitJob(event)">
                <input type="hidden" name="csrf_token" value="<?= \CariIPTV\Core\Session::csrf() ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Content ID *</label>
                        <input type="text" class="form-control" name="content_id" id="job-content-id" placeholder="e.g. movie-123" required>
                        <small style="color:var(--text-muted)">Unique identifier for this content</small>
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" class="form-control" name="title" id="job-title" placeholder="Movie or show title">
                    </div>
                </div>

                <div class="form-group">
                    <label>Source File Path *</label>
                    <div style="display:flex;gap:0.5rem">
                        <input type="text" class="form-control" name="source_path" id="job-source" placeholder="/path/to/source.mp4 or https://url/video.mp4" required style="flex:1">
                        <button type="button" class="btn btn-secondary" onclick="VodPage.browseSource()">
                            <i class="lucide-folder-open"></i> Browse
                        </button>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Source Type</label>
                        <select class="form-control" name="source_type" id="job-source-type">
                            <option value="file">Local File</option>
                            <option value="http">Remote URL</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Transcode Profile</label>
                        <select class="form-control" name="profile" id="job-profile">
                            <option value="standard">Standard (H.264 ABR)</option>
                            <option value="high">High (HEVC)</option>
                            <option value="low">Low Bandwidth (H.264)</option>
                            <option value="hevc_4k">HEVC 4K</option>
                            <option value="av1">AV1 (Web/Mobile)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Priority</label>
                        <select class="form-control" name="priority" id="job-priority">
                            <option value="1">1 - Highest</option>
                            <option value="3">3 - High</option>
                            <option value="5" selected>5 - Normal</option>
                            <option value="7">7 - Low</option>
                            <option value="10">10 - Lowest</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end">
                        <button type="submit" class="btn btn-primary" id="btn-submit-job" style="width:100%">
                            <i class="lucide-play"></i> Submit Transcode Job
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:1rem">
        <div class="card-header"><h3>Transcode Profiles</h3></div>
        <div class="card-body" id="profiles-info">
            <div class="empty-state"><p>Loading profiles...</p></div>
        </div>
    </div>
</div>

<!-- Jobs Panel -->
<div id="panel-jobs" class="vod-panel">
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <h3>Transcode Jobs</h3>
            <div style="display:flex;gap:0.5rem">
                <select class="form-control" style="width:auto;font-size:0.85rem" id="jobs-filter" onchange="VodPage.loadJobs()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="packaging">Packaging</option>
                    <option value="complete">Complete</option>
                    <option value="failed">Failed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button class="btn btn-secondary btn-sm" onclick="VodPage.loadJobs()">
                    <i class="lucide-refresh-cw"></i> Refresh
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Content ID</th>
                        <th>Title</th>
                        <th>Profile</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Step</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="jobs-tbody">
                    <tr><td colspan="9" class="text-center" style="color:var(--text-muted);padding:2rem">Select a server to view jobs</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Content Library Panel -->
<div id="panel-content" class="vod-panel">
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <h3>Content Library</h3>
            <div style="display:flex;gap:0.5rem">
                <input type="text" class="form-control" style="width:200px;font-size:0.85rem" id="content-search" placeholder="Search content..." onkeyup="VodPage.debounceSearch()">
                <button class="btn btn-secondary btn-sm" onclick="VodPage.loadContent()">
                    <i class="lucide-refresh-cw"></i> Refresh
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Content ID</th>
                        <th>Title</th>
                        <th>Codec</th>
                        <th>Renditions</th>
                        <th>Duration</th>
                        <th>Size</th>
                        <th>Thumbs</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="content-tbody">
                    <tr><td colspan="9" class="text-center" style="color:var(--text-muted);padding:2rem">Select a server to view content</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Manage Servers Modal -->
<div id="manage-servers-modal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:650px">
        <div class="modal-header">
            <h2>Manage VOD Servers</h2>
            <button class="btn btn-sm" onclick="VodPage.closeModal('manage-servers-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="server-list-container">
                <div class="empty-state"><p>Loading servers...</p></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="VodPage.closeModal('manage-servers-modal')">Close</button>
            <button class="btn btn-primary" onclick="VodPage.showAddServer()">
                <i class="lucide-plus"></i> Add Server
            </button>
        </div>
    </div>
</div>

<!-- Add/Edit Server Modal -->
<div id="server-form-modal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:550px">
        <div class="modal-header">
            <h2 id="server-form-title">Add VOD Server</h2>
            <button class="btn btn-sm" onclick="VodPage.closeModal('server-form-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="server-form" class="server-form" onsubmit="return VodPage.saveServer(event)">
                <input type="hidden" name="csrf_token" value="<?= \CariIPTV\Core\Session::csrf() ?>">
                <input type="hidden" name="server_edit_id" id="server-edit-id" value="">

                <div class="form-group">
                    <label>Server Name *</label>
                    <input type="text" class="form-control" name="name" id="srv-name" placeholder="e.g. Primary VOD, Backup Server" required>
                </div>

                <div class="form-group">
                    <label>Server URL *</label>
                    <input type="url" class="form-control" name="url" id="srv-url" placeholder="http://192.168.1.100:8090" required>
                    <small style="color:var(--text-muted)">Full URL including port (default: 8090)</small>
                </div>

                <div class="form-group">
                    <label>API Key</label>
                    <div style="display:flex;gap:0.5rem">
                        <input type="password" class="form-control" name="api_key" id="srv-apikey" placeholder="VOD Server API key" style="flex:1">
                        <button type="button" class="btn btn-secondary" onclick="VodPage.toggleField('srv-apikey','srv-apikey-icon')">
                            <i class="lucide-eye" id="srv-apikey-icon"></i>
                        </button>
                    </div>
                    <small style="color:var(--text-muted)">Found in /etc/vod-server/vod-server.conf</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_default" id="srv-default" value="1">
                            Set as default server
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" id="srv-active" value="1" checked>
                            Server is active
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea class="form-control" name="notes" id="srv-notes" rows="2" placeholder="Optional notes about this server"></textarea>
                </div>

                <div id="srv-test-result" style="display:none;margin-bottom:1rem;padding:0.75rem;border-radius:var(--radius-sm);font-size:0.85rem"></div>
            </form>
        </div>
        <div class="modal-footer" style="display:flex;justify-content:space-between">
            <button type="button" class="btn btn-secondary" onclick="VodPage.testServerConnection()">
                <i class="lucide-wifi"></i> Test Connection
            </button>
            <div style="display:flex;gap:0.5rem">
                <button class="btn btn-secondary" onclick="VodPage.closeModal('server-form-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" form="server-form" id="btn-save-server">
                    <i class="lucide-save"></i> Save Server
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Browse Modal -->
<div id="browse-modal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <h2>Browse Files</h2>
            <button class="btn btn-sm" onclick="VodPage.closeModal('browse-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="browse-path" id="browse-path">/</div>
            <div class="browse-modal-list" id="browse-list">
                <div class="empty-state"><p>Loading...</p></div>
            </div>
        </div>
        <div class="modal-footer" style="display:flex;justify-content:space-between">
            <button class="btn btn-secondary" onclick="VodPage.closeModal('browse-modal')">Cancel</button>
            <button class="btn btn-primary" id="browse-select-btn" onclick="VodPage.selectBrowsed()" disabled>Select File</button>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= \CariIPTV\Core\Session::csrf() ?>';
const VodPage = {
    refreshTimer: null,
    searchTimer: null,
    currentBrowsePath: '/',
    selectedBrowseFile: null,
    profiles: [],
    servers: <?= json_encode($servers ?? []) ?>,
    selectedServerId: <?= (int)($defaultServerId ?? 0) ?>,

    init() {
        this.renderServerDropdown();
        if (this.selectedServerId > 0) {
            this.loadDashboard();
        } else if (this.servers.length > 0) {
            this.selectServer(this.servers[0].id);
        } else {
            this.showNoServer();
        }
        this.startAutoRefresh();
    },

    /* ===================== Server Selector ===================== */
    toggleServerDropdown() {
        document.getElementById('server-dropdown').classList.toggle('open');
    },

    renderServerDropdown() {
        const list = document.getElementById('server-dropdown-list');
        const nameEl = document.getElementById('server-selector-name');
        const indicator = document.getElementById('server-indicator');

        if (this.servers.length === 0) {
            list.innerHTML = '<div style="padding:0.75rem 1rem;color:var(--text-muted);font-size:0.85rem">No servers configured</div>';
            nameEl.textContent = 'No Servers';
            indicator.className = 'server-indicator';
            return;
        }

        list.innerHTML = this.servers.map(s => `
            <div class="server-dropdown-item ${s.id == this.selectedServerId ? 'selected' : ''}"
                 onclick="VodPage.selectServer(${s.id})">
                <span class="indicator" style="background:${s.is_active ? 'var(--success)' : 'var(--danger)'}"></span>
                <div style="flex:1">
                    <div class="name">${this.esc(s.name)}${s.is_default ? ' <span class="badge-default">default</span>' : ''}</div>
                    <div class="url">${this.esc(s.url)}</div>
                </div>
            </div>
        `).join('');

        const current = this.servers.find(s => s.id == this.selectedServerId);
        if (current) {
            nameEl.textContent = current.name;
            indicator.className = 'server-indicator ' + (current.is_active ? 'active' : 'inactive');
        } else if (this.servers.length > 0) {
            nameEl.textContent = 'Select Server';
            indicator.className = 'server-indicator';
        }
    },

    selectServer(id) {
        this.selectedServerId = id;
        this.renderServerDropdown();
        document.getElementById('server-dropdown').classList.remove('open');

        // Reload active tab data
        const activeTab = document.querySelector('.vod-tab.active');
        const tab = activeTab ? activeTab.dataset.tab : 'dashboard';
        this.switchTab(tab);
    },

    showNoServer() {
        document.getElementById('dashboard-loading').style.display = 'none';
        document.getElementById('dashboard-content').style.display = 'none';
        document.getElementById('dashboard-no-server').style.display = 'block';
        this.setConnStatus('unconfigured', 'No Server');
    },

    serverParam() {
        return 'server_id=' + this.selectedServerId;
    },

    /* ===================== Server Management ===================== */
    async loadServerList() {
        try {
            const res = await fetch('/admin/vod-server/servers');
            const data = await res.json();
            if (data.success) {
                this.servers = data.servers || [];
                this.renderServerDropdown();
            }
        } catch (err) { /* ignore */ }
    },

    showManageServers() {
        document.getElementById('server-dropdown').classList.remove('open');
        this.renderManageList();
        document.getElementById('manage-servers-modal').style.display = 'flex';
    },

    renderManageList() {
        const container = document.getElementById('server-list-container');
        if (this.servers.length === 0) {
            container.innerHTML = '<div class="empty-state"><h3>No Servers</h3><p>Add a VOD server to get started.</p></div>';
            return;
        }
        container.innerHTML = this.servers.map(s => `
            <div class="server-list-item">
                <span class="indicator" style="width:10px;height:10px;border-radius:50%;background:${s.is_active ? 'var(--success)' : 'var(--danger)'}"></span>
                <div class="info">
                    <div class="name">
                        ${this.esc(s.name)}
                        ${s.is_default ? '<span class="badge-default">default</span>' : ''}
                        ${!s.is_active ? '<span class="badge-inactive">inactive</span>' : ''}
                    </div>
                    <div class="url">${this.esc(s.url)}</div>
                </div>
                <div class="actions">
                    <button class="btn btn-secondary btn-sm" onclick="VodPage.editServer(${s.id})" title="Edit">
                        <i class="lucide-pencil"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="VodPage.deleteServer(${s.id}, '${this.escAttr(s.name)}')" title="Delete">
                        <i class="lucide-trash-2"></i>
                    </button>
                </div>
            </div>
        `).join('');
    },

    showAddServer() {
        document.getElementById('server-dropdown').classList.remove('open');
        document.getElementById('server-form-title').textContent = 'Add VOD Server';
        document.getElementById('server-edit-id').value = '';
        document.getElementById('server-form').reset();
        document.getElementById('srv-active').checked = true;
        document.getElementById('srv-default').checked = this.servers.length === 0;
        document.getElementById('srv-test-result').style.display = 'none';
        document.getElementById('btn-save-server').innerHTML = '<i class="lucide-save"></i> Add Server';
        document.getElementById('server-form-modal').style.display = 'flex';
    },

    editServer(id) {
        const server = this.servers.find(s => s.id === id);
        if (!server) return;

        document.getElementById('server-form-title').textContent = 'Edit VOD Server';
        document.getElementById('server-edit-id').value = id;
        document.getElementById('srv-name').value = server.name;
        document.getElementById('srv-url').value = server.url;
        document.getElementById('srv-apikey').value = server.api_key || '';
        document.getElementById('srv-default').checked = !!server.is_default;
        document.getElementById('srv-active').checked = server.is_active !== 0;
        document.getElementById('srv-notes').value = server.notes || '';
        document.getElementById('srv-test-result').style.display = 'none';
        document.getElementById('btn-save-server').innerHTML = '<i class="lucide-save"></i> Update Server';
        document.getElementById('server-form-modal').style.display = 'flex';
    },

    async saveServer(e) {
        e.preventDefault();
        const editId = document.getElementById('server-edit-id').value;
        const isEdit = editId && editId !== '';
        const btn = document.getElementById('btn-save-server');
        btn.disabled = true;

        try {
            const form = document.getElementById('server-form');
            const body = new URLSearchParams(new FormData(form));

            // Ensure is_active is sent even when unchecked
            if (!document.getElementById('srv-active').checked) {
                body.set('is_active', '0');
            }

            const url = isEdit
                ? '/admin/vod-server/servers/' + editId + '/update'
                : '/admin/vod-server/servers/add';

            const res = await fetch(url, { method: 'POST', body: body });
            const data = await res.json();

            if (data.success) {
                this.toast(data.message || (isEdit ? 'Server updated' : 'Server added'), 'success');
                this.closeModal('server-form-modal');
                await this.loadServerList();
                this.renderManageList();

                // If we just added the first server or this is the new default, select it
                if (!isEdit && data.id) {
                    this.selectServer(data.id);
                } else if (this.selectedServerId == editId) {
                    this.renderServerDropdown();
                    this.loadDashboard();
                }
            } else {
                this.toast(data.error || 'Failed', 'error');
            }
        } catch (err) {
            this.toast(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
        return false;
    },

    async deleteServer(id, name) {
        if (!confirm('Delete server "' + name + '"? This only removes it from the admin panel, not the actual VOD server.')) return;

        try {
            const res = await fetch('/admin/vod-server/servers/' + id + '/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(CSRF)
            });
            const data = await res.json();
            if (data.success) {
                this.toast('Server removed', 'success');
                await this.loadServerList();
                this.renderManageList();

                if (this.selectedServerId === id) {
                    if (this.servers.length > 0) {
                        this.selectServer(this.servers[0].id);
                    } else {
                        this.selectedServerId = 0;
                        this.renderServerDropdown();
                        this.showNoServer();
                    }
                }
            } else {
                this.toast(data.error || 'Failed', 'error');
            }
        } catch (err) {
            this.toast(err.message, 'error');
        }
    },

    async testServerConnection() {
        const url = document.getElementById('srv-url').value;
        const apiKey = document.getElementById('srv-apikey').value;
        const resultDiv = document.getElementById('srv-test-result');

        if (!url) {
            this.toast('Enter a server URL first', 'error');
            return;
        }

        resultDiv.style.display = 'block';
        resultDiv.style.background = 'rgba(99, 102, 241, 0.1)';
        resultDiv.style.color = 'var(--text-secondary)';
        resultDiv.textContent = 'Testing connection...';

        try {
            const res = await fetch('/admin/vod-server/test-connection', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(CSRF) + '&url=' + encodeURIComponent(url) + '&api_key=' + encodeURIComponent(apiKey)
            });
            const data = await res.json();

            if (data.success) {
                resultDiv.style.background = 'rgba(34, 197, 94, 0.1)';
                resultDiv.style.color = 'var(--success)';
                resultDiv.innerHTML = 'Connected! Server: <strong>' + this.esc(data.node_name) + '</strong> v' + this.esc(data.version) + ' | Content: ' + (data.content_count || 0) + ' items | Jobs: ' + (data.active_jobs || 0);
            } else {
                resultDiv.style.background = 'rgba(239, 68, 68, 0.1)';
                resultDiv.style.color = 'var(--danger)';
                resultDiv.textContent = 'Failed: ' + (data.error || 'Unknown error');
            }
        } catch (err) {
            resultDiv.style.background = 'rgba(239, 68, 68, 0.1)';
            resultDiv.style.color = 'var(--danger)';
            resultDiv.textContent = 'Error: ' + err.message;
        }
    },

    toggleField(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'lucide-eye-off';
        } else {
            input.type = 'password';
            icon.className = 'lucide-eye';
        }
    },

    closeModal(id) {
        document.getElementById(id).style.display = 'none';
    },

    /* ===================== Tabs ===================== */
    switchTab(tab) {
        document.querySelectorAll('.vod-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
        document.querySelectorAll('.vod-panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + tab));

        if (!this.selectedServerId) return;

        if (tab === 'jobs') this.loadJobs();
        else if (tab === 'content') this.loadContent();
        else if (tab === 'transcode') this.loadProfiles();
        else if (tab === 'dashboard') this.loadDashboard();
    },

    /* ===================== Dashboard ===================== */
    async loadDashboard() {
        if (!this.selectedServerId) { this.showNoServer(); return; }

        try {
            const res = await fetch('/admin/vod-server/status?' + this.serverParam());
            const data = await res.json();

            if (!data.success) {
                document.getElementById('dashboard-loading').style.display = 'none';
                document.getElementById('dashboard-content').style.display = 'none';
                document.getElementById('dashboard-no-server').style.display = 'block';
                this.setConnStatus('offline', data.error || 'Connection Failed');
                return;
            }

            const s = data.status;
            document.getElementById('dashboard-loading').style.display = 'none';
            document.getElementById('dashboard-content').style.display = 'block';
            document.getElementById('dashboard-no-server').style.display = 'none';

            this.setConnStatus('online', s.node_name || 'Connected');

            document.getElementById('status-cards').innerHTML = `
                <div class="status-card">
                    <div class="label">Content Items</div>
                    <div class="value">${s.content_count || 0}</div>
                </div>
                <div class="status-card">
                    <div class="label">Active Jobs</div>
                    <div class="value">${s.active_jobs || 0}</div>
                </div>
                <div class="status-card">
                    <div class="label">CPU Usage</div>
                    <div class="value">${s.cpu_usage !== undefined ? s.cpu_usage.toFixed(1) + '%' : '-'}</div>
                </div>
                <div class="status-card">
                    <div class="label">Memory Usage</div>
                    <div class="value">${s.memory_usage !== undefined ? s.memory_usage.toFixed(1) + '%' : '-'}</div>
                </div>
            `;

            document.getElementById('info-version').textContent = s.version || '-';
            document.getElementById('info-node').textContent = s.node_name || '-';
            document.getElementById('info-uptime').textContent = s.uptime || this.formatDuration(s.uptime_seconds);
            document.getElementById('info-ffmpeg').textContent = s.ffmpeg_available ? 'Available' : 'Not Found';
            document.getElementById('info-ffprobe').textContent = s.ffprobe_available ? 'Available' : 'Not Found';
            document.getElementById('info-mp4box').textContent = s.mp4box_available ? 'Available' : 'Not Found';
            document.getElementById('info-ssl').textContent = s.ssl_enabled ? 'Enabled' : 'Disabled';

            if (s.disk) {
                document.getElementById('storage-total').textContent = s.disk.total || this.formatBytes(s.disk.total_bytes);
                document.getElementById('storage-used').textContent = s.disk.used || this.formatBytes(s.disk.used_bytes);
                document.getElementById('storage-free').textContent = s.disk.free || this.formatBytes(s.disk.free_bytes);
                const pct = s.disk.usage_percent || 0;
                const fill = document.getElementById('disk-fill');
                fill.style.width = pct.toFixed(1) + '%';
                fill.style.background = pct > 90 ? 'var(--danger)' : pct > 75 ? 'var(--warning)' : 'var(--success)';
                document.getElementById('disk-pct').textContent = pct.toFixed(1) + '% used';
            }

        } catch (err) {
            this.setConnStatus('offline', 'Disconnected');
            document.getElementById('dashboard-loading').style.display = 'none';
            document.getElementById('dashboard-no-server').style.display = 'block';
            document.getElementById('dashboard-content').style.display = 'none';
        }
    },

    setConnStatus(state, text) {
        const badge = document.getElementById('conn-badge');
        badge.className = 'conn-status ' + state;
        document.getElementById('conn-text').textContent = text;
    },

    /* ===================== Jobs ===================== */
    async loadJobs() {
        if (!this.selectedServerId) return;

        const filter = document.getElementById('jobs-filter').value;
        const tbody = document.getElementById('jobs-tbody');

        try {
            const params = new URLSearchParams({ server_id: this.selectedServerId, limit: 50 });
            if (filter) params.set('status', filter);

            const res = await fetch('/admin/vod-server/jobs?' + params);
            const data = await res.json();

            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center" style="color:var(--danger);padding:1rem">${this.esc(data.error)}</td></tr>`;
                return;
            }

            const items = data.data?.items || [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state"><h3>No jobs</h3><p>Submit a transcode job to get started.</p></div></td></tr>';
                return;
            }

            tbody.innerHTML = items.map(j => `
                <tr>
                    <td style="font-family:var(--font-mono);font-size:0.85rem">#${j.id}</td>
                    <td style="font-family:var(--font-mono);font-size:0.8rem">${this.esc(j.content_id)}</td>
                    <td>${this.esc(j.title || '-')}</td>
                    <td>${this.esc(j.profile || '-')}</td>
                    <td><span class="badge-sm badge-${j.status}">${j.status}</span></td>
                    <td class="progress-cell">
                        ${['processing','packaging','downloading'].includes(j.status) ? `
                            <div style="display:flex;align-items:center;gap:0.5rem">
                                <div class="progress-bar-sm"><div class="fill" style="width:${j.progress||0}%"></div></div>
                                <span style="font-size:0.75rem;font-family:var(--font-mono)">${(j.progress||0).toFixed(1)}%</span>
                            </div>
                        ` : j.status === 'complete' ? '<span style="color:var(--success);font-size:0.85rem">100%</span>' : '-'}
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-muted)">${this.esc(j.current_step || '-')}</td>
                    <td style="font-size:0.8rem;color:var(--text-muted)">${this.timeAgo(j.created_at)}</td>
                    <td>
                        ${['pending','processing','packaging','downloading'].includes(j.status) ?
                            `<button class="btn btn-danger btn-sm" onclick="VodPage.cancelJob(${j.id})">Cancel</button>` : ''}
                        ${j.status === 'failed' ?
                            `<button class="btn btn-secondary btn-sm" onclick="VodPage.showJobError(${j.id})" title="${this.esc(j.error_msg||'')}">Error</button>` : ''}
                    </td>
                </tr>
            `).join('');

        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center" style="color:var(--danger);padding:1rem">${this.esc(err.message)}</td></tr>`;
        }
    },

    async cancelJob(id) {
        if (!confirm('Cancel job #' + id + '?')) return;
        try {
            const res = await fetch('/admin/vod-server/jobs/' + id + '/cancel', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(CSRF) + '&server_id=' + this.selectedServerId
            });
            const data = await res.json();
            if (data.success) {
                this.toast('Job cancelled', 'success');
                this.loadJobs();
            } else {
                this.toast(data.error || 'Failed', 'error');
            }
        } catch (err) {
            this.toast(err.message, 'error');
        }
    },

    async showJobError(id) {
        try {
            const res = await fetch('/admin/vod-server/job-detail?' + this.serverParam() + '&job_id=' + id);
            const data = await res.json();
            const job = data.job || {};
            alert('Job #' + id + ' Error:\n\n' + (job.error_msg || 'No error message'));
        } catch (err) {
            alert('Failed to load error: ' + err.message);
        }
    },

    /* ===================== Content ===================== */
    async loadContent() {
        if (!this.selectedServerId) return;

        const tbody = document.getElementById('content-tbody');
        const search = document.getElementById('content-search').value;

        try {
            const params = new URLSearchParams({ server_id: this.selectedServerId, limit: 50 });
            if (search) params.set('search', search);

            const res = await fetch('/admin/vod-server/content?' + params);
            const data = await res.json();

            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center" style="color:var(--danger);padding:1rem">${this.esc(data.error)}</td></tr>`;
                return;
            }

            const items = data.data?.items || [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state"><h3>No content</h3><p>Submit a transcode job to add content.</p></div></td></tr>';
                return;
            }

            tbody.innerHTML = items.map(c => {
                let renditions = '-';
                try {
                    const r = typeof c.renditions === 'string' ? JSON.parse(c.renditions) : c.renditions;
                    if (Array.isArray(r)) {
                        renditions = r.map(x => `<span class="rendition-tag">${x.resolution || x.label || '?'}</span>`).join('');
                    } else if (typeof c.renditions === 'string' && c.renditions) {
                        renditions = c.renditions.split(',').map(x => `<span class="rendition-tag">${x.trim()}</span>`).join('');
                    }
                } catch(e) {}

                return `<tr>
                    <td style="font-family:var(--font-mono);font-size:0.8rem">${this.esc(c.content_id)}</td>
                    <td>${this.esc(c.title || '-')}</td>
                    <td>${this.esc(c.codec || '-')}</td>
                    <td><div class="rendition-tags">${renditions}</div></td>
                    <td>${this.formatDuration(c.duration)}</td>
                    <td>${c.size_human || this.formatBytes(c.size_bytes)}</td>
                    <td>${c.has_thumbnails ? '<span style="color:var(--success)">Yes</span>' : '<span style="color:var(--text-muted)">No</span>'}</td>
                    <td><span class="badge-sm badge-${c.status}">${c.status}</span></td>
                    <td style="white-space:nowrap">
                        <button class="btn btn-secondary btn-sm" onclick="VodPage.copyStreamUrl('${this.esc(c.content_id)}')" title="Copy HLS URL">
                            <i class="lucide-link"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="VodPage.deleteContent('${this.esc(c.content_id)}')" title="Delete">
                            <i class="lucide-trash-2"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');

        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center" style="color:var(--danger);padding:1rem">${this.esc(err.message)}</td></tr>`;
        }
    },

    debounceSearch() {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => this.loadContent(), 400);
    },

    copyStreamUrl(contentId) {
        const server = this.servers.find(s => s.id == this.selectedServerId);
        if (!server) return;
        const streamUrl = server.url + '/content/' + encodeURIComponent(contentId) + '/master.m3u8';
        navigator.clipboard.writeText(streamUrl).then(() => {
            this.toast('HLS URL copied to clipboard', 'success');
        }).catch(() => {
            prompt('Copy this URL:', streamUrl);
        });
    },

    async deleteContent(contentId) {
        if (!confirm('Delete content "' + contentId + '" and all its files from the VOD server?')) return;
        try {
            const res = await fetch('/admin/vod-server/content/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(CSRF) + '&server_id=' + this.selectedServerId + '&content_id=' + encodeURIComponent(contentId)
            });
            const data = await res.json();
            if (data.success) {
                this.toast('Content deleted', 'success');
                this.loadContent();
            } else {
                this.toast(data.error || 'Failed', 'error');
            }
        } catch (err) {
            this.toast(err.message, 'error');
        }
    },

    /* ===================== Transcode ===================== */
    async loadProfiles() {
        if (!this.selectedServerId) return;

        const container = document.getElementById('profiles-info');
        try {
            const res = await fetch('/admin/vod-server/config?' + this.serverParam());
            const data = await res.json();
            if (!data.success) {
                container.innerHTML = '<div class="empty-state"><p>' + this.esc(data.error) + '</p></div>';
                return;
            }

            this.profiles = data.config?.profiles || [];

            const select = document.getElementById('job-profile');
            if (this.profiles.length > 0) {
                select.innerHTML = this.profiles.map(p =>
                    `<option value="${this.esc(p.name)}">${this.esc(p.name)} (${this.esc(p.codec || 'unknown')})</option>`
                ).join('');
            }

            if (this.profiles.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No profiles configured on the server.</p></div>';
                return;
            }

            container.innerHTML = `<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem">
                ${this.profiles.map(p => `
                    <div style="border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:1rem">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem">
                            <strong>${this.esc(p.name)}</strong>
                            <span class="badge-sm badge-ready">${this.esc(p.codec || '')}</span>
                        </div>
                        ${p.renditions ? `
                            <div style="font-size:0.8rem;color:var(--text-muted)">
                                ${(Array.isArray(p.renditions) ? p.renditions : []).map(r =>
                                    `<div style="display:flex;justify-content:space-between;padding:0.15rem 0">
                                        <span>${r.label || r.resolution || (r.width + 'x' + r.height)}</span>
                                        <span>${r.bitrate_kbps ? r.bitrate_kbps + 'k' : ''}</span>
                                    </div>`
                                ).join('')}
                            </div>
                        ` : ''}
                    </div>
                `).join('')}
            </div>`;

        } catch (err) {
            container.innerHTML = '<div class="empty-state"><p>' + this.esc(err.message) + '</p></div>';
        }
    },

    async submitJob(e) {
        e.preventDefault();
        if (!this.selectedServerId) { this.toast('Select a server first', 'error'); return false; }

        const btn = document.getElementById('btn-submit-job');
        btn.disabled = true;
        btn.innerHTML = '<i class="lucide-loader"></i> Submitting...';

        try {
            const form = document.getElementById('transcode-form');
            const body = new URLSearchParams(new FormData(form));
            body.set('server_id', this.selectedServerId);

            const res = await fetch('/admin/vod-server/jobs/submit', {
                method: 'POST',
                body: body
            });
            const data = await res.json();

            if (data.success) {
                this.toast(data.message || 'Job submitted!', 'success');
                form.reset();
                this.switchTab('jobs');
            } else {
                this.toast(data.error || 'Failed to submit job', 'error');
            }
        } catch (err) {
            this.toast(err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="lucide-play"></i> Submit Transcode Job';
        }
        return false;
    },

    /* ===================== Browse Files ===================== */
    browseSource() {
        if (!this.selectedServerId) { this.toast('Select a server first', 'error'); return; }
        this.selectedBrowseFile = null;
        document.getElementById('browse-select-btn').disabled = true;
        document.getElementById('browse-modal').style.display = 'flex';
        this.loadBrowseDir('/');
    },

    async loadBrowseDir(path) {
        this.currentBrowsePath = path;
        const list = document.getElementById('browse-list');
        list.innerHTML = '<div class="empty-state"><p>Loading...</p></div>';

        const parts = path.split('/').filter(Boolean);
        let breadcrumb = '<span onclick="VodPage.loadBrowseDir(\'/\')">/</span>';
        let acc = '';
        parts.forEach(p => {
            acc += '/' + p;
            const fullPath = acc;
            breadcrumb += ' <span onclick="VodPage.loadBrowseDir(\'' + fullPath + '\')">' + p + '</span> /';
        });
        document.getElementById('browse-path').innerHTML = breadcrumb;

        try {
            const res = await fetch('/admin/vod-server/browse?' + this.serverParam() + '&path=' + encodeURIComponent(path));
            const data = await res.json();

            if (!data.success) {
                list.innerHTML = '<div class="empty-state"><p>' + this.esc(data.error) + '</p></div>';
                return;
            }

            const entries = data.data?.entries || data.data?.items || [];
            if (entries.length === 0) {
                list.innerHTML = '<div class="empty-state"><p>Empty directory</p></div>';
                return;
            }

            const dirs = entries.filter(e => e.type === 'directory').sort((a, b) => a.name.localeCompare(b.name));
            const files = entries.filter(e => e.type !== 'directory').sort((a, b) => a.name.localeCompare(b.name));

            let html = '';
            if (path !== '/') {
                const parent = path.replace(/\/[^/]+\/?$/, '') || '/';
                html += `<div class="browse-item" onclick="VodPage.loadBrowseDir('${parent}')">
                    <i class="lucide-arrow-up"></i>
                    <span class="name">..</span>
                    <span class="size"></span>
                </div>`;
            }

            dirs.forEach(d => {
                const fullPath = (path === '/' ? '' : path) + '/' + d.name;
                html += `<div class="browse-item" ondblclick="VodPage.loadBrowseDir('${fullPath}')">
                    <i class="lucide-folder"></i>
                    <span class="name">${this.esc(d.name)}</span>
                    <span class="size"></span>
                </div>`;
            });

            const videoExts = ['.mp4','.mkv','.avi','.mov','.wmv','.flv','.webm','.ts','.m2ts','.mpg','.mpeg','.m4v','.3gp'];
            files.forEach(f => {
                const ext = '.' + (f.name.split('.').pop() || '').toLowerCase();
                const isVideo = videoExts.includes(ext);
                const fullPath = (path === '/' ? '' : path) + '/' + f.name;
                html += `<div class="browse-item ${isVideo ? '' : 'disabled'}" onclick="VodPage.selectFile(this, '${this.escAttr(fullPath)}')">
                    <i class="lucide-${isVideo ? 'film' : 'file'}"></i>
                    <span class="name">${this.esc(f.name)}</span>
                    <span class="size">${f.size ? this.formatBytes(f.size) : ''}</span>
                </div>`;
            });

            list.innerHTML = html;

        } catch (err) {
            list.innerHTML = '<div class="empty-state"><p>' + this.esc(err.message) + '</p></div>';
        }
    },

    selectFile(el, path) {
        document.querySelectorAll('.browse-item.selected').forEach(e => e.classList.remove('selected'));
        el.classList.add('selected');
        this.selectedBrowseFile = path;
        document.getElementById('browse-select-btn').disabled = false;
    },

    selectBrowsed() {
        if (this.selectedBrowseFile) {
            document.getElementById('job-source').value = this.selectedBrowseFile;
            document.getElementById('job-source-type').value = 'file';
        }
        this.closeModal('browse-modal');
    },

    /* ===================== Auto-refresh ===================== */
    startAutoRefresh() {
        this.refreshTimer = setInterval(() => {
            if (!this.selectedServerId) return;
            const activeTab = document.querySelector('.vod-tab.active');
            if (!activeTab) return;
            const tab = activeTab.dataset.tab;
            if (tab === 'dashboard') this.loadDashboard();
            else if (tab === 'jobs') this.loadJobs();
        }, 5000);
    },

    /* ===================== Utilities ===================== */
    formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },

    formatDuration(seconds) {
        if (!seconds) return '0s';
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = Math.floor(seconds % 60);
        if (h > 0) return h + 'h ' + m + 'm';
        if (m > 0) return m + 'm ' + s + 's';
        return s + 's';
    },

    timeAgo(dateStr) {
        if (!dateStr) return 'Never';
        const now = new Date();
        const date = new Date(dateStr);
        const seconds = Math.floor((now - date) / 1000);
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
        return Math.floor(seconds / 86400) + 'd ago';
    },

    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    },

    escAttr(str) {
        return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    },

    toast(message, type) {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + (type || 'info');
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 300ms';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
};

// Close server dropdown when clicking outside
document.addEventListener('click', function(e) {
    const sel = document.getElementById('server-selector');
    if (sel && !sel.contains(e.target)) {
        document.getElementById('server-dropdown').classList.remove('open');
    }
});

document.addEventListener('DOMContentLoaded', () => VodPage.init());
</script>
