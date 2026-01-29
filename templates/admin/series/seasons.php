<?php
$showId = $show['id'];
$showTitle = htmlspecialchars($show['title']);
$hasTmdb = !empty($show['tmdb_id']);
$nextSeasonNumber = 1;
if (!empty($seasons)) {
    $maxSeason = max(array_column($seasons, 'season_number'));
    $nextSeasonNumber = $maxSeason + 1;
}
?>

<div class="page-header">
    <div class="breadcrumb mb-1">
        <a href="/admin/series">TV Shows</a>
        <span class="breadcrumb-separator">/</span>
        <a href="/admin/series/<?= $showId ?>/edit"><?= $showTitle ?></a>
        <span class="breadcrumb-separator">/</span>
        <span>Seasons</span>
    </div>
    <div class="page-header-row">
        <div>
            <h1 class="page-title">Seasons</h1>
            <p class="page-subtitle">Manage seasons for <?= $showTitle ?></p>
        </div>
        <div class="header-actions">
            <a href="/admin/series/<?= $showId ?>/edit" class="btn btn-secondary">
                <i class="lucide-edit"></i> Edit Show Info
            </a>
            <?php if ($hasTmdb): ?>
                <button type="button" class="btn btn-info" onclick="importFromTmdb()" id="importBtn">
                    <i class="lucide-download"></i> Import Seasons from TMDB
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" onclick="openAddSeasonModal()">
                <i class="lucide-plus"></i> Add Season
            </button>
        </div>
    </div>
</div>

<!-- Show Info Card -->
<div class="card mb-3">
    <div class="card-body">
        <div class="show-info-header">
            <div class="show-poster-thumb">
                <?php if (!empty($show['poster_url'])): ?>
                    <img src="<?= htmlspecialchars($show['poster_url']) ?>" alt="<?= $showTitle ?>">
                <?php else: ?>
                    <div class="show-poster-placeholder">
                        <i class="lucide-tv"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="show-info-details">
                <h2 class="show-info-title">
                    <?= $showTitle ?>
                    <?php if (!empty($show['year'])): ?>
                        <span class="show-info-year">(<?= $show['year'] ?>)</span>
                    <?php endif; ?>
                </h2>
                <div class="show-info-meta">
                    <?php if (!empty($show['show_status'])): ?>
                        <?php
                        $statusClass = match(strtolower($show['show_status'])) {
                            'returning series', 'in production' => 'badge-success',
                            'ended', 'canceled' => 'badge-danger',
                            'planned' => 'badge-warning',
                            default => 'badge-info'
                        };
                        ?>
                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($show['show_status']) ?></span>
                    <?php endif; ?>
                    <span class="show-info-stat">
                        <i class="lucide-layers"></i>
                        <?= (int)($show['number_of_seasons'] ?? 0) ?> Season<?= (int)($show['number_of_seasons'] ?? 0) !== 1 ? 's' : '' ?>
                    </span>
                    <span class="show-info-stat">
                        <i class="lucide-film"></i>
                        <?= (int)($show['number_of_episodes'] ?? 0) ?> Episode<?= (int)($show['number_of_episodes'] ?? 0) !== 1 ? 's' : '' ?>
                    </span>
                    <?php if ($hasTmdb): ?>
                        <span class="show-info-stat text-muted">
                            <i class="lucide-database"></i> TMDB #<?= htmlspecialchars($show['tmdb_id']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Seasons Grid -->
<?php if (!empty($seasons)): ?>
    <div class="seasons-grid">
        <?php foreach ($seasons as $season): ?>
            <div class="season-card" id="season-<?= $season['id'] ?>">
                <div class="season-poster">
                    <?php if (!empty($season['poster_url'])): ?>
                        <img src="<?= htmlspecialchars($season['poster_url']) ?>" alt="<?= htmlspecialchars($season['name']) ?>">
                    <?php else: ?>
                        <div class="season-poster-placeholder">
                            <i class="lucide-folder"></i>
                            <span>S<?= $season['season_number'] ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="season-card-body">
                    <h3 class="season-name"><?= htmlspecialchars($season['name']) ?></h3>
                    <div class="season-badges">
                        <span class="badge badge-primary">
                            <i class="lucide-film"></i>
                            <?= (int)($season['actual_episode_count'] ?? $season['episode_count'] ?? 0) ?> Episode<?= (int)($season['actual_episode_count'] ?? $season['episode_count'] ?? 0) !== 1 ? 's' : '' ?>
                        </span>
                        <?php if (!empty($season['trailer_count']) && $season['trailer_count'] > 0): ?>
                            <span class="badge badge-warning">
                                <i class="lucide-play-circle"></i>
                                <?= $season['trailer_count'] ?> Trailer<?= $season['trailer_count'] != 1 ? 's' : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($season['air_date'])): ?>
                        <div class="season-air-date">
                            <i class="lucide-calendar"></i>
                            <?= date('M j, Y', strtotime($season['air_date'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="season-card-footer">
                    <a href="/admin/series/<?= $showId ?>/seasons/<?= $season['id'] ?>/episodes" class="btn btn-primary btn-sm">
                        <i class="lucide-list"></i> Episodes
                    </a>
                    <?php if ($hasTmdb): ?>
                        <button type="button" class="btn btn-info btn-sm" onclick="fetchSeasonTmdb(<?= $season['id'] ?>, <?= $season['season_number'] ?>)" title="Fetch data from TMDB" id="fetchBtn-<?= $season['id'] ?>">
                            <i class="lucide-download"></i> TMDB
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteSeason(<?= $season['id'] ?>, '<?= htmlspecialchars(addslashes($season['name'])) ?>')">
                        <i class="lucide-trash-2"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- Empty State -->
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="lucide-folder-open"></i>
                </div>
                <h3>No seasons yet</h3>
                <p class="text-muted">Add a season manually or import from TMDB.</p>
                <div class="empty-state-actions">
                    <button type="button" class="btn btn-primary" onclick="openAddSeasonModal()">
                        <i class="lucide-plus"></i> Add Season
                    </button>
                    <?php if ($hasTmdb): ?>
                        <button type="button" class="btn btn-info" onclick="importFromTmdb()">
                            <i class="lucide-download"></i> Import from TMDB
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Add Season Modal -->
<div class="modal-overlay" id="addSeasonModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Season</h3>
            <button type="button" class="modal-close" onclick="closeAddSeasonModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label" for="seasonNumber">Season Number <span class="required">*</span></label>
                <input type="number" id="seasonNumber" class="form-input" min="0" value="<?= $nextSeasonNumber ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="seasonName">Name</label>
                <input type="text" id="seasonName" class="form-input" value="Season <?= $nextSeasonNumber ?>" placeholder="Season name">
            </div>
            <div class="form-group">
                <label class="form-label" for="seasonOverview">Overview</label>
                <textarea id="seasonOverview" class="form-input" rows="4" placeholder="Season overview/description"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="seasonPosterUrl">Poster URL</label>
                <input type="text" id="seasonPosterUrl" class="form-input" placeholder="https://image.tmdb.org/...">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAddSeasonModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitAddSeason()" id="addSeasonBtn">
                <i class="lucide-plus"></i> Add Season
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Season</h3>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete "<strong id="deleteSeasonName"></strong>"?</p>
            <p class="text-danger">This will also delete all episodes and trailers in this season. This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="confirmDeleteSeason()" id="confirmDeleteBtn">
                <i class="lucide-trash-2"></i> Delete Season
            </button>
        </div>
    </div>
</div>

<!-- Import Progress Modal -->
<div class="modal-overlay" id="importModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Importing Seasons from TMDB</h3>
        </div>
        <div class="modal-body">
            <div class="import-progress">
                <div class="import-spinner">
                    <div class="spinner"></div>
                </div>
                <p id="importStatus">Fetching season data from TMDB...</p>
                <p class="text-muted text-sm">This may take a moment depending on the number of seasons.</p>
            </div>
        </div>
    </div>
</div>

<style>
.page-header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Show Info Header */
.show-info-header {
    display: flex;
    gap: 1.25rem;
    align-items: center;
}

.show-poster-thumb {
    width: 70px;
    height: 105px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.show-poster-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.show-poster-placeholder {
    width: 100%;
    height: 100%;
    background: var(--bg-hover);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    gap: 0.25rem;
}

.show-poster-placeholder i {
    font-size: 1.5rem;
}

.show-info-details {
    flex: 1;
    min-width: 0;
}

.show-info-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.show-info-year {
    font-weight: 400;
    color: var(--text-secondary);
}

.show-info-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.show-info-stat {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.show-info-stat i {
    font-size: 1rem;
}

/* Seasons Grid */
.seasons-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}

@media (max-width: 1024px) {
    .seasons-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .seasons-grid {
        grid-template-columns: 1fr;
    }

    .page-header-row {
        flex-direction: column;
    }

    .show-info-header {
        flex-direction: column;
        text-align: center;
    }

    .show-info-meta {
        justify-content: center;
    }
}

/* Season Card */
.season-card {
    background: var(--bg-card);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.season-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    border-color: var(--primary);
}

.season-poster {
    width: 100%;
    aspect-ratio: 2/3;
    overflow: hidden;
    position: relative;
}

.season-poster img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.season-card:hover .season-poster img {
    transform: scale(1.03);
}

.season-poster-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--bg-hover) 0%, rgba(99, 102, 241, 0.1) 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    gap: 0.5rem;
}

.season-poster-placeholder i {
    font-size: 3rem;
    opacity: 0.5;
}

.season-poster-placeholder span {
    font-size: 1.25rem;
    font-weight: 600;
    opacity: 0.6;
}

.season-card-body {
    padding: 1rem;
}

.season-name {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.season-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
    margin-bottom: 0.5rem;
}

.season-badges .badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.season-badges .badge i {
    font-size: 0.7rem;
}

.season-air-date {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.season-air-date i {
    font-size: 0.875rem;
}

.season-card-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.season-card-footer .btn-primary {
    flex: 1;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1.5rem;
}

.empty-state-icon {
    margin-bottom: 1rem;
}

.empty-state-icon i {
    font-size: 4rem;
    color: var(--text-muted);
    opacity: 0.4;
}

.empty-state h3 {
    margin-bottom: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
}

.empty-state p {
    margin-bottom: 1.5rem;
}

.empty-state-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
}

/* Modal Styles */
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
    background: var(--bg-card);
    border-radius: 12px;
    max-width: 520px;
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
    font-size: 1.1rem;
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

.required {
    color: var(--danger);
}

/* Import Progress */
.import-progress {
    text-align: center;
    padding: 1.5rem 0;
}

.import-spinner {
    margin-bottom: 1rem;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--border-color);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Button styles */
.btn-info {
    background: var(--info);
    color: white;
}

.btn-info:hover {
    background: #2563eb;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #16a34a;
}

/* Toast Notification */
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

.toast.show {
    transform: translateX(0);
}

.toast-success {
    background: var(--success);
}

.toast-error {
    background: var(--danger);
}
</style>

<script>
const csrfToken = '<?= $csrf ?>';
const showId = <?= $showId ?>;
let deleteSeasonId = null;

// ========================================================================
// ADD SEASON MODAL
// ========================================================================

function openAddSeasonModal() {
    document.getElementById('addSeasonModal').style.display = 'flex';
    document.getElementById('seasonNumber').focus();
}

function closeAddSeasonModal() {
    document.getElementById('addSeasonModal').style.display = 'none';
}

function submitAddSeason() {
    const seasonNumber = document.getElementById('seasonNumber').value;
    const name = document.getElementById('seasonName').value.trim();
    const overview = document.getElementById('seasonOverview').value.trim();
    const posterUrl = document.getElementById('seasonPosterUrl').value.trim();

    if (!seasonNumber || seasonNumber < 0) {
        showToast('Please enter a valid season number.', 'error');
        return;
    }

    const btn = document.getElementById('addSeasonBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="lucide-loader"></i> Adding...';
    btn.disabled = true;

    const body = new URLSearchParams({
        _token: csrfToken,
        season_number: seasonNumber,
        name: name || 'Season ' + seasonNumber,
        overview: overview,
        poster_url: posterUrl
    });

    fetch(`/admin/series/${showId}/seasons/add`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Season added successfully.', 'success');
            closeAddSeasonModal();
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Failed to add season.', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// ========================================================================
// DELETE SEASON
// ========================================================================

function deleteSeason(seasonId, name) {
    deleteSeasonId = seasonId;
    document.getElementById('deleteSeasonName').textContent = name;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteSeasonId = null;
}

function confirmDeleteSeason() {
    if (!deleteSeasonId) return;

    const btn = document.getElementById('confirmDeleteBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="lucide-loader"></i> Deleting...';
    btn.disabled = true;

    fetch(`/admin/series/${showId}/seasons/${deleteSeasonId}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Season deleted.', 'success');
            closeDeleteModal();

            const card = document.getElementById('season-' + deleteSeasonId);
            if (card) {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    card.remove();
                    checkEmptyState();
                }, 300);
            }
        } else {
            showToast(data.message || 'Failed to delete season.', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function checkEmptyState() {
    const grid = document.querySelector('.seasons-grid');
    if (grid && grid.children.length === 0) {
        location.reload();
    }
}

// ========================================================================
// IMPORT FROM TMDB
// ========================================================================

function importFromTmdb() {
    const importModal = document.getElementById('importModal');
    const statusEl = document.getElementById('importStatus');
    importModal.style.display = 'flex';
    statusEl.textContent = 'Fetching season data from TMDB...';

    fetch(`/admin/series/${showId}/seasons/import`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_token=${csrfToken}&season_number=0`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusEl.textContent = data.message || 'Import completed successfully!';
            showToast(data.message || 'Seasons imported from TMDB.', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            importModal.style.display = 'none';
            showToast(data.message || 'Failed to import seasons.', 'error');
        }
    })
    .catch(() => {
        importModal.style.display = 'none';
        showToast('Network error during import. Please try again.', 'error');
    });
}

// ========================================================================
// FETCH SEASON DATA FROM TMDB
// ========================================================================

function fetchSeasonTmdb(seasonId, seasonNumber) {
    const btn = document.getElementById('fetchBtn-' + seasonId);
    if (!btn) return;

    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="lucide-loader"></i>';
    btn.disabled = true;

    fetch(`/admin/series/${showId}/seasons/${seasonId}/fetch-tmdb`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Season data fetched from TMDB.', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Failed to fetch season data.', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// ========================================================================
// TOAST NOTIFICATIONS
// ========================================================================

function showToast(message, type) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icon = type === 'success' ? 'lucide-check-circle' : 'lucide-alert-circle';
    toast.innerHTML = `<i class="${icon}"></i> ${escapeHtml(message)}`;

    document.body.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ========================================================================
// HELPERS
// ========================================================================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-update season name when number changes
document.getElementById('seasonNumber').addEventListener('input', function() {
    const nameInput = document.getElementById('seasonName');
    const currentVal = nameInput.value;
    if (!currentVal || /^Season \d*$/.test(currentVal)) {
        nameInput.value = 'Season ' + (this.value || '');
    }
});

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this && this.id !== 'importModal') {
            this.style.display = 'none';
        }
    });
});

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal-overlay');
        modals.forEach(modal => {
            if (modal.style.display === 'flex' && modal.id !== 'importModal') {
                modal.style.display = 'none';
            }
        });
    }
});
</script>
