<?php
$pageTitle = 'Subscribers';
$countries = [
    'AG' => 'Antigua and Barbuda', 'BS' => 'Bahamas', 'BB' => 'Barbados', 'BZ' => 'Belize',
    'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'GD' => 'Grenada', 'GY' => 'Guyana',
    'HT' => 'Haiti', 'JM' => 'Jamaica', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia',
    'VC' => 'Saint Vincent and the Grenadines', 'SR' => 'Suriname', 'TT' => 'Trinidad and Tobago',
    'CU' => 'Cuba', 'PR' => 'Puerto Rico', 'VI' => 'US Virgin Islands', 'VG' => 'British Virgin Islands',
    'KY' => 'Cayman Islands', 'TC' => 'Turks and Caicos Islands', 'AW' => 'Aruba', 'CW' => 'Curacao',
    'SX' => 'Sint Maarten', 'BM' => 'Bermuda', 'AI' => 'Anguilla', 'MS' => 'Montserrat',
    'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'MX' => 'Mexico',
    'BR' => 'Brazil', 'CO' => 'Colombia', 'VE' => 'Venezuela', 'PA' => 'Panama', 'CR' => 'Costa Rica',
];
asort($countries);
?>

<style>
    /* Groups Section */
    .groups-grid { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .group-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.25rem; min-width: 160px; cursor: pointer; transition: all 0.2s; position: relative; text-align: center; }
    .group-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .group-card.create-card { border-style: dashed; border-color: var(--border-color); }
    .group-card.create-card:hover { border-color: var(--primary); }
    .group-card-icon { font-size: 1.75rem; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 10px; margin: 0 auto 0.5rem; }
    .group-card-name { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); }
    .group-card-count { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }
    .group-card-actions { position: absolute; top: 0.5rem; right: 0.5rem; display: none; }
    .group-card:hover .group-card-actions { display: flex; gap: 0.25rem; }
    .group-card-actions button { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.2rem; font-size: 0.75rem; border-radius: 4px; }
    .group-card-actions button:hover { background: rgba(255,255,255,0.1); color: var(--text-primary); }
    .group-card-actions .delete-btn:hover { color: var(--danger); }

    /* Stats row */
    .stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .stat-pill { display: flex; align-items: center; gap: 0.5rem; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem 1rem; }
    .stat-pill-value { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
    .stat-pill-label { font-size: 0.75rem; color: var(--text-muted); }
    .stat-pill.active .stat-pill-value { color: var(--success); }
    .stat-pill.inactive .stat-pill-value { color: var(--text-muted); }
    .stat-pill.suspended .stat-pill-value { color: var(--warning); }

    /* Subscriber table */
    .subscriber-row { display: flex; align-items: center; gap: 0.75rem; }
    .subscriber-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--bg-dark); display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 0.8rem; flex-shrink: 0; overflow: hidden; }
    .subscriber-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .subscriber-name { font-weight: 500; color: var(--text-primary); }
    .subscriber-email { font-size: 0.8rem; color: var(--text-muted); }

    /* Modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #1e293b; border: 1px solid #475569; border-radius: 12px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8); }
    .modal-box.small { max-width: 480px; }
    .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; background: #1e293b; position: sticky; top: 0; z-index: 10; border-radius: 12px 12px 0 0; }
    .modal-header h3 { font-size: 1rem; font-weight: 600; color: #f1f5f9; }
    .modal-body { padding: 1.25rem; background: #1e293b; }
    .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid #334155; display: flex; justify-content: flex-end; gap: 0.5rem; background: #1e293b; position: sticky; bottom: 0; z-index: 10; border-radius: 0 0 12px 12px; }
    .modal-close { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.25rem; padding: 0.25rem; }
    .modal-close:hover { color: #f1f5f9; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    .form-full { grid-column: 1 / -1; }
    .section-divider { font-size: 0.8rem; font-weight: 600; color: var(--primary-light); margin: 1.25rem 0 0.75rem; padding-bottom: 0.4rem; border-bottom: 1px solid var(--border-color); text-transform: uppercase; letter-spacing: 0.05em; }
    .section-divider:first-child { margin-top: 0; }

    .checkbox-row { display: flex; gap: 1.5rem; flex-wrap: wrap; padding: 0.75rem 0; }
    .checkbox-label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; cursor: pointer; }
    .checkbox-label input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); }

    .help-text { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }

    /* Color swatches */
    .color-options { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.25rem; }
    .color-swatch { width: 28px; height: 28px; border-radius: 6px; cursor: pointer; border: 2px solid transparent; transition: all 0.15s; }
    .color-swatch:hover, .color-swatch.selected { border-color: white; transform: scale(1.15); }

    /* Bulk actions bar */
    .bulk-bar { display: none; align-items: center; gap: 1rem; padding: 0.75rem 1rem; background: var(--primary); border-radius: 8px; margin-bottom: 1rem; color: white; }
    .bulk-bar.active { display: flex; }
    .bulk-bar .bulk-count { font-weight: 600; }
    .bulk-bar .btn { background: rgba(255,255,255,0.2); color: white; border: none; padding: 0.4rem 0.75rem; font-size: 0.8rem; }
    .bulk-bar .btn:hover { background: rgba(255,255,255,0.3); }

    /* Pagination */
    .pagination-wrapper { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-top: 1px solid var(--border-color); }
    .pagination-info { font-size: 0.8rem; color: var(--text-muted); }
    .pagination-controls { display: flex; gap: 0.25rem; }
    .page-btn { padding: 0.4rem 0.75rem; background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); cursor: pointer; font-size: 0.8rem; text-decoration: none; }
    .page-btn:hover { border-color: var(--primary); }
    .page-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
    .page-btn.disabled { opacity: 0.5; pointer-events: none; }

    .per-page-select { display: flex; align-items: center; gap: 0.5rem; }
    .per-page-select label { font-size: 0.8rem; color: var(--text-muted); }
    .per-page-select select { background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); padding: 0.3rem 0.5rem; font-size: 0.8rem; }
</style>

<!-- Groups Section -->
<div class="groups-grid">
    <!-- Create Group Card -->
    <div class="group-card create-card" onclick="openGroupModal()">
        <div class="group-card-icon" style="background: rgba(99,102,241,0.15); color: var(--primary-light);">
            <i class="lucide-plus"></i>
        </div>
        <div class="group-card-name">Create Group</div>
    </div>

    <?php foreach ($groups as $group): ?>
        <div class="group-card" onclick="filterByGroup(<?= $group['id'] ?>)">
            <div class="group-card-actions">
                <button onclick="event.stopPropagation(); editGroup(<?= $group['id'] ?>, <?= htmlspecialchars(json_encode($group), ENT_QUOTES) ?>)" title="Edit">
                    <i class="lucide-pencil"></i>
                </button>
                <button class="delete-btn" onclick="event.stopPropagation(); deleteGroup(<?= $group['id'] ?>, '<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>')" title="Delete">
                    <i class="lucide-trash-2"></i>
                </button>
            </div>
            <div class="group-card-icon" style="background: <?= htmlspecialchars($group['color']) ?>22; color: <?= htmlspecialchars($group['color']) ?>;">
                <i class="<?= htmlspecialchars($group['icon'] ?: 'lucide-users') ?>"></i>
            </div>
            <div class="group-card-name"><?= htmlspecialchars($group['name']) ?></div>
            <div class="group-card-count"><?= number_format($group['member_count']) ?> subscribers</div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Page Header -->
<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Subscribers</h1>
    </div>
    <div class="header-actions" style="display: flex; gap: 0.5rem;">
        <button class="btn btn-primary" onclick="openSubscriberModal()">
            <i class="lucide-user-plus"></i> Create Subscriber
        </button>
    </div>
</div>

<!-- Stats Row -->
<div class="stats-row">
    <div class="stat-pill">
        <div>
            <div class="stat-pill-value"><?= number_format($stats['total']) ?></div>
            <div class="stat-pill-label">Total</div>
        </div>
    </div>
    <div class="stat-pill active">
        <div>
            <div class="stat-pill-value"><?= number_format($stats['active']) ?></div>
            <div class="stat-pill-label">Active</div>
        </div>
    </div>
    <div class="stat-pill inactive">
        <div>
            <div class="stat-pill-value"><?= number_format($stats['inactive']) ?></div>
            <div class="stat-pill-label">Inactive</div>
        </div>
    </div>
    <div class="stat-pill suspended">
        <div>
            <div class="stat-pill-value"><?= number_format($stats['suspended']) ?></div>
            <div class="stat-pill-label">Suspended</div>
        </div>
    </div>
</div>

<!-- Filters & Search -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/subscribers" class="filters-form">
            <div class="filters-row" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <div class="filter-group filter-search" style="flex: 1; min-width: 200px;">
                    <div style="position: relative;">
                        <i class="lucide-search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                        <input type="text" name="search" class="form-input" placeholder="Search..."
                               value="<?= htmlspecialchars($filters['search']) ?>"
                               style="padding-left: 2.25rem;">
                    </div>
                </div>
                <div class="filter-group">
                    <select name="status" class="form-input" style="min-width: 160px;">
                        <option value="">All Status</option>
                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $filters['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        <option value="expired" <?= $filters['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="group_id" class="form-input" style="min-width: 160px;">
                        <option value="">All Groups</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $filters['group_id'] == $g['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">
                    <i class="lucide-search"></i>
                </button>
                <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['group_id'])): ?>
                    <a href="/admin/subscribers" class="btn btn-secondary" style="color: var(--danger);" title="Clear filters">
                        <i class="lucide-x"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div class="bulk-bar" id="bulkBar">
    <span class="bulk-count"><span id="bulkCount">0</span> selected</span>
    <button class="btn" onclick="bulkAction('activate')"><i class="lucide-check"></i> Activate</button>
    <button class="btn" onclick="bulkAction('deactivate')"><i class="lucide-x"></i> Deactivate</button>
    <button class="btn" onclick="bulkAction('suspend')"><i class="lucide-ban"></i> Suspend</button>
    <button class="btn" onclick="bulkAction('delete')" style="background: rgba(239,68,68,0.4);"><i class="lucide-trash-2"></i> Delete</button>
    <button class="btn" onclick="clearSelection()" style="margin-left: auto;"><i class="lucide-x"></i> Cancel</button>
</div>

<!-- Subscribers Table -->
<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                    </th>
                    <th>Name</th>
                    <th>Groups</th>
                    <th>Status</th>
                    <th>Connections</th>
                    <th>Created</th>
                    <th style="width: 100px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscribers)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="lucide-users" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                            No subscribers found.
                            <br><button class="btn btn-primary btn-sm" style="margin-top: 0.75rem;" onclick="openSubscriberModal()">Create Subscriber</button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subscribers as $sub): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="row-select" value="<?= $sub['id'] ?>" onchange="updateBulkBar()">
                            </td>
                            <td>
                                <div class="subscriber-row">
                                    <div class="subscriber-avatar">
                                        <?php if (!empty($sub['avatar'])): ?>
                                            <img src="<?= htmlspecialchars($sub['avatar']) ?>" alt="">
                                        <?php else: ?>
                                            <i class="lucide-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="subscriber-name">
                                            <?= htmlspecialchars(trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? '')) ?: ($sub['username'] ?: 'Unnamed')) ?>
                                        </div>
                                        <?php if (!empty($sub['email'])): ?>
                                            <div class="subscriber-email"><?= htmlspecialchars($sub['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($sub['group_names'])): ?>
                                    <?php foreach (explode(', ', $sub['group_names']) as $gn): ?>
                                        <span class="badge badge-secondary" style="font-size: 0.7rem; margin-right: 0.25rem;"><?= htmlspecialchars($gn) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusColors = [
                                    'active' => 'badge-success',
                                    'inactive' => 'badge-secondary',
                                    'suspended' => 'badge-warning',
                                    'expired' => 'badge-danger',
                                ];
                                $statusClass = $statusColors[$sub['status']] ?? 'badge-secondary';
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= ucfirst($sub['status']) ?></span>
                                <?php if ($sub['is_disabled']): ?>
                                    <span class="badge badge-danger" style="font-size: 0.65rem;">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?= (int) $sub['max_connections'] ?>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">
                                <?= date('M j, Y', strtotime($sub['created_at'])) ?>
                            </td>
                            <td>
                                <div class="table-actions" style="display: flex; gap: 0.25rem; justify-content: flex-end;">
                                    <button class="btn btn-sm btn-secondary" onclick="editSubscriber(<?= $sub['id'] ?>)" title="Edit">
                                        <i class="lucide-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="toggleSubscriberStatus(<?= $sub['id'] ?>)" title="Toggle Status">
                                        <i class="lucide-<?= $sub['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteSubscriber(<?= $sub['id'] ?>, '<?= htmlspecialchars(trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? '')), ENT_QUOTES) ?>')" title="Delete">
                                        <i class="lucide-trash-2"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Showing <?= (($pagination['page'] - 1) * $pagination['per_page']) + 1 ?>
                to <?= min($pagination['page'] * $pagination['per_page'], $pagination['total']) ?>
                of <?= number_format($pagination['total']) ?> subscribers
            </div>
            <div class="pagination-controls">
                <?php if ($pagination['page'] > 1): ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] - 1])) ?>" class="page-btn">
                        <i class="lucide-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $pagination['page'] - 2);
                $end = min($pagination['total_pages'], $pagination['page'] + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"
                       class="page-btn <?= $i === (int)$pagination['page'] ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['page'] + 1])) ?>" class="page-btn">
                        <i class="lucide-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="per-page-select">
                <label>Show</label>
                <select onchange="window.location='?<?= http_build_query(array_merge($filters, ['page' => 1])) ?>&per_page='+this.value">
                    <?php foreach ([10, 25, 50, 100] as $pp): ?>
                        <option value="<?= $pp ?>" <?= (int)$pagination['per_page'] === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
                <label>entries</label>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ========== SUBSCRIBER MODAL ========== -->
<div class="modal-overlay" id="subscriberModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="subscriberModalTitle">Create Subscriber</h3>
            <button class="modal-close" onclick="closeSubscriberModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="subId" value="">

            <!-- Checkboxes -->
            <div class="checkbox-row">
                <label class="checkbox-label">
                    <input type="checkbox" id="subDisabled"> Disabled
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" id="subEmailVerified" checked> Email Verified
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" id="subPhoneVerified" checked> Phone Verified
                </label>
            </div>

            <div class="section-divider">General</div>

            <div class="form-grid">
                <div>
                    <label class="form-label">First Name</label>
                    <input type="text" id="subFirstName" class="form-input" placeholder="First name">
                </div>
                <div>
                    <label class="form-label">Last Name</label>
                    <input type="text" id="subLastName" class="form-input" placeholder="Last name">
                </div>
            </div>

            <div class="form-grid-3" style="margin-top: 0.75rem;">
                <div>
                    <label class="form-label">Username</label>
                    <input type="text" id="subUsername" class="form-input" placeholder="Username">
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" id="subEmail" class="form-input" placeholder="Email address">
                </div>
                <div>
                    <label class="form-label">Phone</label>
                    <input type="text" id="subPhone" class="form-input" placeholder="Phone number">
                </div>
            </div>

            <div class="form-grid" style="margin-top: 0.75rem;">
                <div>
                    <label class="form-label">Password</label>
                    <input type="password" id="subPassword" class="form-input" placeholder="Leave blank to keep unchanged">
                    <div class="help-text">Leave blank when editing to keep the existing password</div>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select id="subStatus" class="form-input">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
            </div>

            <div class="section-divider">Settings</div>

            <div class="form-grid">
                <div>
                    <label class="form-label">NPVR Limit (min)</label>
                    <input type="number" id="subNpvrLimit" class="form-input" value="0" min="0">
                </div>
                <div>
                    <label class="form-label">Max Concurrent Connections</label>
                    <input type="number" id="subMaxConnections" class="form-input" value="1" min="0">
                </div>
            </div>

            <div class="form-grid" style="margin-top: 0.75rem;">
                <div>
                    <label class="form-label">Birthday</label>
                    <input type="date" id="subBirthday" class="form-input">
                </div>
                <div>
                    <label class="form-label">Parental Pin</label>
                    <input type="text" id="subParentalPin" class="form-input" value="0000" maxlength="10" placeholder="0000">
                </div>
            </div>

            <div class="section-divider">Location</div>

            <div class="form-grid">
                <div>
                    <label class="form-label">Country</label>
                    <select id="subCountry" class="form-input">
                        <option value="">Select...</option>
                        <?php foreach ($countries as $code => $name): ?>
                            <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">City</label>
                    <input type="text" id="subCity" class="form-input" placeholder="City">
                </div>
            </div>

            <div class="form-grid" style="margin-top: 0.75rem;">
                <div>
                    <label class="form-label">Address</label>
                    <input type="text" id="subAddress" class="form-input" placeholder="Street address">
                </div>
                <div>
                    <label class="form-label">ZIP Code</label>
                    <input type="text" id="subZipCode" class="form-input" placeholder="ZIP / Postal code">
                </div>
            </div>

            <div class="section-divider">Additional</div>

            <div class="form-grid">
                <div>
                    <label class="form-label">Groups</label>
                    <select id="subGroups" class="form-input" multiple style="min-height: 80px;">
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text">Hold Ctrl/Cmd to select multiple</div>
                </div>
                <div>
                    <label class="form-label">External ID</label>
                    <input type="text" id="subExternalId" class="form-input" placeholder="External system reference">
                    <div class="help-text">Reference ID from an external system</div>
                </div>
            </div>

            <div style="margin-top: 0.75rem;">
                <label class="form-label">Notes</label>
                <textarea id="subNotes" class="form-input" rows="2" placeholder="Internal notes about this subscriber"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeSubscriberModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveSubscriber()" id="subscriberSaveBtn">Save</button>
        </div>
    </div>
</div>

<!-- ========== GROUP MODAL ========== -->
<div class="modal-overlay" id="groupModal">
    <div class="modal-box small">
        <div class="modal-header">
            <h3 id="groupModalTitle">Create Group</h3>
            <button class="modal-close" onclick="closeGroupModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="groupId" value="">

            <div style="margin-bottom: 0.75rem;">
                <label class="form-label">Group Name</label>
                <input type="text" id="groupName" class="form-input" placeholder="e.g. IPTV, DTH, Premium">
            </div>

            <div style="margin-bottom: 0.75rem;">
                <label class="form-label">Description</label>
                <textarea id="groupDescription" class="form-input" rows="2" placeholder="Optional description"></textarea>
            </div>

            <div style="margin-bottom: 0.75rem;">
                <label class="form-label">Color</label>
                <div class="color-options" id="groupColorOptions">
                    <?php
                    $colors = ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b', '#22c55e', '#06b6d4', '#3b82f6', '#64748b'];
                    foreach ($colors as $c):
                    ?>
                        <div class="color-swatch <?= $c === '#6366f1' ? 'selected' : '' ?>"
                             style="background: <?= $c ?>;"
                             data-color="<?= $c ?>"
                             onclick="selectGroupColor(this, '<?= $c ?>')"></div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="groupColor" value="#6366f1">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeGroupModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveGroup()" id="groupSaveBtn">Save</button>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $csrf ?>';

// ========== TOAST NOTIFICATION ==========
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;top:1.5rem;right:1.5rem;z-index:9999;padding:0.75rem 1.25rem;border-radius:8px;color:#fff;font-size:0.875rem;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,0.4);transition:opacity 0.3s;max-width:400px;`;
    toast.style.background = type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#3b82f6';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
}

// ========== SUBSCRIBER MODAL ==========

function openSubscriberModal(id = null) {
    document.getElementById('subId').value = '';
    document.getElementById('subscriberModalTitle').textContent = 'Create Subscriber';
    document.getElementById('subscriberSaveBtn').textContent = 'Create Subscriber';
    document.getElementById('subscriberSaveBtn').disabled = false;

    // Reset all fields
    document.getElementById('subFirstName').value = '';
    document.getElementById('subLastName').value = '';
    document.getElementById('subUsername').value = '';
    document.getElementById('subEmail').value = '';
    document.getElementById('subPhone').value = '';
    document.getElementById('subPassword').value = '';
    document.getElementById('subStatus').value = 'active';
    document.getElementById('subDisabled').checked = false;
    document.getElementById('subEmailVerified').checked = true;
    document.getElementById('subPhoneVerified').checked = true;
    document.getElementById('subNpvrLimit').value = '0';
    document.getElementById('subMaxConnections').value = '1';
    document.getElementById('subBirthday').value = '';
    document.getElementById('subParentalPin').value = '0000';
    document.getElementById('subCountry').value = '';
    document.getElementById('subCity').value = '';
    document.getElementById('subAddress').value = '';
    document.getElementById('subZipCode').value = '';
    document.getElementById('subExternalId').value = '';
    document.getElementById('subNotes').value = '';

    // Reset groups multi-select
    const groupSelect = document.getElementById('subGroups');
    Array.from(groupSelect.options).forEach(o => o.selected = false);

    document.getElementById('subscriberModal').classList.add('active');
}

function closeSubscriberModal() {
    document.getElementById('subscriberModal').classList.remove('active');
}

function editSubscriber(id) {
    fetch(`/admin/subscribers/${id}/show`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || 'Error loading subscriber', 'error');
                return;
            }

            const s = data.subscriber;
            document.getElementById('subId').value = s.id;
            document.getElementById('subscriberModalTitle').textContent = 'Edit Subscriber';
            document.getElementById('subscriberSaveBtn').textContent = 'Save Changes';
            document.getElementById('subscriberSaveBtn').disabled = false;

            document.getElementById('subFirstName').value = s.first_name || '';
            document.getElementById('subLastName').value = s.last_name || '';
            document.getElementById('subUsername').value = s.username || '';
            document.getElementById('subEmail').value = s.email || '';
            document.getElementById('subPhone').value = s.phone || '';
            document.getElementById('subPassword').value = '';
            document.getElementById('subStatus').value = s.status || 'active';
            document.getElementById('subDisabled').checked = s.is_disabled == 1;
            document.getElementById('subEmailVerified').checked = s.email_verified == 1;
            document.getElementById('subPhoneVerified').checked = s.phone_verified == 1;
            document.getElementById('subNpvrLimit').value = s.npvr_limit || 0;
            document.getElementById('subMaxConnections').value = s.max_connections || 1;
            document.getElementById('subBirthday').value = s.birthday || '';
            document.getElementById('subParentalPin').value = s.parental_pin || '0000';
            document.getElementById('subCountry').value = s.country || '';
            document.getElementById('subCity').value = s.city || '';
            document.getElementById('subAddress').value = s.address || '';
            document.getElementById('subZipCode').value = s.zip_code || '';
            document.getElementById('subExternalId').value = s.external_id || '';
            document.getElementById('subNotes').value = s.notes || '';

            // Set groups
            const groupSelect = document.getElementById('subGroups');
            const groupIds = (s.group_ids || []).map(String);
            Array.from(groupSelect.options).forEach(o => {
                o.selected = groupIds.includes(o.value);
            });

            document.getElementById('subscriberModal').classList.add('active');
        })
        .catch(() => showToast('Error loading subscriber', 'error'));
}

function saveSubscriber() {
    const id = document.getElementById('subId').value;
    const url = id ? `/admin/subscribers/${id}/update` : '/admin/subscribers/store';
    const btn = document.getElementById('subscriberSaveBtn');

    if (btn.disabled) return;
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const formData = new URLSearchParams();
    formData.append('_token', csrfToken);
    formData.append('first_name', document.getElementById('subFirstName').value);
    formData.append('last_name', document.getElementById('subLastName').value);
    formData.append('username', document.getElementById('subUsername').value);
    formData.append('email', document.getElementById('subEmail').value);
    formData.append('phone', document.getElementById('subPhone').value);
    formData.append('status', document.getElementById('subStatus').value);
    formData.append('npvr_limit', document.getElementById('subNpvrLimit').value);
    formData.append('max_connections', document.getElementById('subMaxConnections').value);
    formData.append('birthday', document.getElementById('subBirthday').value);
    formData.append('parental_pin', document.getElementById('subParentalPin').value);
    formData.append('country', document.getElementById('subCountry').value);
    formData.append('city', document.getElementById('subCity').value);
    formData.append('address', document.getElementById('subAddress').value);
    formData.append('zip_code', document.getElementById('subZipCode').value);
    formData.append('external_id', document.getElementById('subExternalId').value);
    formData.append('notes', document.getElementById('subNotes').value);

    const password = document.getElementById('subPassword').value;
    if (password) formData.append('password', password);

    if (document.getElementById('subDisabled').checked) formData.append('is_disabled', '1');
    if (document.getElementById('subEmailVerified').checked) formData.append('email_verified', '1');
    if (document.getElementById('subPhoneVerified').checked) formData.append('phone_verified', '1');

    // Groups (multi-select)
    const groupSelect = document.getElementById('subGroups');
    Array.from(groupSelect.selectedOptions).forEach(o => {
        formData.append('groups[]', o.value);
    });

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            location.reload();
        } else {
            showToast(data.message || 'Error saving subscriber', 'error');
            btn.disabled = false;
            btn.textContent = id ? 'Save Changes' : 'Create Subscriber';
        }
    })
    .catch(() => {
        showToast('Error saving subscriber', 'error');
        btn.disabled = false;
        btn.textContent = id ? 'Save Changes' : 'Create Subscriber';
    });
}

function deleteSubscriber(id, name) {
    if (!confirm(`Delete subscriber "${name || 'this subscriber'}"? This cannot be undone.`)) return;

    fetch(`/admin/subscribers/${id}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + csrfToken
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            location.reload();
        } else {
            showToast(data.message || 'Error deleting subscriber', 'error');
        }
    })
    .catch(() => showToast('Error deleting subscriber', 'error'));
}

function toggleSubscriberStatus(id) {
    fetch(`/admin/subscribers/${id}/toggle-status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + csrfToken
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            location.reload();
        } else {
            showToast(data.message || 'Error', 'error');
        }
    })
    .catch(() => showToast('Error toggling status', 'error'));
}

// ========== BULK ACTIONS ==========

function toggleSelectAll(checkbox) {
    document.querySelectorAll('.row-select').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.row-select:checked');
    const bar = document.getElementById('bulkBar');
    document.getElementById('bulkCount').textContent = checked.length;
    bar.classList.toggle('active', checked.length > 0);
}

function clearSelection() {
    document.querySelectorAll('.row-select').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateBulkBar();
}

function bulkAction(action) {
    const ids = Array.from(document.querySelectorAll('.row-select:checked')).map(cb => cb.value);
    if (ids.length === 0) return;

    if (action === 'delete' && !confirm(`Delete ${ids.length} subscriber(s)? This cannot be undone.`)) return;

    fetch('/admin/subscribers/bulk', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_token=${csrfToken}&action=${action}&ids=${ids.join(',')}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            location.reload();
        } else {
            showToast(data.message || 'Error', 'error');
        }
    })
    .catch(() => showToast('Error performing bulk action', 'error'));
}

// ========== GROUP MODAL ==========

function openGroupModal() {
    document.getElementById('groupId').value = '';
    document.getElementById('groupModalTitle').textContent = 'Create Group';
    document.getElementById('groupSaveBtn').textContent = 'Create Group';
    document.getElementById('groupSaveBtn').disabled = false;
    document.getElementById('groupName').value = '';
    document.getElementById('groupDescription').value = '';
    document.getElementById('groupColor').value = '#6366f1';

    // Reset color swatches
    document.querySelectorAll('#groupColorOptions .color-swatch').forEach(s => {
        s.classList.toggle('selected', s.dataset.color === '#6366f1');
    });

    document.getElementById('groupModal').classList.add('active');
}

function closeGroupModal() {
    document.getElementById('groupModal').classList.remove('active');
}

function editGroup(id, group) {
    document.getElementById('groupId').value = id;
    document.getElementById('groupModalTitle').textContent = 'Edit Group';
    document.getElementById('groupSaveBtn').textContent = 'Save Changes';
    document.getElementById('groupSaveBtn').disabled = false;
    document.getElementById('groupName').value = group.name || '';
    document.getElementById('groupDescription').value = group.description || '';
    document.getElementById('groupColor').value = group.color || '#6366f1';

    // Set color swatch
    document.querySelectorAll('#groupColorOptions .color-swatch').forEach(s => {
        s.classList.toggle('selected', s.dataset.color === (group.color || '#6366f1'));
    });

    document.getElementById('groupModal').classList.add('active');
}

function selectGroupColor(el, color) {
    document.querySelectorAll('#groupColorOptions .color-swatch').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('groupColor').value = color;
}

function saveGroup() {
    const id = document.getElementById('groupId').value;
    const url = id ? `/admin/subscribers/groups/${id}/update` : '/admin/subscribers/groups/store';
    const btn = document.getElementById('groupSaveBtn');

    if (btn.disabled) return;

    const name = document.getElementById('groupName').value.trim();
    if (!name) {
        showToast('Group name is required', 'error');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    const formData = new URLSearchParams();
    formData.append('_token', csrfToken);
    formData.append('name', name);
    formData.append('description', document.getElementById('groupDescription').value);
    formData.append('color', document.getElementById('groupColor').value);

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            location.reload();
        } else {
            showToast(data.message || 'Error saving group', 'error');
            btn.disabled = false;
            btn.textContent = id ? 'Save Changes' : 'Create Group';
        }
    })
    .catch(() => {
        showToast('Error saving group', 'error');
        btn.disabled = false;
        btn.textContent = id ? 'Save Changes' : 'Create Group';
    });
}

function deleteGroup(id, name) {
    if (!confirm(`Delete group "${name}"? Subscribers will be removed from this group but not deleted.`)) return;

    fetch(`/admin/subscribers/groups/${id}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + csrfToken
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            location.reload();
        } else {
            showToast(data.message || 'Error deleting group', 'error');
        }
    })
    .catch(() => showToast('Error deleting group', 'error'));
}

function filterByGroup(groupId) {
    window.location = `/admin/subscribers?group_id=${groupId}`;
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});
</script>
