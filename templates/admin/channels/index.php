<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Channels</h1>
        <p class="page-subtitle">Manage your live TV channels and streaming sources.</p>
    </div>
    <a href="/admin/channels/create" class="btn btn-primary">
        <i class="lucide-plus"></i> Add Channel
    </a>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="lucide-tv"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Channels</div>
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
            <i class="lucide-monitor"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">HD Channels</div>
            <div class="stat-value"><?= number_format($stats['hd']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="lucide-rewind"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">With Catchup</div>
            <div class="stat-value"><?= number_format($stats['catchup']) ?></div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/channels" class="filters-form">
            <div class="filters-row">
                <div class="filter-group filter-search">
                    <div class="search-input-wrapper">
                        <i class="lucide-search"></i>
                        <input type="text" name="search" class="form-input" placeholder="Search channels..."
                               value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                </div>

                <div class="filter-group">
                    <select name="status" class="form-input">
                        <option value="">All Status</option>
                        <option value="1" <?= $filters['status'] === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $filters['status'] === '0' ? 'selected' : '' ?>>Inactive</option>
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
                    <select name="package_id" class="form-input">
                        <option value="">All Packages</option>
                        <?php foreach ($packages as $pkg): ?>
                            <option value="<?= $pkg['id'] ?>" <?= $filters['package_id'] == $pkg['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pkg['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <select name="server_id" class="form-input">
                        <option value="">All Servers</option>
                        <?php foreach ($servers as $srv): ?>
                            <option value="<?= $srv['id'] ?>" <?= $filters['server_id'] == $srv['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($srv['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="lucide-filter"></i> Filter
                    </button>
                    <a href="/admin/channels" class="btn btn-secondary btn-sm">
                        <i class="lucide-x"></i> Clear
                    </a>
                </div>
            </div>

            <!-- Hidden sort fields -->
            <input type="hidden" name="sort" value="<?= htmlspecialchars($filters['sort']) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($filters['dir']) ?>">
        </form>
    </div>
</div>

<!-- Channels Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <form id="bulkForm" method="POST" action="/admin/channels/bulk">
            <input type="hidden" name="_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" id="bulkAction" value="">

            <?php if (!empty($channels)): ?>
                <!-- Bulk Actions Bar -->
                <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
                    <div class="bulk-info">
                        <span id="selectedCount">0</span> channel(s) selected
                    </div>
                    <div class="bulk-buttons">
                        <button type="button" class="btn btn-success btn-sm" onclick="submitBulkAction('activate')">
                            <i class="lucide-check"></i> Activate
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" onclick="submitBulkAction('deactivate')">
                            <i class="lucide-x"></i> Deactivate
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
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <th style="width: 60px;">Logo</th>
                            <th>
                                <a href="<?= buildSortUrl('name', $filters) ?>" class="sort-link">
                                    Title
                                    <?= getSortIcon('name', $filters) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= buildSortUrl('key_code', $filters) ?>" class="sort-link">
                                    Key Code
                                    <?= getSortIcon('key_code', $filters) ?>
                                </a>
                            </th>
                            <th>Server</th>
                            <th>EPG Update</th>
                            <th>Categories</th>
                            <th>Packages</th>
                            <th>
                                <a href="<?= buildSortUrl('is_active', $filters) ?>" class="sort-link">
                                    Status
                                    <?= getSortIcon('is_active', $filters) ?>
                                </a>
                            </th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($channels)): ?>
                            <tr>
                                <td colspan="10" class="text-muted" style="text-align: center; padding: 3rem;">
                                    <div class="empty-state">
                                        <i class="lucide-tv" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                                        <p>No channels found.</p>
                                        <a href="/admin/channels/create" class="btn btn-primary btn-sm" style="margin-top: 1rem;">
                                            <i class="lucide-plus"></i> Add Your First Channel
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($channels as $channel): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="channel_ids[]" value="<?= $channel['id'] ?>"
                                               class="channel-checkbox" onchange="updateBulkSelection()">
                                    </td>
                                    <td>
                                        <?php if (!empty($channel['logo_url'])): ?>
                                            <img src="<?= htmlspecialchars($channel['logo_url']) ?>"
                                                 alt="<?= htmlspecialchars($channel['name']) ?>"
                                                 class="channel-logo">
                                        <?php else: ?>
                                            <div class="channel-logo-placeholder">
                                                <i class="lucide-tv"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="channel-title">
                                            <strong><?= htmlspecialchars($channel['name']) ?></strong>
                                            <div class="channel-meta">
                                                <?php if ($channel['is_hd']): ?>
                                                    <span class="badge badge-info">HD</span>
                                                <?php endif; ?>
                                                <?php if ($channel['is_4k']): ?>
                                                    <span class="badge badge-primary">4K</span>
                                                <?php endif; ?>
                                                <?php if ($channel['catchup_days'] > 0): ?>
                                                    <span class="badge badge-warning" title="Catchup: <?= $channel['catchup_days'] ?> <?= $channel['catchup_period_type'] ?? 'days' ?>">
                                                        <i class="lucide-rewind"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-sm font-mono"><?= htmlspecialchars($channel['key_code'] ?? '-') ?></td>
                                    <td class="text-sm">
                                        <?php if ($channel['server_name']): ?>
                                            <span class="server-badge server-<?= htmlspecialchars($channel['server_type'] ?? 'external') ?>">
                                                <?= htmlspecialchars($channel['server_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">External URL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm text-muted">
                                        <?= $channel['epg_last_update'] ? date('M j, H:i', strtotime($channel['epg_last_update'])) : 'never' ?>
                                    </td>
                                    <td class="text-sm">
                                        <?php if (!empty($channel['category_names'])): ?>
                                            <?php
                                            $cats = explode(', ', $channel['category_names']);
                                            $displayCats = array_slice($cats, 0, 2);
                                            ?>
                                            <?= htmlspecialchars(implode(', ', $displayCats)) ?>
                                            <?php if (count($cats) > 2): ?>
                                                <span class="text-muted">+<?= count($cats) - 2 ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm">
                                        <?php if (!empty($channel['package_names'])): ?>
                                            <?php
                                            $pkgs = explode(', ', $channel['package_names']);
                                            $displayPkgs = array_slice($pkgs, 0, 2);
                                            ?>
                                            <?= htmlspecialchars(implode(', ', $displayPkgs)) ?>
                                            <?php if (count($pkgs) > 2): ?>
                                                <span class="text-muted">+<?= count($pkgs) - 2 ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($channel['is_active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="/admin/channels/<?= $channel['id'] ?>/edit" class="btn btn-secondary btn-sm" title="Edit">
                                                <i class="lucide-edit"></i>
                                            </a>
                                            <div class="dropdown action-dropdown">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleActionMenu(this)" title="More">
                                                    <i class="lucide-more-vertical"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <form action="/admin/channels/<?= $channel['id'] ?>/toggle-status" method="POST">
                                                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="lucide-<?= $channel['is_active'] ? 'eye-off' : 'eye' ?>"></i>
                                                            <?= $channel['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                        </button>
                                                    </form>
                                                    <div class="dropdown-divider"></div>
                                                    <form action="/admin/channels/<?= $channel['id'] ?>/delete" method="POST"
                                                          onsubmit="return confirm('Are you sure you want to delete this channel?');">
                                                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="lucide-trash-2"></i>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer">
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <?= (($pagination['page'] - 1) * $pagination['per_page']) + 1 ?>
                        to <?= min($pagination['page'] * $pagination['per_page'], $pagination['total']) ?>
                        of <?= number_format($pagination['total']) ?> channels
                    </div>
                    <div class="pagination">
                        <?php if ($pagination['page'] > 1): ?>
                            <a href="<?= buildPageUrl($pagination['page'] - 1, $filters) ?>" class="pagination-btn">
                                <i class="lucide-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $pagination['page'] - 2);
                        $endPage = min($pagination['total_pages'], $pagination['page'] + 2);
                        ?>

                        <?php if ($startPage > 1): ?>
                            <a href="<?= buildPageUrl(1, $filters) ?>" class="pagination-btn">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="<?= buildPageUrl($i, $filters) ?>"
                               class="pagination-btn <?= $i === $pagination['page'] ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($endPage < $pagination['total_pages']): ?>
                            <?php if ($endPage < $pagination['total_pages'] - 1): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <a href="<?= buildPageUrl($pagination['total_pages'], $filters) ?>" class="pagination-btn">
                                <?= $pagination['total_pages'] ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                            <a href="<?= buildPageUrl($pagination['page'] + 1, $filters) ?>" class="pagination-btn">
                                <i class="lucide-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Helper functions
function buildSortUrl(string $column, array $filters): string {
    $newDir = ($filters['sort'] === $column && $filters['dir'] === 'ASC') ? 'DESC' : 'ASC';
    $params = array_merge($filters, ['sort' => $column, 'dir' => $newDir, 'page' => 1]);
    return '/admin/channels?' . http_build_query($params);
}

function getSortIcon(string $column, array $filters): string {
    if ($filters['sort'] !== $column) {
        return '<i class="lucide-chevrons-up-down sort-icon"></i>';
    }
    return $filters['dir'] === 'ASC'
        ? '<i class="lucide-chevron-up sort-icon active"></i>'
        : '<i class="lucide-chevron-down sort-icon active"></i>';
}

function buildPageUrl(int $page, array $filters): string {
    $params = array_merge($filters, ['page' => $page]);
    return '/admin/channels?' . http_build_query($params);
}
?>

<style>
.filters-form {
    width: 100%;
}

.filters-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
}

.filter-group {
    flex: 0 0 auto;
}

.filter-search {
    flex: 1 1 250px;
    min-width: 200px;
}

.search-input-wrapper {
    position: relative;
}

.search-input-wrapper i {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.search-input-wrapper input {
    padding-left: 2.5rem;
}

.filter-group .form-input {
    min-width: 150px;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
    margin-left: auto;
}

.channel-logo {
    width: 48px;
    height: 36px;
    object-fit: contain;
    border-radius: 6px;
    background: var(--bg-hover);
}

.channel-logo-placeholder {
    width: 48px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-hover);
    border-radius: 6px;
    color: var(--text-muted);
}

.channel-title {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.channel-meta {
    display: flex;
    gap: 0.25rem;
    align-items: center;
}

.channel-meta .badge {
    font-size: 0.65rem;
    padding: 0.125rem 0.375rem;
}

.server-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    background: var(--bg-hover);
}

.server-flussonic { background: rgba(59, 130, 246, 0.15); color: var(--info); }
.server-wowza { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.server-nginx { background: rgba(34, 197, 94, 0.15); color: var(--success); }

.sort-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--text-muted);
    text-decoration: none;
}

.sort-link:hover {
    color: var(--text-primary);
}

.sort-icon {
    font-size: 0.875rem;
    opacity: 0.5;
}

.sort-icon.active {
    opacity: 1;
    color: var(--primary-light);
}

.action-buttons {
    display: flex;
    gap: 0.25rem;
    align-items: center;
}

.action-buttons .btn {
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.action-buttons .btn i {
    font-size: 1rem;
}

.action-dropdown {
    position: relative;
}

.action-dropdown .dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    left: auto;
    min-width: 160px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: var(--shadow-lg);
    z-index: 100;
    padding: 0.5rem 0;
    margin-top: 0.25rem;
}

.action-dropdown.open .dropdown-menu {
    display: block;
}

.dropdown-divider {
    height: 1px;
    background: var(--border-color);
    margin: 0.5rem 0;
}

.action-dropdown .dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: none;
    background: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.875rem;
}

.action-dropdown .dropdown-item:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.bulk-actions-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--bg-hover);
    border-bottom: 1px solid var(--border-color);
}

.bulk-info {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.bulk-buttons {
    display: flex;
    gap: 0.5rem;
}

.pagination-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pagination-info {
    font-size: 0.875rem;
    color: var(--text-muted);
}

.pagination {
    display: flex;
    gap: 0.25rem;
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 0.5rem;
    border-radius: 6px;
    background: var(--bg-hover);
    color: var(--text-secondary);
    font-size: 0.875rem;
    text-decoration: none;
    transition: var(--transition);
}

.pagination-btn:hover {
    background: var(--border-color);
    color: var(--text-primary);
}

.pagination-btn.active {
    background: var(--primary);
    color: white;
}

.pagination-ellipsis {
    display: flex;
    align-items: center;
    padding: 0 0.5rem;
    color: var(--text-muted);
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #16a34a;
}

@media (max-width: 1024px) {
    .filters-row {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-group,
    .filter-search {
        flex: 1 1 100%;
    }

    .filter-actions {
        margin-left: 0;
        justify-content: flex-end;
    }
}

@media (max-width: 768px) {
    .pagination-wrapper {
        flex-direction: column;
        gap: 1rem;
    }

    .table-container {
        font-size: 0.8rem;
    }

    th, td {
        padding: 0.5rem;
    }
}
</style>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.channel-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkSelection();
}

function updateBulkSelection() {
    const checkboxes = document.querySelectorAll('.channel-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionsBar');
    const countSpan = document.getElementById('selectedCount');
    const selectAll = document.getElementById('selectAll');

    if (checkboxes.length > 0) {
        bulkBar.style.display = 'flex';
        countSpan.textContent = checkboxes.length;
    } else {
        bulkBar.style.display = 'none';
    }

    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.channel-checkbox');
    selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
    selectAll.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
}

function submitBulkAction(action) {
    if (action === 'delete' && !confirm('Are you sure you want to delete the selected channels?')) {
        return;
    }

    document.getElementById('bulkAction').value = action;
    document.getElementById('bulkForm').submit();
}

function toggleActionMenu(btn) {
    const dropdown = btn.closest('.dropdown');
    const wasOpen = dropdown.classList.contains('open');

    // Close all dropdowns
    document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('open'));

    // Toggle current
    if (!wasOpen) {
        dropdown.classList.add('open');
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('open'));
    }
});
</script>
