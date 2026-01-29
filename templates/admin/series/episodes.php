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
                                        data-stream-url-backup="<?= htmlspecialchars($ep['stream_url_backup'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
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
                                            <?php if (!empty($ep['stream_url'])): ?>
                                                <span class="badge badge-success">Has URL</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">No URL</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
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
</script>
