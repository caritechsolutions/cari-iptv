<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">TV Shows</h1>
        <p class="page-subtitle">Manage your TV show library with TMDB and Fanart.tv integration.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/series/create" class="btn btn-primary">
            <i class="lucide-plus"></i> Add TV Show
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="lucide-clapperboard"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total TV Shows</div>
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
            <i class="lucide-layers"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Seasons</div>
            <div class="stat-value"><?= number_format($stats['total_seasons']) ?></div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/series" class="filters-form">
            <div class="filters-row">
                <div class="filter-group filter-search">
                    <div class="search-input-wrapper">
                        <i class="lucide-search"></i>
                        <input type="text" name="search" class="form-input" placeholder="Search TV shows..."
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
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="lucide-filter"></i> Filter
                    </button>
                    <a href="/admin/series" class="btn btn-secondary btn-sm">
                        <i class="lucide-x"></i> Clear
                    </a>
                </div>
            </div>

            <input type="hidden" name="sort" value="<?= htmlspecialchars($filters['sort']) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($filters['dir']) ?>">
        </form>
    </div>
</div>

<!-- TV Shows Grid/Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <form id="bulkForm" method="POST" action="/admin/series/bulk">
            <input type="hidden" name="_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" id="bulkAction" value="">

            <?php if (!empty($shows)): ?>
                <!-- Bulk Actions Bar -->
                <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
                    <div class="bulk-info">
                        <span id="selectedCount">0</span> TV show(s) selected
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
                            <th width="100">Seasons</th>
                            <th>Categories</th>
                            <th width="100">Status</th>
                            <th width="100">Source</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shows)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted" style="padding: 3rem;">
                                    <i class="lucide-clapperboard" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="mt-2">No TV shows found. <a href="/admin/series/create">Add your first TV show</a>.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shows as $show): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="<?= $show['id'] ?>" class="show-checkbox" onchange="updateBulkBar()">
                                    </td>
                                    <td>
                                        <?php if ($show['poster_url']): ?>
                                            <img src="<?= htmlspecialchars($show['poster_url']) ?>" alt="" class="show-poster-thumb">
                                        <?php else: ?>
                                            <div class="show-poster-placeholder">
                                                <i class="lucide-clapperboard"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="show-title-cell">
                                            <strong><?= htmlspecialchars($show['title']) ?></strong>
                                            <?php if ($show['is_featured']): ?>
                                                <span class="badge badge-warning" title="Featured"><i class="lucide-star"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= $show['year'] ?: '-' ?></td>
                                    <td>
                                        <?php if ($show['number_of_seasons'] > 0): ?>
                                            <span class="badge badge-primary"><?= $show['number_of_seasons'] ?> season<?= $show['number_of_seasons'] !== 1 ? 's' : '' ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($show['category_names']): ?>
                                            <span class="text-sm"><?= htmlspecialchars($show['category_names']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadge = match($show['status']) {
                                            'published' => 'badge-success',
                                            'draft' => 'badge-warning',
                                            'archived' => 'badge-danger',
                                            default => 'badge-info'
                                        };
                                        ?>
                                        <span class="badge <?= $statusBadge ?>"><?= ucfirst($show['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $sourceLabel = match($show['source']) {
                                            'tmdb' => '<span class="badge badge-info">TMDB</span>',
                                            default => '<span class="badge badge-secondary">Manual</span>'
                                        };
                                        echo $sourceLabel;
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="/admin/series/<?= $show['id'] ?>/seasons" class="btn btn-sm btn-secondary" title="Manage Seasons">
                                                Seasons
                                            </a>
                                            <a href="/admin/series/<?= $show['id'] ?>/edit" class="btn btn-sm btn-outline" title="Edit Info">
                                                Edit Info
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="confirmDelete(<?= $show['id'] ?>, '<?= htmlspecialchars(addslashes($show['title'])) ?>')">
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
                of <?= number_format($pagination['total']) ?> TV shows
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
            <h3>Delete TV Show</h3>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete "<strong id="deleteShowTitle"></strong>"?</p>
            <p class="text-danger">This will also delete all seasons and episodes. This action cannot be undone.</p>
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

.show-poster-thumb {
    width: 40px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
}

.show-poster-placeholder {
    width: 40px;
    height: 60px;
    background: var(--bg-hover);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

.show-title-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.show-title-cell .badge {
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

.btn-outline {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
}

.btn-outline:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
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
    const checkboxes = document.querySelectorAll('.show-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checkboxes = document.querySelectorAll('.show-checkbox:checked');
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
    if (action === 'delete' && !confirm('Are you sure you want to delete the selected TV shows?')) {
        return;
    }

    document.getElementById('bulkAction').value = action;
    document.getElementById('bulkForm').submit();
}

function confirmDelete(id, title) {
    document.getElementById('deleteShowTitle').textContent = title;
    document.getElementById('deleteForm').action = '/admin/series/' + id + '/delete';
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>
