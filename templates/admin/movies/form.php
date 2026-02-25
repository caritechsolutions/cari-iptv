<?php
$isEdit = !empty($movie);
$pageAction = $isEdit ? 'Edit' : 'Add';
?>

<div class="page-header">
    <div class="breadcrumb mb-1">
        <a href="/admin/movies">Movies</a>
        <span class="breadcrumb-separator">/</span>
        <span><?= $pageAction ?> Movie</span>
    </div>
    <h1 class="page-title"><?= $pageAction ?> Movie</h1>
</div>

<form method="POST" action="<?= $isEdit ? "/admin/movies/{$movie['id']}/update" : '/admin/movies/store' ?>" id="movieForm">
    <input type="hidden" name="_token" value="<?= $csrf ?>">
    <input type="hidden" name="tmdb_id" id="tmdb_id" value="<?= $movie['tmdb_id'] ?? '' ?>">

    <div class="form-grid">
        <!-- Left Column - Main Info -->
        <div class="form-column">
            <!-- Basic Info Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="lucide-info"></i> Basic Information</h3>
                </div>
                <div class="card-body">
                    <!-- TMDB Search -->
                    <div class="form-group">
                        <label class="form-label">Search TMDB</label>
                        <div class="metadata-search">
                            <input type="text" id="tmdbSearchQuery" class="form-input" placeholder="Search movie title...">
                            <button type="button" class="btn btn-primary" onclick="searchTmdb()">
                                <i class="lucide-search"></i> Search TMDB
                            </button>
                        </div>
                        <div id="tmdbResults" class="search-results" style="display: none;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-input" required
                               value="<?= htmlspecialchars($movie['title'] ?? '') ?>"
                               placeholder="Enter movie title">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="original_title">Original Title</label>
                            <input type="text" id="original_title" name="original_title" class="form-input"
                                   value="<?= htmlspecialchars($movie['original_title'] ?? '') ?>"
                                   placeholder="Original language title">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="year">Year</label>
                            <input type="number" id="year" name="year" class="form-input"
                                   value="<?= $movie['year'] ?? '' ?>"
                                   min="1888" max="<?= date('Y') + 5 ?>" placeholder="Release year">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="tagline">Tagline</label>
                        <input type="text" id="tagline" name="tagline" class="form-input"
                               value="<?= htmlspecialchars($movie['tagline'] ?? '') ?>"
                               placeholder="Movie tagline">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="synopsis">Synopsis</label>
                        <textarea id="synopsis" name="synopsis" class="form-input" rows="5"
                                  placeholder="Movie description/plot summary"><?= htmlspecialchars($movie['synopsis'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="runtime">Runtime (minutes)</label>
                            <input type="number" id="runtime" name="runtime" class="form-input"
                                   value="<?= $movie['runtime'] ?? '' ?>"
                                   min="1" placeholder="e.g. 120">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="rating">Content Rating</label>
                            <select id="rating" name="rating" class="form-input">
                                <option value="">Select rating</option>
                                <option value="G" <?= ($movie['rating'] ?? '') === 'G' ? 'selected' : '' ?>>G</option>
                                <option value="PG" <?= ($movie['rating'] ?? '') === 'PG' ? 'selected' : '' ?>>PG</option>
                                <option value="PG-13" <?= ($movie['rating'] ?? '') === 'PG-13' ? 'selected' : '' ?>>PG-13</option>
                                <option value="R" <?= ($movie['rating'] ?? '') === 'R' ? 'selected' : '' ?>>R</option>
                                <option value="NC-17" <?= ($movie['rating'] ?? '') === 'NC-17' ? 'selected' : '' ?>>NC-17</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="director">Director</label>
                            <input type="text" id="director" name="director" class="form-input"
                                   value="<?= htmlspecialchars($movie['director'] ?? '') ?>"
                                   placeholder="Director name">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="language">Language</label>
                            <input type="text" id="language" name="language" class="form-input"
                                   value="<?= htmlspecialchars($movie['language'] ?? 'en') ?>"
                                   placeholder="e.g. en">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="genres">Genres</label>
                        <input type="text" id="genres" name="genres" class="form-input"
                               value="<?= is_array($movie['genres'] ?? null) ? implode(', ', $movie['genres']) : '' ?>"
                               placeholder="Action, Comedy, Drama (comma-separated)">
                    </div>
                </div>
            </div>

            <!-- Stream URLs Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="lucide-play-circle"></i> Stream URLs</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" for="stream_url">Primary Stream URL</label>
                        <input type="url" id="stream_url" name="stream_url" class="form-input"
                               value="<?= htmlspecialchars($movie['stream_url'] ?? '') ?>"
                               placeholder="https://example.com/movie.m3u8">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="stream_url_backup">Backup Stream URL</label>
                        <input type="url" id="stream_url_backup" name="stream_url_backup" class="form-input"
                               value="<?= htmlspecialchars($movie['stream_url_backup'] ?? '') ?>"
                               placeholder="https://backup.example.com/movie.m3u8">
                    </div>
                </div>
            </div>

            <?php if ($isEdit && !empty($vodServers)): ?>
            <?php
                $vodStatus   = $movie['vod_status'] ?? null;
                $vodServerId = $movie['vod_server_id'] ?? null;
                $vodJobId    = $movie['vod_job_id'] ?? null;
                $vodProgress = (float)($movie['vod_progress'] ?? 0);
                $vodError    = $movie['vod_error'] ?? null;
                $vodActive   = in_array($vodStatus, ['pending', 'processing', 'packaging', 'downloading']);
            ?>
            <!-- VOD Transcode Card -->
            <div class="card mb-3">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                    <h3 class="card-title"><i class="lucide-hard-drive"></i> VOD Transcode</h3>
                    <div style="display:flex;align-items:center;gap:0.5rem">
                        <?php if ($vodStatus === 'complete'): ?>
                            <span class="badge badge-success"><i class="lucide-check-circle"></i> Ready</span>
                        <?php elseif ($vodActive): ?>
                            <span class="badge badge-primary"><i class="lucide-loader"></i> Processing</span>
                        <?php elseif ($vodStatus === 'failed'): ?>
                            <span class="badge badge-danger"><i class="lucide-alert-circle"></i> Failed</span>
                        <?php endif; ?>
                        <?php if ($vodStatus): ?>
                            <button type="button" class="btn btn-sm" id="vod-delete-btn"
                                    onclick="vodDeleteFromServer()"
                                    title="Delete transcoded content from VOD server and clear status"
                                    style="background:transparent;color:var(--danger);border:1px solid var(--danger);font-size:0.75rem">
                                <i class="lucide-trash-2"></i> Remove VOD
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">

                    <?php if ($vodActive): ?>
                    <!-- Active job — show status on load, hide upload controls -->
                    <div id="vod-transcode-progress" style="margin-bottom:1rem">
                        <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                            <span style="font-size:0.85rem;color:var(--text-secondary)" id="vod-transcode-label">
                                <?= $vodStatus === 'packaging' ? 'Packaging for streaming...' : ($vodStatus === 'downloading' ? 'Downloading source...' : 'Transcoding...') ?>
                            </span>
                            <span style="font-size:0.85rem;font-family:var(--font-mono);color:var(--text-muted)" id="vod-transcode-pct"><?= number_format($vodProgress, 1) ?>%</span>
                        </div>
                        <div style="height:8px;background:var(--bg-hover);border-radius:4px;overflow:hidden">
                            <div id="vod-transcode-bar" style="height:100%;width:<?= $vodProgress ?>%;background:var(--accent, #8b5cf6);border-radius:4px;transition:width 0.3s"></div>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem" id="vod-transcode-step">Checking status...</div>
                    </div>
                    <div id="vod-status-msg" style="display:none;padding:0.75rem;border-radius:8px;font-size:0.85rem;margin-bottom:1rem"></div>

                    <?php elseif ($vodStatus === 'complete'): ?>
                    <!-- Completed — show success and allow re-transcode -->
                    <div style="padding:0.75rem;border-radius:8px;font-size:0.85rem;margin-bottom:1rem;background:rgba(34,197,94,0.1);color:var(--success)">
                        <i class="lucide-check-circle"></i> Transcode complete. Stream URL has been set.
                    </div>
                    <div id="vod-transcode-progress" style="display:none;margin-bottom:1rem">
                        <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                            <span style="font-size:0.85rem;color:var(--text-secondary)" id="vod-transcode-label">Transcoding...</span>
                            <span style="font-size:0.85rem;font-family:var(--font-mono);color:var(--text-muted)" id="vod-transcode-pct">0%</span>
                        </div>
                        <div style="height:8px;background:var(--bg-hover);border-radius:4px;overflow:hidden">
                            <div id="vod-transcode-bar" style="height:100%;width:0%;background:var(--accent, #8b5cf6);border-radius:4px;transition:width 0.3s"></div>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem" id="vod-transcode-step"></div>
                    </div>
                    <div id="vod-status-msg" style="display:none;padding:0.75rem;border-radius:8px;font-size:0.85rem;margin-bottom:1rem"></div>

                    <?php elseif ($vodStatus === 'failed'): ?>
                    <!-- Failed — show error and allow retry -->
                    <div style="padding:0.75rem;border-radius:8px;font-size:0.85rem;margin-bottom:1rem;background:rgba(239,68,68,0.1);color:var(--danger)">
                        <i class="lucide-alert-circle"></i> Transcode failed<?= $vodError ? ': ' . htmlspecialchars($vodError) : '' ?>
                    </div>
                    <div id="vod-transcode-progress" style="display:none;margin-bottom:1rem">
                        <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                            <span style="font-size:0.85rem;color:var(--text-secondary)" id="vod-transcode-label">Transcoding...</span>
                            <span style="font-size:0.85rem;font-family:var(--font-mono);color:var(--text-muted)" id="vod-transcode-pct">0%</span>
                        </div>
                        <div style="height:8px;background:var(--bg-hover);border-radius:4px;overflow:hidden">
                            <div id="vod-transcode-bar" style="height:100%;width:0%;background:var(--accent, #8b5cf6);border-radius:4px;transition:width 0.3s"></div>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem" id="vod-transcode-step"></div>
                    </div>
                    <div id="vod-status-msg" style="display:none;padding:0.75rem;border-radius:8px;font-size:0.85rem;margin-bottom:1rem"></div>

                    <?php else: ?>
                    <!-- No job yet — show instructions -->
                    <div id="vod-transcode-progress" style="display:none;margin-bottom:1rem">
                        <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                            <span style="font-size:0.85rem;color:var(--text-secondary)" id="vod-transcode-label">Transcoding...</span>
                            <span style="font-size:0.85rem;font-family:var(--font-mono);color:var(--text-muted)" id="vod-transcode-pct">0%</span>
                        </div>
                        <div style="height:8px;background:var(--bg-hover);border-radius:4px;overflow:hidden">
                            <div id="vod-transcode-bar" style="height:100%;width:0%;background:var(--accent, #8b5cf6);border-radius:4px;transition:width 0.3s"></div>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem" id="vod-transcode-step"></div>
                    </div>
                    <div id="vod-status-msg" style="display:none;padding:0.75rem;border-radius:8px;font-size:0.85rem;margin-bottom:1rem"></div>
                    <?php endif; ?>

                    <!-- Upload controls (hidden during active job) -->
                    <div id="vod-upload-controls" <?= $vodActive ? 'style="display:none"' : '' ?>>
                        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                            <div class="form-group">
                                <label class="form-label">VOD Server</label>
                                <select class="form-input" id="vod-transcode-server">
                                    <?php foreach ($vodServers as $vs): ?>
                                        <option value="<?= $vs['id'] ?>"
                                            data-url="<?= htmlspecialchars($vs['url']) ?>"
                                            data-public-url="<?= htmlspecialchars($vs['public_url'] ?? '') ?>"
                                            data-api-key="<?= htmlspecialchars($vs['api_key'] ?? '') ?>"
                                            <?= ($vodServerId && $vs['id'] == $vodServerId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vs['name']) ?><?= $vs['is_default'] ? ' (default)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Transcode Profile</label>
                                <select class="form-input" id="vod-transcode-profile">
                                    <option value="standard">Loading profiles...</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Source File</label>
                            <div style="display:flex;gap:0.5rem">
                                <input type="text" class="form-input" id="vod-source-path"
                                       placeholder="No file selected" style="flex:1" readonly>
                                <input type="file" id="vod-file-input" accept="video/*,.mkv,.avi,.ts,.m2ts" style="display:none"
                                       onchange="vodFileSelected(this)">
                                <button type="button" class="btn btn-secondary" id="vod-browse-btn" onclick="document.getElementById('vod-file-input').click()">
                                    <i class="lucide-folder-open"></i> Browse
                                </button>
                                <button type="button" class="btn btn-primary" id="vod-upload-btn" onclick="vodStartUpload(false)" style="display:none">
                                    <i class="lucide-upload"></i> Upload &amp; Transcode
                                </button>
                            </div>
                        </div>

                        <!-- Upload progress -->
                        <div id="vod-upload-progress" style="display:none;margin-bottom:1rem">
                            <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                                <span style="font-size:0.85rem;color:var(--text-secondary)" id="vod-upload-label">Uploading...</span>
                                <span style="font-size:0.85rem;font-family:var(--font-mono);color:var(--text-muted)" id="vod-upload-pct">0%</span>
                            </div>
                            <div style="height:8px;background:var(--bg-hover);border-radius:4px;overflow:hidden">
                                <div id="vod-upload-bar" style="height:100%;width:0%;background:var(--primary);border-radius:4px;transition:width 0.2s"></div>
                            </div>
                            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem" id="vod-upload-size"></div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                var vodPendingFile = null;
                var vodJobPollTimer = null;
                var CSRF = '<?= \CariIPTV\Core\Session::csrf() ?>';
                var MOVIE_ID = <?= $movie['id'] ?? 0 ?>;
                var MOVIE_TITLE = <?= json_encode($movie['title'] ?? '') ?>;

                // Saved job state from database (survives page reload)
                var SAVED_SERVER_ID = <?= (int)($vodServerId ?? 0) ?>;
                var SAVED_JOB_ID = <?= (int)($vodJobId ?? 0) ?>;
                var SAVED_STATUS = <?= json_encode($vodStatus ?? '') ?>;

                // Load profiles from VOD server dynamically
                function loadVodProfiles(serverId) {
                    if (!serverId) return;
                    fetch('/admin/vod-server/profiles?server_id=' + serverId)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var select = document.getElementById('vod-transcode-profile');
                            if (!select) return;
                            var profiles = (data.success && data.data && data.data.profiles) ? data.data.profiles : [];
                            if (profiles.length === 0) {
                                select.innerHTML = '<option value="standard">standard</option>';
                                return;
                            }
                            select.innerHTML = profiles.map(function(p) {
                                return '<option value="' + p.name + '">' + p.name + ' (' + (p.codec || '?') + ')</option>';
                            }).join('');
                        })
                        .catch(function() {});
                }
                // Load profiles for the initially selected server
                var initServer = document.getElementById('vod-transcode-server');
                if (initServer) {
                    loadVodProfiles(initServer.value);
                    initServer.addEventListener('change', function() { loadVodProfiles(this.value); });
                }

                function fmt(bytes) {
                    if (!bytes) return '0 B';
                    var k = 1024, s = ['B','KB','MB','GB'];
                    var i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + s[i];
                }

                function showMsg(text, type) {
                    var el = document.getElementById('vod-status-msg');
                    el.style.display = 'block';
                    el.style.background = type === 'success' ? 'rgba(34,197,94,0.1)' :
                                          type === 'error'   ? 'rgba(239,68,68,0.1)' :
                                                               'rgba(99,102,241,0.1)';
                    el.style.color = type === 'success' ? 'var(--success)' :
                                     type === 'error'   ? 'var(--danger)' :
                                                          'var(--text-secondary)';
                    el.innerHTML = text;
                }

                window.vodFileSelected = function(input) {
                    if (!input.files || !input.files[0]) return;
                    vodPendingFile = input.files[0];
                    document.getElementById('vod-source-path').value = vodPendingFile.name + ' (' + fmt(vodPendingFile.size) + ')';
                    document.getElementById('vod-upload-btn').style.display = '';
                    document.getElementById('vod-upload-progress').style.display = 'none';
                    document.getElementById('vod-status-msg').style.display = 'none';
                    document.getElementById('vod-upload-bar').style.width = '0%';
                    document.getElementById('vod-upload-bar').style.background = 'var(--primary)';
                };

                window.vodStartUpload = function(overwrite) {
                    if (!vodPendingFile) { alert('Please browse for a file first.'); return; }

                    var file = vodPendingFile;
                    var serverSel = document.getElementById('vod-transcode-server');
                    var serverId = serverSel.value;
                    var serverOpt = serverSel.options[serverSel.selectedIndex];
                    var vodUrl = serverOpt.getAttribute('data-public-url') || serverOpt.getAttribute('data-url');
                    var vodApiKey = serverOpt.getAttribute('data-api-key');
                    var profile = document.getElementById('vod-transcode-profile').value;
                    var contentId = 'movie-' + MOVIE_ID;
                    var uploadBtn = document.getElementById('vod-upload-btn');
                    var browseBtn = document.getElementById('vod-browse-btn');

                    if (!vodUrl) { showMsg('VOD server URL not configured', 'error'); return; }

                    uploadBtn.disabled = true;
                    uploadBtn.innerHTML = '<i class="lucide-loader"></i> Uploading...';
                    browseBtn.disabled = true;
                    serverSel.disabled = true;
                    document.getElementById('vod-transcode-profile').disabled = true;

                    document.getElementById('vod-upload-progress').style.display = 'block';
                    document.getElementById('vod-status-msg').style.display = 'none';
                    document.getElementById('vod-upload-label').textContent = 'Uploading: ' + file.name;
                    document.getElementById('vod-upload-pct').textContent = '0%';
                    document.getElementById('vod-upload-bar').style.width = '0%';
                    document.getElementById('vod-upload-bar').style.background = 'var(--primary)';
                    document.getElementById('vod-upload-size').textContent = '0 / ' + fmt(file.size);

                    // Upload directly to VOD server (bypasses IPTV Nginx/PHP file size limits)
                    var uploadUrl = vodUrl.replace(/\/+$/, '') + '/api/upload?filename=' + encodeURIComponent(file.name);
                    console.log('[VOD Upload] Target URL:', uploadUrl);
                    console.log('[VOD Upload] File:', file.name, '(' + fmt(file.size) + ')');
                    console.log('[VOD Upload] Server ID:', serverId, '| Profile:', profile, '| Content ID:', contentId);
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', uploadUrl, true);
                    xhr.setRequestHeader('Content-Type', 'application/octet-stream');
                    if (vodApiKey) xhr.setRequestHeader('X-API-Key', vodApiKey);

                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var pct = Math.round((e.loaded / e.total) * 100);
                            document.getElementById('vod-upload-pct').textContent = pct + '%';
                            document.getElementById('vod-upload-bar').style.width = pct + '%';
                            document.getElementById('vod-upload-size').textContent = fmt(e.loaded) + ' / ' + fmt(e.total);
                            if (pct >= 100) {
                                document.getElementById('vod-upload-label').textContent = 'Processing on server...';
                            }
                        }
                    });

                    xhr.addEventListener('load', function() {
                        console.log('[VOD Upload] Response: HTTP', xhr.status, xhr.responseText.substring(0, 500));
                        if (xhr.status === 0 || xhr.responseText === '') {
                            showMsg('Upload failed: VOD server returned empty response.', 'error');
                            resetButtons();
                            return;
                        }

                        try {
                            var uploadData = JSON.parse(xhr.responseText);
                        } catch(e) {
                            showMsg('Upload failed: Invalid response from VOD server (HTTP ' + xhr.status + ')', 'error');
                            resetButtons();
                            return;
                        }

                        if (xhr.status >= 400 || uploadData.error) {
                            showMsg('Upload failed: ' + (uploadData.error || 'HTTP ' + xhr.status), 'error');
                            resetButtons();
                            return;
                        }

                        var uploadPath = uploadData.path || uploadData.file || '';
                        if (!uploadPath) {
                            showMsg('Upload succeeded but no file path returned', 'error');
                            resetButtons();
                            return;
                        }

                        document.getElementById('vod-upload-label').textContent = 'Submitting transcode job...';

                        // Step 2: Submit job via IPTV backend (small JSON request, no file)
                        var jobForm = new FormData();
                        jobForm.append('csrf_token', CSRF);
                        jobForm.append('server_id', serverId);
                        jobForm.append('content_id', contentId);
                        jobForm.append('upload_path', uploadPath);
                        jobForm.append('title', MOVIE_TITLE);
                        jobForm.append('profile', profile);
                        jobForm.append('entity_type', 'movie');
                        jobForm.append('entity_id', MOVIE_ID);
                        if (overwrite) jobForm.append('overwrite', '1');

                        fetch('/admin/vod-server/submit-direct-job', { method: 'POST', body: jobForm })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.success) {
                                    document.getElementById('vod-upload-bar').style.background = 'var(--success)';
                                    document.getElementById('vod-upload-label').textContent = file.name + ' — Uploaded!';
                                    document.getElementById('vod-upload-pct').textContent = 'Done';

                                    var job = data.job || {};
                                    var sid = data.server_id || serverId;
                                    var jid = data.job_id || job.id || job.job_id || 0;
                                    if (jid) {
                                        startTranscodePoll(sid, jid);
                                    } else {
                                        showMsg('File uploaded and job submitted. Refresh the page to check transcode status.', 'success');
                                        resetButtons();
                                    }

                                    vodPendingFile = null;
                                    document.getElementById('vod-upload-btn').style.display = 'none';
                                } else if (data.error === 'duplicate') {
                                    document.getElementById('vod-upload-bar').style.background = 'var(--warning)';
                                    document.getElementById('vod-upload-label').textContent = 'Duplicate detected';
                                    document.getElementById('vod-upload-pct').textContent = '';
                                    showMsg(
                                        data.message + '<br><br>' +
                                        '<button class="btn btn-primary btn-sm" onclick="vodStartUpload(true)" style="margin-right:0.5rem">' +
                                        '<i class="lucide-refresh-cw"></i> Overwrite &amp; Re-transcode</button>' +
                                        '<button class="btn btn-outline btn-sm" onclick="vodCancelOverwrite()">Cancel</button>',
                                        'info'
                                    );
                                    uploadBtn.style.display = 'none';
                                } else {
                                    showMsg('Job submission failed: ' + (data.error || 'Unknown error'), 'error');
                                    resetButtons();
                                }
                            })
                            .catch(function(err) {
                                showMsg('Job submission failed: ' + err.message, 'error');
                                resetButtons();
                            });
                    });

                    xhr.addEventListener('error', function() {
                        console.error('[VOD Upload] XHR error event fired. readyState:', xhr.readyState, 'status:', xhr.status);
                        showMsg('Upload failed: Cannot connect to VOD server. Check CORS config on the VOD proxy host.', 'error');
                        resetButtons();
                    });

                    xhr.send(file);
                };

                window.vodCancelOverwrite = function() {
                    document.getElementById('vod-status-msg').style.display = 'none';
                    document.getElementById('vod-upload-progress').style.display = 'none';
                    resetButtons();
                };

                function resetButtons() {
                    var uploadBtn = document.getElementById('vod-upload-btn');
                    var browseBtn = document.getElementById('vod-browse-btn');
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="lucide-upload"></i> Upload &amp; Transcode';
                    if (vodPendingFile) uploadBtn.style.display = '';
                    browseBtn.disabled = false;
                    document.getElementById('vod-transcode-server').disabled = false;
                    document.getElementById('vod-transcode-profile').disabled = false;
                    // Show upload controls again
                    document.getElementById('vod-upload-controls').style.display = '';
                }

                function startTranscodePoll(serverId, jobId) {
                    // Show transcode progress, hide upload controls
                    var progDiv = document.getElementById('vod-transcode-progress');
                    progDiv.style.display = 'block';
                    document.getElementById('vod-upload-controls').style.display = 'none';
                    document.getElementById('vod-transcode-label').textContent = 'Checking transcode status...';
                    document.getElementById('vod-transcode-step').textContent = 'Polling VOD server...';
                    console.log('[VOD] Starting transcode poll: server=' + serverId + ' job=' + jobId + ' movie=' + MOVIE_ID);

                    if (vodJobPollTimer) clearInterval(vodJobPollTimer);

                    function pollOnce() {
                        fetch('/admin/vod-server/job-status?server_id=' + serverId + '&job_id=' + jobId + '&movie_id=' + MOVIE_ID)
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                console.log('[VOD] Poll response:', data);
                                if (!data.success) {
                                    // VOD server can't find this job — stop polling, show failure
                                    clearInterval(vodJobPollTimer);
                                    vodJobPollTimer = null;
                                    document.getElementById('vod-transcode-bar').style.background = 'var(--danger)';
                                    document.getElementById('vod-transcode-label').textContent = 'Job not found on VOD server';
                                    document.getElementById('vod-transcode-step').textContent = data.error || '';
                                    showMsg('VOD job no longer exists on the server. You can re-upload.', 'error');
                                    resetButtons();
                                    return;
                                }
                                if (!data.job) {
                                    document.getElementById('vod-transcode-step').textContent = 'Waiting for response...';
                                    return;
                                }
                                var j = data.job;
                                var pct = parseFloat(j.progress || 0);

                                document.getElementById('vod-transcode-pct').textContent = pct.toFixed(1) + '%';
                                document.getElementById('vod-transcode-bar').style.width = pct + '%';
                                document.getElementById('vod-transcode-step').textContent = j.current_step || j.status || '';

                                if (j.status === 'pending' || j.status === 'queued') {
                                    document.getElementById('vod-transcode-label').textContent = 'Waiting in queue...';
                                } else if (j.status === 'processing' || j.status === 'packaging' || j.status === 'downloading') {
                                    document.getElementById('vod-transcode-label').textContent =
                                        j.status === 'packaging' ? 'Packaging for streaming...' :
                                        j.status === 'downloading' ? 'Downloading source...' :
                                        'Transcoding...';
                                } else if (j.status === 'complete') {
                                    clearInterval(vodJobPollTimer);
                                    vodJobPollTimer = null;
                                    document.getElementById('vod-transcode-bar').style.width = '100%';
                                    document.getElementById('vod-transcode-bar').style.background = 'var(--success)';
                                    document.getElementById('vod-transcode-pct').textContent = '100%';
                                    document.getElementById('vod-transcode-label').textContent = 'Transcode complete!';
                                    document.getElementById('vod-transcode-step').textContent = '';
                                    if (j.stream_url) {
                                        document.getElementById('stream_url').value = j.stream_url;
                                    }
                                    showMsg('Transcode complete! Stream URL has been saved automatically.', 'success');
                                    resetButtons();
                                } else if (j.status === 'failed') {
                                    clearInterval(vodJobPollTimer);
                                    vodJobPollTimer = null;
                                    document.getElementById('vod-transcode-bar').style.background = 'var(--danger)';
                                    document.getElementById('vod-transcode-label').textContent = 'Transcode failed';
                                    showMsg('Transcode failed: ' + (j.error_msg || 'Unknown error'), 'error');
                                    resetButtons();
                                } else if (j.status === 'cancelled') {
                                    clearInterval(vodJobPollTimer);
                                    vodJobPollTimer = null;
                                    document.getElementById('vod-transcode-bar').style.background = 'var(--warning)';
                                    document.getElementById('vod-transcode-label').textContent = 'Job cancelled';
                                    resetButtons();
                                }
                            })
                            .catch(function(err) {
                                console.log('[VOD] Poll error:', err);
                                document.getElementById('vod-transcode-step').textContent = 'Connection error, retrying...';
                            });
                    }

                    // Poll immediately, then every 3 seconds
                    pollOnce();
                    vodJobPollTimer = setInterval(pollOnce, 3000);
                }

                window.vodDeleteFromServer = function() {
                    var serverId = SAVED_SERVER_ID || (document.getElementById('vod-transcode-server') ? document.getElementById('vod-transcode-server').value : 0);
                    var contentId = 'movie-' + MOVIE_ID;
                    var jobId = SAVED_JOB_ID || 0;

                    if (!confirm('Delete this movie\'s transcoded content from the VOD server?\n\nThis will:\n- Remove the transcoded files from the VOD server\n- Clear the stream URL\n- Cancel any active transcode job\n\nThe movie record in the IPTV system will be kept.')) {
                        return;
                    }

                    var btn = document.getElementById('vod-delete-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="lucide-loader"></i> Removing...';
                    }

                    // Stop any active polling
                    if (vodJobPollTimer) {
                        clearInterval(vodJobPollTimer);
                        vodJobPollTimer = null;
                    }

                    var formData = new FormData();
                    formData.append('csrf_token', CSRF);
                    formData.append('server_id', serverId);
                    formData.append('content_id', contentId);
                    formData.append('movie_id', MOVIE_ID);
                    formData.append('job_id', jobId);

                    fetch('/admin/vod-server/movie-vod-delete', { method: 'POST', body: formData })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                if (typeof showToast === 'function') {
                                    showToast(data.message || 'VOD content removed', 'success');
                                }
                                // Reload the page to show clean state (no VOD status)
                                setTimeout(function() { location.reload(); }, 800);
                            } else {
                                alert('Failed: ' + (data.error || 'Unknown error'));
                                if (btn) {
                                    btn.disabled = false;
                                    btn.innerHTML = '<i class="lucide-trash-2"></i> Remove VOD';
                                }
                            }
                        })
                        .catch(function(err) {
                            alert('Network error: ' + err);
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="lucide-trash-2"></i> Remove VOD';
                            }
                        });
                };

                // Auto-resume polling if there's an active job from DB
                if (SAVED_JOB_ID > 0 && SAVED_SERVER_ID > 0 &&
                    ['pending', 'processing', 'packaging', 'downloading'].indexOf(SAVED_STATUS) !== -1) {
                    startTranscodePoll(SAVED_SERVER_ID, SAVED_JOB_ID);
                }
            })();
            </script>
            <?php endif; ?>

            <?php if ($isEdit): ?>
            <!-- Content Markers Card (Intro, Credits, Ad Cue Points) -->
            <div class="card mb-3">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                    <h3 class="card-title"><i class="lucide-bookmark"></i> Content Markers</h3>
                    <span class="text-muted" style="font-size:0.75rem">Set intro/credits/ad markers for the player</span>
                </div>
                <div class="card-body">
                    <?php $movieStreamUrl = $movie['stream_url'] ?? ''; ?>
                    <?php if ($movieStreamUrl): ?>
                    <!-- Preview Player -->
                    <div style="margin-bottom:1rem;">
                        <video id="movieMarkerVideo" style="width:100%;max-height:360px;background:#000;border-radius:6px" controls></video>
                        <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.25rem">
                            Current: <span id="movieMarkerTime">0:00.000</span>
                        </div>
                    </div>

                    <!-- Marker Buttons -->
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
                        <button type="button" class="btn btn-sm" style="background:#22c55e;color:#fff" onclick="setMovieMarker('intro_start')">
                            <i class="lucide-log-in"></i> Intro Start
                        </button>
                        <button type="button" class="btn btn-sm" style="background:#3b82f6;color:#fff" onclick="setMovieMarker('intro_end')">
                            <i class="lucide-log-out"></i> Intro End
                        </button>
                        <button type="button" class="btn btn-sm" style="background:#f59e0b;color:#fff" onclick="setMovieMarker('credits_start')">
                            <i class="lucide-film"></i> Credits Start
                        </button>
                        <button type="button" class="btn btn-sm" style="background:#ef4444;color:#fff" onclick="setMovieMarker('ad_cue')">
                            <i class="lucide-megaphone"></i> Ad Cue Point
                        </button>
                    </div>

                    <!-- Timeline -->
                    <div id="movieMarkerTimeline" style="position:relative;height:24px;background:rgba(255,255,255,0.06);border-radius:4px;margin-bottom:1rem;cursor:pointer" onclick="seekMovieTimeline(event)"></div>

                    <?php else: ?>
                    <p class="text-muted" style="font-size:0.85rem"><i class="lucide-info" style="font-size:0.9rem"></i> Add a stream URL or upload VOD content first, then you can set markers by previewing the video.</p>
                    <?php endif; ?>

                    <!-- Marker List -->
                    <div id="movieMarkerList" style="font-size:0.85rem">
                        <div class="text-muted" style="text-align:center;padding:0.5rem">Loading markers...</div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                const MOVIE_ID = <?= (int)$movie['id'] ?>;
                const CSRF = '<?= \CariIPTV\Core\Session::csrf() ?>';
                const video = document.getElementById('movieMarkerVideo');
                const timeDisplay = document.getElementById('movieMarkerTime');
                const timeline = document.getElementById('movieMarkerTimeline');
                const listEl = document.getElementById('movieMarkerList');
                let markers = [];

                // Init video
                <?php if (!empty($movieStreamUrl)): ?>
                const streamUrl = <?= json_encode($movieStreamUrl) ?>;
                if (streamUrl.endsWith('.m3u8') && window.Hls) {
                    if (Hls.isSupported()) {
                        const hls = new Hls();
                        hls.loadSource(streamUrl);
                        hls.attachMedia(video);
                    }
                } else if (video) {
                    video.src = streamUrl;
                }

                if (video) {
                    video.addEventListener('timeupdate', () => {
                        if (timeDisplay) timeDisplay.textContent = fmtTime(video.currentTime);
                        updateTimelineCursor();
                    });
                }
                <?php endif; ?>

                // Load markers
                loadMovieMarkers();

                function loadMovieMarkers() {
                    fetch('/admin/vod-server/markers?content_type=movie&content_id=' + MOVIE_ID)
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                markers = data.markers || [];
                                renderMovieMarkers();
                            }
                        })
                        .catch(() => {
                            if (listEl) listEl.innerHTML = '<div class="text-muted" style="text-align:center;padding:0.5rem">Failed to load markers</div>';
                        });
                }

                function renderMovieMarkers() {
                    if (!listEl) return;
                    if (!markers.length) {
                        listEl.innerHTML = '<div class="text-muted" style="text-align:center;padding:0.5rem">No markers set. Use the buttons above while previewing to add markers.</div>';
                    } else {
                        listEl.innerHTML = markers.map(m => {
                            const colors = {intro_start:'#22c55e',intro_end:'#3b82f6',credits_start:'#f59e0b',ad_cue:'#ef4444'};
                            const labels = {intro_start:'Intro Start',intro_end:'Intro End',credits_start:'Credits Start',ad_cue:'Ad Cue'};
                            return '<div style="display:flex;align-items:center;gap:0.5rem;padding:0.375rem 0;border-bottom:1px solid rgba(255,255,255,0.06)">' +
                                '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + (colors[m.marker_type]||'#888') + '"></span>' +
                                '<span style="font-weight:500;min-width:100px">' + (labels[m.marker_type]||m.marker_type) + '</span>' +
                                '<span style="color:var(--text-muted);font-family:monospace;font-size:0.8rem">' + fmtTime(parseFloat(m.position_seconds)) + '</span>' +
                                (m.label ? '<span style="color:var(--text-muted);font-size:0.75rem">— ' + escHtml(m.label) + '</span>' : '') +
                                '<button type="button" onclick="deleteMovieMarker(' + m.id + ')" style="margin-left:auto;background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.8rem" title="Delete"><i class="lucide-x"></i></button>' +
                                '</div>';
                        }).join('');
                    }

                    // Render timeline flags
                    if (timeline && video) {
                        renderTimelineFlags();
                    }
                }

                function renderTimelineFlags() {
                    if (!timeline) return;
                    timeline.innerHTML = '';
                    const dur = video.duration || 0;
                    if (dur <= 0) return;
                    markers.forEach(m => {
                        const pct = (parseFloat(m.position_seconds) / dur) * 100;
                        const colors = {intro_start:'#22c55e',intro_end:'#3b82f6',credits_start:'#f59e0b',ad_cue:'#ef4444'};
                        const flag = document.createElement('div');
                        flag.style.cssText = 'position:absolute;left:' + pct + '%;top:0;bottom:0;width:2px;background:' + (colors[m.marker_type]||'#888');
                        flag.title = (m.marker_type.replace('_', ' ')) + ' at ' + fmtTime(parseFloat(m.position_seconds));
                        timeline.appendChild(flag);
                    });
                }

                function updateTimelineCursor() {
                    // Remove old cursor
                    const old = timeline?.querySelector('.tl-cursor');
                    if (old) old.remove();
                    if (!timeline || !video || !video.duration) return;
                    const pct = (video.currentTime / video.duration) * 100;
                    const cursor = document.createElement('div');
                    cursor.className = 'tl-cursor';
                    cursor.style.cssText = 'position:absolute;left:' + pct + '%;top:0;bottom:0;width:2px;background:#fff;z-index:2;pointer-events:none';
                    timeline.appendChild(cursor);
                }

                function fmtTime(s) {
                    s = parseFloat(s) || 0;
                    const h = Math.floor(s / 3600);
                    const m = Math.floor((s % 3600) / 60);
                    const sec = (s % 60).toFixed(3);
                    if (h > 0) return h + ':' + String(m).padStart(2, '0') + ':' + String(sec).padStart(6, '0');
                    return m + ':' + String(sec).padStart(6, '0');
                }

                function escHtml(str) {
                    const d = document.createElement('div');
                    d.textContent = str;
                    return d.innerHTML;
                }

                // Expose functions to global scope
                window.setMovieMarker = function(markerType) {
                    if (!video || video.readyState < 1) { alert('Video not ready. Please wait for it to load.'); return; }
                    const pos = video.currentTime;
                    const fd = new FormData();
                    fd.append('csrf_token', CSRF);
                    fd.append('content_type', 'movie');
                    fd.append('content_id', MOVIE_ID);
                    fd.append('marker_type', markerType);
                    fd.append('position_seconds', pos.toFixed(3));

                    fetch('/admin/vod-server/markers/save', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) loadMovieMarkers();
                            else alert(data.error || 'Failed to save marker');
                        })
                        .catch(err => alert('Error: ' + err));
                };

                window.deleteMovieMarker = function(id) {
                    if (!confirm('Delete this marker?')) return;
                    const fd = new FormData();
                    fd.append('csrf_token', CSRF);
                    fd.append('id', id);
                    fetch('/admin/vod-server/markers/delete', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) loadMovieMarkers();
                            else alert(data.error || 'Failed to delete marker');
                        })
                        .catch(err => alert('Error: ' + err));
                };

                window.seekMovieTimeline = function(e) {
                    if (!video || !video.duration) return;
                    const rect = timeline.getBoundingClientRect();
                    const pct = (e.clientX - rect.left) / rect.width;
                    video.currentTime = pct * video.duration;
                };
            })();
            </script>
            <?php endif; ?>

            <!-- Trailers Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="lucide-youtube"></i> Trailers</h3>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="searchTrailersModal()">
                        <i class="lucide-search"></i> Search YouTube
                    </button>
                </div>
                <div class="card-body">
                    <div id="trailersList">
                        <?php if ($isEdit && !empty($movie['trailers'])): ?>
                            <?php foreach ($movie['trailers'] as $index => $trailer): ?>
                                <div class="trailer-item" data-trailer-id="<?= $trailer['id'] ?>">
                                    <div class="trailer-preview" onclick="previewTrailer('<?= $trailer['video_key'] ?>')" style="cursor: pointer;">
                                        <img src="https://img.youtube.com/vi/<?= $trailer['video_key'] ?>/mqdefault.jpg" alt="">
                                        <div class="play-overlay"><i class="lucide-play"></i></div>
                                    </div>
                                    <div class="trailer-info">
                                        <strong><?= htmlspecialchars($trailer['name'] ?: 'Trailer') ?></strong>
                                        <small class="text-muted"><?= ucfirst($trailer['type']) ?></small>
                                        <button type="button" class="btn btn-xs btn-secondary" onclick="previewTrailer('<?= $trailer['video_key'] ?>')">
                                            <i class="lucide-play"></i> Preview
                                        </button>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeTrailer(<?= $trailer['id'] ?>)">
                                        <i class="lucide-trash-2"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div id="noTrailers" class="text-muted text-center" style="padding: 1rem; <?= ($isEdit && !empty($movie['trailers'])) ? 'display: none;' : '' ?>">
                        No trailers added yet. Use "Search YouTube" to find trailers.
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Media & Settings -->
        <div class="form-column">
            <!-- Media Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="lucide-image"></i> Media</h3>
                    <?php if ($isEdit && !empty($movie['tmdb_id'])): ?>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="searchFanart()">
                            <i class="lucide-palette"></i> Browse Fanart.tv
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="media-preview-grid">
                        <div class="media-preview-item">
                            <label class="form-label">Poster</label>
                            <div class="media-preview" id="posterPreview">
                                <?php if (!empty($movie['poster_url'])): ?>
                                    <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="Poster">
                                <?php else: ?>
                                    <div class="media-placeholder">
                                        <i class="lucide-image"></i>
                                        <span>No poster</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <input type="text" name="poster_url" id="poster_url" class="form-input mt-1"
                                   value="<?= htmlspecialchars($movie['poster_url'] ?? '') ?>"
                                   placeholder="Poster URL or path" onchange="updatePosterPreview()">
                        </div>
                        <div class="media-preview-item">
                            <label class="form-label">Backdrop</label>
                            <div class="media-preview backdrop" id="backdropPreview">
                                <?php if (!empty($movie['backdrop_url'])): ?>
                                    <img src="<?= htmlspecialchars($movie['backdrop_url']) ?>" alt="Backdrop">
                                <?php else: ?>
                                    <div class="media-placeholder">
                                        <i class="lucide-image"></i>
                                        <span>No backdrop</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <input type="text" name="backdrop_url" id="backdrop_url" class="form-input mt-1"
                                   value="<?= htmlspecialchars($movie['backdrop_url'] ?? '') ?>"
                                   placeholder="Backdrop URL or path" onchange="updateBackdropPreview()">
                        </div>
                    </div>

                    <div class="form-group mt-2">
                        <label class="form-label" for="logo_url">Clear Logo URL</label>
                        <input type="text" name="logo_url" id="logo_url" class="form-input"
                               value="<?= htmlspecialchars($movie['logo_url'] ?? '') ?>"
                               placeholder="HD logo from Fanart.tv">
                    </div>
                </div>
            </div>

            <!-- Categories Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="lucide-folder"></i> Categories</h3>
                </div>
                <div class="card-body">
                    <?php
                    $selectedCategories = [];
                    $primaryCat = null;
                    if ($isEdit && !empty($movie['categories'])) {
                        foreach ($movie['categories'] as $cat) {
                            $selectedCategories[] = $cat['category_id'];
                            if ($cat['is_primary']) {
                                $primaryCat = $cat['category_id'];
                            }
                        }
                    }
                    ?>
                    <div class="category-checkboxes">
                        <?php foreach ($categories as $cat): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>"
                                       <?= in_array($cat['id'], $selectedCategories) ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($cat['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($categories)): ?>
                        <p class="text-muted text-sm">No categories available. Categories will be auto-created from TMDB genres.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Settings Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="lucide-settings"></i> Settings</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-input">
                            <option value="draft" <?= ($movie['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= ($movie['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="archived" <?= ($movie['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_featured" value="1"
                                   <?= !empty($movie['is_featured']) ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Featured Movie</strong>
                                <small>Display prominently in featured sections</small>
                            </span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_free" value="1"
                                   <?= !empty($movie['is_free']) ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Free Content</strong>
                                <small>Royalty-free or Creative Commons content</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <a href="/admin/movies" class="btn btn-secondary">
                    <i class="lucide-x"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="lucide-save"></i> <?= $isEdit ? 'Update' : 'Create' ?> Movie
                </button>
            </div>
        </div>
    </div>
</form>

<!-- TMDB Search Results Modal -->
<div class="modal-overlay" id="tmdbModal" style="display: none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>TMDB Search Results</h3>
            <button type="button" class="modal-close" onclick="closeTmdbModal()">&times;</button>
        </div>
        <div class="modal-body" id="tmdbModalContent">
            <!-- Results will be loaded here -->
        </div>
    </div>
</div>

<!-- Fanart.tv Artwork Modal -->
<div class="modal-overlay" id="fanartModal" style="display: none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>Fanart.tv Artwork</h3>
            <button type="button" class="modal-close" onclick="closeFanartModal()">&times;</button>
        </div>
        <div class="modal-body" id="fanartModalContent">
            <div class="loading-spinner">Loading artwork...</div>
        </div>
    </div>
</div>

<!-- YouTube Trailers Search Modal -->
<div class="modal-overlay" id="trailersModal" style="display: none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>Search YouTube Trailers</h3>
            <button type="button" class="modal-close" onclick="closeTrailersModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <div class="metadata-search">
                    <input type="text" id="trailerSearchQuery" class="form-input" placeholder="Movie title for trailer search">
                    <button type="button" class="btn btn-primary" onclick="searchTrailers()">
                        <i class="lucide-search"></i> Search
                    </button>
                </div>
            </div>
            <div id="trailerSearchResults"></div>
        </div>
    </div>
</div>

<!-- Trailer Preview Modal -->
<div class="modal-overlay" id="trailerPreviewModal" style="display: none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>Trailer Preview</h3>
            <button type="button" class="modal-close" onclick="closeTrailerPreview()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <div class="video-container">
                <iframe id="trailerPreviewFrame" src="" frameborder="0" allowfullscreen allow="autoplay; encrypted-media"></iframe>
            </div>
        </div>
    </div>
</div>

<style>
/* Modal Overlay - Fixed positioning */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
}

.modal-content {
    background: #1e293b;
    border-radius: 12px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    margin: 0;
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

.modal-close:hover {
    color: var(--text-primary);
}

.form-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 1200px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

.form-column {
    display: flex;
    flex-direction: column;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.required {
    color: var(--danger);
}

.metadata-search {
    display: flex;
    gap: 0.5rem;
}

.metadata-search input {
    flex: 1;
}

.search-results {
    margin-top: 1rem;
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
}

.search-result-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.2s;
}

.search-result-item:hover {
    background: var(--bg-hover);
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-poster {
    width: 60px;
    height: 90px;
    object-fit: cover;
    border-radius: 4px;
    background: var(--bg-hover);
}

.search-result-info {
    flex: 1;
}

.search-result-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
}

.search-result-info p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-muted);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.media-preview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.media-preview {
    aspect-ratio: 2/3;
    background: var(--bg-hover);
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.media-preview.backdrop {
    aspect-ratio: 16/9;
}

.media-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.media-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
}

.media-placeholder i {
    font-size: 2rem;
}

.category-checkboxes {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
    max-height: 200px;
    overflow-y: auto;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: 4px;
    cursor: pointer;
}

.checkbox-item:hover {
    background: var(--bg-hover);
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--bg-hover);
    border-radius: 8px;
    cursor: pointer;
}

.checkbox-text {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.checkbox-text small {
    color: var(--text-muted);
}

.form-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding-top: 1rem;
}

.trailer-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: var(--bg-hover);
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.trailer-preview {
    width: 120px;
    height: 68px;
    border-radius: 4px;
    overflow: hidden;
    background: var(--bg-dark);
}

.trailer-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.trailer-preview {
    position: relative;
}

.trailer-preview .play-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 36px;
    height: 36px;
    background: rgba(0, 0, 0, 0.7);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    opacity: 0;
    transition: opacity 0.2s;
}

.trailer-preview:hover .play-overlay {
    opacity: 1;
}

.video-container {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    background: #000;
}

.video-container iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.btn-xs {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.trailer-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.modal-lg {
    max-width: 800px;
}

.modal-body {
    padding: 1.5rem;
    max-height: 70vh;
    overflow-y: auto;
}

.artwork-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
}

.artwork-item {
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s;
}

.artwork-item:hover {
    border-color: var(--primary);
}

.artwork-item img {
    width: 100%;
    aspect-ratio: 2/3;
    object-fit: cover;
}

.artwork-item.backdrop img {
    aspect-ratio: 16/9;
}

.artwork-section {
    margin-bottom: 2rem;
}

.artwork-section h4 {
    margin-bottom: 1rem;
    color: var(--text-secondary);
}

.loading-spinner {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

.mt-1 {
    margin-top: 0.5rem;
}

.mt-2 {
    margin-top: 1rem;
}
</style>

<script>
const csrfToken = '<?= $csrf ?>';
const movieId = <?= $movie['id'] ?? 'null' ?>;

// TMDB Search
function searchTmdb() {
    const query = document.getElementById('tmdbSearchQuery').value.trim();
    if (!query) return;

    const resultsDiv = document.getElementById('tmdbResults');
    resultsDiv.innerHTML = '<div class="loading-spinner">Searching...</div>';
    resultsDiv.style.display = 'block';

    fetch('/admin/movies/search-tmdb', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${csrfToken}&query=${encodeURIComponent(query)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.results.length > 0) {
            resultsDiv.innerHTML = data.results.map(movie => `
                <div class="search-result-item" onclick="selectTmdbMovie(${movie.id})">
                    <img src="${movie.poster || '/assets/images/no-poster.png'}" alt="" class="search-result-poster">
                    <div class="search-result-info">
                        <h4>${escapeHtml(movie.title)} ${movie.year ? '(' + movie.year + ')' : ''}</h4>
                        <p>${escapeHtml(movie.overview || 'No description available')}</p>
                    </div>
                </div>
            `).join('');
        } else {
            resultsDiv.innerHTML = '<div class="text-muted text-center" style="padding: 1rem;">No results found</div>';
        }
    })
    .catch(() => {
        resultsDiv.innerHTML = '<div class="text-danger text-center" style="padding: 1rem;">Search failed</div>';
    });
}

function selectTmdbMovie(tmdbId) {
    const resultsDiv = document.getElementById('tmdbResults');
    resultsDiv.innerHTML = '<div class="loading-spinner">Loading movie details...</div>';

    fetch('/admin/movies/tmdb-details', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${csrfToken}&tmdb_id=${tmdbId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const movie = data.movie;

            // Fill form fields
            document.getElementById('tmdb_id').value = movie.id;
            document.getElementById('title').value = movie.title || '';
            document.getElementById('original_title').value = movie.original_title || '';
            document.getElementById('tagline').value = movie.tagline || '';
            document.getElementById('synopsis').value = movie.overview || '';
            document.getElementById('year').value = movie.year || '';
            document.getElementById('runtime').value = movie.runtime || '';
            document.getElementById('director').value = movie.directors ? movie.directors.join(', ') : '';
            document.getElementById('genres').value = movie.genres ? movie.genres.join(', ') : '';
            document.getElementById('poster_url').value = movie.poster || '';
            document.getElementById('backdrop_url').value = movie.backdrop || '';

            // Update previews
            updatePosterPreview();
            updateBackdropPreview();

            // Add trailers
            if (data.trailers && data.trailers.length > 0) {
                data.trailers.forEach(trailer => {
                    if (trailer.type === 'Trailer' || trailer.type === 'Teaser') {
                        addTrailerToList(trailer);
                    }
                });
            }

            resultsDiv.style.display = 'none';
        } else {
            alert('Failed to load movie details');
        }
    })
    .catch(() => {
        alert('Error loading movie details');
    });
}

// Fanart.tv Search
function searchFanart() {
    const tmdbId = document.getElementById('tmdb_id').value;
    if (!tmdbId) {
        alert('Please search and select a movie from TMDB first');
        return;
    }

    const modal = document.getElementById('fanartModal');
    const content = document.getElementById('fanartModalContent');
    modal.style.display = 'flex';
    content.innerHTML = '<div class="loading-spinner">Loading artwork from Fanart.tv...</div>';

    fetch('/admin/movies/search-fanart', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${csrfToken}&tmdb_id=${tmdbId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.artwork) {
            let html = '';

            if (data.artwork.posters && data.artwork.posters.length > 0) {
                html += `<div class="artwork-section">
                    <h4>Posters (${data.artwork.posters.length})</h4>
                    <div class="artwork-grid">
                        ${data.artwork.posters.map(img => `
                            <div class="artwork-item" onclick="selectArtwork('poster_url', '${img.url}')">
                                <img src="${img.url}" alt="Poster">
                            </div>
                        `).join('')}
                    </div>
                </div>`;
            }

            if (data.artwork.backdrops && data.artwork.backdrops.length > 0) {
                html += `<div class="artwork-section">
                    <h4>Backdrops (${data.artwork.backdrops.length})</h4>
                    <div class="artwork-grid">
                        ${data.artwork.backdrops.map(img => `
                            <div class="artwork-item backdrop" onclick="selectArtwork('backdrop_url', '${img.url}')">
                                <img src="${img.url}" alt="Backdrop">
                            </div>
                        `).join('')}
                    </div>
                </div>`;
            }

            if (data.artwork.logos && data.artwork.logos.length > 0) {
                html += `<div class="artwork-section">
                    <h4>Clear Logos (${data.artwork.logos.length})</h4>
                    <div class="artwork-grid">
                        ${data.artwork.logos.map(img => `
                            <div class="artwork-item" onclick="selectArtwork('logo_url', '${img.url}')" style="background: #333;">
                                <img src="${img.url}" alt="Logo" style="object-fit: contain; padding: 1rem;">
                            </div>
                        `).join('')}
                    </div>
                </div>`;
            }

            content.innerHTML = html || '<div class="text-muted text-center">No artwork found on Fanart.tv</div>';
        } else {
            content.innerHTML = '<div class="text-muted text-center">No artwork found</div>';
        }
    })
    .catch(() => {
        content.innerHTML = '<div class="text-danger text-center">Failed to load artwork</div>';
    });
}

function selectArtwork(field, url) {
    document.getElementById(field).value = url;

    if (field === 'poster_url') updatePosterPreview();
    if (field === 'backdrop_url') updateBackdropPreview();

    closeFanartModal();
}

function closeFanartModal() {
    document.getElementById('fanartModal').style.display = 'none';
}

// Trailer Search
function searchTrailersModal() {
    const title = document.getElementById('title').value || '';
    document.getElementById('trailerSearchQuery').value = title;
    document.getElementById('trailersModal').style.display = 'flex';
}

function searchTrailers() {
    const query = document.getElementById('trailerSearchQuery').value.trim();
    const year = document.getElementById('year').value;

    if (!query) return;

    const resultsDiv = document.getElementById('trailerSearchResults');
    resultsDiv.innerHTML = '<div class="loading-spinner">Searching YouTube...</div>';

    fetch('/admin/movies/search-trailers', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${csrfToken}&title=${encodeURIComponent(query)}&year=${year}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.results.length > 0) {
            resultsDiv.innerHTML = data.results.map(video => `
                <div class="search-result-item" onclick="addYoutubeTrailer('${video.video_id}', '${escapeHtml(video.title)}', '${video.url}')">
                    <img src="${video.thumbnail}" alt="" class="search-result-poster" style="width: 120px; height: auto; aspect-ratio: 16/9;">
                    <div class="search-result-info">
                        <h4>${escapeHtml(video.title)}</h4>
                        <p>${escapeHtml(video.channel)}</p>
                    </div>
                </div>
            `).join('');
        } else {
            resultsDiv.innerHTML = '<div class="text-muted text-center" style="padding: 1rem;">No trailers found</div>';
        }
    })
    .catch(() => {
        resultsDiv.innerHTML = '<div class="text-danger text-center" style="padding: 1rem;">Search failed</div>';
    });
}

function addYoutubeTrailer(videoId, title, url) {
    const trailer = {
        key: videoId,
        name: title,
        type: 'Trailer',
        url: url,
        embed_url: `https://www.youtube.com/embed/${videoId}`
    };
    addTrailerToList(trailer);
    closeTrailersModal();
}

function addTrailerToList(trailer) {
    const list = document.getElementById('trailersList');
    document.getElementById('noTrailers').style.display = 'none';

    const div = document.createElement('div');
    div.className = 'trailer-item';
    div.innerHTML = `
        <div class="trailer-preview" onclick="previewTrailer('${trailer.key}')" style="cursor: pointer;">
            <img src="https://img.youtube.com/vi/${trailer.key}/mqdefault.jpg" alt="">
            <div class="play-overlay"><i class="lucide-play"></i></div>
        </div>
        <div class="trailer-info">
            <strong>${escapeHtml(trailer.name)}</strong>
            <small class="text-muted">${trailer.type}</small>
            <button type="button" class="btn btn-xs btn-secondary" onclick="previewTrailer('${trailer.key}')">
                <i class="lucide-play"></i> Preview
            </button>
            <input type="hidden" name="trailers_new[]" value='${JSON.stringify({
                name: trailer.name,
                type: trailer.type.toLowerCase(),
                url: trailer.url,
                video_key: trailer.key,
                source: 'youtube'
            })}'>
        </div>
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove(); checkNoTrailers();">
            <i class="lucide-trash-2"></i>
        </button>
    `;
    list.appendChild(div);
}

function checkNoTrailers() {
    const list = document.getElementById('trailersList');
    const noTrailers = document.getElementById('noTrailers');
    if (list.children.length === 0) {
        noTrailers.style.display = 'block';
    }
}

function removeTrailer(trailerId) {
    if (!confirm('Remove this trailer?')) return;

    fetch(`/admin/movies/${movieId}/trailers/${trailerId}/remove`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-trailer-id="${trailerId}"]`).remove();
        }
    });
}

function closeTrailersModal() {
    document.getElementById('trailersModal').style.display = 'none';
}

// Trailer Preview
function previewTrailer(videoKey) {
    const modal = document.getElementById('trailerPreviewModal');
    const iframe = document.getElementById('trailerPreviewFrame');
    iframe.src = `https://www.youtube.com/embed/${videoKey}?autoplay=1`;
    modal.style.display = 'flex';
}

function closeTrailerPreview() {
    const modal = document.getElementById('trailerPreviewModal');
    const iframe = document.getElementById('trailerPreviewFrame');
    iframe.src = '';
    modal.style.display = 'none';
}

function closeTmdbModal() {
    document.getElementById('tmdbModal').style.display = 'none';
}

// Preview updates
function updatePosterPreview() {
    const url = document.getElementById('poster_url').value;
    const preview = document.getElementById('posterPreview');
    if (url) {
        preview.innerHTML = `<img src="${url}" alt="Poster">`;
    } else {
        preview.innerHTML = '<div class="media-placeholder"><i class="lucide-image"></i><span>No poster</span></div>';
    }
}

function updateBackdropPreview() {
    const url = document.getElementById('backdrop_url').value;
    const preview = document.getElementById('backdropPreview');
    if (url) {
        preview.innerHTML = `<img src="${url}" alt="Backdrop">`;
    } else {
        preview.innerHTML = '<div class="media-placeholder"><i class="lucide-image"></i><span>No backdrop</span></div>';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            // Stop video if it's the trailer preview modal
            if (this.id === 'trailerPreviewModal') {
                document.getElementById('trailerPreviewFrame').src = '';
            }
            this.style.display = 'none';
        }
    });
});

// Initialize search query from title on load
document.addEventListener('DOMContentLoaded', function() {
    const title = document.getElementById('title').value;
    if (title) {
        document.getElementById('tmdbSearchQuery').value = title;
    }
});
</script>
