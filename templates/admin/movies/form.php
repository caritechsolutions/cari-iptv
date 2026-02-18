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
                    <?php if ($vodStatus === 'complete'): ?>
                        <span class="badge badge-success"><i class="lucide-check-circle"></i> Ready</span>
                    <?php elseif ($vodActive): ?>
                        <span class="badge badge-primary"><i class="lucide-loader"></i> Processing</span>
                    <?php elseif ($vodStatus === 'failed'): ?>
                        <span class="badge badge-danger"><i class="lucide-alert-circle"></i> Failed</span>
                    <?php endif; ?>
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
                                        <option value="<?= $vs['id'] ?>" data-url="<?= htmlspecialchars($vs['url']) ?>"
                                            <?= ($vodServerId && $vs['id'] == $vodServerId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vs['name']) ?><?= $vs['is_default'] ? ' (default)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Transcode Profile</label>
                                <select class="form-input" id="vod-transcode-profile">
                                    <option value="standard">Standard (H.264 ABR)</option>
                                    <option value="high">High (HEVC)</option>
                                    <option value="low">Low Bandwidth</option>
                                    <option value="hevc_4k">HEVC 4K</option>
                                    <option value="av1">AV1 (Web/Mobile)</option>
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
                    var serverId = document.getElementById('vod-transcode-server').value;
                    var profile = document.getElementById('vod-transcode-profile').value;
                    var contentId = 'movie-' + MOVIE_ID;
                    var uploadBtn = document.getElementById('vod-upload-btn');
                    var browseBtn = document.getElementById('vod-browse-btn');

                    uploadBtn.disabled = true;
                    uploadBtn.innerHTML = '<i class="lucide-loader"></i> Uploading...';
                    browseBtn.disabled = true;
                    document.getElementById('vod-transcode-server').disabled = true;
                    document.getElementById('vod-transcode-profile').disabled = true;

                    document.getElementById('vod-upload-progress').style.display = 'block';
                    document.getElementById('vod-status-msg').style.display = 'none';
                    document.getElementById('vod-upload-label').textContent = 'Uploading: ' + file.name;
                    document.getElementById('vod-upload-pct').textContent = '0%';
                    document.getElementById('vod-upload-bar').style.width = '0%';
                    document.getElementById('vod-upload-bar').style.background = 'var(--primary)';
                    document.getElementById('vod-upload-size').textContent = '0 / ' + fmt(file.size);

                    var formData = new FormData();
                    formData.append('csrf_token', CSRF);
                    formData.append('video_file', file);
                    formData.append('server_id', serverId);
                    formData.append('movie_id', MOVIE_ID);
                    formData.append('content_id', contentId);
                    formData.append('title', MOVIE_TITLE);
                    formData.append('profile', profile);
                    if (overwrite) formData.append('overwrite', '1');

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '/admin/vod-server/upload-source', true);

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
                        if (xhr.status === 0 || xhr.responseText === '') {
                            showMsg('Upload failed: File too large. Check PHP upload_max_filesize and Nginx client_max_body_size.', 'error');
                            resetButtons();
                            return;
                        }

                        try {
                            var data = JSON.parse(xhr.responseText);
                        } catch(e) {
                            var msg = 'Invalid server response';
                            if (xhr.responseText.indexOf('413') !== -1 || xhr.responseText.indexOf('Too Large') !== -1) {
                                msg = 'File too large. Increase upload_max_filesize in PHP and client_max_body_size in Nginx.';
                            }
                            showMsg('Upload failed: ' + msg, 'error');
                            resetButtons();
                            return;
                        }

                        if (data.success) {
                            document.getElementById('vod-upload-bar').style.background = 'var(--success)';
                            document.getElementById('vod-upload-label').textContent = file.name + ' — Uploaded!';
                            document.getElementById('vod-upload-pct').textContent = 'Done';

                            var job = data.job || {};
                            var sid = data.server_id || serverId;
                            // Accept id or job_id from the VOD server response
                            var jid = job.id || job.job_id || 0;
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
                            showMsg('Upload failed: ' + (data.error || 'Unknown error'), 'error');
                            resetButtons();
                        }
                    });

                    xhr.addEventListener('error', function() {
                        showMsg('Upload failed: Network error or file too large', 'error');
                        resetButtons();
                    });

                    xhr.send(formData);
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
                                if (!data.success || !data.job) {
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

                // Auto-resume polling if there's an active job from DB
                if (SAVED_JOB_ID > 0 && SAVED_SERVER_ID > 0 &&
                    ['pending', 'processing', 'packaging', 'downloading'].indexOf(SAVED_STATUS) !== -1) {
                    startTranscodePoll(SAVED_SERVER_ID, SAVED_JOB_ID);
                }
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
