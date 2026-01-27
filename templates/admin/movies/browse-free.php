<div class="page-header">
    <div class="breadcrumb mb-1">
        <a href="/admin/movies">Movies</a>
        <span class="breadcrumb-separator">/</span>
        <span>Browse Free Content</span>
    </div>
    <h1 class="page-title">Browse Free Content</h1>
    <p class="page-subtitle">Discover royalty-free movies and videos from YouTube Creative Commons.</p>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="search-section">
            <div class="form-group mb-2">
                <label class="form-label">Search YouTube Creative Commons</label>
                <div class="search-row">
                    <input type="text" id="searchQuery" class="form-input" placeholder="Search for free movies, documentaries, public domain films...">
                    <select id="searchType" class="form-input" style="width: auto;">
                        <option value="movie">Full Movies</option>
                        <option value="documentary">Documentaries</option>
                        <option value="short">Short Films</option>
                        <option value="any">Any Length</option>
                    </select>
                    <button type="button" class="btn btn-primary" onclick="searchFreeContent()">
                        <i class="lucide-search"></i> Search
                    </button>
                </div>
            </div>
            <p class="text-sm text-muted">
                <i class="lucide-info"></i> This searches for videos with Creative Commons licenses that can be freely used and distributed.
            </p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="lucide-gift"></i> Search Results</h3>
        <span id="resultCount" class="text-muted"></span>
    </div>
    <div class="card-body">
        <div id="searchResults" class="free-content-grid">
            <div class="empty-state">
                <i class="lucide-search"></i>
                <h3>Search for Free Content</h3>
                <p>Try searching for "public domain movies", "classic films", or specific genres like "documentary nature".</p>
            </div>
        </div>
        <div id="loadingSpinner" class="loading-spinner" style="display: none;">
            <i class="lucide-loader-2"></i> Searching YouTube...
        </div>
    </div>
</div>

<!-- Import Confirmation Modal -->
<div class="modal-overlay" id="importModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Import Free Content</h3>
            <button type="button" class="modal-close" onclick="closeImportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="import-preview">
                <img id="importThumbnail" src="" alt="" class="import-thumbnail">
                <div class="import-info">
                    <h4 id="importTitle"></h4>
                    <p id="importDuration" class="text-sm text-muted"></p>
                    <p id="importChannel" class="text-sm text-muted"></p>
                </div>
            </div>
            <div class="alert alert-info mt-2">
                <i class="lucide-info"></i>
                This video uses a Creative Commons license. Please verify the specific license terms on YouTube.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeImportModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="confirmImport()">
                <i class="lucide-download"></i> Import Movie
            </button>
        </div>
    </div>
</div>

<style>
.search-row {
    display: flex;
    gap: 0.5rem;
}

.search-row input {
    flex: 1;
}

.free-content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.content-card {
    background: var(--bg-hover);
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.content-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.content-thumbnail {
    position: relative;
    aspect-ratio: 16/9;
    overflow: hidden;
}

.content-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.content-duration {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.content-info {
    padding: 1rem;
}

.content-info h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.content-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.75rem;
}

.content-channel {
    font-size: 0.875rem;
    color: var(--text-muted);
}

.content-actions {
    display: flex;
    gap: 0.5rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 4rem;
    opacity: 0.3;
    margin-bottom: 1rem;
}

.empty-state h3 {
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
}

.loading-spinner {
    text-align: center;
    padding: 3rem;
    color: var(--text-muted);
}

.loading-spinner i {
    animation: spin 1s linear infinite;
    margin-right: 0.5rem;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.import-preview {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.import-thumbnail {
    width: 200px;
    border-radius: 8px;
}

.import-info {
    flex: 1;
}

.import-info h4 {
    margin: 0 0 0.5rem 0;
}

.modal-footer {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
}

.mt-2 {
    margin-top: 1rem;
}

.mb-2 {
    margin-bottom: 1rem;
}
</style>

<script>
const csrfToken = '<?= $csrf ?>';
let selectedVideo = null;

function searchFreeContent() {
    const query = document.getElementById('searchQuery').value.trim();
    const type = document.getElementById('searchType').value;

    if (!query) {
        alert('Please enter a search term');
        return;
    }

    const resultsDiv = document.getElementById('searchResults');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const resultCount = document.getElementById('resultCount');

    resultsDiv.innerHTML = '';
    loadingSpinner.style.display = 'block';
    resultCount.textContent = '';

    fetch('/admin/movies/search-free', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${csrfToken}&query=${encodeURIComponent(query)}&type=${type}`
    })
    .then(r => r.json())
    .then(data => {
        loadingSpinner.style.display = 'none';

        if (data.success && data.results && data.results.length > 0) {
            resultCount.textContent = `${data.results.length} videos found`;
            resultsDiv.innerHTML = data.results.map(video => `
                <div class="content-card">
                    <div class="content-thumbnail">
                        <img src="${video.thumbnail}" alt="">
                        ${video.duration_formatted ? `<span class="content-duration">${video.duration_formatted}</span>` : ''}
                    </div>
                    <div class="content-info">
                        <h4>${escapeHtml(video.title)}</h4>
                        <div class="content-meta">
                            <span class="content-channel">${escapeHtml(video.channel)}</span>
                            <div class="content-actions">
                                <a href="${video.url}" target="_blank" class="btn btn-sm btn-secondary" title="View on YouTube">
                                    <i class="lucide-external-link"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-primary" onclick='showImportModal(${JSON.stringify(video)})' title="Import">
                                    <i class="lucide-download"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            resultsDiv.innerHTML = `
                <div class="empty-state">
                    <i class="lucide-search-x"></i>
                    <h3>No Results Found</h3>
                    <p>Try different search terms or adjust the content type filter.</p>
                </div>
            `;
        }
    })
    .catch(error => {
        loadingSpinner.style.display = 'none';
        resultsDiv.innerHTML = `
            <div class="empty-state">
                <i class="lucide-alert-circle"></i>
                <h3>Search Failed</h3>
                <p>Please check your YouTube API configuration in Settings.</p>
            </div>
        `;
    });
}

function showImportModal(video) {
    selectedVideo = video;
    document.getElementById('importThumbnail').src = video.thumbnail;
    document.getElementById('importTitle').textContent = video.title;
    document.getElementById('importDuration').textContent = video.duration_formatted ? `Duration: ${video.duration_formatted}` : '';
    document.getElementById('importChannel').textContent = `Channel: ${video.channel}`;
    document.getElementById('importModal').style.display = 'flex';
}

function closeImportModal() {
    document.getElementById('importModal').style.display = 'none';
    selectedVideo = null;
}

function confirmImport() {
    if (!selectedVideo) return;

    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="lucide-loader-2"></i> Importing...';

    fetch('/admin/movies/import-free', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${csrfToken}&video_id=${selectedVideo.video_id}&title=${encodeURIComponent(selectedVideo.title)}&description=${encodeURIComponent(selectedVideo.description || '')}&thumbnail=${encodeURIComponent(selectedVideo.thumbnail)}&duration=${selectedVideo.duration || 0}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeImportModal();
            window.location.href = `/admin/movies/${data.movie_id}/edit`;
        } else {
            alert('Import failed: ' + (data.message || 'Unknown error'));
            btn.disabled = false;
            btn.innerHTML = '<i class="lucide-download"></i> Import Movie';
        }
    })
    .catch(() => {
        alert('Import failed. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="lucide-download"></i> Import Movie';
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Allow Enter key to search
document.getElementById('searchQuery').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchFreeContent();
    }
});

// Close modal on overlay click
document.getElementById('importModal').addEventListener('click', function(e) {
    if (e.target === this) closeImportModal();
});
</script>
