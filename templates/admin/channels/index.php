<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Channels</h1>
        <p class="page-subtitle">Manage your live TV channels and streaming sources.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-info" onclick="openIptvOrgModal()">
            <i class="lucide-globe"></i> Import from IPTV-org
        </button>
        <a href="/admin/channels/create" class="btn btn-primary">
            <i class="lucide-plus"></i> Add Channel
        </a>
    </div>
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
                                            <a href="/admin/channels/<?= $channel['id'] ?>/edit" class="btn btn-secondary btn-sm">Edit</a>
                                            <button type="button" class="btn btn-<?= $channel['is_active'] ? 'warning' : 'success' ?> btn-sm" onclick="toggleChannelStatus(<?= $channel['id'] ?>)"><?= $channel['is_active'] ? 'Disable' : 'Enable' ?></button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteChannel(<?= $channel['id'] ?>)">Delete</button>
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
    gap: 0.5rem;
    align-items: center;
    flex-wrap: nowrap;
}

.action-buttons .btn {
    white-space: nowrap;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
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

// Toggle channel status
function toggleChannelStatus(id) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/channels/' + id + '/toggle-status';

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = '<?= $csrf ?>';
    form.appendChild(token);

    document.body.appendChild(form);
    form.submit();
}

// Delete channel
function deleteChannel(id) {
    if (!confirm('Are you sure you want to delete this channel?')) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/channels/' + id + '/delete';

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = '<?= $csrf ?>';
    form.appendChild(token);

    document.body.appendChild(form);
    form.submit();
}
</script>

<!-- IPTV-org Import Modal -->
<div class="modal-overlay" id="iptvOrgModal" style="display: none;">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h3><i class="lucide-globe"></i> Import from IPTV-org</h3>
            <button type="button" class="modal-close" onclick="closeIptvOrgModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Search Filters -->
            <div class="import-filters">
                <div class="import-filter-row">
                    <div class="import-filter-group import-filter-search">
                        <input type="text" id="iptvSearch" class="form-input" placeholder="Search channels by name...">
                    </div>
                    <div class="import-filter-group">
                        <select id="iptvCountry" class="form-select">
                            <option value="">All Countries</option>
                        </select>
                    </div>
                    <div class="import-filter-group">
                        <select id="iptvCategory" class="form-select">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="searchIptvOrg()" id="iptvSearchBtn">
                        <i class="lucide-search"></i> Search
                    </button>
                </div>
                <div class="import-quick-filters">
                    <span class="text-muted text-sm">Quick:</span>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickFilter('TT')">Trinidad</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickFilter('JM')">Jamaica</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickFilter('BB')">Barbados</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickFilter('GY')">Guyana</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickFilter('BS')">Bahamas</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickFilter('LC')">St. Lucia</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickFilter('DO')">Dom. Rep.</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="quickFilter('PR')">Puerto Rico</button>
                </div>
            </div>

            <!-- Results -->
            <div id="iptvResults" class="import-results">
                <div class="import-empty">
                    <i class="lucide-globe"></i>
                    <p>Search by country or channel name to find channels.<br>
                    <span class="text-muted">Over 39,000 channels available from the iptv-org database.</span></p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="import-footer-info">
                <span id="iptvSelectedCount">0</span> channel(s) selected
            </div>
            <button type="button" class="btn btn-secondary" onclick="closeIptvOrgModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="importSelectedChannels()" id="iptvImportBtn" disabled>
                <i class="lucide-download"></i> Import Selected
            </button>
        </div>
    </div>
</div>

<!-- Stream Preview Modal -->
<div class="modal-overlay" id="previewModal" style="display: none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3 id="previewTitle">Stream Preview</h3>
            <button type="button" class="modal-close" onclick="closePreview()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 0; background: #000;">
            <video id="previewPlayer" controls autoplay style="width: 100%; max-height: 60vh; display: block;"></video>
            <div id="previewError" class="preview-error" style="display: none;">
                <i class="lucide-alert-triangle"></i>
                <p>Unable to play this stream. It may be offline, geo-restricted, or require a specific player.</p>
            </div>
        </div>
        <div class="modal-footer">
            <div class="preview-info">
                <span id="previewQuality" class="badge badge-info"></span>
                <span id="previewUrl" class="text-muted text-xs" style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block;"></span>
            </div>
            <button type="button" class="btn btn-secondary" onclick="closePreview()">Close</button>
        </div>
    </div>
</div>

<style>
/* IPTV-org Import Modal */
.modal-xl {
    max-width: 960px;
}

.modal-lg {
    max-width: 720px;
}

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
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    flex-shrink: 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
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
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}

.modal-footer {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    justify-content: flex-end;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    flex-shrink: 0;
}

.import-footer-info {
    margin-right: auto;
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
}

/* Import Filters */
.import-filters {
    margin-bottom: 1rem;
}

.import-filter-row {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 0.75rem;
}

.import-filter-group {
    flex: 0 0 auto;
}

.import-filter-search {
    flex: 1 1 200px;
}

.import-filter-search input {
    width: 100%;
}

.import-filter-group select {
    min-width: 150px;
}

.import-quick-filters {
    display: flex;
    gap: 0.375rem;
    align-items: center;
    flex-wrap: wrap;
}

.import-quick-filters .text-sm {
    font-size: 0.8rem;
}

/* Import Results */
.import-results {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
}

.import-empty {
    text-align: center;
    padding: 3rem 1.5rem;
    color: var(--text-muted);
}

.import-empty i {
    font-size: 3rem;
    opacity: 0.3;
    display: block;
    margin-bottom: 0.75rem;
}

.import-loading {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

.import-table {
    width: 100%;
    border-collapse: collapse;
}

.import-table th {
    position: sticky;
    top: 0;
    background: var(--bg-card);
    z-index: 1;
    text-transform: uppercase;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-muted);
    letter-spacing: 0.05em;
    padding: 0.625rem 0.75rem;
    border-bottom: 2px solid var(--border-color);
    text-align: left;
}

.import-table td {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.875rem;
    vertical-align: middle;
}

.import-table tr:hover {
    background: rgba(255, 255, 255, 0.03);
}

.import-table .ch-logo {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    object-fit: contain;
    background: var(--bg-hover);
}

.import-table .ch-logo-placeholder {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    background: var(--bg-hover);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-size: 0.875rem;
}

.import-table .ch-name {
    font-weight: 600;
}

.import-table .ch-country {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.import-actions {
    display: flex;
    gap: 0.375rem;
    align-items: center;
}

/* Stream Preview */
.preview-error {
    text-align: center;
    padding: 3rem 1.5rem;
    color: var(--danger);
    background: rgba(239, 68, 68, 0.1);
}

.preview-error i {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 0.75rem;
}

.preview-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-right: auto;
}

/* Button styles */
.btn-info {
    background: var(--info, #3b82f6);
    color: white;
}

.btn-info:hover {
    background: #2563eb;
}

.btn-preview {
    padding: 0.2rem 0.4rem;
    font-size: 0.7rem;
    background: rgba(99, 102, 241, 0.15);
    color: var(--primary);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 4px;
    cursor: pointer;
    white-space: nowrap;
}

.btn-preview:hover {
    background: rgba(99, 102, 241, 0.25);
}

.header-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
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

.toast.show { transform: translateX(0); }
.toast-success { background: var(--success); }
.toast-error { background: var(--danger); }
</style>

<!-- HLS.js for stream preview -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest/dist/hls.min.js"></script>

<script>
const csrfToken = '<?= $csrf ?>';
let iptvResults = [];
let selectedChannels = {};
let hlsInstance = null;

// ========================================================================
// IPTV-ORG MODAL
// ========================================================================

function openIptvOrgModal() {
    document.getElementById('iptvOrgModal').style.display = 'flex';
    loadIptvOrgFilters();
}

function closeIptvOrgModal() {
    document.getElementById('iptvOrgModal').style.display = 'none';
    selectedChannels = {};
    updateSelectedCount();
}

function loadIptvOrgFilters() {
    fetch('/admin/channels/iptv-org-countries')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;

            const countrySelect = document.getElementById('iptvCountry');
            countrySelect.innerHTML = '<option value="">All Countries</option>';

            // Caribbean countries first
            const caribbean = ['TT','JM','BB','GY','BS','AG','DM','GD','KN','LC','VC','BZ','SR','HT','CU','DO','PR','AW','CW'];
            const caribCountries = data.countries.filter(c => caribbean.includes(c.code));
            const otherCountries = data.countries.filter(c => !caribbean.includes(c.code));

            if (caribCountries.length > 0) {
                const group1 = document.createElement('optgroup');
                group1.label = 'Caribbean';
                caribCountries.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.code;
                    opt.textContent = `${c.flag} ${c.name}`;
                    group1.appendChild(opt);
                });
                countrySelect.appendChild(group1);

                const group2 = document.createElement('optgroup');
                group2.label = 'All Countries';
                otherCountries.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.code;
                    opt.textContent = `${c.flag} ${c.name}`;
                    group2.appendChild(opt);
                });
                countrySelect.appendChild(group2);
            }

            const catSelect = document.getElementById('iptvCategory');
            catSelect.innerHTML = '<option value="">All Categories</option>';
            (data.categories || []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                catSelect.appendChild(opt);
            });
        })
        .catch(() => {});
}

function quickFilter(countryCode) {
    document.getElementById('iptvCountry').value = countryCode;
    document.getElementById('iptvSearch').value = '';
    document.getElementById('iptvCategory').value = '';
    searchIptvOrg();
}

// ========================================================================
// SEARCH
// ========================================================================

function searchIptvOrg() {
    const search = document.getElementById('iptvSearch').value.trim();
    const country = document.getElementById('iptvCountry').value;
    const category = document.getElementById('iptvCategory').value;

    if (!search && !country && !category) {
        showToast('Please enter a search term or select a country.', 'error');
        return;
    }

    const btn = document.getElementById('iptvSearchBtn');
    btn.innerHTML = '<i class="lucide-loader"></i> Searching...';
    btn.disabled = true;

    const resultsEl = document.getElementById('iptvResults');
    resultsEl.innerHTML = '<div class="import-loading"><div class="spinner"></div><p>Searching iptv-org database...</p></div>';

    const body = new URLSearchParams({ search, country, category });

    fetch('/admin/channels/search-iptv-org', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = '<i class="lucide-search"></i> Search';
        btn.disabled = false;

        if (!data.success) {
            resultsEl.innerHTML = `<div class="import-empty"><i class="lucide-alert-circle"></i><p>${escapeHtml(data.message || 'Search failed.')}</p></div>`;
            return;
        }

        iptvResults = data.results || [];

        if (iptvResults.length === 0) {
            resultsEl.innerHTML = '<div class="import-empty"><i class="lucide-search-x"></i><p>No channels found matching your search.</p></div>';
            return;
        }

        renderResults(iptvResults, data.total, data.showing);
    })
    .catch(() => {
        btn.innerHTML = '<i class="lucide-search"></i> Search';
        btn.disabled = false;
        resultsEl.innerHTML = '<div class="import-empty"><i class="lucide-wifi-off"></i><p>Network error. Please try again.</p></div>';
    });
}

function renderResults(results, total, showing) {
    const resultsEl = document.getElementById('iptvResults');

    let html = `<div style="padding: 0.5rem 0.75rem; font-size: 0.8rem; color: var(--text-muted); border-bottom: 1px solid var(--border-color);">
        Showing ${showing} of ${total} channels
        ${total > showing ? ' (refine your search for more)' : ''}
    </div>`;

    html += `<table class="import-table">
        <thead><tr>
            <th width="30"><input type="checkbox" onchange="toggleSelectAllImport(this)"></th>
            <th width="40"></th>
            <th>Channel</th>
            <th>Country</th>
            <th>Category</th>
            <th>Quality</th>
            <th width="120">Actions</th>
        </tr></thead><tbody>`;

    results.forEach((ch, i) => {
        const checked = selectedChannels[ch.id] ? 'checked' : '';
        const hasStream = ch.has_stream;
        const logo = ch.logo_url
            ? `<img src="${escapeHtml(ch.logo_url)}" class="ch-logo" onerror="this.outerHTML='<div class=\\'ch-logo-placeholder\\'><i class=\\'lucide-tv\\'></i></div>'">`
            : '<div class="ch-logo-placeholder"><i class="lucide-tv"></i></div>';

        html += `<tr>
            <td><input type="checkbox" ${checked} ${!hasStream ? 'disabled title="No stream available"' : ''} onchange="toggleChannelSelect('${escapeHtml(ch.id)}', ${i}, this.checked)"></td>
            <td>${logo}</td>
            <td>
                <div class="ch-name">${escapeHtml(ch.name)}</div>
                ${ch.network ? `<div class="ch-country">${escapeHtml(ch.network)}</div>` : ''}
            </td>
            <td><span class="ch-country">${escapeHtml(ch.country || '')}</span></td>
            <td>${(ch.categories || []).map(c => `<span class="badge badge-secondary" style="font-size:0.65rem">${escapeHtml(c)}</span>`).join(' ')}</td>
            <td>
                ${hasStream
                    ? `<span class="badge badge-success">${escapeHtml(ch.stream_quality || 'Available')}</span>`
                    : '<span class="badge badge-danger">No stream</span>'}
            </td>
            <td>
                <div class="import-actions">
                    ${hasStream ? `<button type="button" class="btn-preview" onclick="previewStream(${i})"><i class="lucide-play"></i> Preview</button>` : ''}
                </div>
            </td>
        </tr>`;
    });

    html += '</tbody></table>';
    resultsEl.innerHTML = html;
}

// ========================================================================
// SELECTION
// ========================================================================

function toggleSelectAllImport(masterCheckbox) {
    const checked = masterCheckbox.checked;
    const checkboxes = document.querySelectorAll('.import-table tbody input[type="checkbox"]:not([disabled])');

    checkboxes.forEach((cb, idx) => {
        cb.checked = checked;
        const row = cb.closest('tr');
        const rowIdx = parseInt(row.querySelector('input[type="checkbox"]').getAttribute('onchange').match(/(\d+)/g)[1]);
        const ch = iptvResults[rowIdx];
        if (ch) {
            if (checked) {
                selectedChannels[ch.id] = ch;
            } else {
                delete selectedChannels[ch.id];
            }
        }
    });

    updateSelectedCount();
}

function toggleChannelSelect(channelId, idx, checked) {
    if (checked) {
        selectedChannels[channelId] = iptvResults[idx];
    } else {
        delete selectedChannels[channelId];
    }
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = Object.keys(selectedChannels).length;
    document.getElementById('iptvSelectedCount').textContent = count;
    document.getElementById('iptvImportBtn').disabled = count === 0;
}

// ========================================================================
// IMPORT
// ========================================================================

function importSelectedChannels() {
    const channels = Object.values(selectedChannels).map(ch => ({
        name: ch.name,
        stream_url: ch.stream_url,
        logo_url: ch.logo_url || '',
        country: ch.country || '',
        quality: ch.stream_quality || '',
        categories: ch.categories || [],
        iptv_org_id: ch.id,
    }));

    if (channels.length === 0) {
        showToast('No channels selected.', 'error');
        return;
    }

    const btn = document.getElementById('iptvImportBtn');
    btn.innerHTML = '<i class="lucide-loader"></i> Importing...';
    btn.disabled = true;

    const body = new URLSearchParams({
        _token: csrfToken,
        channels: JSON.stringify(channels)
    });

    fetch('/admin/channels/import-iptv-org', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = '<i class="lucide-download"></i> Import Selected';

        if (data.success) {
            showToast(data.message || 'Import completed.', 'success');
            closeIptvOrgModal();
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message || 'Import failed.', 'error');
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Network error during import.', 'error');
        btn.innerHTML = '<i class="lucide-download"></i> Import Selected';
        btn.disabled = false;
    });
}

// ========================================================================
// STREAM PREVIEW
// ========================================================================

function previewStream(idx) {
    const ch = iptvResults[idx];
    if (!ch || !ch.stream_url) return;

    document.getElementById('previewTitle').textContent = ch.name;
    document.getElementById('previewQuality').textContent = ch.stream_quality || 'Unknown';
    document.getElementById('previewUrl').textContent = ch.stream_url;
    document.getElementById('previewError').style.display = 'none';

    const video = document.getElementById('previewPlayer');
    video.style.display = 'block';

    // Destroy previous HLS instance
    if (hlsInstance) {
        hlsInstance.destroy();
        hlsInstance = null;
    }

    const url = ch.stream_url;

    if (url.includes('.m3u8') && Hls.isSupported()) {
        hlsInstance = new Hls({
            enableWorker: true,
            lowLatencyMode: false,
        });
        hlsInstance.loadSource(url);
        hlsInstance.attachMedia(video);
        hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
            video.play().catch(() => {});
        });
        hlsInstance.on(Hls.Events.ERROR, (event, data) => {
            if (data.fatal) {
                video.style.display = 'none';
                document.getElementById('previewError').style.display = 'block';
            }
        });
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Native HLS (Safari)
        video.src = url;
        video.addEventListener('loadedmetadata', () => video.play().catch(() => {}), { once: true });
        video.addEventListener('error', () => {
            video.style.display = 'none';
            document.getElementById('previewError').style.display = 'block';
        }, { once: true });
    } else {
        // Try direct play
        video.src = url;
        video.addEventListener('error', () => {
            video.style.display = 'none';
            document.getElementById('previewError').style.display = 'block';
        }, { once: true });
    }

    document.getElementById('previewModal').style.display = 'flex';
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
    const video = document.getElementById('previewPlayer');
    video.pause();
    video.removeAttribute('src');
    video.load();
    if (hlsInstance) {
        hlsInstance.destroy();
        hlsInstance = null;
    }
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
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Allow Enter key in search
document.getElementById('iptvSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchIptvOrg();
    }
});

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            if (this.id === 'previewModal') closePreview();
            else closeIptvOrgModal();
        }
    });
});

// Close modals on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('previewModal').style.display === 'flex') {
            closePreview();
        } else if (document.getElementById('iptvOrgModal').style.display === 'flex') {
            closeIptvOrgModal();
        }
    }
});
</script>
