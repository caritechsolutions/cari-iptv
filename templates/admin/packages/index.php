<?php
$pageTitle = 'Packages';
$countries = [
    'AG' => 'Antigua and Barbuda', 'BS' => 'Bahamas', 'BB' => 'Barbados', 'BZ' => 'Belize',
    'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'GD' => 'Grenada', 'GY' => 'Guyana',
    'HT' => 'Haiti', 'JM' => 'Jamaica', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia',
    'VC' => 'Saint Vincent', 'SR' => 'Suriname', 'TT' => 'Trinidad and Tobago',
    'CU' => 'Cuba', 'PR' => 'Puerto Rico', 'VI' => 'US Virgin Islands', 'VG' => 'British Virgin Islands',
    'KY' => 'Cayman Islands', 'TC' => 'Turks and Caicos', 'AW' => 'Aruba', 'CW' => 'Curacao',
    'SX' => 'Sint Maarten', 'BM' => 'Bermuda', 'AI' => 'Anguilla', 'MS' => 'Montserrat',
    'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
];
asort($countries);
$allPlatforms = ['web' => 'Web', 'mobile' => 'Mobile', 'tv' => 'Smart TV', 'stb' => 'Set-Top Box'];
?>

<style>
    .tab-nav { display: flex; border-bottom: 2px solid #334155; margin-bottom: 1.5rem; gap: 0; }
    .tab-btn { padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer; color: #94a3b8; font-size: 0.9rem; font-weight: 500; transition: all 0.2s; }
    .tab-btn:hover { color: #f1f5f9; }
    .tab-btn.active { color: #818cf8; border-bottom-color: #6366f1; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .stat-pill { display: flex; align-items: center; gap: 0.5rem; background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 0.6rem 1rem; }
    .stat-pill-value { font-size: 1.1rem; font-weight: 700; }
    .stat-pill-label { font-size: 0.75rem; color: #94a3b8; }

    /* Content Group Cards */
    .cg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .cg-card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 1rem; transition: all 0.2s; position: relative; }
    .cg-card:hover { border-color: #6366f1; }
    .cg-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; }
    .cg-card-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .cg-card-actions { display: flex; gap: 0.25rem; }
    .cg-card-actions button { background: none; border: none; color: #94a3b8; cursor: pointer; padding: 0.25rem; border-radius: 4px; font-size: 0.8rem; }
    .cg-card-actions button:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }
    .cg-card-name { font-weight: 600; font-size: 0.95rem; margin-bottom: 0.25rem; }
    .cg-card-desc { font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.5rem; }
    .cg-card-count { font-size: 0.75rem; color: #64748b; }
    .cg-card-count span { font-weight: 600; color: #94a3b8; }
    .cg-card.create-card { border-style: dashed; display: flex; align-items: center; justify-content: center; min-height: 130px; cursor: pointer; }
    .cg-card.create-card:hover { border-color: #6366f1; background: rgba(99,102,241,0.05); }

    /* Package Cards */
    .pkg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; }
    .pkg-card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 1.25rem; transition: all 0.2s; }
    .pkg-card:hover { border-color: #6366f1; }
    .pkg-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; }
    .pkg-card-name { font-weight: 600; font-size: 1.05rem; }
    .pkg-card-price { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }
    .pkg-card-price small { font-size: 0.75rem; font-weight: 400; color: #94a3b8; }
    .pkg-card-period { font-size: 0.8rem; color: #94a3b8; }
    .pkg-card-features { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0.75rem 0; }
    .pkg-feature-tag { font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 4px; background: rgba(99,102,241,0.15); color: #818cf8; }
    .pkg-card-groups { font-size: 0.8rem; color: #94a3b8; margin-top: 0.5rem; }
    .pkg-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #334155; }
    .pkg-card-actions { display: flex; gap: 0.25rem; }

    /* Modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #1e293b; border: 1px solid #475569; border-radius: 12px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8); }
    .modal-box.large { max-width: 850px; }
    .modal-box.small { max-width: 480px; }
    .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; background: #1e293b; position: sticky; top: 0; z-index: 10; border-radius: 12px 12px 0 0; }
    .modal-header h3 { font-size: 1rem; font-weight: 600; color: #f1f5f9; }
    .modal-body { padding: 1.25rem; background: #1e293b; }
    .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid #334155; display: flex; justify-content: flex-end; gap: 0.5rem; background: #1e293b; position: sticky; bottom: 0; z-index: 10; border-radius: 0 0 12px 12px; }
    .modal-close { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.25rem; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    .section-divider { font-size: 0.8rem; font-weight: 600; color: #818cf8; margin: 1.25rem 0 0.75rem; padding-bottom: 0.4rem; border-bottom: 1px solid #334155; text-transform: uppercase; letter-spacing: 0.05em; }
    .section-divider:first-child { margin-top: 0; }
    .help-text { font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }

    .checkbox-row { display: flex; gap: 1.5rem; flex-wrap: wrap; padding: 0.5rem 0; }
    .checkbox-label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; }
    .checkbox-label input[type="checkbox"] { width: 16px; height: 16px; accent-color: #6366f1; }

    .color-options { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.25rem; }
    .color-swatch { width: 26px; height: 26px; border-radius: 6px; cursor: pointer; border: 2px solid transparent; transition: all 0.15s; }
    .color-swatch:hover, .color-swatch.selected { border-color: white; transform: scale(1.15); }

    /* Content items list in group modal */
    .content-items-list { max-height: 200px; overflow-y: auto; border: 1px solid #334155; border-radius: 8px; }
    .content-item { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.75rem; border-bottom: 1px solid #334155; }
    .content-item:last-child { border-bottom: none; }
    .content-item-info { display: flex; align-items: center; gap: 0.5rem; }
    .content-type-badge { font-size: 0.65rem; padding: 0.1rem 0.4rem; border-radius: 3px; background: #334155; color: #94a3b8; text-transform: uppercase; }
    .content-item-remove { background: none; border: none; color: #ef4444; cursor: pointer; padding: 0.25rem; }

    /* Search results dropdown */
    .search-results { max-height: 180px; overflow-y: auto; border: 1px solid #334155; border-radius: 8px; margin-top: 0.5rem; }
    .search-result { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; cursor: pointer; border-bottom: 1px solid #1e293b; background: #0f172a; }
    .search-result:hover { background: #334155; }
    .search-result:last-child { border-bottom: none; }

    .empty-state { text-align: center; padding: 2rem; color: #64748b; }
    .empty-state i { font-size: 2rem; display: block; margin-bottom: 0.5rem; }
</style>

<!-- Page Header -->
<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Packages</h1>
        <p class="page-subtitle">Manage content groups and subscription packages</p>
    </div>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-pill"><div><div class="stat-pill-value"><?= $stats['groups_total'] ?></div><div class="stat-pill-label">Content Groups</div></div></div>
    <div class="stat-pill"><div><div class="stat-pill-value"><?= $stats['packages_total'] ?></div><div class="stat-pill-label">Packages</div></div></div>
    <div class="stat-pill"><div><div class="stat-pill-value" style="color:#22c55e;"><?= $stats['packages_active'] ?></div><div class="stat-pill-label">Active</div></div></div>
    <div class="stat-pill"><div><div class="stat-pill-value" style="color:#94a3b8;"><?= $stats['packages_free'] ?></div><div class="stat-pill-label">Free Tier</div></div></div>
</div>

<!-- Tabs -->
<div class="tab-nav">
    <button class="tab-btn <?= $tab === 'groups' ? 'active' : '' ?>" onclick="switchTab('groups')">Content Groups</button>
    <button class="tab-btn <?= $tab !== 'groups' ? 'active' : '' ?>" onclick="switchTab('packages')">Packages</button>
</div>

<!-- ==================== CONTENT GROUPS TAB ==================== -->
<div class="tab-content <?= $tab === 'groups' ? 'active' : '' ?>" id="tab-groups">
    <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.8rem;color:#94a3b8;">
        <strong style="color:#818cf8;">How it works:</strong> Create content groups to bundle channels, movies, series, and categories together. Then assign these groups to packages. Click <strong>Manage Content</strong> on a group to add items to it.
    </div>

    <div style="margin-bottom:1rem;">
        <button class="btn btn-primary" onclick="openGroupModal()">
            <i class="lucide-plus"></i> Create Content Group
        </button>
    </div>

    <?php if (empty($contentGroups)): ?>
        <div class="card"><div class="empty-state"><i class="lucide-layers"></i>No content groups yet. Create your first content group to start bundling channels, movies, and more.</div></div>
    <?php else: ?>
        <div class="cg-grid">
            <?php foreach ($contentGroups as $cg): ?>
                <div class="cg-card">
                    <div class="cg-card-header">
                        <div class="cg-card-icon" style="background:<?= htmlspecialchars($cg['color']) ?>22;color:<?= htmlspecialchars($cg['color']) ?>;">
                            <i class="lucide-layers"></i>
                        </div>
                        <div class="cg-card-actions">
                            <button onclick="editGroup(<?= $cg['id'] ?>, <?= htmlspecialchars(json_encode($cg), ENT_QUOTES) ?>)" title="Edit"><i class="lucide-pencil"></i></button>
                            <button onclick="deleteGroup(<?= $cg['id'] ?>, '<?= htmlspecialchars($cg['name'], ENT_QUOTES) ?>')" title="Delete" style="color:#ef4444;"><i class="lucide-trash-2"></i></button>
                        </div>
                    </div>
                    <div class="cg-card-name"><?= htmlspecialchars($cg['name']) ?></div>
                    <?php if (!empty($cg['description'])): ?>
                        <div class="cg-card-desc"><?= htmlspecialchars($cg['description']) ?></div>
                    <?php endif; ?>
                    <div class="cg-card-count"><span><?= number_format($cg['item_count']) ?></span> content items</div>
                    <button class="btn btn-sm btn-secondary" style="margin-top:0.75rem;width:100%;" onclick="manageGroupContent(<?= $cg['id'] ?>)">
                        <i class="lucide-list-plus"></i> Manage Content
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ==================== PACKAGES TAB ==================== -->
<div class="tab-content <?= $tab !== 'groups' ? 'active' : '' ?>" id="tab-packages">
    <div style="margin-bottom:1rem;">
        <button class="btn btn-primary" onclick="openPackageModal()">
            <i class="lucide-plus"></i> Create Package
        </button>
    </div>

    <?php if (empty($packages)): ?>
        <div class="card"><div class="empty-state"><i class="lucide-package"></i>No packages yet. Create your first package above.</div></div>
    <?php else: ?>
        <div class="pkg-grid">
            <?php foreach ($packages as $pkg): ?>
                <div class="pkg-card" style="border-left: 3px solid <?= htmlspecialchars($pkg['color']) ?>;">
                    <div class="pkg-card-header">
                        <div>
                            <div class="pkg-card-name"><?= htmlspecialchars($pkg['name']) ?></div>
                            <?php if ($pkg['is_featured']): ?><span class="badge badge-warning" style="font-size:0.65rem;">Featured</span><?php endif; ?>
                        </div>
                        <span class="badge <?= $pkg['is_active'] ? 'badge-success' : 'badge-secondary' ?>"><?= $pkg['is_active'] ? 'Active' : 'Inactive' ?></span>
                    </div>
                    <div class="pkg-card-price">
                        <?php if ($pkg['is_free']): ?>
                            Free
                        <?php else: ?>
                            <?= htmlspecialchars($pkg['currency']) ?> <?= number_format($pkg['price'], 2) ?>
                            <?php if ($pkg['tax_rate'] > 0): ?>
                                <small>(<?= $pkg['tax_inclusive'] ? 'incl.' : '+' ?> <?= $pkg['tax_rate'] ?>% tax)</small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="pkg-card-period"><?= ucfirst(str_replace('_', ' ', $pkg['billing_period'])) ?><?php if ($pkg['trial_days'] > 0): ?> &middot; <?= $pkg['trial_days'] ?>-day trial<?php endif; ?></div>

                    <div class="pkg-card-features">
                        <span class="pkg-feature-tag"><i class="lucide-monitor"></i> <?= (int)$pkg['max_connections'] ?> stream<?= (int)$pkg['max_connections'] !== 1 ? 's' : '' ?></span>
                        <?php $f = $pkg['features']; ?>
                        <?php if (!empty($f['video_quality'])): ?><span class="pkg-feature-tag"><?= strtoupper($f['video_quality']) ?></span><?php endif; ?>
                        <?php if (!empty($f['ad_free'])): ?><span class="pkg-feature-tag">Ad-Free</span><?php endif; ?>
                        <?php if (!empty($f['catchup_days'])): ?><span class="pkg-feature-tag"><?= $f['catchup_days'] ?>d Catch-up</span><?php endif; ?>
                        <?php if (!empty($f['npvr_hours'])): ?><span class="pkg-feature-tag"><?= $f['npvr_hours'] ?>h DVR</span><?php endif; ?>
                        <?php if (!empty($pkg['platforms'])): ?>
                            <?php foreach ($pkg['platforms'] as $plat): ?><span class="pkg-feature-tag"><?= ucfirst($plat) ?></span><?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($pkg['group_names'])): ?>
                        <div class="pkg-card-groups"><i class="lucide-layers" style="font-size:0.75rem;"></i> <?= htmlspecialchars($pkg['group_names']) ?></div>
                    <?php endif; ?>

                    <div class="pkg-card-footer">
                        <?php if (!empty($pkg['geo_countries'])): ?>
                            <span style="font-size:0.75rem;color:#64748b;"><?= count($pkg['geo_countries']) ?> countries</span>
                        <?php else: ?>
                            <span style="font-size:0.75rem;color:#64748b;">All countries</span>
                        <?php endif; ?>
                        <div class="pkg-card-actions">
                            <button class="btn btn-sm btn-secondary" onclick="editPackage(<?= $pkg['id'] ?>)"><i class="lucide-pencil"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deletePackage(<?= $pkg['id'] ?>, '<?= htmlspecialchars($pkg['name'], ENT_QUOTES) ?>')"><i class="lucide-trash-2"></i></button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ==================== CONTENT GROUP MODAL ==================== -->
<div class="modal-overlay" id="groupModal">
    <div class="modal-box small">
        <div class="modal-header">
            <h3 id="groupModalTitle">Create Content Group</h3>
            <button class="modal-close" onclick="closeModal('groupModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="cgId" value="">
            <div style="margin-bottom:0.75rem;">
                <label class="form-label">Group Name</label>
                <input type="text" id="cgName" class="form-input" placeholder="e.g. Basic Channels, Premium Movies">
            </div>
            <div style="margin-bottom:0.75rem;">
                <label class="form-label">Description</label>
                <textarea id="cgDescription" class="form-input" rows="2" placeholder="Optional description"></textarea>
            </div>
            <div>
                <label class="form-label">Color</label>
                <div class="color-options" id="cgColorOptions">
                    <?php foreach (['#6366f1','#8b5cf6','#ec4899','#ef4444','#f59e0b','#22c55e','#06b6d4','#3b82f6','#64748b'] as $c): ?>
                        <div class="color-swatch <?= $c === '#6366f1' ? 'selected' : '' ?>" style="background:<?= $c ?>;" data-color="<?= $c ?>" onclick="selectColor('cg', this, '<?= $c ?>')"></div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="cgColor" value="#6366f1">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('groupModal')">Cancel</button>
            <button class="btn btn-primary" id="cgSaveBtn" onclick="saveGroup()">Create Group</button>
        </div>
    </div>
</div>

<!-- ==================== MANAGE GROUP CONTENT MODAL ==================== -->
<div class="modal-overlay" id="contentModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="contentModalTitle">Manage Content</h3>
            <button class="modal-close" onclick="closeModal('contentModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="contentGroupId" value="">

            <!-- Add content -->
            <div class="form-grid" style="margin-bottom:1rem;">
                <div>
                    <select id="addContentType" class="form-input" onchange="clearSearchResults()">
                        <option value="channel">Channels</option>
                        <option value="movie">Movies</option>
                        <option value="series">Series</option>
                        <option value="category">Categories</option>
                    </select>
                </div>
                <div style="display:flex;gap:0.5rem;">
                    <input type="text" id="addContentSearch" class="form-input" placeholder="Search..." onkeydown="if(event.key==='Enter'){event.preventDefault();searchContentForGroup();}">
                    <button class="btn btn-primary" onclick="searchContentForGroup()"><i class="lucide-search"></i></button>
                </div>
            </div>

            <div id="searchResults" style="display:none;" class="search-results"></div>

            <div class="section-divider">Current Content</div>
            <div id="groupContentList" class="content-items-list">
                <div class="empty-state" style="padding:1rem;">Loading...</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('contentModal')">Done</button>
        </div>
    </div>
</div>

<!-- ==================== PACKAGE MODAL ==================== -->
<div class="modal-overlay" id="packageModal">
    <div class="modal-box large">
        <div class="modal-header">
            <h3 id="pkgModalTitle">Create Package</h3>
            <button class="modal-close" onclick="closeModal('packageModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="pkgId" value="">

            <div class="form-grid">
                <div>
                    <label class="form-label">Package Name</label>
                    <input type="text" id="pkgName" class="form-input" placeholder="e.g. Basic, Premium, Family">
                </div>
                <div>
                    <label class="form-label">Color</label>
                    <div class="color-options" style="margin-top:0.5rem;">
                        <?php foreach (['#6366f1','#8b5cf6','#ec4899','#ef4444','#f59e0b','#22c55e','#06b6d4','#3b82f6','#64748b'] as $c): ?>
                            <div class="color-swatch <?= $c === '#6366f1' ? 'selected' : '' ?>" style="background:<?= $c ?>;" data-color="<?= $c ?>" onclick="selectColor('pkg', this, '<?= $c ?>')"></div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="pkgColor" value="#6366f1">
                </div>
            </div>

            <div style="margin-top:0.75rem;">
                <label class="form-label">Description</label>
                <textarea id="pkgDescription" class="form-input" rows="2" placeholder="Package description for customers"></textarea>
            </div>

            <div class="section-divider">Pricing</div>

            <div class="checkbox-row">
                <label class="checkbox-label"><input type="checkbox" id="pkgIsFree" onchange="toggleFreePackage()"> Free Package (no charge)</label>
            </div>

            <div id="pricingFields">
                <div class="form-grid-3">
                    <div>
                        <label class="form-label">Price</label>
                        <input type="number" id="pkgPrice" class="form-input" value="0.00" step="0.01" min="0">
                    </div>
                    <div>
                        <label class="form-label">Currency</label>
                        <select id="pkgCurrency" class="form-input">
                            <?php foreach ($currencies as $code => $label): ?>
                                <option value="<?= $code ?>" <?= $code === 'USD' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Billing Period</label>
                        <select id="pkgBillingPeriod" class="form-input">
                            <?php foreach ($billingPeriods as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $key === 'monthly' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid-3" style="margin-top:0.75rem;">
                    <div>
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" id="pkgTaxRate" class="form-input" value="0" step="0.01" min="0" max="100">
                    </div>
                    <div style="display:flex;align-items:flex-end;padding-bottom:0.25rem;">
                        <label class="checkbox-label"><input type="checkbox" id="pkgTaxInclusive"> Price includes tax</label>
                    </div>
                    <div>
                        <label class="form-label">Trial Days</label>
                        <input type="number" id="pkgTrialDays" class="form-input" value="0" min="0">
                    </div>
                </div>
            </div>

            <div class="section-divider">Features & Limits</div>

            <div class="form-grid-3">
                <div>
                    <label class="form-label">Simultaneous Streams</label>
                    <input type="number" id="pkgMaxConnections" class="form-input" value="1" min="1">
                </div>
                <div>
                    <label class="form-label">Video Quality</label>
                    <select id="pkgVideoQuality" class="form-input">
                        <option value="">No limit</option>
                        <option value="sd">SD (480p)</option>
                        <option value="hd">HD (720p)</option>
                        <option value="fhd">FHD (1080p)</option>
                        <option value="4k">4K (2160p)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Catch-up TV (days)</label>
                    <input type="number" id="pkgCatchupDays" class="form-input" value="0" min="0">
                </div>
            </div>

            <div class="form-grid" style="margin-top:0.75rem;">
                <div>
                    <label class="form-label">Cloud DVR / NPVR (hours)</label>
                    <input type="number" id="pkgNpvrHours" class="form-input" value="0" min="0">
                </div>
                <div style="display:flex;align-items:flex-end;padding-bottom:0.25rem;">
                    <label class="checkbox-label"><input type="checkbox" id="pkgAdFree"> Ad-Free</label>
                </div>
            </div>

            <div class="section-divider">Content Groups</div>

            <div>
                <label class="form-label">Assign Content Groups</label>
                <select id="pkgContentGroups" class="form-input" multiple style="min-height:90px;">
                    <?php foreach ($contentGroups as $cg): ?>
                        <option value="<?= $cg['id'] ?>"><?= htmlspecialchars($cg['name']) ?> (<?= $cg['item_count'] ?> items)</option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">Hold Ctrl/Cmd to select multiple groups</div>
            </div>

            <div class="section-divider">Availability</div>

            <div class="form-grid">
                <div>
                    <label class="form-label">Platforms</label>
                    <div class="checkbox-row">
                        <?php foreach ($allPlatforms as $pk => $pl): ?>
                            <label class="checkbox-label"><input type="checkbox" name="platforms" value="<?= $pk ?>" class="pkg-platform-cb"> <?= $pl ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="help-text">Leave all unchecked for all platforms</div>
                </div>
                <div>
                    <label class="form-label">Countries</label>
                    <select id="pkgCountries" class="form-input" multiple style="min-height:90px;">
                        <?php foreach ($countries as $code => $name): ?>
                            <option value="<?= $code ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text">Leave empty for all countries</div>
                </div>
            </div>

            <div class="section-divider">Status</div>

            <div class="checkbox-row">
                <label class="checkbox-label"><input type="checkbox" id="pkgIsActive" checked> Active</label>
                <label class="checkbox-label"><input type="checkbox" id="pkgIsFeatured"> Featured</label>
            </div>

            <div style="margin-top:0.75rem;">
                <label class="form-label">Sort Order</label>
                <input type="number" id="pkgSortOrder" class="form-input" value="0" min="0" style="max-width:120px;">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('packageModal')">Cancel</button>
            <button class="btn btn-primary" id="pkgSaveBtn" onclick="savePackage()">Create Package</button>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $csrf ?>';

function showToast(message, type = 'info') {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;top:1.5rem;right:1.5rem;z-index:9999;padding:0.75rem 1.25rem;border-radius:8px;color:#fff;font-size:0.875rem;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,0.4);max-width:400px;transition:opacity 0.3s;';
    t.style.background = type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#3b82f6';
    t.textContent = message;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
}

function jsonFetch(url, opts) {
    return fetch(url, opts).then(r => {
        if (!r.ok && r.status >= 500) {
            throw new Error('Server error (migration may need to be run). Status: ' + r.status);
        }
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            throw new Error('Server returned non-JSON response. The database tables may not exist yet.');
        }
        return r.json();
    });
}

function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.classList.add('active');
    history.replaceState(null, '', '?tab=' + tab);
}

function selectColor(prefix, el, color) {
    el.closest('.color-options').querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById(prefix + 'Color').value = color;
}

// ==================== CONTENT GROUPS ====================

function openGroupModal() {
    document.getElementById('cgId').value = '';
    document.getElementById('groupModalTitle').textContent = 'Create Content Group';
    document.getElementById('cgSaveBtn').textContent = 'Create Group';
    document.getElementById('cgSaveBtn').disabled = false;
    document.getElementById('cgName').value = '';
    document.getElementById('cgDescription').value = '';
    document.getElementById('cgColor').value = '#6366f1';
    document.querySelectorAll('#cgColorOptions .color-swatch').forEach(s => s.classList.toggle('selected', s.dataset.color === '#6366f1'));
    document.getElementById('groupModal').classList.add('active');
}

function editGroup(id, group) {
    document.getElementById('cgId').value = id;
    document.getElementById('groupModalTitle').textContent = 'Edit Content Group';
    document.getElementById('cgSaveBtn').textContent = 'Save Changes';
    document.getElementById('cgSaveBtn').disabled = false;
    document.getElementById('cgName').value = group.name || '';
    document.getElementById('cgDescription').value = group.description || '';
    document.getElementById('cgColor').value = group.color || '#6366f1';
    document.querySelectorAll('#cgColorOptions .color-swatch').forEach(s => s.classList.toggle('selected', s.dataset.color === (group.color || '#6366f1')));
    document.getElementById('groupModal').classList.add('active');
}

function saveGroup() {
    const id = document.getElementById('cgId').value;
    const btn = document.getElementById('cgSaveBtn');
    if (btn.disabled) return;

    const name = document.getElementById('cgName').value.trim();
    if (!name) { showToast('Group name is required', 'error'); return; }

    btn.disabled = true; btn.textContent = 'Saving...';

    const url = id ? `/admin/packages/groups/${id}/update` : '/admin/packages/groups/store';
    jsonFetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _token: csrfToken, name, description: document.getElementById('cgDescription').value, color: document.getElementById('cgColor').value })
    }).then(data => {
        if (data.success) { showToast(data.message, 'success'); location.reload(); }
        else { showToast(data.message, 'error'); btn.disabled = false; btn.textContent = id ? 'Save Changes' : 'Create Group'; }
    }).catch(() => { showToast('Error saving group', 'error'); btn.disabled = false; btn.textContent = id ? 'Save Changes' : 'Create Group'; });
}

function deleteGroup(id, name) {
    if (!confirm(`Delete content group "${name}"? This will also remove it from any packages.`)) return;
    jsonFetch(`/admin/packages/groups/${id}/delete`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '_token=' + csrfToken })
        .then(data => { if (data.success) { showToast(data.message, 'success'); location.reload(); } else showToast(data.message, 'error'); })
        .catch(e => showToast(e.message || 'Error deleting group', 'error'));
}

// ==================== MANAGE GROUP CONTENT ====================

function manageGroupContent(groupId) {
    document.getElementById('contentGroupId').value = groupId;
    document.getElementById('contentModalTitle').textContent = 'Manage Content';
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('addContentSearch').value = '';
    loadGroupContent(groupId);
    document.getElementById('contentModal').classList.add('active');
}

function loadGroupContent(groupId) {
    const list = document.getElementById('groupContentList');
    list.innerHTML = '<div class="empty-state" style="padding:1rem;">Loading...</div>';

    jsonFetch(`/admin/packages/groups/${groupId}/show`).then(data => {
        if (!data.success) { list.innerHTML = '<div class="empty-state" style="padding:1rem;">Error loading content</div>'; return; }
        const items = data.group.items || [];
        if (items.length === 0) { list.innerHTML = '<div class="empty-state" style="padding:1rem;">No content yet. Use the search above to find and add channels, movies, series, or categories.</div>'; return; }
        list.innerHTML = items.map(item => `
            <div class="content-item" id="ci-${item.id}">
                <div class="content-item-info">
                    <span class="content-type-badge">${item.content_type}</span>
                    <span>${escHtml(item.name)}</span>
                </div>
                <button class="content-item-remove" onclick="removeContent(${groupId}, ${item.id})"><i class="lucide-x"></i></button>
            </div>
        `).join('');
    }).catch(e => { list.innerHTML = '<div class="empty-state" style="padding:1rem;">' + escHtml(e.message || 'Error loading') + '</div>'; });
}

function searchContentForGroup() {
    const type = document.getElementById('addContentType').value;
    const q = document.getElementById('addContentSearch').value.trim();
    if (!q) return;

    const resultsDiv = document.getElementById('searchResults');
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = '<div style="padding:0.75rem;color:#94a3b8;">Searching...</div>';

    jsonFetch(`/admin/packages/search-content?type=${type}&q=${encodeURIComponent(q)}`).then(data => {
        if (!data.results || data.results.length === 0) { resultsDiv.innerHTML = '<div style="padding:0.75rem;color:#64748b;">No results found</div>'; return; }
        const groupId = document.getElementById('contentGroupId').value;
        resultsDiv.innerHTML = data.results.map(r => `
            <div class="search-result" onclick="addContentToGroup(${groupId}, '${type}', ${r.id}, this)">
                <span class="content-type-badge">${type}</span>
                <span>${escHtml(r.name)}</span>
            </div>
        `).join('');
    }).catch(e => { resultsDiv.innerHTML = '<div style="padding:0.75rem;color:#ef4444;">' + escHtml(e.message || 'Search error') + '</div>'; });
}

function clearSearchResults() { document.getElementById('searchResults').style.display = 'none'; }

function addContentToGroup(groupId, type, contentId, el) {
    if (el) el.style.opacity = '0.5';
    jsonFetch(`/admin/packages/groups/${groupId}/content/add`, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _token: csrfToken, content_type: type, content_id: contentId })
    }).then(data => {
        if (data.success) { if (el) el.remove(); loadGroupContent(groupId); showToast('Content added', 'success'); }
        else { showToast(data.message, 'error'); if (el) el.style.opacity = '1'; }
    }).catch(e => { showToast(e.message || 'Error adding content', 'error'); if (el) el.style.opacity = '1'; });
}

function removeContent(groupId, itemId) {
    jsonFetch(`/admin/packages/groups/${groupId}/content/${itemId}/remove`, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '_token=' + csrfToken
    }).then(data => {
        if (data.success) { const el = document.getElementById('ci-' + itemId); if (el) el.remove(); showToast('Removed', 'success'); }
        else showToast(data.message, 'error');
    }).catch(e => showToast(e.message || 'Error removing content', 'error'));
}

// ==================== PACKAGES ====================

function openPackageModal() {
    document.getElementById('pkgId').value = '';
    document.getElementById('pkgModalTitle').textContent = 'Create Package';
    document.getElementById('pkgSaveBtn').textContent = 'Create Package';
    document.getElementById('pkgSaveBtn').disabled = false;
    document.getElementById('pkgName').value = '';
    document.getElementById('pkgDescription').value = '';
    document.getElementById('pkgColor').value = '#6366f1';
    document.getElementById('pkgPrice').value = '0.00';
    document.getElementById('pkgCurrency').value = 'USD';
    document.getElementById('pkgBillingPeriod').value = 'monthly';
    document.getElementById('pkgTaxRate').value = '0';
    document.getElementById('pkgTaxInclusive').checked = false;
    document.getElementById('pkgTrialDays').value = '0';
    document.getElementById('pkgMaxConnections').value = '1';
    document.getElementById('pkgVideoQuality').value = '';
    document.getElementById('pkgCatchupDays').value = '0';
    document.getElementById('pkgNpvrHours').value = '0';
    document.getElementById('pkgAdFree').checked = false;
    document.getElementById('pkgIsFree').checked = false;
    document.getElementById('pkgIsActive').checked = true;
    document.getElementById('pkgIsFeatured').checked = false;
    document.getElementById('pkgSortOrder').value = '0';
    document.getElementById('pricingFields').style.display = '';
    document.querySelectorAll('.pkg-platform-cb').forEach(cb => cb.checked = false);
    Array.from(document.getElementById('pkgContentGroups').options).forEach(o => o.selected = false);
    Array.from(document.getElementById('pkgCountries').options).forEach(o => o.selected = false);
    document.querySelectorAll('#packageModal .color-swatch').forEach(s => s.classList.toggle('selected', s.dataset.color === '#6366f1'));
    document.getElementById('packageModal').classList.add('active');
}

function editPackage(id) {
    jsonFetch(`/admin/packages/${id}/show`).then(data => {
        if (!data.success) { showToast(data.message, 'error'); return; }
        const p = data.package;
        document.getElementById('pkgId').value = p.id;
        document.getElementById('pkgModalTitle').textContent = 'Edit Package';
        document.getElementById('pkgSaveBtn').textContent = 'Save Changes';
        document.getElementById('pkgSaveBtn').disabled = false;
        document.getElementById('pkgName').value = p.name || '';
        document.getElementById('pkgDescription').value = p.description || '';
        document.getElementById('pkgColor').value = p.color || '#6366f1';
        document.getElementById('pkgPrice').value = p.price || '0.00';
        document.getElementById('pkgCurrency').value = p.currency || 'USD';
        document.getElementById('pkgBillingPeriod').value = p.billing_period || 'monthly';
        document.getElementById('pkgTaxRate').value = p.tax_rate || '0';
        document.getElementById('pkgTaxInclusive').checked = p.tax_inclusive == 1;
        document.getElementById('pkgTrialDays').value = p.trial_days || '0';
        document.getElementById('pkgMaxConnections').value = p.max_connections || '1';
        document.getElementById('pkgVideoQuality').value = (p.features || {}).video_quality || '';
        document.getElementById('pkgCatchupDays').value = (p.features || {}).catchup_days || '0';
        document.getElementById('pkgNpvrHours').value = (p.features || {}).npvr_hours || '0';
        document.getElementById('pkgAdFree').checked = !!((p.features || {}).ad_free);
        document.getElementById('pkgIsFree').checked = p.is_free == 1;
        document.getElementById('pkgIsActive').checked = p.is_active == 1;
        document.getElementById('pkgIsFeatured').checked = p.is_featured == 1;
        document.getElementById('pkgSortOrder').value = p.sort_order || '0';
        document.getElementById('pricingFields').style.display = p.is_free == 1 ? 'none' : '';

        // Color swatch
        document.querySelectorAll('#packageModal .color-swatch').forEach(s => s.classList.toggle('selected', s.dataset.color === (p.color || '#6366f1')));

        // Platforms
        const plats = p.platforms || [];
        document.querySelectorAll('.pkg-platform-cb').forEach(cb => cb.checked = plats.includes(cb.value));

        // Countries
        const geos = (p.geo_countries || []).map(String);
        Array.from(document.getElementById('pkgCountries').options).forEach(o => o.selected = geos.includes(o.value));

        // Content groups
        const gids = (p.content_group_ids || []).map(String);
        Array.from(document.getElementById('pkgContentGroups').options).forEach(o => o.selected = gids.includes(o.value));

        document.getElementById('packageModal').classList.add('active');
    }).catch(e => showToast(e.message || 'Error loading package', 'error'));
}

function toggleFreePackage() {
    document.getElementById('pricingFields').style.display = document.getElementById('pkgIsFree').checked ? 'none' : '';
}

function savePackage() {
    const id = document.getElementById('pkgId').value;
    const btn = document.getElementById('pkgSaveBtn');
    if (btn.disabled) return;

    const name = document.getElementById('pkgName').value.trim();
    if (!name) { showToast('Package name is required', 'error'); return; }

    btn.disabled = true; btn.textContent = 'Saving...';

    const fd = new URLSearchParams();
    fd.append('_token', csrfToken);
    fd.append('name', name);
    fd.append('description', document.getElementById('pkgDescription').value);
    fd.append('color', document.getElementById('pkgColor').value);
    fd.append('price', document.getElementById('pkgPrice').value);
    fd.append('currency', document.getElementById('pkgCurrency').value);
    fd.append('billing_period', document.getElementById('pkgBillingPeriod').value);
    fd.append('tax_rate', document.getElementById('pkgTaxRate').value);
    if (document.getElementById('pkgTaxInclusive').checked) fd.append('tax_inclusive', '1');
    fd.append('trial_days', document.getElementById('pkgTrialDays').value);
    fd.append('max_connections', document.getElementById('pkgMaxConnections').value);
    fd.append('video_quality', document.getElementById('pkgVideoQuality').value);
    fd.append('catchup_days', document.getElementById('pkgCatchupDays').value);
    fd.append('npvr_hours', document.getElementById('pkgNpvrHours').value);
    if (document.getElementById('pkgAdFree').checked) fd.append('ad_free', '1');
    if (document.getElementById('pkgIsFree').checked) fd.append('is_free', '1');
    if (document.getElementById('pkgIsActive').checked) fd.append('is_active', '1');
    if (document.getElementById('pkgIsFeatured').checked) fd.append('is_featured', '1');
    fd.append('sort_order', document.getElementById('pkgSortOrder').value);

    // Platforms
    document.querySelectorAll('.pkg-platform-cb:checked').forEach(cb => fd.append('platforms[]', cb.value));

    // Countries
    Array.from(document.getElementById('pkgCountries').selectedOptions).forEach(o => fd.append('geo_countries[]', o.value));

    // Content groups
    Array.from(document.getElementById('pkgContentGroups').selectedOptions).forEach(o => fd.append('content_group_ids[]', o.value));

    const url = id ? `/admin/packages/${id}/update` : '/admin/packages/store';
    jsonFetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() })
        .then(data => {
            if (data.success) { showToast(data.message, 'success'); location.reload(); }
            else { showToast(data.message, 'error'); btn.disabled = false; btn.textContent = id ? 'Save Changes' : 'Create Package'; }
        }).catch(e => { showToast(e.message || 'Error saving package', 'error'); btn.disabled = false; btn.textContent = id ? 'Save Changes' : 'Create Package'; });
}

function deletePackage(id, name) {
    if (!confirm(`Delete package "${name}"? This cannot be undone.`)) return;
    jsonFetch(`/admin/packages/${id}/delete`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '_token=' + csrfToken })
        .then(data => { if (data.success) { showToast(data.message, 'success'); location.reload(); } else showToast(data.message, 'error'); })
        .catch(e => showToast(e.message || 'Error deleting package', 'error'));
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// Close modals
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); }));
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active')); });
</script>
