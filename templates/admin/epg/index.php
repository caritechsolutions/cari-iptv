<?php $pageTitle = 'EPG'; ?>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">EPG Management</h1>
        <p class="page-subtitle">Manage electronic programme guide sources and channel mappings.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-secondary" onclick="cleanupProgrammes()">
            <i class="lucide-trash-2"></i> Cleanup Old
        </button>
        <button type="button" class="btn btn-primary" onclick="openAddModal()">
            <i class="lucide-plus"></i> Add Source
        </button>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="lucide-database"></i></div>
        <div class="stat-content">
            <div class="stat-label">EPG Sources</div>
            <div class="stat-value"><?= $stats['total_sources'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="lucide-calendar"></i></div>
        <div class="stat-content">
            <div class="stat-label">Programmes</div>
            <div class="stat-value"><?= number_format($stats['total_programmes']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="lucide-clock"></i></div>
        <div class="stat-content">
            <div class="stat-label">Upcoming</div>
            <div class="stat-value"><?= number_format($stats['upcoming_programmes']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="lucide-link"></i></div>
        <div class="stat-content">
            <div class="stat-label">Mapped Channels</div>
            <div class="stat-value"><?= $stats['mapped_channels'] ?> / <?= $stats['total_mappings'] ?></div>
        </div>
    </div>
</div>

<!-- Sources -->
<div class="card">
    <div class="card-header">
        <h3><i class="lucide-radio-tower"></i> EPG Sources</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($sources)): ?>
            <div class="empty-state" style="padding: 3rem; text-align: center;">
                <i class="lucide-radio-tower" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
                <p>No EPG sources configured.</p>
                <button class="btn btn-primary btn-sm" onclick="openAddModal()" style="margin-top: 1rem;">
                    <i class="lucide-plus"></i> Add Your First Source
                </button>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Source</th>
                            <th>Programmes</th>
                            <th>Channels</th>
                            <th>Last Fetch</th>
                            <th>Status</th>
                            <th style="width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sources as $src): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($src['name']) ?></strong>
                                    <?php if ($src['auto_refresh']): ?>
                                        <span class="badge badge-info" style="font-size: 0.6rem;">AUTO</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $typeBadge = match($src['type']) {
                                        'eit' => '<span class="badge badge-primary">EIT/DVB</span>',
                                        'xmltv_file' => '<span class="badge badge-secondary">XMLTV File</span>',
                                        'xmltv_url' => '<span class="badge badge-info">XMLTV URL</span>',
                                        default => '<span class="badge">' . htmlspecialchars($src['type']) . '</span>',
                                    };
                                    echo $typeBadge;
                                    ?>
                                </td>
                                <td class="text-sm text-muted" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php if ($src['type'] === 'eit'): ?>
                                        <?= htmlspecialchars($src['source_url'] . ':' . $src['source_port']) ?>
                                        <span class="text-xs">(PID <?= htmlspecialchars($src['eit_pid']) ?>)</span>
                                    <?php elseif ($src['source_url']): ?>
                                        <?= htmlspecialchars($src['source_url']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($src['programme_count']) ?></td>
                                <td><?= number_format($src['channel_count']) ?></td>
                                <td class="text-sm text-muted">
                                    <?= $src['last_fetch'] ? date('M j, H:i', strtotime($src['last_fetch'])) : 'Never' ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match($src['last_status']) {
                                        'success' => '<span class="badge badge-success">OK</span>',
                                        'error' => '<span class="badge badge-danger" title="' . htmlspecialchars($src['last_message'] ?? '') . '">Error</span>',
                                        'running' => '<span class="badge badge-warning">Running</span>',
                                        default => '<span class="badge badge-secondary">Pending</span>',
                                    };
                                    echo $statusBadge;
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-primary btn-sm" onclick="fetchSource(<?= $src['id'] ?>, <?= (int)$src['capture_timeout'] ?>, '<?= htmlspecialchars($src['type']) ?>')" title="Fetch now" id="fetchBtn-<?= $src['id'] ?>">
                                            <i class="lucide-download"></i> Fetch
                                        </button>
                                        <?php if ($src['type'] === 'xmltv_file'): ?>
                                            <button class="btn btn-info btn-sm" onclick="openUploadModal(<?= $src['id'] ?>, '<?= htmlspecialchars($src['name']) ?>')" title="Upload XMLTV">
                                                <i class="lucide-upload"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-secondary btn-sm" onclick="openMappingsModal(<?= $src['id'] ?>, '<?= htmlspecialchars($src['name']) ?>')" title="Channel mappings">
                                            <i class="lucide-link"></i> Map
                                        </button>
                                        <button class="btn btn-secondary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($src)) ?>)" title="Edit">
                                            Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteSource(<?= $src['id'] ?>, '<?= htmlspecialchars($src['name']) ?>')" title="Delete">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Fetch Progress -->
<div id="fetchProgress" class="card" style="display: none; margin-top: 16px;">
    <div class="card-body" style="padding: 1.25rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="fetch-spinner"></div>
                <span id="fetchPhaseText" style="font-weight: 500; color: #e2e8f0;">Initializing...</span>
            </div>
            <span id="fetchTimer" style="font-size: 13px; color: #94a3b8; font-variant-numeric: tabular-nums;">0s</span>
        </div>
        <div class="fetch-progress-track">
            <div class="fetch-progress-bar" id="fetchProgressBar"></div>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 6px;">
            <span id="fetchStepInfo" style="font-size: 12px; color: #64748b;">Starting capture...</span>
            <span id="fetchPctText" style="font-size: 12px; color: #64748b;">0%</span>
        </div>
    </div>
</div>

<!-- Programme Guide -->
<div class="content-card" style="margin-top: 24px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <h3><i class="lucide-tv"></i> Programme Guide</h3>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <select id="progChannelFilter" class="form-input" style="width: auto; min-width: 180px;" onchange="loadProgrammes()">
                <option value="">All Channels</option>
            </select>
            <input type="date" id="progDateFilter" class="form-input" style="width: auto;" onchange="loadProgrammes()">
            <label style="display: flex; align-items: center; gap: 4px; color: #94a3b8; font-size: 13px; cursor: pointer;">
                <input type="checkbox" id="progNowFilter" onchange="loadProgrammes()"> Now Showing
            </label>
            <button class="btn btn-primary btn-sm" onclick="loadProgrammes()">
                <i class="lucide-refresh-cw"></i> Refresh
            </button>
        </div>
    </div>
    <div id="programmeContent">
        <p style="text-align: center; color: #64748b; padding: 40px 0;">Click <strong>Refresh</strong> or change filters to load programme listings.</p>
    </div>
    <div id="programmePagination" style="display: none; padding: 16px; border-top: 1px solid #2d3748; text-align: center;">
        <button class="btn btn-secondary btn-sm" id="progPrevBtn" onclick="changePage(-1)" disabled>&laquo; Previous</button>
        <span id="progPageInfo" style="margin: 0 12px; color: #94a3b8; font-size: 13px;"></span>
        <button class="btn btn-secondary btn-sm" id="progNextBtn" onclick="changePage(1)" disabled>Next &raquo;</button>
    </div>
</div>

<!-- Add/Edit Source Modal -->
<div class="modal-overlay" id="sourceModal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="sourceModalTitle"><i class="lucide-plus"></i> Add EPG Source</h3>
            <button type="button" class="modal-close" onclick="closeSourceModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="sourceId" value="">

            <div class="form-group">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" id="sourceName" class="form-input" placeholder="e.g. Satellite EIT Feed">
            </div>

            <div class="form-group">
                <label class="form-label">Type <span class="text-danger">*</span></label>
                <select id="sourceType" class="form-input" onchange="toggleTypeFields()">
                    <option value="eit">EIT / DVB (MPTS Stream)</option>
                    <option value="xmltv_url">XMLTV URL</option>
                    <option value="xmltv_file">XMLTV File Upload</option>
                </select>
            </div>

            <!-- EIT Fields -->
            <div id="eitFields">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label">Multicast / Stream Address</label>
                        <input type="text" id="sourceUrl" class="form-input" placeholder="e.g. 239.1.1.1">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Port</label>
                        <input type="number" id="sourcePort" class="form-input" placeholder="e.g. 5500">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">EIT PID</label>
                        <input type="text" id="eitPid" class="form-input" value="0x12" placeholder="0x12">
                        <small class="form-hint">Default 0x12 (18) for standard EIT</small>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Capture Timeout (seconds)</label>
                        <input type="number" id="captureTimeout" class="form-input" value="30" min="10" max="600">
                        <small class="form-hint">How long to capture EIT data</small>
                    </div>
                </div>
            </div>

            <!-- XMLTV URL Fields -->
            <div id="urlFields" style="display: none;">
                <div class="form-group">
                    <label class="form-label">XMLTV URL</label>
                    <input type="text" id="xmltvUrl" class="form-input" placeholder="https://example.com/epg.xml">
                    <small class="form-hint">Supports .xml and .xml.gz files</small>
                </div>
            </div>

            <!-- File info -->
            <div id="fileFields" style="display: none;">
                <div class="form-notice">
                    <i class="lucide-info"></i>
                    Upload XMLTV files after creating the source using the upload button.
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">
                        <input type="checkbox" id="sourceActive" checked> Active
                    </label>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">
                        <input type="checkbox" id="sourceAutoRefresh" onchange="toggleRefreshInterval()"> Auto Refresh
                    </label>
                </div>
            </div>

            <div class="form-group" id="refreshIntervalGroup" style="display: none;">
                <label class="form-label">Refresh Interval</label>
                <select id="refreshInterval" class="form-input">
                    <option value="1800">Every 30 minutes</option>
                    <option value="3600" selected>Every hour</option>
                    <option value="7200">Every 2 hours</option>
                    <option value="14400">Every 4 hours</option>
                    <option value="43200">Every 12 hours</option>
                    <option value="86400">Every 24 hours</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeSourceModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveSource()" id="saveSourceBtn">
                <i class="lucide-check"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- Channel Mappings Modal -->
<div class="modal-overlay" id="mappingsModal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 id="mappingsTitle"><i class="lucide-link"></i> Channel Mappings</h3>
            <button type="button" class="modal-close" onclick="closeMappingsModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="mappings-toolbar">
                <button type="button" class="btn btn-info btn-sm" onclick="autoMapChannels()" id="autoMapBtn">
                    <i class="lucide-wand-2"></i> Auto-Map by Name
                </button>
                <span class="text-muted text-sm" id="mappingsInfo"></span>
            </div>
            <div id="mappingsContent" class="mappings-table-wrap">
                <div class="import-loading"><p>Loading mappings...</p></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeMappingsModal()">Close</button>
        </div>
    </div>
</div>

<!-- XMLTV Upload Modal -->
<div class="modal-overlay" id="uploadModal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="uploadTitle"><i class="lucide-upload"></i> Upload XMLTV</h3>
            <button type="button" class="modal-close" onclick="closeUploadModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="uploadSourceId" value="">
            <div class="form-group">
                <label class="form-label">XMLTV File (.xml or .xml.gz)</label>
                <input type="file" id="xmltvFileInput" class="form-input" accept=".xml,.gz,.xmltv">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="uploadXmltvFile()" id="uploadBtn">
                <i class="lucide-upload"></i> Upload & Import
            </button>
        </div>
    </div>
</div>

<style>
.header-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.action-buttons {
    display: flex;
    gap: 0.375rem;
    align-items: center;
    flex-wrap: wrap;
}

.action-buttons .btn {
    white-space: nowrap;
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
}

.form-row {
    display: flex;
    gap: 1rem;
}

.form-hint {
    display: block;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.form-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
}

.modal-content {
    background: var(--bg-card);
    border-radius: 12px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    flex-shrink: 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-muted);
    padding: 0;
    line-height: 1;
}

.modal-close:hover { color: var(--text-primary); }

.modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}

.modal-footer {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    justify-content: flex-end;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    flex-shrink: 0;
}

.mappings-toolbar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.mappings-table-wrap {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
}

.mappings-table {
    width: 100%;
    border-collapse: collapse;
}

.mappings-table th {
    position: sticky;
    top: 0;
    background: var(--bg-card);
    z-index: 1;
    text-transform: uppercase;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-muted);
    letter-spacing: 0.05em;
    padding: 0.625rem 0.75rem;
    border-bottom: 2px solid var(--border-color);
    text-align: left;
}

.mappings-table td {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.875rem;
    vertical-align: middle;
}

.mappings-table tr:hover {
    background: rgba(255, 255, 255, 0.03);
}

.mappings-table select {
    min-width: 200px;
}

.mapping-status {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.mapping-status.mapped { background: var(--success); }
.mapping-status.unmapped { background: var(--danger); }

.toast {
    position: fixed;
    top: 80px;
    right: 1.5rem;
    padding: 0.875rem 1.25rem;
    border-radius: 8px;
    color: white;
    font-size: 0.875rem;
    font-weight: 500;
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    transform: translateX(120%);
    transition: transform 0.3s ease;
    max-width: 400px;
}

.toast.show { transform: translateX(0); }
.toast-success { background: var(--success); }
.toast-error { background: var(--danger); }

.fetch-progress-track {
    width: 100%;
    height: 8px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 4px;
    overflow: hidden;
}

.fetch-progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #6366f1, #818cf8);
    border-radius: 4px;
    transition: width 1s linear;
    position: relative;
}

.fetch-progress-bar::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
    animation: fetchShimmer 1.5s ease-in-out infinite;
}

@keyframes fetchShimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.fetch-spinner {
    width: 18px;
    height: 18px;
    border: 2px solid rgba(99, 102, 241, 0.3);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<script>
const csrfToken = '<?= $csrf ?>';
let currentMappingsSourceId = null;

// ========================================================================
// SOURCE CRUD
// ========================================================================

function openAddModal() {
    document.getElementById('sourceId').value = '';
    document.getElementById('sourceName').value = '';
    document.getElementById('sourceType').value = 'eit';
    document.getElementById('sourceType').disabled = false;
    document.getElementById('sourceUrl').value = '';
    document.getElementById('sourcePort').value = '';
    document.getElementById('eitPid').value = '0x12';
    document.getElementById('captureTimeout').value = '30';
    document.getElementById('xmltvUrl').value = '';
    document.getElementById('sourceActive').checked = true;
    document.getElementById('sourceAutoRefresh').checked = false;
    document.getElementById('refreshInterval').value = '3600';
    document.getElementById('sourceModalTitle').innerHTML = '<i class="lucide-plus"></i> Add EPG Source';
    toggleTypeFields();
    toggleRefreshInterval();
    document.getElementById('sourceModal').style.display = 'flex';
}

function openEditModal(src) {
    if (typeof src === 'string') src = JSON.parse(src);

    document.getElementById('sourceId').value = src.id;
    document.getElementById('sourceName').value = src.name;
    document.getElementById('sourceType').value = src.type;
    document.getElementById('sourceType').disabled = true;
    document.getElementById('sourceActive').checked = src.is_active == 1;
    document.getElementById('sourceAutoRefresh').checked = src.auto_refresh == 1;
    document.getElementById('refreshInterval').value = src.refresh_interval || '3600';

    if (src.type === 'eit') {
        document.getElementById('sourceUrl').value = src.source_url || '';
        document.getElementById('sourcePort').value = src.source_port || '';
        document.getElementById('eitPid').value = src.eit_pid || '0x12';
        document.getElementById('captureTimeout').value = src.capture_timeout || 30;
    } else {
        document.getElementById('xmltvUrl').value = src.source_url || '';
    }

    document.getElementById('sourceModalTitle').innerHTML = '<i class="lucide-edit"></i> Edit EPG Source';
    toggleTypeFields();
    toggleRefreshInterval();
    document.getElementById('sourceModal').style.display = 'flex';
}

function closeSourceModal() {
    document.getElementById('sourceModal').style.display = 'none';
}

function toggleTypeFields() {
    const type = document.getElementById('sourceType').value;
    document.getElementById('eitFields').style.display = type === 'eit' ? '' : 'none';
    document.getElementById('urlFields').style.display = type === 'xmltv_url' ? '' : 'none';
    document.getElementById('fileFields').style.display = type === 'xmltv_file' ? '' : 'none';
}

function toggleRefreshInterval() {
    const auto = document.getElementById('sourceAutoRefresh').checked;
    document.getElementById('refreshIntervalGroup').style.display = auto ? '' : 'none';
}

function saveSource() {
    const id = document.getElementById('sourceId').value;
    const type = document.getElementById('sourceType').value;

    const body = new URLSearchParams({
        _token: csrfToken,
        name: document.getElementById('sourceName').value,
        type: type,
        is_active: document.getElementById('sourceActive').checked ? '1' : '',
        auto_refresh: document.getElementById('sourceAutoRefresh').checked ? '1' : '',
        refresh_interval: document.getElementById('refreshInterval').value,
    });

    if (type === 'eit') {
        body.set('source_url', document.getElementById('sourceUrl').value);
        body.set('source_port', document.getElementById('sourcePort').value);
        body.set('eit_pid', document.getElementById('eitPid').value);
        body.set('capture_timeout', document.getElementById('captureTimeout').value);
    } else if (type === 'xmltv_url') {
        body.set('source_url', document.getElementById('xmltvUrl').value);
    }

    const url = id ? `/admin/epg/${id}/update` : '/admin/epg/store';
    const btn = document.getElementById('saveSourceBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="lucide-loader"></i> Saving...';

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="lucide-check"></i> Save';
        if (data.success) {
            showToast(data.message, 'success');
            closeSourceModal();
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Failed to save', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="lucide-check"></i> Save';
        showToast('Network error', 'error');
    });
}

function deleteSource(id, name) {
    if (!confirm(`Delete EPG source "${name}"? This will also remove all its programme data.`)) return;

    fetch(`/admin/epg/${id}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _token: csrfToken }).toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(() => showToast('Network error', 'error'));
}

// ========================================================================
// FETCH WITH PROGRESS
// ========================================================================

let fetchTimerInterval = null;
let fetchProgressInterval = null;

function fetchSource(id, captureTimeout, sourceType) {
    const btn = document.getElementById('fetchBtn-' + id);
    if (!btn) return;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="lucide-loader"></i>';

    // Disable all other fetch buttons while running
    document.querySelectorAll('[id^="fetchBtn-"]').forEach(b => b.disabled = true);

    // Show progress bar
    const progressEl = document.getElementById('fetchProgress');
    const progressBar = document.getElementById('fetchProgressBar');
    const phaseText = document.getElementById('fetchPhaseText');
    const timerText = document.getElementById('fetchTimer');
    const stepInfo = document.getElementById('fetchStepInfo');
    const pctText = document.getElementById('fetchPctText');

    progressEl.style.display = 'block';
    progressBar.style.width = '0%';
    progressBar.style.background = 'linear-gradient(90deg, #6366f1, #818cf8)';
    pctText.textContent = '0%';

    // Estimate total duration for progress calculation
    // EIT: capture_timeout for EIT + min(timeout,30) for SDT + ~5s for parsing
    // XMLTV: ~10-30s depending on size
    let totalEstimate;
    const phases = [];

    if (sourceType === 'eit') {
        const sdtTime = Math.min(captureTimeout, 30);
        const parseTime = 5;
        totalEstimate = captureTimeout + sdtTime + parseTime;
        phases.push(
            { at: 0, pct: 0, label: 'Capturing EIT data...', step: `EIT capture (PID 0x12) — up to ${captureTimeout}s` },
            { at: captureTimeout, pct: Math.round((captureTimeout / totalEstimate) * 100), label: 'Capturing SDT service names...', step: `SDT capture — up to ${sdtTime}s` },
            { at: captureTimeout + sdtTime, pct: Math.round(((captureTimeout + sdtTime) / totalEstimate) * 100), label: 'Parsing and importing programmes...', step: 'Processing XML and writing to database' }
        );
    } else {
        totalEstimate = 20;
        phases.push(
            { at: 0, pct: 0, label: 'Fetching XMLTV data...', step: 'Downloading and parsing XML' },
            { at: 10, pct: 50, label: 'Importing programmes...', step: 'Writing to database' }
        );
    }

    // Start elapsed timer
    let elapsed = 0;
    phaseText.textContent = phases[0].label;
    stepInfo.textContent = phases[0].step;

    fetchTimerInterval = setInterval(() => {
        elapsed++;
        const mins = Math.floor(elapsed / 60);
        const secs = elapsed % 60;
        timerText.textContent = mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;

        // Update phase based on elapsed time
        for (let i = phases.length - 1; i >= 0; i--) {
            if (elapsed >= phases[i].at) {
                phaseText.textContent = phases[i].label;
                stepInfo.textContent = phases[i].step;
                break;
            }
        }
    }, 1000);

    // Animate progress bar smoothly
    fetchProgressInterval = setInterval(() => {
        const pct = Math.min(95, Math.round((elapsed / totalEstimate) * 100));
        progressBar.style.width = pct + '%';
        pctText.textContent = pct + '%';
    }, 500);

    // Send the actual fetch request
    fetch(`/admin/epg/${id}/fetch`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _token: csrfToken }).toString()
    })
    .then(r => r.json())
    .then(data => {
        clearInterval(fetchTimerInterval);
        clearInterval(fetchProgressInterval);

        if (data.success) {
            // Complete the progress bar
            progressBar.style.width = '100%';
            progressBar.style.background = 'linear-gradient(90deg, #22c55e, #4ade80)';
            pctText.textContent = '100%';
            phaseText.textContent = 'Fetch complete!';
            stepInfo.textContent = data.message || 'Done';

            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            progressBar.style.width = '100%';
            progressBar.style.background = 'linear-gradient(90deg, #ef4444, #f87171)';
            pctText.textContent = '';
            phaseText.textContent = 'Fetch failed';
            stepInfo.textContent = data.message || 'Unknown error';

            showToast(data.message || 'Fetch failed', 'error');
            // Re-enable buttons
            document.querySelectorAll('[id^="fetchBtn-"]').forEach(b => b.disabled = false);
            btn.innerHTML = origHtml;
        }
    })
    .catch(() => {
        clearInterval(fetchTimerInterval);
        clearInterval(fetchProgressInterval);

        progressBar.style.width = '100%';
        progressBar.style.background = 'linear-gradient(90deg, #ef4444, #f87171)';
        pctText.textContent = '';
        phaseText.textContent = 'Network error';
        stepInfo.textContent = 'Connection failed or request timed out';

        showToast('Network error during fetch', 'error');
        document.querySelectorAll('[id^="fetchBtn-"]').forEach(b => b.disabled = false);
        btn.innerHTML = origHtml;
    });
}

// ========================================================================
// XMLTV UPLOAD
// ========================================================================

function openUploadModal(id, name) {
    document.getElementById('uploadSourceId').value = id;
    document.getElementById('uploadTitle').innerHTML = `<i class="lucide-upload"></i> Upload XMLTV - ${escapeHtml(name)}`;
    document.getElementById('xmltvFileInput').value = '';
    document.getElementById('uploadModal').style.display = 'flex';
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}

function uploadXmltvFile() {
    const id = document.getElementById('uploadSourceId').value;
    const fileInput = document.getElementById('xmltvFileInput');

    if (!fileInput.files.length) {
        showToast('Please select a file', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('_token', csrfToken);
    formData.append('xmltv_file', fileInput.files[0]);

    const btn = document.getElementById('uploadBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="lucide-loader"></i> Uploading...';

    fetch(`/admin/epg/${id}/upload`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="lucide-upload"></i> Upload & Import';
        if (data.success) {
            showToast(data.message, 'success');
            closeUploadModal();
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message || 'Upload failed', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="lucide-upload"></i> Upload & Import';
        showToast('Network error during upload', 'error');
    });
}

// ========================================================================
// CHANNEL MAPPINGS
// ========================================================================

function openMappingsModal(id, name) {
    currentMappingsSourceId = id;
    document.getElementById('mappingsTitle').innerHTML = `<i class="lucide-link"></i> Channel Mappings - ${escapeHtml(name)}`;
    document.getElementById('mappingsContent').innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-muted);">Loading...</div>';
    document.getElementById('mappingsModal').style.display = 'flex';
    loadMappings(id);
}

function closeMappingsModal() {
    document.getElementById('mappingsModal').style.display = 'none';
    currentMappingsSourceId = null;
}

function loadMappings(id) {
    fetch(`/admin/epg/${id}/mappings`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('mappingsContent').innerHTML = '<p>Failed to load mappings.</p>';
                return;
            }

            const mappings = data.mappings || [];
            const channels = data.channels || [];
            const mapped = mappings.filter(m => m.is_mapped).length;

            document.getElementById('mappingsInfo').textContent =
                `${mapped} of ${mappings.length} channel(s) mapped`;

            if (mappings.length === 0) {
                document.getElementById('mappingsContent').innerHTML =
                    '<div style="padding: 2rem; text-align: center; color: var(--text-muted);">No channels found. Fetch data first to discover channels.</div>';
                return;
            }

            let html = '<table class="mappings-table"><thead><tr>' +
                '<th width="30"></th><th>EPG ID</th><th>Service Name</th><th>Progs</th><th>Map To Channel</th>' +
                '</tr></thead><tbody>';

            mappings.forEach(m => {
                const dot = m.is_mapped
                    ? '<span class="mapping-status mapped"></span>'
                    : '<span class="mapping-status unmapped"></span>';

                let options = '<option value="">-- Not Mapped --</option>';
                channels.forEach(ch => {
                    const sel = ch.id == m.channel_id ? 'selected' : '';
                    options += `<option value="${ch.id}" ${sel}>${escapeHtml(ch.name)}</option>`;
                });

                html += `<tr>
                    <td>${dot}</td>
                    <td class="text-sm font-mono">${escapeHtml(m.epg_channel_id)}</td>
                    <td>${escapeHtml(m.epg_channel_name || '-')}</td>
                    <td class="text-sm">${m.programme_count}</td>
                    <td><select class="form-input form-input-sm" onchange="saveMappingInline(${m.id}, this.value)">
                        ${options}
                    </select></td>
                </tr>`;
            });

            html += '</tbody></table>';
            document.getElementById('mappingsContent').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('mappingsContent').innerHTML = '<p>Network error loading mappings.</p>';
        });
}

function saveMappingInline(mappingId, channelId) {
    fetch('/admin/epg/save-mapping', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            _token: csrfToken,
            mapping_id: mappingId,
            channel_id: channelId
        }).toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Mapping saved', 'success');
        } else {
            showToast(data.message || 'Failed', 'error');
        }
    })
    .catch(() => showToast('Network error', 'error'));
}

function autoMapChannels() {
    if (!currentMappingsSourceId) return;

    const btn = document.getElementById('autoMapBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="lucide-loader"></i> Mapping...';

    fetch(`/admin/epg/${currentMappingsSourceId}/auto-map`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _token: csrfToken }).toString()
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="lucide-wand-2"></i> Auto-Map by Name';
        if (data.success) {
            showToast(data.message, 'success');
            loadMappings(currentMappingsSourceId);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="lucide-wand-2"></i> Auto-Map by Name';
        showToast('Network error', 'error');
    });
}

// ========================================================================
// CLEANUP & HELPERS
// ========================================================================

function cleanupProgrammes() {
    if (!confirm('Remove all expired programme data (older than 1 day)?')) return;

    fetch('/admin/epg/cleanup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _token: csrfToken }).toString()
    })
    .then(r => r.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 1000);
    })
    .catch(() => showToast('Network error', 'error'));
}

// ========================================================================
// PROGRAMME GUIDE
// ========================================================================

let currentProgPage = 1;
let channelsLoaded = false;

function loadProgrammes(page) {
    if (page !== undefined) currentProgPage = page;
    else currentProgPage = 1;

    const channelId = document.getElementById('progChannelFilter').value;
    const date = document.getElementById('progDateFilter').value;
    const now = document.getElementById('progNowFilter').checked ? '1' : '';

    const params = new URLSearchParams({ page: currentProgPage, per_page: 50 });
    if (channelId) params.set('channel_id', channelId);
    if (date) params.set('date', date);
    if (now) params.set('now', now);

    document.getElementById('programmeContent').innerHTML =
        '<p style="text-align:center;color:#64748b;padding:40px 0;"><i class="lucide-loader"></i> Loading programmes...</p>';

    fetch(`/admin/epg/programmes?${params.toString()}`)
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            document.getElementById('programmeContent').innerHTML =
                '<p style="text-align:center;color:#ef4444;padding:40px 0;">' + (data.message || 'Failed to load') + '</p>';
            return;
        }

        // Populate channel filter dropdown (once)
        if (!channelsLoaded && data.channels && data.channels.length > 0) {
            const sel = document.getElementById('progChannelFilter');
            const currentVal = sel.value;
            sel.innerHTML = '<option value="">All Channels</option>';
            data.channels.forEach(ch => {
                sel.innerHTML += `<option value="${ch.id}" ${ch.id == currentVal ? 'selected' : ''}>${esc(ch.name)}</option>`;
            });
            channelsLoaded = true;
        }

        if (!data.programmes || data.programmes.length === 0) {
            document.getElementById('programmeContent').innerHTML =
                '<p style="text-align:center;color:#64748b;padding:40px 0;">No programmes found. Try changing the filters or run a fetch first.</p>';
            document.getElementById('programmePagination').style.display = 'none';
            return;
        }

        let html = '<div class="table-responsive"><table class="data-table"><thead><tr>';
        html += '<th>Channel</th><th>Time</th><th>Title</th><th>Description</th><th>Category</th>';
        html += '</tr></thead><tbody>';

        const now = new Date();
        data.programmes.forEach(p => {
            const start = new Date(p.start_time.replace(' ', 'T'));
            const end = new Date(p.end_time.replace(' ', 'T'));
            const isLive = start <= now && end > now;

            const startStr = start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const endStr = end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const dateStr = start.toLocaleDateString([], { month: 'short', day: 'numeric' });

            html += `<tr style="${isLive ? 'background: rgba(99, 102, 241, 0.1); border-left: 3px solid #6366f1;' : ''}">`;
            html += `<td><strong>${esc(p.channel_name || 'Unmapped')}</strong></td>`;
            html += `<td style="white-space: nowrap;">`;
            html += `<span style="color: #94a3b8; font-size: 11px;">${dateStr}</span><br>`;
            html += `${startStr} - ${endStr}`;
            if (isLive) html += ' <span class="badge badge-success" style="font-size: 10px;">LIVE</span>';
            html += `</td>`;
            html += `<td><strong>${esc(p.title)}</strong>`;
            if (p.subtitle) html += `<br><span style="color: #94a3b8; font-size: 12px;">${esc(p.subtitle)}</span>`;
            html += `</td>`;
            html += `<td style="max-width: 300px; font-size: 12px; color: #94a3b8;">${esc(p.description || '')}</td>`;
            html += `<td>${p.category ? '<span class="badge badge-info">' + esc(p.category) + '</span>' : ''}</td>`;
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        document.getElementById('programmeContent').innerHTML = html;

        // Pagination
        const pagination = document.getElementById('programmePagination');
        if (data.total_pages > 1) {
            pagination.style.display = 'block';
            document.getElementById('progPageInfo').textContent =
                `Page ${data.page} of ${data.total_pages} (${data.total} programmes)`;
            document.getElementById('progPrevBtn').disabled = data.page <= 1;
            document.getElementById('progNextBtn').disabled = data.page >= data.total_pages;
        } else {
            pagination.style.display = data.total > 0 ? 'block' : 'none';
            document.getElementById('progPageInfo').textContent = `${data.total} programme${data.total !== 1 ? 's' : ''}`;
            document.getElementById('progPrevBtn').disabled = true;
            document.getElementById('progNextBtn').disabled = true;
        }
    })
    .catch(() => {
        document.getElementById('programmeContent').innerHTML =
            '<p style="text-align:center;color:#ef4444;padding:40px 0;">Failed to load programmes.</p>';
    });
}

function changePage(delta) {
    loadProgrammes(currentProgPage + delta);
}

function esc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// Auto-load programmes on page load if there are any
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('progDateFilter').value = today;
    if (<?= (int)$stats['total_programmes'] ?> > 0) {
        loadProgrammes();
    }
});

function showToast(message, type) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icon = type === 'success' ? 'lucide-check-circle' : 'lucide-alert-circle';
    toast.innerHTML = `<i class="${icon}"></i> ${escapeHtml(message)}`;
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    }
});
</script>
