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
            <!-- VOD Transcode Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="lucide-hard-drive"></i> VOD Transcode</h3>
                </div>
                <div class="card-body">
                    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem">
                        Send this movie to a VOD server for transcoding. Once complete, the stream URL will be set automatically.
                    </p>

                    <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                        <div class="form-group">
                            <label class="form-label">VOD Server</label>
                            <select class="form-input" id="vod-transcode-server">
                                <?php foreach ($vodServers as $vs): ?>
                                    <option value="<?= $vs['id'] ?>" data-url="<?= htmlspecialchars($vs['url']) ?>">
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
                                   placeholder="/path/to/movie.mp4 or https://url/movie.mp4" style="flex:1">
                            <input type="file" id="vod-file-input" accept="video/*,.mkv,.avi,.ts,.m2ts" style="display:none"
                                   onchange="vodHandleFileSelect(this)">
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('vod-file-input').click()" title="Upload from your computer">
                                <i class="lucide-upload"></i> Upload
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="vodBrowseSource()" title="Browse files on VOD server">
                                <i class="lucide-folder-open"></i> Server
                            </button>
                        </div>
                        <small style="color:var(--text-muted)">Upload from your computer, browse the VOD server, or enter a URL</small>
                    </div>

                    <!-- Upload progress -->
                    <div id="vod-upload-progress" style="display:none;margin-bottom:1rem">
                        <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                            <span style="font-size:0.85rem;color:var(--text-secondary)" id="vod-upload-filename">Uploading...</span>
                            <span style="font-size:0.85rem;font-family:var(--font-mono);color:var(--text-muted)" id="vod-upload-pct">0%</span>
                        </div>
                        <div style="height:8px;background:var(--bg-hover);border-radius:4px;overflow:hidden">
                            <div id="vod-upload-bar" style="height:100%;width:0%;background:var(--primary);border-radius:4px;transition:width 0.2s"></div>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem" id="vod-upload-size"></div>
                    </div>

                    <div id="vod-transcode-status" style="display:none;padding:0.75rem;border-radius:8px;font-size:0.85rem;margin-bottom:1rem"></div>

                    <div style="display:flex;justify-content:flex-end;gap:0.5rem">
                        <button type="button" class="btn btn-primary" id="vod-submit-btn" onclick="vodSubmitTranscode()">
                            <i class="lucide-play"></i> Submit for Transcoding
                        </button>
                    </div>
                </div>
            </div>

            <!-- VOD Browse Modal -->
            <div id="vod-browse-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:1000;display:none;align-items:center;justify-content:center">
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;max-width:600px;width:90%;max-height:80vh;overflow:hidden">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border-color)">
                        <h3 style="margin:0;font-size:1.1rem">Browse Files on VOD Server</h3>
                        <button class="btn btn-sm" onclick="document.getElementById('vod-browse-modal').style.display='none'">&times;</button>
                    </div>
                    <div style="padding:1rem 1.25rem">
                        <div id="vod-browse-path" style="font-family:var(--font-mono);font-size:0.8rem;color:var(--text-muted);padding:0.5rem 0.75rem;background:var(--bg-dark);border-radius:6px;margin-bottom:0.75rem">/</div>
                        <div id="vod-browse-list" style="max-height:400px;overflow-y:auto">
                            <div style="text-align:center;padding:2rem;color:var(--text-muted)">Loading...</div>
                        </div>
                    </div>
                    <div style="padding:1rem 1.25rem;border-top:1px solid var(--border-color);display:flex;justify-content:space-between">
                        <button class="btn btn-secondary" onclick="document.getElementById('vod-browse-modal').style.display='none'">Cancel</button>
                        <button class="btn btn-primary" id="vod-browse-select" onclick="vodSelectFile()" disabled>Select File</button>
                    </div>
                </div>
            </div>

            <script>
            var vodSelectedFile = null;

            function vodBrowseSource() {
                vodSelectedFile = null;
                document.getElementById('vod-browse-select').disabled = true;
                document.getElementById('vod-browse-modal').style.display = 'flex';
                vodLoadDir('/');
            }

            function vodLoadDir(path) {
                var serverId = document.getElementById('vod-transcode-server').value;
                var list = document.getElementById('vod-browse-list');
                list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-muted)">Loading...</div>';

                // Update breadcrumb
                var parts = path.split('/').filter(Boolean);
                var bc = '<span style="cursor:pointer;color:var(--primary)" onclick="vodLoadDir(\'/\')">/</span>';
                var acc = '';
                parts.forEach(function(p) {
                    acc += '/' + p;
                    bc += ' <span style="cursor:pointer;color:var(--primary)" onclick="vodLoadDir(\'' + acc + '\')">' + p + '</span> /';
                });
                document.getElementById('vod-browse-path').innerHTML = bc;

                fetch('/admin/vod-server/browse?server_id=' + serverId + '&path=' + encodeURIComponent(path))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success) { list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--danger)">' + (data.error || 'Error') + '</div>'; return; }
                        var entries = data.data?.entries || data.data?.items || [];
                        if (entries.length === 0) { list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-muted)">Empty directory</div>'; return; }

                        var dirs = entries.filter(function(e) { return e.type === 'directory'; }).sort(function(a,b) { return a.name.localeCompare(b.name); });
                        var files = entries.filter(function(e) { return e.type !== 'directory'; }).sort(function(a,b) { return a.name.localeCompare(b.name); });
                        var html = '';
                        var videoExts = ['.mp4','.mkv','.avi','.mov','.wmv','.flv','.webm','.ts','.m2ts','.mpg','.mpeg','.m4v'];

                        if (path !== '/') {
                            var parent = path.replace(/\/[^/]+\/?$/, '') || '/';
                            html += '<div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.75rem;cursor:pointer;border-radius:6px" onmouseover="this.style.background=\'var(--bg-hover)\'" onmouseout="this.style.background=\'transparent\'" onclick="vodLoadDir(\'' + parent + '\')"><i class="lucide-arrow-up" style="color:var(--text-muted)"></i><span>..</span></div>';
                        }

                        dirs.forEach(function(d) {
                            var fp = (path === '/' ? '' : path) + '/' + d.name;
                            html += '<div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.75rem;cursor:pointer;border-radius:6px" onmouseover="this.style.background=\'var(--bg-hover)\'" onmouseout="this.style.background=\'transparent\'" ondblclick="vodLoadDir(\'' + fp + '\')"><i class="lucide-folder" style="color:var(--text-muted)"></i><span style="flex:1">' + d.name + '</span></div>';
                        });

                        files.forEach(function(f) {
                            var ext = '.' + (f.name.split('.').pop() || '').toLowerCase();
                            var isVideo = videoExts.indexOf(ext) !== -1;
                            var fp = (path === '/' ? '' : path) + '/' + f.name;
                            html += '<div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.75rem;cursor:pointer;border-radius:6px;border:1px solid transparent" onclick="vodPickFile(this, \'' + fp.replace(/'/g, "\\'") + '\')" onmouseover="this.style.background=\'var(--bg-hover)\'" onmouseout="if(!this.classList.contains(\'sel\'))this.style.background=\'transparent\'"><i class="lucide-' + (isVideo ? 'film' : 'file') + '" style="color:var(--text-muted)"></i><span style="flex:1;font-size:0.9rem">' + f.name + '</span><span style="font-size:0.75rem;color:var(--text-muted)">' + (f.size_human || '') + '</span></div>';
                        });

                        list.innerHTML = html;
                    })
                    .catch(function(err) { list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--danger)">' + err.message + '</div>'; });
            }

            function vodPickFile(el, path) {
                document.querySelectorAll('#vod-browse-list > div.sel').forEach(function(d) { d.classList.remove('sel'); d.style.borderColor = 'transparent'; d.style.background = 'transparent'; });
                el.classList.add('sel');
                el.style.borderColor = 'var(--primary)';
                el.style.background = 'rgba(99,102,241,0.08)';
                vodSelectedFile = path;
                document.getElementById('vod-browse-select').disabled = false;
            }

            function vodSelectFile() {
                if (vodSelectedFile) {
                    document.getElementById('vod-source-path').value = vodSelectedFile;
                }
                document.getElementById('vod-browse-modal').style.display = 'none';
            }

            function vodFormatSize(bytes) {
                if (!bytes) return '0 B';
                var k = 1024, sizes = ['B', 'KB', 'MB', 'GB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
            }

            function vodHandleFileSelect(input) {
                if (!input.files || !input.files[0]) return;
                var file = input.files[0];

                // Show progress bar
                var progressDiv = document.getElementById('vod-upload-progress');
                var statusDiv = document.getElementById('vod-transcode-status');
                progressDiv.style.display = 'block';
                statusDiv.style.display = 'none';
                document.getElementById('vod-upload-filename').textContent = 'Uploading: ' + file.name;
                document.getElementById('vod-upload-pct').textContent = '0%';
                document.getElementById('vod-upload-bar').style.width = '0%';
                document.getElementById('vod-upload-size').textContent = '0 / ' + vodFormatSize(file.size);

                var formData = new FormData();
                formData.append('csrf_token', '<?= \CariIPTV\Core\Session::csrf() ?>');
                formData.append('video_file', file);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/admin/vod-server/upload-source', true);

                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var pct = Math.round((e.loaded / e.total) * 100);
                        document.getElementById('vod-upload-pct').textContent = pct + '%';
                        document.getElementById('vod-upload-bar').style.width = pct + '%';
                        document.getElementById('vod-upload-size').textContent = vodFormatSize(e.loaded) + ' / ' + vodFormatSize(e.total);
                    }
                });

                xhr.addEventListener('load', function() {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            document.getElementById('vod-source-path').value = data.download_url;
                            document.getElementById('vod-upload-filename').textContent = file.name + ' - Uploaded!';
                            document.getElementById('vod-upload-bar').style.background = 'var(--success)';
                            document.getElementById('vod-upload-pct').textContent = 'Done';
                        } else {
                            document.getElementById('vod-upload-filename').textContent = 'Upload failed: ' + (data.error || 'Unknown error');
                            document.getElementById('vod-upload-bar').style.background = 'var(--danger)';
                            document.getElementById('vod-upload-bar').style.width = '100%';
                        }
                    } catch(e) {
                        document.getElementById('vod-upload-filename').textContent = 'Upload failed: Invalid server response';
                        document.getElementById('vod-upload-bar').style.background = 'var(--danger)';
                    }
                    input.value = '';
                });

                xhr.addEventListener('error', function() {
                    document.getElementById('vod-upload-filename').textContent = 'Upload failed: Network error';
                    document.getElementById('vod-upload-bar').style.background = 'var(--danger)';
                    document.getElementById('vod-upload-bar').style.width = '100%';
                    input.value = '';
                });

                xhr.send(formData);
            }

            function vodSubmitTranscode() {
                var serverId = document.getElementById('vod-transcode-server').value;
                var profile = document.getElementById('vod-transcode-profile').value;
                var sourcePath = document.getElementById('vod-source-path').value.trim();
                var movieId = <?= $movie['id'] ?? 0 ?>;
                var movieTitle = <?= json_encode($movie['title'] ?? '') ?>;
                var statusDiv = document.getElementById('vod-transcode-status');
                var btn = document.getElementById('vod-submit-btn');

                if (!sourcePath) { alert('Enter a source file path or URL.'); return; }

                btn.disabled = true;
                btn.innerHTML = '<i class="lucide-loader"></i> Submitting...';
                statusDiv.style.display = 'block';
                statusDiv.style.background = 'rgba(99,102,241,0.1)';
                statusDiv.style.color = 'var(--text-secondary)';
                statusDiv.textContent = 'Submitting transcode job...';

                var sourceType = sourcePath.startsWith('http') ? 'http' : 'file';
                var contentId = 'movie-' + movieId;

                var body = new URLSearchParams({
                    csrf_token: '<?= \CariIPTV\Core\Session::csrf() ?>',
                    server_id: serverId,
                    content_id: contentId,
                    source_path: sourcePath,
                    source_type: sourceType,
                    profile: profile,
                    title: movieTitle,
                    priority: '5'
                });

                fetch('/admin/vod-server/jobs/submit', { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            statusDiv.style.background = 'rgba(34,197,94,0.1)';
                            statusDiv.style.color = 'var(--success)';
                            statusDiv.innerHTML = 'Job submitted successfully! The stream URL will be available once transcoding completes.';

                            // Auto-fill the stream URL with the expected HLS URL
                            var serverOpt = document.getElementById('vod-transcode-server').selectedOptions[0];
                            var serverUrl = serverOpt ? serverOpt.getAttribute('data-url') : '';
                            if (serverUrl) {
                                var hlsUrl = serverUrl + '/content/' + encodeURIComponent(contentId) + '/master.m3u8';
                                document.getElementById('stream_url').value = hlsUrl;
                            }
                        } else {
                            statusDiv.style.background = 'rgba(239,68,68,0.1)';
                            statusDiv.style.color = 'var(--danger)';
                            statusDiv.textContent = 'Failed: ' + (data.error || 'Unknown error');
                        }
                    })
                    .catch(function(err) {
                        statusDiv.style.background = 'rgba(239,68,68,0.1)';
                        statusDiv.style.color = 'var(--danger)';
                        statusDiv.textContent = 'Error: ' + err.message;
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="lucide-play"></i> Submit for Transcoding';
                    });
            }
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
