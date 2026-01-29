<?php
$typeLabels = ['live' => 'Live TV', 'vod' => 'Movies', 'series' => 'TV Shows'];
$typeColors = ['live' => 'badge-info', 'vod' => 'badge-warning', 'series' => 'badge-success'];
?>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Categories</h1>
        <p class="page-subtitle">Manage content categories for channels, movies, and TV shows.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="openAddModal()">
            <i class="lucide-plus"></i> Add Category
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="lucide-folder"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Categories</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="lucide-check-circle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Active</div>
            <div class="stat-value"><?= number_format($stats['active']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="lucide-tv"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Live TV</div>
            <div class="stat-value"><?= number_format($stats['live']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="lucide-film"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Movies</div>
            <div class="stat-value"><?= number_format($stats['vod']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="lucide-clapperboard"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">TV Shows</div>
            <div class="stat-value"><?= number_format($stats['series']) ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/categories" class="filters-form">
            <div class="filters-row">
                <div class="filter-group filter-search">
                    <div class="search-input-wrapper">
                        <i class="lucide-search"></i>
                        <input type="text" name="search" class="form-input" placeholder="Search categories..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="live" <?= ($filters['type'] ?? '') === 'live' ? 'selected' : '' ?>>Live TV</option>
                        <option value="vod" <?= ($filters['type'] ?? '') === 'vod' ? 'selected' : '' ?>>Movies</option>
                        <option value="series" <?= ($filters['type'] ?? '') === 'series' ? 'selected' : '' ?>>TV Shows</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="is_active" class="form-select">
                        <option value="">All Status</option>
                        <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="filter-group filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="lucide-search"></i> Filter
                    </button>
                    <a href="/admin/categories" class="btn btn-secondary btn-sm">
                        <i class="lucide-x"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Categories Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (!empty($categories)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="40">#</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Parent</th>
                            <th>Icon</th>
                            <th class="text-center">Items</th>
                            <th class="text-center">Order</th>
                            <th class="text-center">Status</th>
                            <th class="text-right" width="140">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $i => $cat): ?>
                            <tr id="cat-row-<?= $cat['id'] ?>">
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <div class="cat-name-cell">
                                        <?php if (!empty($cat['parent_id'])): ?>
                                            <span class="cat-indent">
                                                <i class="lucide-corner-down-right"></i>
                                            </span>
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                    </div>
                                    <div class="text-muted text-xs"><?= htmlspecialchars($cat['slug']) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $typeColors[$cat['type']] ?? 'badge-secondary' ?>">
                                        <?= $typeLabels[$cat['type']] ?? $cat['type'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($cat['parent_name'])): ?>
                                        <span class="text-muted"><?= htmlspecialchars($cat['parent_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($cat['icon'])): ?>
                                        <i class="<?= htmlspecialchars($cat['icon']) ?>"></i>
                                        <span class="text-muted text-xs"><?= htmlspecialchars($cat['icon']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $itemCount = 0;
                                    $itemLabel = '';
                                    if ($cat['type'] === 'live') {
                                        $itemCount = (int)($cat['channel_count'] ?? 0);
                                        $itemLabel = $itemCount === 1 ? 'channel' : 'channels';
                                    } elseif ($cat['type'] === 'vod') {
                                        $itemCount = (int)($cat['movie_count'] ?? 0);
                                        $itemLabel = $itemCount === 1 ? 'movie' : 'movies';
                                    } else {
                                        $itemCount = (int)($cat['series_count'] ?? 0);
                                        $itemLabel = $itemCount === 1 ? 'show' : 'shows';
                                    }
                                    ?>
                                    <?php if ($itemCount > 0): ?>
                                        <span class="badge badge-secondary"><?= $itemCount ?> <?= $itemLabel ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-muted"><?= (int)$cat['sort_order'] ?></td>
                                <td class="text-center">
                                    <button type="button"
                                        class="badge-toggle <?= $cat['is_active'] ? 'badge-success' : 'badge-danger' ?>"
                                        onclick="toggleActive(<?= $cat['id'] ?>, this)"
                                        title="Click to toggle">
                                        <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                    </button>
                                </td>
                                <td class="text-right">
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($cat)) ?>)">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="lucide-folder-open"></i>
                </div>
                <h3>No categories found</h3>
                <p class="text-muted">
                    <?php if (!empty($filters['search']) || !empty($filters['type']) || $filters['is_active'] !== ''): ?>
                        No categories match your filters.
                    <?php else: ?>
                        Get started by adding your first category.
                    <?php endif; ?>
                </p>
                <button type="button" class="btn btn-primary" onclick="openAddModal()">
                    <i class="lucide-plus"></i> Add Category
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="categoryModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Category</h3>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editId" value="">
            <div class="form-group">
                <label class="form-label" for="catName">Name <span class="required">*</span></label>
                <input type="text" id="catName" class="form-input" placeholder="e.g. Action, Sports, Drama">
            </div>
            <div class="form-group">
                <label class="form-label" for="catType">Type <span class="required">*</span></label>
                <select id="catType" class="form-select" onchange="loadParentCategories()">
                    <option value="live">Live TV</option>
                    <option value="vod" selected>Movies</option>
                    <option value="series">TV Shows</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="catParent">Parent Category</label>
                <select id="catParent" class="form-select">
                    <option value="">None (Top Level)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="catIcon">Icon Class</label>
                <input type="text" id="catIcon" class="form-input" placeholder="e.g. lucide-film">
                <small class="form-hint">Lucide icon class name. Browse at <a href="https://lucide.dev/icons" target="_blank" rel="noopener">lucide.dev/icons</a></small>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label class="form-label" for="catSortOrder">Sort Order</label>
                    <input type="number" id="catSortOrder" class="form-input" min="0" value="0">
                </div>
                <div class="form-group" style="flex:1">
                    <label class="form-label" for="catActive">Status</label>
                    <select id="catActive" class="form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveCategory()" id="saveBtn">
                <i class="lucide-check"></i> Save Category
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Category</h3>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete "<strong id="deleteCatName"></strong>"?</p>
            <p class="text-danger">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()" id="confirmDeleteBtn">
                <i class="lucide-trash-2"></i> Delete
            </button>
        </div>
    </div>
</div>

<style>
/* Category-specific styles */
.cat-name-cell {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.cat-indent {
    color: var(--text-muted);
    font-size: 0.875rem;
}

.badge-toggle {
    cursor: pointer;
    border: none;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    transition: opacity 0.2s ease;
}

.badge-toggle:hover {
    opacity: 0.8;
}

.badge-toggle.badge-success {
    background: var(--success);
    color: white;
}

.badge-toggle.badge-danger {
    background: var(--danger);
    color: white;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: nowrap;
}

.action-buttons .btn {
    white-space: nowrap;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.stat-icon.purple {
    background: rgba(168, 85, 247, 0.15);
    color: #a855f7;
}

.form-row {
    display: flex;
    gap: 1rem;
}

.form-hint {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.form-hint a {
    color: var(--primary);
}

.text-xs {
    font-size: 0.75rem;
}

.required {
    color: var(--danger);
}

/* Table tweaks */
.data-table td {
    vertical-align: middle;
}

/* Empty state */
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

/* Modal styles */
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

/* Toast */
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
let deleteCatId = null;

// All categories data for parent dropdown
const allCategories = <?= json_encode(array_map(function($c) {
    return ['id' => $c['id'], 'name' => $c['name'], 'type' => $c['type'], 'parent_id' => $c['parent_id']];
}, $categories)) ?>;

// ========================================================================
// ADD / EDIT MODAL
// ========================================================================

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Category';
    document.getElementById('editId').value = '';
    document.getElementById('catName').value = '';
    document.getElementById('catType').value = 'vod';
    document.getElementById('catIcon').value = '';
    document.getElementById('catSortOrder').value = '0';
    document.getElementById('catActive').value = '1';
    document.getElementById('catType').disabled = false;
    loadParentCategories();
    document.getElementById('categoryModal').style.display = 'flex';
    document.getElementById('catName').focus();
}

function openEditModal(cat) {
    document.getElementById('modalTitle').textContent = 'Edit Category';
    document.getElementById('editId').value = cat.id;
    document.getElementById('catName').value = cat.name;
    document.getElementById('catType').value = cat.type;
    document.getElementById('catIcon').value = cat.icon || '';
    document.getElementById('catSortOrder').value = cat.sort_order || 0;
    document.getElementById('catActive').value = cat.is_active ? '1' : '0';
    // Disable type change on edit to prevent data inconsistency
    document.getElementById('catType').disabled = true;
    loadParentCategories(cat.type, cat.parent_id, cat.id);
    document.getElementById('categoryModal').style.display = 'flex';
    document.getElementById('catName').focus();
}

function closeModal() {
    document.getElementById('categoryModal').style.display = 'none';
}

function loadParentCategories(type, selectedId, excludeId) {
    type = type || document.getElementById('catType').value;
    const select = document.getElementById('catParent');
    select.innerHTML = '<option value="">None (Top Level)</option>';

    allCategories.forEach(cat => {
        if (cat.type === type && !cat.parent_id && cat.id !== excludeId) {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
            if (cat.id == selectedId) option.selected = true;
            select.appendChild(option);
        }
    });
}

function saveCategory() {
    const editId = document.getElementById('editId').value;
    const name = document.getElementById('catName').value.trim();
    const type = document.getElementById('catType').value;
    const parentId = document.getElementById('catParent').value;
    const icon = document.getElementById('catIcon').value.trim();
    const sortOrder = document.getElementById('catSortOrder').value;
    const isActive = document.getElementById('catActive').value;

    if (!name) {
        showToast('Category name is required.', 'error');
        return;
    }

    const btn = document.getElementById('saveBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="lucide-loader"></i> Saving...';
    btn.disabled = true;

    const url = editId
        ? `/admin/categories/${editId}/update`
        : '/admin/categories/store';

    const body = new URLSearchParams({
        _token: csrfToken,
        name: name,
        type: type,
        parent_id: parentId,
        icon: icon,
        sort_order: sortOrder,
        is_active: isActive,
    });

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Category saved.', 'success');
            closeModal();
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Failed to save category.', 'error');
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
// TOGGLE ACTIVE
// ========================================================================

function toggleActive(id, el) {
    fetch(`/admin/categories/${id}/toggle-active`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const isActive = el.classList.contains('badge-danger');
            el.classList.toggle('badge-success', isActive);
            el.classList.toggle('badge-danger', !isActive);
            el.textContent = isActive ? 'Active' : 'Inactive';
        } else {
            showToast(data.message || 'Failed to update status.', 'error');
        }
    })
    .catch(() => {
        showToast('Network error.', 'error');
    });
}

// ========================================================================
// DELETE
// ========================================================================

function deleteCategory(id, name) {
    deleteCatId = id;
    document.getElementById('deleteCatName').textContent = name;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteCatId = null;
}

function confirmDelete() {
    if (!deleteCatId) return;

    const btn = document.getElementById('confirmDeleteBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="lucide-loader"></i> Deleting...';
    btn.disabled = true;

    fetch(`/admin/categories/${deleteCatId}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Category deleted.', 'success');
            closeDeleteModal();
            const row = document.getElementById('cat-row-' + deleteCatId);
            if (row) {
                row.style.transition = 'opacity 0.3s ease';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
        } else {
            showToast(data.message || 'Failed to delete category.', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Network error.', 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// ========================================================================
// TOAST & HELPERS
// ========================================================================

function showToast(message, type) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icon = type === 'success' ? 'lucide-check-circle' : 'lucide-alert-circle';
    toast.innerHTML = `<i class="${icon}"></i> ${escapeHtml(message)}`;

    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
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

// Close modals on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            if (modal.style.display === 'flex') {
                modal.style.display = 'none';
            }
        });
    }
});
</script>
