<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Movies</h1>
        <p class="page-subtitle">Manage your movie library with TMDB and Fanart.tv integration.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/movies/browse-free" class="btn btn-secondary">
            <i class="lucide-gift"></i> Browse Free Content
        </a>
        <a href="/admin/movies/create" class="btn btn-primary">
            <i class="lucide-plus"></i> Add Movie
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="lucide-film"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Movies</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="lucide-check-circle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Published</div>
            <div class="stat-value"><?= number_format($stats['published']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="lucide-star"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Featured</div>
            <div class="stat-value"><?= number_format($stats['featured']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="lucide-gift"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Free Content</div>
            <div class="stat-value"><?= number_format($stats['free']) ?></div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/movies" class="filters-form">
            <div class="filters-row">
                <div class="filter-group filter-search">
                    <div class="search-input-wrapper">
                        <i class="lucide-search"></i>
                        <input type="text" name="search" class="form-input" placeholder="Search movies..."
                               value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                </div>

                <div class="filter-group">
                    <select name="status" class="form-input">
                        <option value="">All Status</option>
                        <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                <div class="filter-group">
                    <select name="category_id" class="form-input">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filters['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <select name="year" class="form-input">
                        <option value="">All Years</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y['year'] ?>" <?= $filters['year'] == $y['year'] ? 'selected' : '' ?>>
                                <?= $y['year'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <select name="source" class="form-input">
                        <option value="">All Sources</option>
                        <option value="manual" <?= $filters['source'] === 'manual' ? 'selected' : '' ?>>Manual</option>
                        <option value="tmdb" <?= $filters['source'] === 'tmdb' ? 'selected' : '' ?>>TMDB Import</option>
                        <option value="youtube_cc" <?= $filters['source'] === 'youtube_cc' ? 'selected' : '' ?>>YouTube CC</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="lucide-filter"></i> Filter
                    </button>
                    <a href="/admin/movies" class="btn btn-secondary btn-sm">
                        <i class="lucide-x"></i> Clear
                    </a>
                </div>
            </div>

            <input type="hidden" name="sort" value="<?= htmlspecialchars($filters['sort']) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($filters['dir']) ?>">
        </form>
    </div>
</div>

<!-- Movies Grid/Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <form id="bulkForm" method="POST" action="/admin/movies/bulk">
            <input type="hidden" name="_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" id="bulkAction" value="">

            <?php if (!empty($movies)): ?>
                <!-- Bulk Actions Bar -->
                <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
                    <div class="bulk-info">
                        <span id="selectedCount">0</span> movie(s) selected
                    </div>
                    <div class="bulk-buttons">
                        <button type="button" class="btn btn-success btn-sm" onclick="submitBulkAction('publish')">
                            <i class="lucide-check"></i> Publish
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" onclick="submitBulkAction('draft')">
                            <i class="lucide-file-edit"></i> Set Draft
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="submitBulkAction('delete')">
                            <i class="lucide-trash-2"></i> Delete
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <th width="60">Poster</th>
                            <th>
                                <a href="?<?= http_build_query(array_merge($filters, ['sort' => 'title', 'dir' => $filters['sort'] === 'title' && $filters['dir'] === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="sort-link">
                                    Title
                                    <?php if ($filters['sort'] === 'title'): ?>
                                        <i class="lucide-chevron-<?= $filters['dir'] === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th width="80">
                                <a href="?<?= http_build_query(array_merge($filters, ['sort' => 'year', 'dir' => $filters['sort'] === 'year' && $filters['dir'] === 'DESC' ? 'ASC' : 'DESC'])) ?>" class="sort-link">
                                    Year
                                    <?php if ($filters['sort'] === 'year'): ?>
                                        <i class="lucide-chevron-<?= $filters['dir'] === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Categories</th>
                            <th width="100">Status</th>
                            <th width="80">Trailers</th>
                            <th width="100">Source</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movies)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted" style="padding: 3rem;">
                                    <i class="lucide-film" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="mt-2">No movies found. <a href="/admin/movies/create">Add your first movie</a> or <a href="/admin/movies/browse-free">browse free content</a>.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($movies as $movie): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="<?= $movie['id'] ?>" class="movie-checkbox" onchange="updateBulkBar()">
                                    </td>
                                    <td>
                                        <?php if ($movie['poster_url']): ?>
                                            <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="" class="movie-poster-thumb">
                                        <?php else: ?>
                                            <div class="movie-poster-placeholder">
                                                <i class="lucide-film"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="movie-title-cell">
                                            <strong><?= htmlspecialchars($movie['title']) ?></strong>
                                            <?php if ($movie['is_featured']): ?>
                                                <span class="badge badge-warning" title="Featured"><i class="lucide-star"></i></span>
                                            <?php endif; ?>
                                            <?php if ($movie['is_free']): ?>
                                                <span class="badge badge-info" title="Free Content"><i class="lucide-gift"></i></span>
                                            <?php endif; ?>
                                            <?php if ($movie['runtime']): ?>
                                                <small class="text-muted"><?= $movie['runtime'] ?> min</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= $movie['year'] ?: '-' ?></td>
                                    <td>
                                        <?php if ($movie['category_names']): ?>
                                            <span class="text-sm"><?= htmlspecialchars($movie['category_names']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadge = match($movie['status']) {
                                            'published' => 'badge-success',
                                            'draft' => 'badge-warning',
                                            'archived' => 'badge-danger',
                                            default => 'badge-info'
                                        };
                                        ?>
                                        <span class="badge <?= $statusBadge ?>"><?= ucfirst($movie['status']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($movie['trailer_count'] > 0): ?>
                                            <span class="badge badge-primary"><?= $movie['trailer_count'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $sourceLabel = match($movie['source']) {
                                            'tmdb' => '<span class="badge badge-info">TMDB</span>',
                                            'youtube_cc' => '<span class="badge badge-success">YouTube CC</span>',
                                            'internet_archive' => '<span class="badge badge-warning">Archive</span>',
                                            default => '<span class="badge badge-secondary">Manual</span>'
                                        };
                                        echo $sourceLabel;
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="/admin/movies/<?= $movie['id'] ?>/edit" class="btn btn-sm btn-secondary" title="Edit">
                                                Edit
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="confirmDelete(<?= $movie['id'] ?>, '<?= htmlspecialchars(addslashes($movie['title'])) ?>')">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer">
            <div class="pagination-info">
                Showing <?= (($pagination['page'] - 1) * $pagination['per_page']) + 1 ?> to
                <?= min($pagination['page'] * $pagination['per_page'], $pagination['total']) ?>
                of <?= number_format($pagination['total']) ?> movies
            </div>
            <div class="pagination">
                <?php if ($pagination['page'] > 1): ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] - 1])) ?>" class="pagination-link">
                        <i class="lucide-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $pagination['page'] - 2);
                $end = min($pagination['total_pages'], $pagination['page'] + 2);
                ?>

                <?php if ($start > 1): ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => 1])) ?>" class="pagination-link">1</a>
                    <?php if ($start > 2): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"
                       class="pagination-link <?= $i === $pagination['page'] ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end < $pagination['total_pages']): ?>
                    <?php if ($end < $pagination['total_pages'] - 1): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['total_pages']])) ?>" class="pagination-link"><?= $pagination['total_pages'] ?></a>
                <?php endif; ?>

                <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] + 1])) ?>" class="pagination-link">
                        <i class="lucide-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Movie</h3>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete "<strong id="deleteMovieTitle"></strong>"?</p>
            <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <form id="deleteForm" method="POST" style="display: inline;">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<style>
.header-actions {
    display: flex;
    gap: 0.5rem;
}

.movie-poster-thumb {
    width: 40px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
}

.movie-poster-placeholder {
    width: 40px;
    height: 60px;
    background: var(--bg-hover);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

.movie-title-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.movie-title-cell .badge {
    display: inline-flex;
    width: fit-content;
}

.filters-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-search {
    flex: 2;
    min-width: 250px;
}

.search-input-wrapper {
    position: relative;
}

.search-input-wrapper i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.search-input-wrapper input {
    padding-left: 2.5rem;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

.action-buttons {
    display: flex;
    gap: 0.25rem;
}

.sort-link {
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.sort-link:hover {
    color: var(--text-primary);
}

.bulk-actions-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: rgba(99, 102, 241, 0.1);
    border-bottom: 1px solid var(--border-color);
}

.bulk-buttons {
    display: flex;
    gap: 0.5rem;
}

.pagination {
    display: flex;
    gap: 0.25rem;
}

.pagination-link {
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    background: var(--bg-hover);
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.pagination-link:hover {
    background: var(--border-color);
    color: var(--text-primary);
}

.pagination-link.active {
    background: var(--primary);
    color: white;
}

.pagination-ellipsis {
    padding: 0.5rem 0.25rem;
    color: var(--text-muted);
}

.pagination-info {
    color: var(--text-muted);
    font-size: 0.875rem;
}

.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--border-color);
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
</style>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.movie-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checkboxes = document.querySelectorAll('.movie-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionsBar');
    const countSpan = document.getElementById('selectedCount');

    if (checkboxes.length > 0) {
        bulkBar.style.display = 'flex';
        countSpan.textContent = checkboxes.length;
    } else {
        bulkBar.style.display = 'none';
    }
}

function submitBulkAction(action) {
    if (action === 'delete' && !confirm('Are you sure you want to delete the selected movies?')) {
        return;
    }

    document.getElementById('bulkAction').value = action;
    document.getElementById('bulkForm').submit();
}

function confirmDelete(id, title) {
    document.getElementById('deleteMovieTitle').textContent = title;
    document.getElementById('deleteForm').action = '/admin/movies/' + id + '/delete';
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>
