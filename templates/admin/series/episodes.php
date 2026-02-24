<?php
$pageTitle = $season['name'] . ' - ' . htmlspecialchars($show['title']);
$episodeCount = count($episodes);
?>

<div class="page-header">
    <div class="breadcrumb mb-1">
        <a href="/admin/series">TV Shows</a>
        <span class="breadcrumb-separator">/</span>
        <a href="/admin/series/<?= $show['id'] ?>/edit"><?= htmlspecialchars($show['title']) ?></a>
        <span class="breadcrumb-separator">/</span>
        <a href="/admin/series/<?= $show['id'] ?>/seasons">Seasons</a>
        <span class="breadcrumb-separator">/</span>
        <span><?= htmlspecialchars($season['name']) ?></span>
    </div>
    <h1 class="page-title"><?= htmlspecialchars($season['name']) ?></h1>
</div>

<!-- Season Info Header -->
<div class="card mb-3">
    <div class="card-body">
        <div class="season-info-header">
            <div class="season-poster-thumb">
                <?php if (!empty($season['poster_url'])): ?>
                    <img src="<?= htmlspecialchars($season['poster_url']) ?>" alt="<?= htmlspecialchars($season['name']) ?>">
                <?php else: ?>
                    <div class="season-poster-placeholder">
                        <i class="lucide-image"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="season-meta">
                <h2 class="season-meta-title"><?= htmlspecialchars($season['name']) ?></h2>
                <?php if (!empty($season['overview'])): ?>
                    <p class="season-overview"><?= htmlspecialchars(mb_strimwidth($season['overview'], 0, 300, '...')) ?></p>
                <?php endif; ?>
                <div class="season-stats">
                    <span class="badge badge-primary">
                        <i class="lucide-play-circle"></i>
                        <?= $episodeCount ?> Episode<?= $episodeCount !== 1 ? 's' : '' ?>
                    </span>
                    <?php if (!empty($season['air_date'])): ?>
                        <span class="badge badge-info">
                            <i class="lucide-calendar"></i>
                            <?= date('M j, Y', strtotime($season['air_date'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="season-actions">
                <a href="/admin/series/<?= $show['id'] ?>/seasons" class="btn btn-secondary">
                    <i class="lucide-arrow-left"></i> Back to Seasons
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div class="episodes-layout">
    <!-- LEFT: Episodes Table -->
    <div class="episodes-column">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="lucide-list"></i> Episodes</h3>
                <button type="button" class="btn btn-sm btn-primary" onclick="openAddEpisodeModal()">
                    <i class="lucide-plus"></i> Add Episode
                </button>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (!empty($episodes)): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th width="40">#</th>
                                    <th width="70">Still</th>
                                    <th>Title</th>
                                    <th width="100">Air Date</th>
                                    <th width="80">Runtime</th>
                                    <th width="100">Stream</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="episodesTableBody">
                                <?php foreach ($episodes as $ep): ?>
                                    <tr id="episode-row-<?= $ep['id'] ?>"
                                        data-id="<?= $ep['id'] ?>"
                                        data-episode-number="<?= $ep['episode_number'] ?>"
                                        data-name="<?= htmlspecialchars($ep['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-overview="<?= htmlspecialchars($ep['overview'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-air-date="<?= htmlspecialchars($ep['air_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-runtime="<?= $ep['runtime'] ?? '' ?>"
                                        data-still-url="<?= htmlspecialchars($ep['still_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-stream-url="<?= htmlspecialchars($ep['stream_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-stream-url-backup="<?= htmlspecialchars($ep['stream_url_backup'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-vod-server-id="<?= $ep['vod_server_id'] ?? '' ?>"
                                        data-vod-job-id="<?= $ep['vod_job_id'] ?? '' ?>"
                                        data-vod-content-id="<?= htmlspecialchars($ep['vod_content_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-vod-status="<?= htmlspecialchars($ep['vod_status'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-vod-progress="<?= $ep['vod_progress'] ?? 0 ?>">
                                        <td class="text-center">
                                            <span class="episode-number"><?= $ep['episode_number'] ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($ep['still_url'])): ?>
                                                <img src="<?= htmlspecialchars($ep['still_url']) ?>" alt="" class="episode-still-thumb">
                                            <?php else: ?>
                                                <div class="episode-still-placeholder">
                                                    <i class="lucide-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="episode-title-cell">
                                                <strong><?= htmlspecialchars($ep['name'] ?: 'Episode ' . $ep['episode_number']) ?></strong>
                                            </div>
                                        </td>
                                        <td class="text-sm text-muted">
                                            <?= !empty($ep['air_date']) ? date('M j, Y', strtotime($ep['air_date'])) : '-' ?>
                                        </td>
                                        <td class="text-sm text-muted">
                                            <?= !empty($ep['runtime']) ? $ep['runtime'] . ' min' : '-' ?>
                                        </td>
                                        <td>
                                            <?php
                                                $epVodStatus = $ep['vod_status'] ?? null;
                                                $epVodActive = in_array($epVodStatus, ['pending', 'processing', 'packaging', 'downloading']);
                                            ?>
                                            <?php if ($epVodStatus === 'complete'): ?>
                                                <span class="badge badge-success" title="VOD Ready"><i class="lucide-check-circle"></i> Ready</span>
                                            <?php elseif ($epVodActive): ?>
                                                <span class="badge badge-primary" title="<?= ucfirst($epVodStatus) ?>"><i class="lucide-loader"></i> <?= (int)($ep['vod_progress'] ?? 0) ?>%</span>
                                            <?php elseif ($epVodStatus === 'failed'): ?>
                                                <span class="badge badge-danger" title="Transcode failed"><i class="lucide-alert-circle"></i> Failed</span>
                                            <?php elseif (!empty($ep['stream_url'])): ?>
                                                <span class="badge badge-success">Has URL</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">No URL</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if (!empty($vodServers)): ?>
                                                <button type="button" class="btn btn-sm" style="background:var(--accent,#8b5cf6);color:#fff" onclick="openVodModal(<?= $ep['id'] ?>)" title="VOD & Markers">
                                                    <i class="lucide-hard-drive"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-secondary" onclick="openEditEpisodeModal(<?= $ep['id'] ?>)" title="Edit">
                                                    Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteEpisode(<?= $ep['id'] ?>)" title="Delete">
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state" id="episodesEmptyState">
                        <i class="lucide-film" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-2">No episodes yet. Add episodes manually.</p>
                        <button type="button" class="btn btn-primary btn-sm" style="margin-top: 0.5rem;" onclick="openAddEpisodeModal()">
                            <i class="lucide-plus"></i> Add First Episode
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Season Trailers -->
    <div class="trailers-column">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="lucide-youtube"></i> Season Trailers</h3>
                <button type="button" class="btn btn-sm btn-secondary" onclick="searchTrailersModal()">
                    <i class="lucide-search"></i> Search YouTube
                </button>
            </div>
            <div class="card-body">
                <div id="trailersList">
                    <?php if (!empty($trailers)): ?>
                        <?php foreach ($trailers as $trailer): ?>
                            <div class="trailer-item" data-trailer-id="<?= $trailer['id'] ?>">
                                <div class="trailer-preview" onclick="previewTrailer('<?= htmlspecialchars($trailer['video_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>')" style="cursor: pointer;">
                                    <?php if (!empty($trailer['video_key'])): ?>
                                        <img src="https://img.youtube.com/vi/<?= htmlspecialchars($trailer['video_key']) ?>/mqdefault.jpg" alt="">
                                    <?php else: ?>
                                        <div class="trailer-thumb-placeholder"><i class="lucide-play"></i></div>
                                    <?php endif; ?>
                                    <div class="play-overlay"><i class="lucide-play"></i></div>
                                </div>
                                <div class="trailer-info">
                                    <strong><?= htmlspecialchars($trailer['name'] ?: 'Trailer') ?></strong>
                                    <span class="badge badge-info text-xs"><?= ucfirst(htmlspecialchars($trailer['type'] ?? 'trailer')) ?></span>
                                    <button type="button" class="btn btn-xs btn-secondary" onclick="previewTrailer('<?= htmlspecialchars($trailer['video_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>')">
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
                <div id="noTrailers" class="text-muted text-center" style="padding: 1.5rem; <?= !empty($trailers) ? 'display: none;' : '' ?>">
                    No trailers added yet. Use "Search YouTube" to find trailers.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Episode Modal -->
<div class="modal-overlay" id="addEpisodeModal" style="display: none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>Add Episode</h3>
            <button type="button" class="modal-close" onclick="closeAddEpisodeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addEpisodeForm" onsubmit="event.preventDefault(); submitAddEpisode();">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_episode_number">Episode Number <span class="required">*</span></label>
                        <input type="number" id="add_episode_number" class="form-input" min="1" value="<?= $episodeCount + 1 ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_name">Episode Name</label>
                        <input type="text" id="add_name" class="form-input" placeholder="Episode title">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="add_overview">Overview</label>
                    <textarea id="add_overview" class="form-input" rows="3" placeholder="Episode description"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_air_date">Air Date</label>
                        <input type="date" id="add_air_date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_runtime">Runtime (minutes)</label>
                        <input type="number" id="add_runtime" class="form-input" min="1" placeholder="e.g. 45">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="add_still_url">Still Image URL</label>
                    <input type="text" id="add_still_url" class="form-input" placeholder="https://image.tmdb.org/...">
                </div>
                <div class="form-group">
                    <label class="form-label" for="add_stream_url">Stream URL (Primary)</label>
                    <input type="url" id="add_stream_url" class="form-input" placeholder="https://example.com/episode.m3u8">
                </div>
                <div class="form-group">
                    <label class="form-label" for="add_stream_url_backup">Stream URL (Backup)</label>
                    <input type="url" id="add_stream_url_backup" class="form-input" placeholder="https://backup.example.com/episode.m3u8">
                </div>
                <div class="modal-form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddEpisodeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addEpisodeSubmitBtn">
                        <i class="lucide-plus"></i> Add Episode
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Episode Modal -->
<div class="modal-overlay" id="editEpisodeModal" style="display: none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>Edit Episode</h3>
            <button type="button" class="modal-close" onclick="closeEditEpisodeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editEpisodeForm" onsubmit="event.preventDefault(); submitEditEpisode();">
                <input type="hidden" id="edit_episode_id" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_episode_number">Episode Number <span class="required">*</span></label>
                        <input type="number" id="edit_episode_number" class="form-input" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_name">Episode Name</label>
                        <input type="text" id="edit_name" class="form-input" placeholder="Episode title">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_overview">Overview</label>
                    <textarea id="edit_overview" class="form-input" rows="3" placeholder="Episode description"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_air_date">Air Date</label>
                        <input type="date" id="edit_air_date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_runtime">Runtime (minutes)</label>
                        <input type="number" id="edit_runtime" class="form-input" min="1" placeholder="e.g. 45">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_still_url">Still Image URL</label>
                    <input type="text" id="edit_still_url" class="form-input" placeholder="https://image.tmdb.org/...">
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_stream_url">Stream URL (Primary)</label>
                    <input type="url" id="edit_stream_url" class="form-input" placeholder="https://example.com/episode.m3u8">
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_stream_url_backup">Stream URL (Backup)</label>
                    <input type="url" id="edit_stream_url_backup" class="form-input" placeholder="https://backup.example.com/episode.m3u8">
                </div>
                <div class="modal-form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditEpisodeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editEpisodeSubmitBtn">
                        <i class="lucide-save"></i> Update Episode
                    </button>
                </div>
            </form>
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
                    <input type="text" id="trailerSearchQuery" class="form-input" placeholder="Search for trailers...">
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

<!-- Delete Episode Confirmation Modal -->
<div class="modal-overlay" id="deleteEpisodeModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Episode</h3>
            <button type="button" class="modal-close" onclick="closeDeleteEpisodeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete "<strong id="deleteEpisodeName"></strong>"?</p>
            <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteEpisodeModal()">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteEpisodeBtn" onclick="confirmDeleteEpisode()">
                <i class="lucide-trash-2"></i> Delete
            </button>
        </div>
    </div>
</div>

<?php if (!empty($vodServers)): ?>
<!-- VOD & Markers Modal -->
<div class="modal-overlay" id="vodModal" style="display: none;">
    <div class="modal-content" style="max-width: 960px;">
        <div class="modal-header">
            <h3 id="vodModalTitle"><i class="lucide-hard-drive"></i> VOD & Markers</h3>
            <button type="button" class="modal-close" onclick="closeVodModal()">&times;</button>
        </div>
        <div class="modal-body" style="overflow-y: auto; max-height: 80vh;">
            <input type="hidden" id="vod_episode_id" value="">

            <!-- VOD Transcode Section -->
            <div class="card mb-3">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                    <h4 class="card-title" style="margin:0;font-size:0.9375rem"><i class="lucide-hard-drive"></i> Transcode</h4>
                    <div id="vodStatusBadge"></div>
                </div>
                <div class="card-body">
                    <!-- Progress bar (hidden until active) -->
                    <div id="ep-vod-progress" style="display:none;margin-bottom:1rem">
                        <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
                            <span style="font-size:0.85rem;color:var(--text-secondary)" id="ep-vod-label">Transcoding...</span>
                            <span style="font-size:0.85rem;font-family:var(--font-mono);color:var(--text-muted)" id="ep-vod-pct">0%</span>
                        </div>
                        <div style="height:8px;background:var(--bg-hover);border-radius:4px;overflow:hidden">
                            <div id="ep-vod-bar" style="height:100%;width:0%;background:var(--accent, #8b5cf6);border-radius:4px;transition:width 0.3s"></div>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem" id="ep-vod-step"></div>
                    </div>
                    <div id="ep-vod-msg" style="display:none;padding:0.75rem;border-radius:8px;font-size:0.85rem;margin-bottom:1rem"></div>

                    <!-- Upload controls -->
                    <div id="ep-vod-upload-controls">
                        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                            <div class="form-group">
                                <label class="form-label">VOD Server</label>
                                <select class="form-input" id="ep-vod-server">
                                    <?php foreach ($vodServers as $vs): ?>
                                        <option value="<?= $vs['id'] ?>" data-url="<?= htmlspecialchars($vs['url']) ?>"
                                            <?= $vs['is_default'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vs['name']) ?><?= $vs['is_default'] ? ' (default)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Transcode Profile</label>
                                <select class="form-input" id="ep-vod-profile">
                                    <option value="standard">Loading profiles...</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Source File</label>
                            <div style="display:flex;gap:0.5rem">
                                <input type="text" class="form-input" id="ep-vod-source-path" placeholder="No file selected" style="flex:1" readonly>
                                <input type="file" id="ep-vod-file-input" accept="video/*,.mkv,.avi,.ts,.m2ts" style="display:none">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('ep-vod-file-input').click()">
                                    <i class="lucide-upload"></i> Choose File
                                </button>
                                <button type="button" class="btn btn-primary" id="ep-vod-submit-btn" onclick="epVodSubmit()" disabled>
                                    <i class="lucide-send"></i> Upload & Transcode
                                </button>
                            </div>
                            <div id="ep-vod-upload-progress" style="display:none;margin-top:0.5rem">
                                <div style="height:4px;background:var(--bg-hover);border-radius:2px;overflow:hidden">
                                    <div id="ep-vod-upload-bar" style="height:100%;width:0%;background:var(--primary);transition:width 0.3s"></div>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem" id="ep-vod-upload-text">Uploading...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Remove VOD button -->
                    <div id="ep-vod-remove-wrap" style="display:none;margin-top:0.5rem">
                        <button type="button" class="btn btn-sm" style="background:transparent;color:var(--danger);border:1px solid var(--danger);font-size:0.75rem" onclick="epVodDelete()">
                            <i class="lucide-trash-2"></i> Remove VOD
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview Player + Markers Section -->
            <div class="card mb-3" id="markerSection" style="display:none">
                <div class="card-header">
                    <h4 class="card-title" style="margin:0;font-size:0.9375rem"><i class="lucide-scissors"></i> Preview & Markers</h4>
                </div>
                <div class="card-body">
                    <!-- Video Preview -->
                    <div style="position:relative;background:#000;border-radius:8px;overflow:hidden;margin-bottom:1rem">
                        <video id="markerVideo" style="width:100%;max-height:360px;display:block" controls preload="metadata"></video>
                    </div>

                    <!-- Current time + marker buttons -->
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem">
                        <span style="font-family:var(--font-mono);font-size:0.9375rem;color:var(--text-secondary);min-width:70px" id="markerCurrentTime">0:00.000</span>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="setMarker('intro_start')" title="Mark where the intro/recap begins">
                            <i class="lucide-skip-forward"></i> Intro Start
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="setMarker('intro_end')" title="Mark where the intro ends (skip-to point)">
                            <i class="lucide-fast-forward"></i> Intro End
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="setMarker('credits_start')" title="Mark where end credits begin">
                            <i class="lucide-list-end"></i> Credits Start
                        </button>
                        <button type="button" class="btn btn-sm" style="background:var(--warning);color:#000" onclick="setMarker('ad_cue')" title="Add an ad insertion cue point">
                            <i class="lucide-megaphone"></i> Ad Cue
                        </button>
                    </div>

                    <!-- Timeline with markers -->
                    <div style="position:relative;height:24px;background:var(--bg-hover);border-radius:4px;margin-bottom:1rem;cursor:pointer" id="markerTimeline" onclick="seekFromTimeline(event)">
                        <div id="markerTimelineProgress" style="position:absolute;top:0;left:0;height:100%;background:rgba(99,102,241,0.3);border-radius:4px;pointer-events:none"></div>
                        <div id="markerFlags"></div>
                    </div>

                    <!-- Markers list -->
                    <div id="markersList"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Season Info Header */
.season-info-header {
    display: flex;
    gap: 1.25rem;
    align-items: flex-start;
}

.season-poster-thumb {
    width: 80px;
    height: 120px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--bg-hover);
}

.season-poster-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.season-poster-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-size: 1.5rem;
}

.season-meta {
    flex: 1;
}

.season-meta-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.season-overview {
    color: var(--text-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
    margin-bottom: 0.75rem;
}

.season-stats {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.season-stats .badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.season-actions {
    flex-shrink: 0;
}

/* Two Column Layout */
.episodes-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 1200px) {
    .episodes-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .season-info-header {
        flex-direction: column;
        align-items: stretch;
    }

    .season-poster-thumb {
        width: 60px;
        height: 90px;
    }

    .season-actions {
        align-self: flex-start;
    }
}

/* Episodes Table */
.episode-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: var(--bg-hover);
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.episode-still-thumb {
    width: 60px;
    height: 34px;
    object-fit: cover;
    border-radius: 4px;
    background: var(--bg-hover);
}

.episode-still-placeholder {
    width: 60px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-hover);
    border-radius: 4px;
    color: var(--text-muted);
    font-size: 0.75rem;
}

.episode-title-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.25rem;
}

.action-buttons .btn {
    white-space: nowrap;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

/* Trailer Items */
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
    position: relative;
    flex-shrink: 0;
}

.trailer-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.trailer-thumb-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-size: 1.5rem;
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

.trailer-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.trailer-info strong {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Modals */
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

.modal-lg {
    max-width: 800px;
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

.modal-body {
    padding: 1.5rem;
    max-height: 70vh;
    overflow-y: auto;
}

.modal-footer {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
}

.modal-form-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
    margin-top: 1rem;
}

/* Form Layout */
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

/* Search Results */
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
    width: 120px;
    height: auto;
    aspect-ratio: 16/9;
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

/* Video Container */
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

/* Button Extras */
.btn-xs {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

/* Marker flags on timeline */
.marker-flag {
    position: absolute;
    top: 0;
    width: 3px;
    height: 100%;
    border-radius: 1px;
    cursor: pointer;
    z-index: 2;
    transition: transform 0.1s;
}
.marker-flag:hover { transform: scaleX(2); }
.marker-flag[data-type="intro_start"] { background: #22c55e; }
.marker-flag[data-type="intro_end"] { background: #3b82f6; }
.marker-flag[data-type="credits_start"] { background: #f59e0b; }
.marker-flag[data-type="ad_cue"] { background: #ef4444; }

/* Marker list items */
.marker-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.85rem;
}
.marker-item:last-child { border-bottom: none; }
.marker-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.marker-dot[data-type="intro_start"] { background: #22c55e; }
.marker-dot[data-type="intro_end"] { background: #3b82f6; }
.marker-dot[data-type="credits_start"] { background: #f59e0b; }
.marker-dot[data-type="ad_cue"] { background: #ef4444; }
.marker-time {
    font-family: var(--font-mono);
    color: var(--text-secondary);
    cursor: pointer;
    min-width: 70px;
}
.marker-time:hover { color: var(--primary); }
.marker-label { flex: 1; }
.marker-type-badge {
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
    border-radius: 3px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-weight: 600;
}
.marker-type-badge[data-type="intro_start"] { background: rgba(34,197,94,0.15); color: #22c55e; }
.marker-type-badge[data-type="intro_end"] { background: rgba(59,130,246,0.15); color: #3b82f6; }
.marker-type-badge[data-type="credits_start"] { background: rgba(245,158,11,0.15); color: #f59e0b; }
.marker-type-badge[data-type="ad_cue"] { background: rgba(239,68,68,0.15); color: #ef4444; }

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #16a34a;
}

/* Loading */
.loading-spinner {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

/* Utilities */
.text-center {
    text-align: center;
}

.text-xs {
    font-size: 0.75rem;
}

.mt-2 {
    margin-top: 1rem;
}

.text-muted {
    color: var(--text-muted);
}
</style>

<script>
const csrfToken = '<?= $csrf ?>';
const showId = <?= $show['id'] ?>;
const seasonId = <?= $season['id'] ?>;

let pendingDeleteEpisodeId = null;

// ========================================================================
// ADD EPISODE
// ========================================================================

function openAddEpisodeModal() {
    document.getElementById('addEpisodeForm').reset();
    // Set default episode number to next available
    const rows = document.querySelectorAll('#episodesTableBody tr');
    let maxNum = 0;
    rows.forEach(row => {
        const num = parseInt(row.dataset.episodeNumber) || 0;
        if (num > maxNum) maxNum = num;
    });
    document.getElementById('add_episode_number').value = maxNum + 1;
    document.getElementById('addEpisodeModal').style.display = 'flex';
}

function closeAddEpisodeModal() {
    document.getElementById('addEpisodeModal').style.display = 'none';
}

function submitAddEpisode() {
    const btn = document.getElementById('addEpisodeSubmitBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="lucide-loader"></i> Adding...';
    btn.disabled = true;

    const body = new URLSearchParams();
    body.append('_token', csrfToken);
    body.append('episode_number', document.getElementById('add_episode_number').value);
    body.append('name', document.getElementById('add_name').value);
    body.append('overview', document.getElementById('add_overview').value);
    body.append('air_date', document.getElementById('add_air_date').value);
    body.append('runtime', document.getElementById('add_runtime').value);
    body.append('still_url', document.getElementById('add_still_url').value);
    body.append('stream_url', document.getElementById('add_stream_url').value);
    body.append('stream_url_backup', document.getElementById('add_stream_url_backup').value);

    fetch(`/admin/series/${showId}/seasons/${seasonId}/episodes/add`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;

        if (data.success) {
            closeAddEpisodeModal();
            // Reload the page to show the new episode
            window.location.reload();
        } else {
            alert(data.message || 'Failed to add episode');
        }
    })
    .catch(err => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Error adding episode: ' + err.message);
    });
}

// ========================================================================
// EDIT EPISODE
// ========================================================================

function openEditEpisodeModal(episodeId) {
    const row = document.getElementById('episode-row-' + episodeId);
    if (!row) return;

    document.getElementById('edit_episode_id').value = episodeId;
    document.getElementById('edit_episode_number').value = row.dataset.episodeNumber || '';
    document.getElementById('edit_name').value = row.dataset.name || '';
    document.getElementById('edit_overview').value = row.dataset.overview || '';
    document.getElementById('edit_air_date').value = row.dataset.airDate || '';
    document.getElementById('edit_runtime').value = row.dataset.runtime || '';
    document.getElementById('edit_still_url').value = row.dataset.stillUrl || '';
    document.getElementById('edit_stream_url').value = row.dataset.streamUrl || '';
    document.getElementById('edit_stream_url_backup').value = row.dataset.streamUrlBackup || '';

    document.getElementById('editEpisodeModal').style.display = 'flex';
}

function closeEditEpisodeModal() {
    document.getElementById('editEpisodeModal').style.display = 'none';
}

function submitEditEpisode() {
    const episodeId = document.getElementById('edit_episode_id').value;
    const btn = document.getElementById('editEpisodeSubmitBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="lucide-loader"></i> Saving...';
    btn.disabled = true;

    const body = new URLSearchParams();
    body.append('_token', csrfToken);
    body.append('episode_number', document.getElementById('edit_episode_number').value);
    body.append('name', document.getElementById('edit_name').value);
    body.append('overview', document.getElementById('edit_overview').value);
    body.append('air_date', document.getElementById('edit_air_date').value);
    body.append('runtime', document.getElementById('edit_runtime').value);
    body.append('still_url', document.getElementById('edit_still_url').value);
    body.append('stream_url', document.getElementById('edit_stream_url').value);
    body.append('stream_url_backup', document.getElementById('edit_stream_url_backup').value);

    fetch(`/admin/series/${showId}/seasons/${seasonId}/episodes/${episodeId}/update`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;

        if (data.success) {
            closeEditEpisodeModal();
            // Reload the page to show updated data
            window.location.reload();
        } else {
            alert(data.message || 'Failed to update episode');
        }
    })
    .catch(err => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Error updating episode: ' + err.message);
    });
}

// ========================================================================
// DELETE EPISODE
// ========================================================================

function deleteEpisode(episodeId) {
    const row = document.getElementById('episode-row-' + episodeId);
    const name = row ? (row.dataset.name || 'Episode ' + row.dataset.episodeNumber) : 'this episode';

    pendingDeleteEpisodeId = episodeId;
    document.getElementById('deleteEpisodeName').textContent = name;
    document.getElementById('deleteEpisodeModal').style.display = 'flex';
}

function closeDeleteEpisodeModal() {
    document.getElementById('deleteEpisodeModal').style.display = 'none';
    pendingDeleteEpisodeId = null;
}

function confirmDeleteEpisode() {
    if (!pendingDeleteEpisodeId) return;

    const episodeId = pendingDeleteEpisodeId;
    const btn = document.getElementById('confirmDeleteEpisodeBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="lucide-loader"></i> Deleting...';
    btn.disabled = true;

    fetch(`/admin/series/${showId}/seasons/${seasonId}/episodes/${episodeId}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;

        if (data.success) {
            closeDeleteEpisodeModal();
            const row = document.getElementById('episode-row-' + episodeId);
            if (row) {
                row.remove();
            }
            // Check if table is now empty
            const tbody = document.getElementById('episodesTableBody');
            if (tbody && tbody.children.length === 0) {
                window.location.reload();
            }
        } else {
            alert(data.message || 'Failed to delete episode');
        }
    })
    .catch(err => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Error deleting episode: ' + err.message);
    });
}

// ========================================================================
// TRAILERS - YOUTUBE SEARCH
// ========================================================================

function searchTrailersModal() {
    const showTitle = <?= json_encode($show['title']) ?>;
    const seasonName = <?= json_encode($season['name']) ?>;
    document.getElementById('trailerSearchQuery').value = showTitle + ' ' + seasonName + ' trailer';
    document.getElementById('trailerSearchResults').innerHTML = '';
    document.getElementById('trailersModal').style.display = 'flex';
}

function closeTrailersModal() {
    document.getElementById('trailersModal').style.display = 'none';
}

function searchTrailers() {
    const query = document.getElementById('trailerSearchQuery').value.trim();
    if (!query) return;

    const resultsDiv = document.getElementById('trailerSearchResults');
    resultsDiv.innerHTML = '<div class="loading-spinner">Searching YouTube...</div>';

    fetch('/admin/series/search-trailers', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_token=${csrfToken}&title=${encodeURIComponent(query)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.results && data.results.length > 0) {
            resultsDiv.innerHTML = data.results.map(video => `
                <div class="search-result-item" onclick="addYoutubeTrailer('${escapeAttr(video.video_id)}', '${escapeAttr(video.title)}', '${escapeAttr(video.url)}')">
                    <img src="${escapeAttr(video.thumbnail)}" alt="" class="search-result-poster">
                    <div class="search-result-info">
                        <h4>${escapeHtml(video.title)}</h4>
                        <p>${escapeHtml(video.channel || '')}</p>
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
    closeTrailersModal();

    const body = new URLSearchParams();
    body.append('_token', csrfToken);
    body.append('name', title);
    body.append('type', 'trailer');
    body.append('url', url);
    body.append('video_key', videoId);
    body.append('source', 'youtube');

    fetch(`/admin/series/${showId}/seasons/${seasonId}/trailers/add`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            addTrailerToList({
                id: data.trailer_id,
                key: videoId,
                name: title,
                type: 'trailer'
            });
        } else {
            alert(data.message || 'Failed to add trailer');
        }
    })
    .catch(err => {
        alert('Error adding trailer: ' + err.message);
    });
}

function addTrailerToList(trailer) {
    const list = document.getElementById('trailersList');
    document.getElementById('noTrailers').style.display = 'none';

    const div = document.createElement('div');
    div.className = 'trailer-item';
    div.dataset.trailerId = trailer.id || '';
    div.innerHTML = `
        <div class="trailer-preview" onclick="previewTrailer('${escapeAttr(trailer.key)}')" style="cursor: pointer;">
            <img src="https://img.youtube.com/vi/${escapeAttr(trailer.key)}/mqdefault.jpg" alt="">
            <div class="play-overlay"><i class="lucide-play"></i></div>
        </div>
        <div class="trailer-info">
            <strong>${escapeHtml(trailer.name)}</strong>
            <span class="badge badge-info text-xs">${escapeHtml(trailer.type || 'Trailer')}</span>
            <button type="button" class="btn btn-xs btn-secondary" onclick="previewTrailer('${escapeAttr(trailer.key)}')">
                <i class="lucide-play"></i> Preview
            </button>
        </div>
        <button type="button" class="btn btn-sm btn-danger" onclick="removeTrailer(${trailer.id})">
            <i class="lucide-trash-2"></i>
        </button>
    `;
    list.appendChild(div);
}

// ========================================================================
// TRAILERS - REMOVE & PREVIEW
// ========================================================================

function removeTrailer(trailerId) {
    if (!confirm('Remove this trailer?')) return;

    fetch(`/admin/series/${showId}/seasons/${seasonId}/trailers/${trailerId}/remove`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const item = document.querySelector(`.trailer-item[data-trailer-id="${trailerId}"]`);
            if (item) {
                item.remove();
            }
            checkNoTrailers();
        } else {
            alert(data.message || 'Failed to remove trailer');
        }
    })
    .catch(err => {
        alert('Error removing trailer: ' + err.message);
    });
}

function checkNoTrailers() {
    const list = document.getElementById('trailersList');
    const noTrailers = document.getElementById('noTrailers');
    if (list.children.length === 0) {
        noTrailers.style.display = 'block';
    }
}

function previewTrailer(videoKey) {
    if (!videoKey) return;
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

// ========================================================================
// UTILITIES
// ========================================================================

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeAttr(text) {
    if (!text) return '';
    return text.replace(/&/g, '&amp;')
               .replace(/'/g, '&#39;')
               .replace(/"/g, '&quot;')
               .replace(/</g, '&lt;')
               .replace(/>/g, '&gt;');
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
            pendingDeleteEpisodeId = null;
        }
    });
});

// Handle Enter key on trailer search
document.getElementById('trailerSearchQuery').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchTrailers();
    }
});

// ========================================================================
// VOD & MARKERS MODAL
// ========================================================================

let vodEpisodeId = 0;
let vodPollTimer = null;
let currentMarkers = [];

function openVodModal(episodeId) {
    vodEpisodeId = episodeId;
    const row = document.getElementById('episode-row-' + episodeId);
    if (!row) return;

    document.getElementById('vod_episode_id').value = episodeId;
    const epName = row.dataset.name || ('Episode ' + row.dataset.episodeNumber);
    document.getElementById('vodModalTitle').innerHTML = '<i class="lucide-hard-drive"></i> ' + escapeHtml(epName) + ' — VOD & Markers';

    // Read VOD state from row data
    const vodStatus = row.dataset.vodStatus || '';
    const vodServerId = row.dataset.vodServerId || '';
    const vodJobId = row.dataset.vodJobId || '';
    const vodContentId = row.dataset.vodContentId || '';
    const vodProgress = parseFloat(row.dataset.vodProgress) || 0;
    const streamUrl = row.dataset.streamUrl || '';

    // Reset UI
    const progressEl = document.getElementById('ep-vod-progress');
    const uploadEl = document.getElementById('ep-vod-upload-controls');
    const removeEl = document.getElementById('ep-vod-remove-wrap');
    const msgEl = document.getElementById('ep-vod-msg');
    const badgeEl = document.getElementById('vodStatusBadge');
    msgEl.style.display = 'none';

    const isActive = ['pending', 'processing', 'packaging', 'downloading'].includes(vodStatus);

    if (vodStatus === 'complete') {
        badgeEl.innerHTML = '<span class="badge badge-success"><i class="lucide-check-circle"></i> Ready</span>';
        progressEl.style.display = 'none';
        uploadEl.style.display = '';
        removeEl.style.display = '';
    } else if (isActive) {
        badgeEl.innerHTML = '<span class="badge badge-primary"><i class="lucide-loader"></i> Processing</span>';
        progressEl.style.display = '';
        uploadEl.style.display = 'none';
        removeEl.style.display = 'none';
        document.getElementById('ep-vod-bar').style.width = vodProgress + '%';
        document.getElementById('ep-vod-pct').textContent = vodProgress.toFixed(1) + '%';
        // Start polling
        startVodPoll(vodServerId, vodJobId, episodeId);
    } else if (vodStatus === 'failed') {
        badgeEl.innerHTML = '<span class="badge badge-danger"><i class="lucide-alert-circle"></i> Failed</span>';
        progressEl.style.display = 'none';
        uploadEl.style.display = '';
        removeEl.style.display = vodServerId ? '' : 'none';
    } else {
        badgeEl.innerHTML = '';
        progressEl.style.display = 'none';
        uploadEl.style.display = '';
        removeEl.style.display = 'none';
    }

    // Show preview player if stream URL exists
    const markerSection = document.getElementById('markerSection');
    const video = document.getElementById('markerVideo');
    if (streamUrl) {
        markerSection.style.display = '';
        // Use shaka or native for HLS
        if (streamUrl.endsWith('.m3u8') && window.Hls) {
            if (video._hls) { video._hls.destroy(); }
            const hls = new Hls();
            hls.loadSource(streamUrl);
            hls.attachMedia(video);
            video._hls = hls;
        } else {
            video.src = streamUrl;
        }
        loadMarkers(episodeId);
    } else {
        markerSection.style.display = 'none';
        video.src = '';
    }

    // Time display
    video.ontimeupdate = function() {
        document.getElementById('markerCurrentTime').textContent = formatTimePrecise(video.currentTime);
        const dur = video.duration || 1;
        document.getElementById('markerTimelineProgress').style.width = ((video.currentTime / dur) * 100) + '%';
    };

    // Load profiles for the selected server
    loadEpVodProfiles();

    document.getElementById('vodModal').style.display = 'flex';
}

function closeVodModal() {
    document.getElementById('vodModal').style.display = 'none';
    stopVodPoll();
    const video = document.getElementById('markerVideo');
    if (video) { video.pause(); if (video._hls) { video._hls.destroy(); video._hls = null; } video.src = ''; }
}

// ---- VOD Profiles ----

function loadEpVodProfiles() {
    const serverId = document.getElementById('ep-vod-server').value;
    fetch('/admin/vod-server/profiles?server_id=' + serverId)
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('ep-vod-profile');
            sel.innerHTML = '';
            const profiles = data.profiles || data.data?.profiles || [];
            if (profiles.length === 0) {
                sel.innerHTML = '<option value="standard">standard</option>';
                return;
            }
            profiles.forEach(p => {
                const name = p.name || p;
                sel.innerHTML += '<option value="' + escapeAttr(name) + '">' + escapeHtml(name) + '</option>';
            });
        })
        .catch(() => {});
}

document.getElementById('ep-vod-server')?.addEventListener('change', loadEpVodProfiles);

// ---- VOD File Upload ----

document.getElementById('ep-vod-file-input')?.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        document.getElementById('ep-vod-source-path').value = file.name;
        document.getElementById('ep-vod-submit-btn').disabled = false;
    }
});

function epVodSubmit() {
    const file = document.getElementById('ep-vod-file-input').files[0];
    if (!file) return;

    const episodeId = vodEpisodeId;
    const row = document.getElementById('episode-row-' + episodeId);
    const epNum = row?.dataset.episodeNumber || episodeId;
    const contentId = 'episode-' + episodeId;
    const serverId = document.getElementById('ep-vod-server').value;
    const profile = document.getElementById('ep-vod-profile').value;
    const title = '<?= htmlspecialchars($show['title'], ENT_QUOTES, 'UTF-8') ?> S<?= $season['season_number'] ?? '' ?>E' + epNum;

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('server_id', serverId);
    formData.append('content_id', contentId);
    formData.append('entity_type', 'episode');
    formData.append('entity_id', episodeId);
    formData.append('title', title);
    formData.append('profile', profile);
    formData.append('video_file', file);

    // Show upload progress
    const btn = document.getElementById('ep-vod-submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="lucide-loader"></i> Uploading...';
    document.getElementById('ep-vod-upload-progress').style.display = '';

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/admin/vod-server/upload-source');
    xhr.setRequestHeader('Accept', 'application/json');

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            document.getElementById('ep-vod-upload-bar').style.width = pct + '%';
            document.getElementById('ep-vod-upload-text').textContent = 'Uploading... ' + pct + '%';
        }
    };

    xhr.onload = function() {
        document.getElementById('ep-vod-upload-progress').style.display = 'none';
        btn.innerHTML = '<i class="lucide-send"></i> Upload & Transcode';
        btn.disabled = false;

        // Check for empty/failed responses (file too large, Nginx 413, etc.)
        if (xhr.status === 0 || xhr.responseText === '') {
            showVodMsg('Upload failed: File too large. Check PHP upload_max_filesize and Nginx client_max_body_size.', 'danger');
            return;
        }

        try {
            const data = JSON.parse(xhr.responseText);
            if (data.success) {
                const jobId = data.job_id || data.job?.id || 0;
                // Update row data
                if (row) {
                    row.dataset.vodServerId = serverId;
                    row.dataset.vodJobId = jobId;
                    row.dataset.vodContentId = contentId;
                    row.dataset.vodStatus = 'pending';
                    row.dataset.vodProgress = '0';
                }
                // Show progress UI
                document.getElementById('ep-vod-progress').style.display = '';
                document.getElementById('ep-vod-upload-controls').style.display = 'none';
                document.getElementById('vodStatusBadge').innerHTML = '<span class="badge badge-primary"><i class="lucide-loader"></i> Processing</span>';
                startVodPoll(serverId, jobId, episodeId);
            } else {
                showVodMsg(data.error || 'Upload failed', 'danger');
            }
        } catch (e) {
            // Response is not JSON — likely an HTML error page from Nginx or session timeout
            let msg = 'Invalid server response (HTTP ' + xhr.status + ')';
            if (xhr.responseText.indexOf('413') !== -1 || xhr.responseText.indexOf('Too Large') !== -1) {
                msg = 'File too large. Increase upload_max_filesize in PHP and client_max_body_size in Nginx.';
            } else if (xhr.status === 302 || xhr.responseText.indexOf('login') !== -1) {
                msg = 'Session expired. Please refresh the page and try again.';
            }
            showVodMsg('Upload failed: ' + msg, 'danger');
        }
    };

    xhr.onerror = function() {
        document.getElementById('ep-vod-upload-progress').style.display = 'none';
        btn.innerHTML = '<i class="lucide-send"></i> Upload & Transcode';
        btn.disabled = false;
        showVodMsg('Upload failed: Network error or file too large', 'danger');
    };

    xhr.send(formData);
}

function showVodMsg(msg, type) {
    const el = document.getElementById('ep-vod-msg');
    el.style.display = '';
    el.style.background = type === 'danger' ? 'rgba(239,68,68,0.1)' : 'rgba(34,197,94,0.1)';
    el.style.color = type === 'danger' ? 'var(--danger)' : 'var(--success)';
    el.innerHTML = '<i class="lucide-' + (type === 'danger' ? 'alert-circle' : 'check-circle') + '"></i> ' + escapeHtml(msg);
}

// ---- VOD Status Polling ----

function startVodPoll(serverId, jobId, episodeId) {
    stopVodPoll();
    pollVodStatus(serverId, jobId, episodeId);
    vodPollTimer = setInterval(() => pollVodStatus(serverId, jobId, episodeId), 3000);
}

function stopVodPoll() {
    if (vodPollTimer) { clearInterval(vodPollTimer); vodPollTimer = null; }
}

function pollVodStatus(serverId, jobId, episodeId) {
    fetch(`/admin/vod-server/job-status?server_id=${serverId}&job_id=${jobId}&entity_type=episode&entity_id=${episodeId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const job = data.job || {};
            const status = job.status || '';
            const progress = parseFloat(job.progress) || 0;
            const row = document.getElementById('episode-row-' + episodeId);

            document.getElementById('ep-vod-bar').style.width = progress + '%';
            document.getElementById('ep-vod-pct').textContent = progress.toFixed(1) + '%';

            const stepLabel = status === 'packaging' ? 'Packaging for streaming...'
                : status === 'downloading' ? 'Downloading source...'
                : status === 'processing' ? 'Transcoding...'
                : status === 'pending' ? 'Waiting in queue...'
                : status;
            document.getElementById('ep-vod-step').textContent = stepLabel;

            if (row) {
                row.dataset.vodStatus = status;
                row.dataset.vodProgress = progress;
            }

            if (status === 'complete') {
                stopVodPoll();
                document.getElementById('vodStatusBadge').innerHTML = '<span class="badge badge-success"><i class="lucide-check-circle"></i> Ready</span>';
                document.getElementById('ep-vod-progress').style.display = 'none';
                document.getElementById('ep-vod-upload-controls').style.display = '';
                document.getElementById('ep-vod-remove-wrap').style.display = '';
                showVodMsg('Transcode complete! Stream URL has been set.', 'success');
                if (row && job.stream_url) {
                    row.dataset.streamUrl = job.stream_url;
                    // Update status badge in the table
                    const streamTd = row.children[5];
                    if (streamTd) streamTd.innerHTML = '<span class="badge badge-success" title="VOD Ready"><i class="lucide-check-circle"></i> Ready</span>';
                }
                // Show the preview player now
                if (job.stream_url) {
                    document.getElementById('markerSection').style.display = '';
                    const video = document.getElementById('markerVideo');
                    if (job.stream_url.endsWith('.m3u8') && window.Hls) {
                        if (video._hls) video._hls.destroy();
                        const hls = new Hls();
                        hls.loadSource(job.stream_url);
                        hls.attachMedia(video);
                        video._hls = hls;
                    } else {
                        video.src = job.stream_url;
                    }
                    loadMarkers(episodeId);
                }
            } else if (status === 'failed') {
                stopVodPoll();
                document.getElementById('vodStatusBadge').innerHTML = '<span class="badge badge-danger"><i class="lucide-alert-circle"></i> Failed</span>';
                document.getElementById('ep-vod-progress').style.display = 'none';
                document.getElementById('ep-vod-upload-controls').style.display = '';
                showVodMsg(job.error_msg || 'Transcode failed', 'danger');
                if (row) {
                    const streamTd = row.children[5];
                    if (streamTd) streamTd.innerHTML = '<span class="badge badge-danger" title="Failed"><i class="lucide-alert-circle"></i> Failed</span>';
                }
            } else if (status === 'cancelled') {
                stopVodPoll();
                document.getElementById('ep-vod-progress').style.display = 'none';
                document.getElementById('ep-vod-upload-controls').style.display = '';
                showVodMsg('Transcode was cancelled', 'danger');
            }
        })
        .catch(() => {});
}

// ---- VOD Delete ----

function epVodDelete() {
    if (!confirm('Remove transcoded content from VOD server and clear episode VOD data?')) return;
    const row = document.getElementById('episode-row-' + vodEpisodeId);
    const body = new URLSearchParams();
    body.append('csrf_token', csrfToken);
    body.append('entity_type', 'episode');
    body.append('entity_id', vodEpisodeId);
    body.append('server_id', row?.dataset.vodServerId || '');
    body.append('content_id', row?.dataset.vodContentId || '');
    body.append('job_id', row?.dataset.vodJobId || '');

    fetch('/admin/vod-server/movie-vod-delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (row) {
                row.dataset.vodServerId = '';
                row.dataset.vodJobId = '';
                row.dataset.vodContentId = '';
                row.dataset.vodStatus = '';
                row.dataset.vodProgress = '0';
                row.dataset.streamUrl = '';
                const streamTd = row.children[5];
                if (streamTd) streamTd.innerHTML = '<span class="badge badge-danger">No URL</span>';
            }
            document.getElementById('vodStatusBadge').innerHTML = '';
            document.getElementById('ep-vod-remove-wrap').style.display = 'none';
            document.getElementById('markerSection').style.display = 'none';
            showVodMsg('VOD data cleared', 'success');
        } else {
            showVodMsg(data.error || 'Delete failed', 'danger');
        }
    })
    .catch(err => showVodMsg('Error: ' + err.message, 'danger'));
}

// ========================================================================
// MARKERS (Preview Player)
// ========================================================================

function loadMarkers(episodeId) {
    fetch('/admin/vod-server/markers?content_type=episode&content_id=' + episodeId)
        .then(r => r.json())
        .then(data => {
            currentMarkers = data.markers || [];
            renderMarkers();
        })
        .catch(() => {});
}

function renderMarkers() {
    const list = document.getElementById('markersList');
    const flags = document.getElementById('markerFlags');
    const video = document.getElementById('markerVideo');
    const duration = video.duration || 0;

    // Render timeline flags
    flags.innerHTML = '';
    currentMarkers.forEach(m => {
        if (duration > 0) {
            const pct = (parseFloat(m.position_seconds) / duration) * 100;
            const flag = document.createElement('div');
            flag.className = 'marker-flag';
            flag.dataset.type = m.marker_type;
            flag.style.left = pct + '%';
            flag.title = markerTypeLabel(m.marker_type) + ' @ ' + formatTimePrecise(m.position_seconds);
            flag.onclick = function(e) { e.stopPropagation(); video.currentTime = parseFloat(m.position_seconds); };
            flags.appendChild(flag);
        }
    });

    // Render list
    if (currentMarkers.length === 0) {
        list.innerHTML = '<div style="text-align:center;padding:1rem;color:var(--text-muted);font-size:0.85rem">No markers set. Use the buttons above while playing to add markers.</div>';
        return;
    }

    list.innerHTML = '';
    currentMarkers.forEach(m => {
        const item = document.createElement('div');
        item.className = 'marker-item';
        item.innerHTML = `
            <span class="marker-dot" data-type="${escapeAttr(m.marker_type)}"></span>
            <span class="marker-time" onclick="document.getElementById('markerVideo').currentTime=${parseFloat(m.position_seconds)}">${formatTimePrecise(m.position_seconds)}</span>
            <span class="marker-type-badge" data-type="${escapeAttr(m.marker_type)}">${escapeHtml(markerTypeLabel(m.marker_type))}</span>
            <span class="marker-label">${escapeHtml(m.label || '')}</span>
            <button type="button" class="btn btn-xs btn-danger" onclick="deleteMarker(${m.id})" title="Remove"><i class="lucide-x"></i></button>
        `;
        list.appendChild(item);
    });
}

function markerTypeLabel(type) {
    const labels = { 'intro_start': 'Intro Start', 'intro_end': 'Intro End', 'credits_start': 'Credits Start', 'ad_cue': 'Ad Cue' };
    return labels[type] || type;
}

function setMarker(markerType) {
    const video = document.getElementById('markerVideo');
    const position = video.currentTime;
    const label = markerType === 'ad_cue' ? prompt('Optional label for this ad cue point:', '') : '';

    // Cancel if user pressed Cancel on the prompt for ad_cue (label === null)
    if (markerType === 'ad_cue' && label === null) return;

    const body = new URLSearchParams();
    body.append('csrf_token', csrfToken);
    body.append('content_type', 'episode');
    body.append('content_id', vodEpisodeId);
    body.append('marker_type', markerType);
    body.append('position_seconds', position.toFixed(3));
    if (label) body.append('label', label);

    fetch('/admin/vod-server/markers/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadMarkers(vodEpisodeId);
        } else {
            alert(data.error || 'Failed to save marker');
        }
    })
    .catch(err => alert('Error: ' + err.message));
}

function deleteMarker(markerId) {
    const body = new URLSearchParams();
    body.append('csrf_token', csrfToken);
    body.append('id', markerId);

    fetch('/admin/vod-server/markers/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) loadMarkers(vodEpisodeId);
    })
    .catch(() => {});
}

function seekFromTimeline(e) {
    const video = document.getElementById('markerVideo');
    if (!video.duration) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const pct = (e.clientX - rect.left) / rect.width;
    video.currentTime = pct * video.duration;
}

function formatTimePrecise(seconds) {
    seconds = parseFloat(seconds) || 0;
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    const ms = Math.floor((seconds % 1) * 1000);
    const time = h > 0
        ? h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0')
        : m + ':' + String(s).padStart(2, '0');
    return time + '.' + String(ms).padStart(3, '0');
}

// Re-render marker flags once video metadata loads (so we know duration)
document.getElementById('markerVideo')?.addEventListener('loadedmetadata', function() {
    renderMarkers();
});
</script>
