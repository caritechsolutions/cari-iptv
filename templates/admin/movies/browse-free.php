<div class="page-header">
    <div class="breadcrumb mb-1">
        <a href="/admin/movies">Movies</a>
        <span class="breadcrumb-separator">/</span>
        <span>Browse Free Content</span>
    </div>
    <h1 class="page-title">Browse Free Content</h1>
    <p class="page-subtitle">Discover royalty-free movies from multiple sources including Internet Archive and YouTube.</p>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="search-section">
            <!-- Source Selection Tabs -->
            <div class="source-tabs mb-3">
                <button type="button" class="source-tab active" data-source="internet_archive" onclick="selectSource('internet_archive')">
                    <i class="lucide-archive"></i> Internet Archive
                    <small>Public Domain Films</small>
                </button>
                <button type="button" class="source-tab" data-source="youtube" onclick="selectSource('youtube')">
                    <i class="lucide-youtube"></i> YouTube CC
                    <small>Creative Commons</small>
                </button>
            </div>

            <div class="form-group mb-2">
                <label class="form-label" id="searchLabel">Search Internet Archive</label>
                <div class="search-row">
                    <input type="text" id="searchQuery" class="form-input" placeholder="Search for classic movies, documentaries, public domain films...">
                    <select id="searchType" class="form-input" style="width: auto;">
                        <option value="movie">Full Movies</option>
                        <option value="documentary">Documentaries</option>
                        <option value="short">Short Films</option>
                    </select>
                    <button type="button" class="btn btn-primary" onclick="searchFreeContent()">
                        <i class="lucide-search"></i> Search
                    </button>
                </div>
            </div>
            <p class="text-sm text-muted" id="sourceInfo">
                <i class="lucide-info"></i> Internet Archive hosts thousands of public domain classic movies, documentaries, and short films.
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
            <i class="lucide-loader-2"></i> <span id="loadingText">Searching...</span>
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

<!-- Import Success Modal -->
<div class="modal-overlay" id="successModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="lucide-check-circle" style="color: var(--success);"></i> Import Successful</h3>
            <button type="button" class="modal-close" onclick="closeSuccessModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p id="successMessage" class="mb-2">Movie has been imported successfully!</p>
            <p class="text-muted text-sm">The video has been added to your library as a draft. You can edit the details and publish when ready.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeSuccessModal()">
                <i class="lucide-search"></i> Continue Browsing
            </button>
            <a href="#" id="editMovieBtn" class="btn btn-primary">
                <i class="lucide-edit"></i> Edit Movie
            </a>
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

.modal-close:hover {
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
    max-height: 60vh;
    overflow-y: auto;
}

.source-tabs {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.source-tab {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem 1.5rem;
    background: var(--bg-hover);
    border: 2px solid transparent;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 160px;
}

.source-tab:hover {
    background: var(--bg-dark);
    border-color: var(--primary);
}

.source-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.source-tab i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.source-tab small {
    font-size: 0.75rem;
    opacity: 0.8;
}

.search-row {
    display: flex;
    gap: 0.5rem;
}

.search-row input {
    flex: 1;
}

.imported-badge {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    background: var(--success);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.content-card.imported {
    opacity: 0.7;
}

.content-card.imported .content-thumbnail::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(34, 197, 94, 0.2);
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
let currentSource = 'internet_archive';

// Source selection
function selectSource(source) {
    currentSource = source;

    // Update tab UI
    document.querySelectorAll('.source-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.source === source);
    });

    // Update labels and placeholders
    const searchLabel = document.getElementById('searchLabel');
    const sourceInfo = document.getElementById('sourceInfo');
    const searchQuery = document.getElementById('searchQuery');

    if (source === 'internet_archive') {
        searchLabel.textContent = 'Search Internet Archive';
        searchQuery.placeholder = 'Search for classic movies, documentaries, public domain films...';
        sourceInfo.innerHTML = '<i class="lucide-info"></i> Internet Archive hosts thousands of public domain classic movies, documentaries, and short films.';
    } else {
        searchLabel.textContent = 'Search YouTube Creative Commons';
        searchQuery.placeholder = 'Search for free movies, documentaries, public domain films...';
        sourceInfo.innerHTML = '<i class="lucide-info"></i> This searches for videos with Creative Commons licenses that can be freely used and distributed.';
    }

    // Clear previous results
    document.getElementById('searchResults').innerHTML = `
        <div class="empty-state">
            <i class="lucide-search"></i>
            <h3>Search for Free Content</h3>
            <p>Try searching for "public domain movies", "classic films", or specific genres like "documentary nature".</p>
        </div>
    `;
    document.getElementById('resultCount').textContent = '';
}

function searchFreeContent() {
    const query = document.getElementById('searchQuery').value.trim();
    const type = document.getElementById('searchType').value;

    if (!query) {
        alert('Please enter a search term');
        return;
    }

    const resultsDiv = document.getElementById('searchResults');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const loadingText = document.getElementById('loadingText');
    const resultCount = document.getElementById('resultCount');

    resultsDiv.innerHTML = '';
    loadingSpinner.style.display = 'block';
    loadingText.textContent = currentSource === 'internet_archive' ? 'Searching Internet Archive...' : 'Searching YouTube...';
    resultCount.textContent = '';

    fetch('/admin/movies/search-free', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `_token=${csrfToken}&query=${encodeURIComponent(query)}&type=${type}&source=${currentSource}`
    })
    .then(r => r.json())
    .then(data => {
        loadingSpinner.style.display = 'none';

        if (data.success && data.results && data.results.length > 0) {
            const importedCount = data.results.filter(v => v.already_imported).length;
            resultCount.textContent = `${data.results.length} videos found` + (importedCount > 0 ? ` (${importedCount} already imported)` : '');

            resultsDiv.innerHTML = data.results.map(video => `
                <div class="content-card ${video.already_imported ? 'imported' : ''}">
                    <div class="content-thumbnail">
                        <img src="${video.thumbnail}" alt="" onerror="this.src='/assets/images/no-poster.png'">
                        ${video.duration_formatted ? `<span class="content-duration">${video.duration_formatted}</span>` : ''}
                        ${video.already_imported ? '<span class="imported-badge"><i class="lucide-check"></i> Imported</span>' : ''}
                    </div>
                    <div class="content-info">
                        <h4>${escapeHtml(video.title)}</h4>
                        ${video.year ? `<span class="text-sm text-muted">${video.year}</span>` : ''}
                        <div class="content-meta">
                            <span class="content-channel">${escapeHtml(video.channel || 'Unknown')}</span>
                            <div class="content-actions">
                                <a href="${video.url}" target="_blank" class="btn btn-sm btn-secondary">
                                    <i class="lucide-external-link"></i> View
                                </a>
                                ${video.already_imported
                                    ? '<span class="text-success text-sm"><i class="lucide-check"></i> Imported</span>'
                                    : `<button type="button" class="btn btn-sm btn-primary import-btn" data-video='${JSON.stringify(video).replace(/'/g, "&#39;")}'>
                                        <i class="lucide-download"></i> Import
                                    </button>`
                                }
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
        const errorMsg = currentSource === 'internet_archive'
            ? 'Failed to search Internet Archive. Please try again.'
            : 'Please check your YouTube API configuration in Settings.';
        resultsDiv.innerHTML = `
            <div class="empty-state">
                <i class="lucide-alert-circle"></i>
                <h3>Search Failed</h3>
                <p>${errorMsg}</p>
            </div>
        `;
    });
}

function showImportModal(video) {
    selectedVideo = video;
    document.getElementById('importThumbnail').src = video.thumbnail;
    document.getElementById('importTitle').textContent = video.title;
    document.getElementById('importDuration').textContent = video.duration_formatted ? `Duration: ${video.duration_formatted}` : '';
    document.getElementById('importChannel').textContent = `Source: ${video.channel || (video.source === 'internet_archive' ? 'Internet Archive' : 'YouTube')}`;
    document.getElementById('importModal').style.display = 'flex';
}

function closeImportModal() {
    document.getElementById('importModal').style.display = 'none';
    selectedVideo = null;
}

function confirmImport() {
    if (!selectedVideo) return;

    const btn = document.querySelector('#importModal .btn-primary');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="lucide-loader-2"></i> Importing...';
    }

    // Build request body with all necessary fields
    const source = selectedVideo.source || currentSource;
    const params = new URLSearchParams({
        _token: csrfToken,
        video_id: selectedVideo.video_id,
        title: selectedVideo.title,
        description: selectedVideo.description || '',
        thumbnail: selectedVideo.thumbnail || '',
        duration: selectedVideo.duration || 0,
        source: source,
        stream_url: selectedVideo.stream_url || '',
        source_url: selectedVideo.url || '',
        year: selectedVideo.year || ''
    });

    fetch('/admin/movies/import-free', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => {
        // Read as text first to avoid JSON parse errors
        return r.text().then(text => {
            return { status: r.status, text: text };
        });
    })
    .then(({ status, text }) => {
        // Try to parse JSON from the response
        let data;
        try {
            // Strip any content before the JSON (PHP warnings/errors)
            const jsonStart = text.indexOf('{');
            if (jsonStart > 0) {
                text = text.substring(jsonStart);
            }
            data = JSON.parse(text);
        } catch (e) {
            console.error('Response was not JSON:', text);
            throw new Error('Invalid response from server');
        }

        if (data.success) {
            closeImportModal();
            showSuccessModal(data.movie_id, selectedVideo.title);

            // Mark the card as imported
            markVideoAsImported(selectedVideo.video_id);
        } else {
            alert('Import failed: ' + (data.message || 'Unknown error'));
        }

        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="lucide-download"></i> Import Movie';
        }
    })
    .catch(err => {
        console.error('Import error:', err);
        alert('Import failed: ' + err.message);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="lucide-download"></i> Import Movie';
        }
    });
}

function markVideoAsImported(videoId) {
    // Find and update the card in the results
    document.querySelectorAll('.import-btn').forEach(btn => {
        try {
            const video = JSON.parse(btn.dataset.video);
            if (video.video_id === videoId) {
                const card = btn.closest('.content-card');
                card.classList.add('imported');

                // Add imported badge
                const thumbnail = card.querySelector('.content-thumbnail');
                if (!thumbnail.querySelector('.imported-badge')) {
                    thumbnail.insertAdjacentHTML('beforeend', '<span class="imported-badge"><i class="lucide-check"></i> Imported</span>');
                }

                // Replace button with imported text
                btn.replaceWith(document.createRange().createContextualFragment('<span class="text-success text-sm"><i class="lucide-check"></i> Imported</span>'));
            }
        } catch (e) {}
    });
}

function showSuccessModal(movieId, title) {
    document.getElementById('successMessage').textContent = `"${title}" has been imported successfully!`;
    document.getElementById('editMovieBtn').href = `/admin/movies/${movieId}/edit`;
    document.getElementById('successModal').style.display = 'flex';
}

function closeSuccessModal() {
    document.getElementById('successModal').style.display = 'none';
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

// Event delegation for import buttons
document.getElementById('searchResults').addEventListener('click', function(e) {
    const importBtn = e.target.closest('.import-btn');
    if (importBtn) {
        const video = JSON.parse(importBtn.dataset.video);
        showImportModal(video);
    }
});

// Close modals on overlay click
document.getElementById('importModal').addEventListener('click', function(e) {
    if (e.target === this) closeImportModal();
});
document.getElementById('successModal').addEventListener('click', function(e) {
    if (e.target === this) closeSuccessModal();
});
</script>
