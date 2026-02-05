<?php
$isEdit = !empty($show);
$pageAction = $isEdit ? 'Edit' : 'Add';
?>

<div class="page-header">
    <div class="breadcrumb mb-1">
        <a href="/admin/series">TV Shows</a>
        <span class="breadcrumb-separator">/</span>
        <span><?= $pageAction ?> TV Show</span>
    </div>
    <h1 class="page-title"><?= $pageAction ?> TV Show</h1>
</div>

<form method="POST" action="<?= $isEdit ? "/admin/series/{$show['id']}/update" : '/admin/series/store' ?>" id="seriesForm">
    <input type="hidden" name="_token" value="<?= $csrf ?>">
    <input type="hidden" name="tmdb_id" id="tmdb_id" value="<?= $show['tmdb_id'] ?? '' ?>">

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
                            <input type="text" id="tmdbSearchQuery" class="form-input" placeholder="Search TV show title...">
                            <button type="button" class="btn btn-primary" onclick="searchTmdb()">
                                <i class="lucide-search"></i> Search TMDB
                            </button>
                        </div>
                        <div id="tmdbResults" class="search-results" style="display: none;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-input" required
                               value="<?= htmlspecialchars($show['title'] ?? '') ?>"
                               placeholder="Enter TV show title">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="original_title">Original Title</label>
                            <input type="text" id="original_title" name="original_title" class="form-input"
                                   value="<?= htmlspecialchars($show['original_title'] ?? '') ?>"
                                   placeholder="Original language title">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="year">Year</label>
                            <input type="number" id="year" name="year" class="form-input"
                                   value="<?= $show['year'] ?? '' ?>"
                                   min="1928" max="<?= date('Y') + 5 ?>" placeholder="First aired year">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="tagline">Tagline</label>
                        <input type="text" id="tagline" name="tagline" class="form-input"
                               value="<?= htmlspecialchars($show['tagline'] ?? '') ?>"
                               placeholder="TV show tagline">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="synopsis">Synopsis</label>
                        <textarea id="synopsis" name="synopsis" class="form-input" rows="5"
                                  placeholder="TV show description/plot summary"><?= htmlspecialchars($show['synopsis'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="episode_run_time">Episode Run Time (minutes)</label>
                            <input type="number" id="episode_run_time" name="episode_run_time" class="form-input"
                                   value="<?= $show['episode_run_time'] ?? '' ?>"
                                   min="1" placeholder="e.g. 45">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="rating">Content Rating</label>
                            <select id="rating" name="rating" class="form-input">
                                <option value="">Select rating</option>
                                <option value="TV-Y" <?= ($show['rating'] ?? '') === 'TV-Y' ? 'selected' : '' ?>>TV-Y</option>
                                <option value="TV-Y7" <?= ($show['rating'] ?? '') === 'TV-Y7' ? 'selected' : '' ?>>TV-Y7</option>
                                <option value="TV-G" <?= ($show['rating'] ?? '') === 'TV-G' ? 'selected' : '' ?>>TV-G</option>
                                <option value="TV-PG" <?= ($show['rating'] ?? '') === 'TV-PG' ? 'selected' : '' ?>>TV-PG</option>
                                <option value="TV-14" <?= ($show['rating'] ?? '') === 'TV-14' ? 'selected' : '' ?>>TV-14</option>
                                <option value="TV-MA" <?= ($show['rating'] ?? '') === 'TV-MA' ? 'selected' : '' ?>>TV-MA</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="creators">Creators</label>
                            <input type="text" id="creators" name="creators" class="form-input"
                                   value="<?= htmlspecialchars($show['creators'] ?? '') ?>"
                                   placeholder="Creator names (comma-separated)">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="language">Language</label>
                            <input type="text" id="language" name="language" class="form-input"
                                   value="<?= htmlspecialchars($show['language'] ?? 'en') ?>"
                                   placeholder="e.g. en">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="networks">Networks</label>
                        <input type="text" id="networks" name="networks" class="form-input"
                               value="<?= htmlspecialchars($show['networks'] ?? '') ?>"
                               placeholder="HBO, Netflix, ABC (comma-separated)">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="genres">Genres</label>
                        <input type="text" id="genres" name="genres" class="form-input"
                               value="<?= is_array($show['genres'] ?? null) ? implode(', ', $show['genres']) : htmlspecialchars($show['genres'] ?? '') ?>"
                               placeholder="Drama, Comedy, Sci-Fi (comma-separated)">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="show_status">Show Status</label>
                        <select id="show_status" name="show_status" class="form-input">
                            <option value="">Select status</option>
                            <option value="Returning Series" <?= ($show['show_status'] ?? '') === 'Returning Series' ? 'selected' : '' ?>>Returning Series</option>
                            <option value="Ended" <?= ($show['show_status'] ?? '') === 'Ended' ? 'selected' : '' ?>>Ended</option>
                            <option value="Canceled" <?= ($show['show_status'] ?? '') === 'Canceled' ? 'selected' : '' ?>>Canceled</option>
                            <option value="In Production" <?= ($show['show_status'] ?? '') === 'In Production' ? 'selected' : '' ?>>In Production</option>
                            <option value="Pilot" <?= ($show['show_status'] ?? '') === 'Pilot' ? 'selected' : '' ?>>Pilot</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Seasons & Episodes Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="lucide-tv"></i> Seasons & Episodes</h3>
                </div>
                <div class="card-body">
                    <?php if ($isEdit): ?>
                        <div class="seasons-info">
                            <p class="text-muted mb-1">
                                <i class="lucide-layers"></i>
                                <?= (int)($show['number_of_seasons'] ?? 0) ?> Seasons, <?= (int)($show['number_of_episodes'] ?? 0) ?> Episodes
                            </p>
                            <a href="/admin/series/<?= $show['id'] ?>/seasons" class="btn btn-primary">
                                <i class="lucide-list"></i> Manage Seasons & Episodes
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-muted text-center" style="padding: 1rem;">
                            <i class="lucide-info" style="margin-right: 0.5rem;"></i>
                            Save the TV show first to manage seasons and episodes.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column - Media & Settings -->
        <div class="form-column">
            <!-- Media Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="lucide-image"></i> Media</h3>
                    <?php if ($isEdit && !empty($show['tmdb_id'])): ?>
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
                                <?php if (!empty($show['poster_url'])): ?>
                                    <img src="<?= htmlspecialchars($show['poster_url']) ?>" alt="Poster">
                                <?php else: ?>
                                    <div class="media-placeholder">
                                        <i class="lucide-image"></i>
                                        <span>No poster</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <input type="text" name="poster_url" id="poster_url" class="form-input mt-1"
                                   value="<?= htmlspecialchars($show['poster_url'] ?? '') ?>"
                                   placeholder="Poster URL or path" onchange="updatePosterPreview()">
                        </div>
                        <div class="media-preview-item">
                            <label class="form-label">Backdrop</label>
                            <div class="media-preview backdrop" id="backdropPreview">
                                <?php if (!empty($show['backdrop_url'])): ?>
                                    <img src="<?= htmlspecialchars($show['backdrop_url']) ?>" alt="Backdrop">
                                <?php else: ?>
                                    <div class="media-placeholder">
                                        <i class="lucide-image"></i>
                                        <span>No backdrop</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <input type="text" name="backdrop_url" id="backdrop_url" class="form-input mt-1"
                                   value="<?= htmlspecialchars($show['backdrop_url'] ?? '') ?>"
                                   placeholder="Backdrop URL or path" onchange="updateBackdropPreview()">
                        </div>
                    </div>

                    <div class="form-group mt-2">
                        <label class="form-label" for="logo_url">Clear Logo URL</label>
                        <input type="text" name="logo_url" id="logo_url" class="form-input"
                               value="<?= htmlspecialchars($show['logo_url'] ?? '') ?>"
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
                    if ($isEdit && !empty($show['categories'])) {
                        foreach ($show['categories'] as $cat) {
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
                            <option value="draft" <?= ($show['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= ($show['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="archived" <?= ($show['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_featured" value="1"
                                   <?= !empty($show['is_featured']) ? 'checked' : '' ?>>
                            <span class="checkbox-text">
                                <strong>Featured TV Show</strong>
                                <small>Display prominently in featured sections</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <a href="/admin/series" class="btn btn-secondary">
                    <i class="lucide-x"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="lucide-save"></i> <?= $isEdit ? 'Update' : 'Create' ?> TV Show
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

.seasons-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    text-align: center;
}

.seasons-info p {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
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

.mb-1 {
    margin-bottom: 0.5rem;
}
</style>

<script>
const csrfToken = '<?= $csrf ?>';
const showId = <?= $show['id'] ?? 'null' ?>;

// TMDB Search
function searchTmdb() {
    const query = document.getElementById('tmdbSearchQuery').value.trim();
    if (!query) return;

    const resultsDiv = document.getElementById('tmdbResults');
    resultsDiv.innerHTML = '<div class="loading-spinner">Searching...</div>';
    resultsDiv.style.display = 'block';

    fetch('/admin/series/search-tmdb', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${csrfToken}&query=${encodeURIComponent(query)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.results.length > 0) {
            resultsDiv.innerHTML = data.results.map(show => `
                <div class="search-result-item" onclick="selectTmdbShow(${show.id})">
                    <img src="${show.poster || '/assets/images/no-poster.png'}" alt="" class="search-result-poster">
                    <div class="search-result-info">
                        <h4>${escapeHtml(show.name || show.title)} ${show.year ? '(' + show.year + ')' : ''}</h4>
                        <p>${escapeHtml(show.overview || 'No description available')}</p>
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

function selectTmdbShow(tmdbId) {
    const resultsDiv = document.getElementById('tmdbResults');
    resultsDiv.innerHTML = '<div class="loading-spinner">Loading TV show details...</div>';

    fetch('/admin/series/tmdb-details', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${csrfToken}&tmdb_id=${tmdbId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const show = data.show;

            // Fill form fields
            document.getElementById('tmdb_id').value = show.id;
            document.getElementById('title').value = show.name || '';
            document.getElementById('original_title').value = show.original_name || '';
            document.getElementById('tagline').value = show.tagline || '';
            document.getElementById('synopsis').value = show.overview || '';
            document.getElementById('year').value = show.year || '';
            document.getElementById('episode_run_time').value = show.episode_run_time || '';
            document.getElementById('creators').value = show.creators ? (Array.isArray(show.creators) ? show.creators.join(', ') : show.creators) : '';
            document.getElementById('networks').value = show.networks ? (Array.isArray(show.networks) ? show.networks.join(', ') : show.networks) : '';
            document.getElementById('genres').value = show.genres ? (Array.isArray(show.genres) ? show.genres.join(', ') : show.genres) : '';
            document.getElementById('poster_url').value = show.poster || '';
            document.getElementById('backdrop_url').value = show.backdrop || '';

            // Set show status if available
            if (show.show_status) {
                const statusSelect = document.getElementById('show_status');
                for (let i = 0; i < statusSelect.options.length; i++) {
                    if (statusSelect.options[i].value === show.show_status) {
                        statusSelect.selectedIndex = i;
                        break;
                    }
                }
            }

            // Set vote average / rating if available
            if (show.vote_average) {
                // Store vote_average for reference if needed
                document.getElementById('tmdb_id').dataset.voteAverage = show.vote_average;
            }

            // Update previews
            updatePosterPreview();
            updateBackdropPreview();

            resultsDiv.style.display = 'none';
        } else {
            alert('Failed to load TV show details');
        }
    })
    .catch(() => {
        alert('Error loading TV show details');
    });
}

// Fanart.tv Search
function searchFanart() {
    const tmdbId = document.getElementById('tmdb_id').value;
    if (!tmdbId) {
        alert('Please search and select a TV show from TMDB first');
        return;
    }

    const modal = document.getElementById('fanartModal');
    const content = document.getElementById('fanartModalContent');
    modal.style.display = 'flex';
    content.innerHTML = '<div class="loading-spinner">Loading artwork from Fanart.tv...</div>';

    fetch('/admin/series/search-fanart', {
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
