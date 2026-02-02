<?php $pageTitle = 'Advertising'; ?>

<style>
    .header-actions { display: flex; gap: 0.5rem; }
    .filters-form { display: flex; }
    .filters-row { display: flex; gap: 0.75rem; width: 100%; align-items: center; flex-wrap: wrap; }
    .filter-group { min-width: 140px; }
    .filter-search { flex: 1; min-width: 200px; }
    .search-input-wrapper { position: relative; }
    .search-input-wrapper i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
    .search-input-wrapper input { padding-left: 2.5rem; }
    .type-badges { display: flex; gap: 0.25rem; flex-wrap: wrap; }
    .type-badge { font-size: 0.65rem; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 500; }
    .type-badge.text_scroller { background: rgba(59, 130, 246, 0.15); color: var(--info); }
    .type-badge.banner { background: rgba(34, 197, 94, 0.15); color: var(--success); }
    .type-badge.pre_roll { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
    .type-badge.mid_roll { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
    .campaign-meta { font-size: 0.8rem; color: var(--text-muted); }
    .campaign-meta span { margin-right: 1rem; }
    .progress-bar-container { width: 100px; height: 6px; background: var(--bg-hover); border-radius: 3px; overflow: hidden; }
    .progress-bar-fill { height: 100%; background: var(--primary); border-radius: 3px; transition: width 0.3s; }
    .bulk-actions { display: none; padding: 0.75rem 1rem; background: rgba(99, 102, 241, 0.1); border-bottom: 1px solid var(--border-color); }
    .bulk-actions.show { display: flex; align-items: center; gap: 0.75rem; }
    .pagination { display: flex; justify-content: center; gap: 0.25rem; margin-top: 1.5rem; }
    .pagination a, .pagination span { padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.875rem; }
    .pagination a { background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary); }
    .pagination a:hover { background: var(--bg-hover); color: var(--text-primary); }
    .pagination .active { background: var(--primary); color: white; border: 1px solid var(--primary); }
</style>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Advertising</h1>
        <p class="page-subtitle">Manage ad campaigns, creatives, zones, and placements.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/ads/zones" class="btn btn-secondary">
            <i class="lucide-layout"></i> Ad Zones
        </a>
        <a href="/admin/ads/reports" class="btn btn-secondary">
            <i class="lucide-bar-chart-2"></i> Reports
        </a>
        <a href="/admin/ads/create" class="btn btn-primary">
            <i class="lucide-plus"></i> New Campaign
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="lucide-megaphone"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Campaigns</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="lucide-play-circle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Active</div>
            <div class="stat-value"><?= number_format($stats['active']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="lucide-eye"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Impressions</div>
            <div class="stat-value"><?= number_format($stats['total_impressions']) ?></div>
            <?php if ($stats['impressions_today'] > 0): ?>
                <div class="stat-change positive">+<?= number_format($stats['impressions_today']) ?> today</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="lucide-mouse-pointer-click"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Clicks</div>
            <div class="stat-value"><?= number_format($stats['total_clicks']) ?></div>
            <?php if ($stats['ctr'] > 0): ?>
                <div class="stat-change"><?= $stats['ctr'] ?>% CTR</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/ads" class="filters-form">
            <div class="filters-row">
                <div class="filter-group filter-search">
                    <div class="search-input-wrapper">
                        <i class="lucide-search"></i>
                        <input type="text" name="search" class="form-input" placeholder="Search campaigns..."
                               value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <select name="status" class="form-input">
                        <option value="">All Status</option>
                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="paused" <?= $filters['status'] === 'paused' ? 'selected' : '' ?>>Paused</option>
                        <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="type" class="form-input">
                        <option value="">All Types</option>
                        <?php foreach ($adTypes as $key => $type): ?>
                            <option value="<?= $key ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= $type['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['type'])): ?>
                    <a href="/admin/ads" class="btn btn-secondary btn-sm">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Campaigns Table -->
<div class="card">
    <!-- Bulk Actions -->
    <div class="bulk-actions" id="bulkActions">
        <span id="selectedCount">0</span> selected
        <form method="POST" action="/admin/ads/bulk" id="bulkForm" style="display:flex;gap:0.5rem;align-items:center;">
            <input type="hidden" name="_token" value="<?= $csrf ?>">
            <div id="bulkIds"></div>
            <select name="action" class="form-input" style="width:auto;padding:0.375rem 0.75rem;font-size:0.8rem;">
                <option value="">Choose action...</option>
                <option value="activate">Activate</option>
                <option value="pause">Pause</option>
                <option value="delete">Delete</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="selectAll"></th>
                    <th>Campaign</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Schedule</th>
                    <th>Impressions</th>
                    <th>Clicks</th>
                    <th>CTR</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($campaigns)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:3rem;color:var(--text-muted);">
                            <i class="lucide-megaphone" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                            No campaigns found. <a href="/admin/ads/create">Create your first campaign</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td><input type="checkbox" class="item-checkbox" value="<?= $campaign['id'] ?>"></td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($campaign['name']) ?></div>
                                <?php if ($campaign['advertiser']): ?>
                                    <div class="campaign-meta"><?= htmlspecialchars($campaign['advertiser']) ?></div>
                                <?php endif; ?>
                                <div class="campaign-meta">
                                    <span><?= $campaign['creative_count'] ?> creative(s)</span>
                                    <span><?= $campaign['placement_count'] ?> placement(s)</span>
                                </div>
                            </td>
                            <td>
                                <div class="type-badges">
                                    <?php
                                    $types = $campaign['creative_types'] ? explode(',', $campaign['creative_types']) : [];
                                    foreach ($types as $t):
                                        $typeInfo = $adTypes[$t] ?? null;
                                    ?>
                                        <span class="type-badge <?= $t ?>"><?= $typeInfo ? $typeInfo['name'] : $t ?></span>
                                    <?php endforeach; ?>
                                    <?php if (empty($types)): ?>
                                        <span class="type-badge" style="color:var(--text-muted);">No creatives</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $statusColors = [
                                    'active' => 'success', 'paused' => 'warning', 'draft' => 'info',
                                    'completed' => 'primary', 'archived' => 'danger'
                                ];
                                $color = $statusColors[$campaign['status']] ?? 'info';
                                ?>
                                <span class="badge badge-<?= $color ?>"><?= ucfirst($campaign['status']) ?></span>
                            </td>
                            <td>
                                <?php if ($campaign['start_date'] || $campaign['end_date']): ?>
                                    <div style="font-size:0.8rem;">
                                        <?php if ($campaign['start_date']): ?>
                                            <?= date('M j, Y', strtotime($campaign['start_date'])) ?>
                                        <?php else: ?>
                                            No start
                                        <?php endif; ?>
                                        <br>
                                        <?php if ($campaign['end_date']): ?>
                                            <?= date('M j, Y', strtotime($campaign['end_date'])) ?>
                                        <?php else: ?>
                                            No end
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:0.8rem;">Always on</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= number_format($campaign['total_impressions']) ?>
                                <?php if ($campaign['total_impressions_cap']): ?>
                                    <div class="progress-bar-container" style="margin-top:4px;">
                                        <div class="progress-bar-fill" style="width: <?= min(100, round(($campaign['total_impressions'] / $campaign['total_impressions_cap']) * 100)) ?>%;"></div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($campaign['total_clicks']) ?></td>
                            <td>
                                <?php
                                $ctr = $campaign['total_impressions'] > 0
                                    ? round(($campaign['total_clicks'] / $campaign['total_impressions']) * 100, 2)
                                    : 0;
                                ?>
                                <?= $ctr ?>%
                            </td>
                            <td>
                                <div style="display:flex;gap:0.25rem;">
                                    <a href="/admin/ads/<?= $campaign['id'] ?>/edit" class="btn btn-secondary btn-sm">Edit</a>
                                    <button type="button" class="btn btn-<?= $campaign['status'] === 'active' ? 'warning' : 'success' ?> btn-sm"
                                            onclick="toggleCampaign(<?= $campaign['id'] ?>)">
                                        <?= $campaign['status'] === 'active' ? 'Pause' : 'Activate' ?>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteCampaign(<?= $campaign['id'] ?>, '<?= htmlspecialchars(addslashes($campaign['name'])) ?>')">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination">
        <?php if ($pagination['page'] > 1): ?>
            <a href="/admin/ads?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] - 1])) ?>">Prev</a>
        <?php endif; ?>

        <?php
        $start = max(1, $pagination['page'] - 2);
        $end = min($pagination['total_pages'], $pagination['page'] + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
            <?php if ($i == $pagination['page']): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="/admin/ads?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($pagination['page'] < $pagination['total_pages']): ?>
            <a href="/admin/ads?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] + 1])) ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="_token" value="<?= $csrf ?>">
</form>

<script>
// Select all checkbox
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.checked = this.checked;
    });
    updateBulkActions();
});

document.querySelectorAll('.item-checkbox').forEach(cb => {
    cb.addEventListener('change', updateBulkActions);
});

function updateBulkActions() {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    const bulkEl = document.getElementById('bulkActions');
    const countEl = document.getElementById('selectedCount');
    const idsEl = document.getElementById('bulkIds');

    if (checked.length > 0) {
        bulkEl.classList.add('show');
        countEl.textContent = checked.length;
        idsEl.innerHTML = '';
        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            idsEl.appendChild(input);
        });
    } else {
        bulkEl.classList.remove('show');
    }
}

function toggleCampaign(id) {
    if (!confirm('Toggle campaign status?')) return;

    fetch(`/admin/ads/${id}/toggle-status`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_token=<?= $csrf ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message || 'Error toggling campaign');
    });
}

function deleteCampaign(id, name) {
    if (!confirm(`Delete campaign "${name}"? This will also delete all creatives, placements, and targeting rules.`)) return;

    const form = document.getElementById('deleteForm');
    form.action = `/admin/ads/${id}/delete`;
    form.submit();
}

document.getElementById('bulkForm')?.addEventListener('submit', function(e) {
    const action = this.querySelector('select[name="action"]').value;
    if (!action) {
        e.preventDefault();
        alert('Please select an action.');
        return;
    }
    if (action === 'delete' && !confirm('Delete selected campaigns? This cannot be undone.')) {
        e.preventDefault();
    }
});
</script>
