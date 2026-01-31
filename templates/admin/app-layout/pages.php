<?php
$platforms = ['web' => 'Web', 'mobile' => 'Mobile', 'tv' => 'TV', 'stb' => 'STB'];
?>

<style>
.pages-nav-container { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media (max-width: 1200px) { .pages-nav-container { grid-template-columns: 1fr; } }

/* Platform tabs */
.platform-tabs { display: flex; gap: 8px; margin-bottom: 24px; }
.platform-tab {
    padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 500;
    background: var(--card-bg); color: var(--text-secondary); border: 1px solid var(--border-color);
    cursor: pointer; text-decoration: none; transition: all 0.2s;
}
.platform-tab:hover { border-color: var(--primary); color: var(--text-primary); }
.platform-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }

/* Section panels */
.panel { background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); }
.panel-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border-color);
}
.panel-header h3 { font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.panel-body { padding: 16px 20px; }

/* Page list */
.page-list { list-style: none; padding: 0; margin: 0; }
.page-item {
    display: flex; align-items: center; gap: 12px; padding: 12px 16px;
    border-radius: 8px; margin-bottom: 4px; background: rgba(255,255,255,0.03);
    border: 1px solid transparent; transition: all 0.2s; cursor: grab;
}
.page-item:hover { background: rgba(255,255,255,0.06); border-color: var(--border-color); }
.page-item.dragging { opacity: 0.5; }
.page-item .drag-handle { color: var(--text-muted); cursor: grab; }
.page-item .page-icon {
    width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
    background: rgba(99,102,241,0.15); color: var(--primary); font-size: 16px;
}
.page-item .page-info { flex: 1; min-width: 0; }
.page-item .page-name { font-weight: 500; font-size: 14px; }
.page-item .page-meta { font-size: 12px; color: var(--text-muted); display: flex; gap: 8px; align-items: center; }
.page-item .page-actions { display: flex; gap: 4px; }
.page-item .page-actions button {
    width: 28px; height: 28px; border-radius: 6px; border: none; cursor: pointer;
    background: transparent; color: var(--text-muted); display: flex; align-items: center; justify-content: center;
}
.page-item .page-actions button:hover { background: rgba(255,255,255,0.1); color: var(--text-primary); }
.page-item .page-actions button.delete-btn:hover { color: var(--danger); }

.page-item.inactive { opacity: 0.5; }
.page-item .system-badge {
    font-size: 10px; padding: 1px 6px; border-radius: 4px; background: rgba(99,102,241,0.2);
    color: var(--primary); font-weight: 600; text-transform: uppercase;
}
.page-item .layout-link {
    font-size: 11px; padding: 2px 8px; border-radius: 4px; background: rgba(34,197,94,0.15);
    color: var(--success); text-decoration: none; display: inline-flex; align-items: center; gap: 3px;
}
.page-item .layout-link:hover { background: rgba(34,197,94,0.25); }
.page-item .no-layout { font-size: 11px; color: var(--text-muted); font-style: italic; }

/* Nav items */
.nav-item-row {
    display: flex; align-items: center; gap: 12px; padding: 10px 14px;
    border-radius: 8px; margin-bottom: 4px; background: rgba(255,255,255,0.03);
    border: 1px solid transparent; transition: all 0.2s; cursor: grab;
}
.nav-item-row:hover { background: rgba(255,255,255,0.06); border-color: var(--border-color); }
.nav-item-row.dragging { opacity: 0.5; }
.nav-item-row .nav-icon {
    width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center;
    background: rgba(99,102,241,0.12); color: var(--primary); font-size: 14px;
}
.nav-item-row .nav-label { flex: 1; font-size: 14px; font-weight: 500; }
.nav-item-row .nav-target { font-size: 12px; color: var(--text-muted); }
.nav-item-row .nav-actions { display: flex; gap: 4px; }
.nav-item-row .nav-actions button {
    width: 26px; height: 26px; border-radius: 6px; border: none; cursor: pointer;
    background: transparent; color: var(--text-muted); display: flex; align-items: center; justify-content: center;
}
.nav-item-row .nav-actions button:hover { background: rgba(255,255,255,0.1); color: var(--text-primary); }
.nav-item-row .nav-actions button.delete-btn:hover { color: var(--danger); }
.nav-item-row.inactive { opacity: 0.5; }

/* Nav style selector */
.nav-style-options { display: flex; gap: 8px; margin-bottom: 16px; }
.nav-style-opt {
    flex: 1; padding: 10px; text-align: center; border-radius: 8px;
    border: 2px solid var(--border-color); cursor: pointer; transition: all 0.2s;
}
.nav-style-opt:hover { border-color: rgba(99,102,241,0.5); }
.nav-style-opt.active { border-color: var(--primary); background: rgba(99,102,241,0.1); }
.nav-style-opt i { font-size: 20px; display: block; margin-bottom: 4px; }
.nav-style-opt span { font-size: 11px; color: var(--text-muted); }

/* Preview */
.nav-preview {
    background: var(--bg-dark); border-radius: 8px; border: 1px solid var(--border-color);
    padding: 16px; margin-top: 16px;
}
.nav-preview-label { font-size: 11px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.preview-bottom-bar {
    display: flex; justify-content: space-around; align-items: center;
    padding: 8px 0; border-top: 1px solid var(--border-color);
}
.preview-bottom-item {
    display: flex; flex-direction: column; align-items: center; gap: 2px;
    font-size: 10px; color: var(--text-muted); padding: 4px 8px;
}
.preview-bottom-item i { font-size: 16px; }
.preview-bottom-item.active { color: var(--primary); }
.preview-sidebar-list { display: flex; flex-direction: column; gap: 2px; }
.preview-sidebar-item {
    display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 6px;
    font-size: 12px; color: var(--text-secondary);
}
.preview-sidebar-item.active { background: rgba(99,102,241,0.15); color: var(--primary); }
.preview-sidebar-item i { font-size: 14px; }

/* Empty state */
.empty-state { text-align: center; padding: 32px 16px; color: var(--text-muted); }
.empty-state i { font-size: 32px; margin-bottom: 8px; display: block; opacity: 0.5; }
.empty-state p { font-size: 13px; }

/* Modal styles */
.modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    z-index: 1000; align-items: center; justify-content: center;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: var(--card-bg); border-radius: 12px; width: 480px; max-width: 90vw;
    border: 1px solid var(--border-color); max-height: 90vh; overflow-y: auto;
}
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border-color);
}
.modal-header h3 { font-size: 16px; font-weight: 600; }
.modal-close {
    width: 32px; height: 32px; border-radius: 8px; border: none;
    background: transparent; color: var(--text-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.modal-close:hover { background: rgba(255,255,255,0.1); }
.modal-body { padding: 20px; }
.modal-footer {
    display: flex; justify-content: flex-end; gap: 8px;
    padding: 16px 20px; border-top: 1px solid var(--border-color);
}

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: var(--text-secondary); }
.form-group input, .form-group select {
    width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--bg-dark); color: var(--text-primary); font-size: 14px;
}
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.btn-primary {
    padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer;
    background: var(--primary); color: #fff; font-size: 13px; font-weight: 500;
}
.btn-primary:hover { filter: brightness(1.1); }
.btn-secondary {
    padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border-color);
    background: transparent; color: var(--text-secondary); font-size: 13px; cursor: pointer;
}
.btn-secondary:hover { background: rgba(255,255,255,0.05); }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-icon {
    display: inline-flex; align-items: center; gap: 6px;
}

/* Icon picker grid */
.icon-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; max-height: 200px; overflow-y: auto; }
.icon-option {
    width: 100%; aspect-ratio: 1; border-radius: 8px; border: 2px solid transparent;
    background: rgba(255,255,255,0.03); cursor: pointer; display: flex; align-items: center;
    justify-content: center; color: var(--text-muted); transition: all 0.2s;
}
.icon-option:hover { border-color: rgba(99,102,241,0.5); color: var(--text-primary); }
.icon-option.selected { border-color: var(--primary); background: rgba(99,102,241,0.15); color: var(--primary); }
</style>

<!-- Platform Tabs -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div class="platform-tabs">
        <?php foreach ($platforms as $key => $label): ?>
            <a href="/admin/app-layout/pages?platform=<?= $key ?>"
               class="platform-tab <?= ($activePlatform ?? 'web') === $key ? 'active' : '' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>
    <a href="/admin/app-layout" class="btn-secondary btn-sm btn-icon">
        <i class="lucide-arrow-left" style="width:14px;height:14px;"></i> Back to Layouts
    </a>
</div>

<div class="pages-nav-container">
    <!-- Pages Panel -->
    <div class="panel">
        <div class="panel-header">
            <h3><i class="lucide-file-text" style="width:18px;height:18px;"></i> Pages</h3>
            <button class="btn-primary btn-sm btn-icon" onclick="openAddPageModal()">
                <i class="lucide-plus" style="width:14px;height:14px;"></i> Add Page
            </button>
        </div>
        <div class="panel-body">
            <?php if (empty($pages)): ?>
                <div class="empty-state">
                    <i class="lucide-file-x"></i>
                    <p>No pages configured for this platform.</p>
                </div>
            <?php else: ?>
                <ul class="page-list" id="pageList">
                    <?php foreach ($pages as $pg): ?>
                        <li class="page-item <?= !$pg['is_active'] ? 'inactive' : '' ?>"
                            data-id="<?= $pg['id'] ?>"
                            data-system="<?= $pg['is_system'] ?>">
                            <div class="drag-handle"><i class="lucide-grip-vertical" style="width:14px;height:14px;"></i></div>
                            <div class="page-icon"><i class="<?= htmlspecialchars($pg['icon'] ?? 'lucide-file') ?>" style="width:18px;height:18px;"></i></div>
                            <div class="page-info">
                                <div class="page-name"><?= htmlspecialchars($pg['name']) ?></div>
                                <div class="page-meta">
                                    <span>/<?= htmlspecialchars($pg['slug']) ?></span>
                                    <span><?= htmlspecialchars($pg['page_type']) ?></span>
                                    <?php if ($pg['is_system']): ?>
                                        <span class="system-badge">System</span>
                                    <?php endif; ?>
                                    <?php if ($pg['layout_name']): ?>
                                        <a href="/admin/app-layout/<?= $pg['layout_id'] ?>/builder" class="layout-link">
                                            <i class="lucide-layout" style="width:10px;height:10px;"></i>
                                            <?= htmlspecialchars($pg['layout_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?php
                                        $typeInfo = $pageTypes[$pg['page_type']] ?? null;
                                        if ($typeInfo && $typeInfo['has_layout']): ?>
                                            <span class="no-layout">No layout</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="page-actions">
                                <button onclick="openEditPageModal(<?= htmlspecialchars(json_encode($pg)) ?>)" title="Edit">
                                    <i class="lucide-pencil" style="width:14px;height:14px;"></i>
                                </button>
                                <button onclick="togglePage(<?= $pg['id'] ?>, <?= $pg['is_active'] ? 0 : 1 ?>)" title="<?= $pg['is_active'] ? 'Disable' : 'Enable' ?>">
                                    <i class="lucide-<?= $pg['is_active'] ? 'eye-off' : 'eye' ?>" style="width:14px;height:14px;"></i>
                                </button>
                                <?php if (!$pg['is_system']): ?>
                                    <button class="delete-btn" onclick="deletePage(<?= $pg['id'] ?>, '<?= htmlspecialchars($pg['name'], ENT_QUOTES) ?>')" title="Delete">
                                        <i class="lucide-trash-2" style="width:14px;height:14px;"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Navigation Panel -->
    <div class="panel">
        <div class="panel-header">
            <h3><i class="lucide-navigation" style="width:18px;height:18px;"></i> Navigation</h3>
            <button class="btn-primary btn-sm btn-icon" onclick="openAddNavItemModal()">
                <i class="lucide-plus" style="width:14px;height:14px;"></i> Add Item
            </button>
        </div>
        <div class="panel-body">
            <?php
            $mainNav = null;
            foreach ($navigation as $nav) {
                if ($nav['position'] === 'main') { $mainNav = $nav; break; }
            }
            ?>

            <?php if ($mainNav): ?>
                <!-- Navigation style -->
                <div style="margin-bottom:16px;">
                    <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:8px;">Navigation Style</label>
                    <div class="nav-style-options">
                        <?php
                        $currentStyle = $mainNav['settings']['style'] ?? 'sidebar';
                        $styles = [
                            'bottom_tab' => ['icon' => 'lucide-smartphone', 'label' => 'Bottom Tab'],
                            'sidebar' => ['icon' => 'lucide-panel-left', 'label' => 'Sidebar'],
                            'top_bar' => ['icon' => 'lucide-panel-top', 'label' => 'Top Bar'],
                        ];
                        foreach ($styles as $sKey => $sVal): ?>
                            <div class="nav-style-opt <?= $currentStyle === $sKey ? 'active' : '' ?>"
                                 onclick="setNavStyle('<?= $sKey ?>')">
                                <i class="<?= $sVal['icon'] ?>" style="width:20px;height:20px;margin:0 auto 4px;"></i>
                                <span><?= $sVal['label'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Nav items -->
                <?php if (empty($mainNav['items'])): ?>
                    <div class="empty-state">
                        <i class="lucide-compass"></i>
                        <p>No navigation items yet. Add items to build the menu.</p>
                    </div>
                <?php else: ?>
                    <div id="navItemList">
                        <?php foreach ($mainNav['items'] as $item): ?>
                            <div class="nav-item-row <?= !$item['is_active'] ? 'inactive' : '' ?>"
                                 data-id="<?= $item['id'] ?>">
                                <div class="drag-handle"><i class="lucide-grip-vertical" style="width:12px;height:12px;"></i></div>
                                <div class="nav-icon"><i class="<?= htmlspecialchars($item['icon'] ?? 'lucide-circle') ?>" style="width:14px;height:14px;"></i></div>
                                <div class="nav-label"><?= htmlspecialchars($item['label']) ?></div>
                                <div class="nav-target">
                                    <?php if ($item['target'] === 'page' && $item['page_name']): ?>
                                        <?= htmlspecialchars($item['page_name']) ?>
                                    <?php elseif ($item['target'] === 'url'): ?>
                                        <?= htmlspecialchars($item['url'] ?? 'URL') ?>
                                    <?php else: ?>
                                        <span style="color:var(--warning);">No target</span>
                                    <?php endif; ?>
                                </div>
                                <div class="nav-actions">
                                    <button onclick='openEditNavItemModal(<?= htmlspecialchars(json_encode($item)) ?>)' title="Edit">
                                        <i class="lucide-pencil" style="width:12px;height:12px;"></i>
                                    </button>
                                    <button onclick="toggleNavItem(<?= $item['id'] ?>, <?= $item['is_active'] ? 0 : 1 ?>)" title="<?= $item['is_active'] ? 'Hide' : 'Show' ?>">
                                        <i class="lucide-<?= $item['is_active'] ? 'eye-off' : 'eye' ?>" style="width:12px;height:12px;"></i>
                                    </button>
                                    <button class="delete-btn" onclick="removeNavItem(<?= $item['id'] ?>)" title="Remove">
                                        <i class="lucide-trash-2" style="width:12px;height:12px;"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Preview -->
                <div class="nav-preview">
                    <div class="nav-preview-label">Preview</div>
                    <?php if (($mainNav['settings']['style'] ?? 'sidebar') === 'bottom_tab'): ?>
                        <div class="preview-bottom-bar">
                            <?php foreach ($mainNav['items'] as $i => $item):
                                if (!$item['is_active']) continue; ?>
                                <div class="preview-bottom-item <?= $i === 0 ? 'active' : '' ?>">
                                    <i class="<?= htmlspecialchars($item['icon'] ?? 'lucide-circle') ?>" style="width:16px;height:16px;"></i>
                                    <span><?= htmlspecialchars($item['label']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="preview-sidebar-list">
                            <?php foreach ($mainNav['items'] as $i => $item):
                                if (!$item['is_active']) continue; ?>
                                <div class="preview-sidebar-item <?= $i === 0 ? 'active' : '' ?>">
                                    <i class="<?= htmlspecialchars($item['icon'] ?? 'lucide-circle') ?>" style="width:14px;height:14px;"></i>
                                    <span><?= htmlspecialchars($item['label']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="lucide-compass"></i>
                    <p>No navigation configured for this platform.</p>
                    <button class="btn-primary btn-sm" onclick="createDefaultNav()" style="margin-top:12px;">
                        Create Default Navigation
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Page Modal -->
<div class="modal-overlay" id="addPageModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add Page</h3>
            <button class="modal-close" onclick="closeModal('addPageModal')"><i class="lucide-x" style="width:18px;height:18px;"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Page Name</label>
                    <input type="text" id="pageName" placeholder="e.g. Kids Zone">
                </div>
                <div class="form-group">
                    <label>URL Slug</label>
                    <input type="text" id="pageSlug" placeholder="e.g. kids-zone">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Page Type</label>
                    <select id="pageType">
                        <?php foreach ($pageTypes as $key => $type): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($type['name']) ?> - <?= htmlspecialchars($type['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Layout (optional)</label>
                    <select id="pageLayout">
                        <option value="">None</option>
                        <?php foreach ($availableLayouts as $al): ?>
                            <option value="<?= $al['id'] ?>"><?= htmlspecialchars($al['name']) ?> (<?= $al['status'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Icon</label>
                <div class="icon-grid" id="pageIconGrid">
                    <?php
                    $icons = ['lucide-home','lucide-film','lucide-clapperboard','lucide-radio','lucide-tv','lucide-grid-3x3',
                              'lucide-search','lucide-bookmark','lucide-settings','lucide-play','lucide-star','lucide-heart',
                              'lucide-music','lucide-gamepad-2','lucide-baby','lucide-trophy','lucide-globe','lucide-newspaper',
                              'lucide-video','lucide-podcast','lucide-compass','lucide-sparkles','lucide-zap','lucide-flame'];
                    foreach ($icons as $icon): ?>
                        <div class="icon-option" data-icon="<?= $icon ?>" onclick="selectIcon(this, 'addPage')">
                            <i class="<?= $icon ?>" style="width:18px;height:18px;"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="pageIcon" value="lucide-home">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('addPageModal')">Cancel</button>
            <button class="btn-primary" onclick="savePage()">Add Page</button>
        </div>
    </div>
</div>

<!-- Edit Page Modal -->
<div class="modal-overlay" id="editPageModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Page</h3>
            <button class="modal-close" onclick="closeModal('editPageModal')"><i class="lucide-x" style="width:18px;height:18px;"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editPageId">
            <div class="form-row">
                <div class="form-group">
                    <label>Page Name</label>
                    <input type="text" id="editPageName">
                </div>
                <div class="form-group">
                    <label>URL Slug</label>
                    <input type="text" id="editPageSlug">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Page Type</label>
                    <input type="text" id="editPageType" disabled style="opacity:0.6;">
                </div>
                <div class="form-group">
                    <label>Layout</label>
                    <select id="editPageLayout">
                        <option value="">None</option>
                        <?php foreach ($availableLayouts as $al): ?>
                            <option value="<?= $al['id'] ?>"><?= htmlspecialchars($al['name']) ?> (<?= $al['status'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Icon</label>
                <div class="icon-grid" id="editPageIconGrid">
                    <?php foreach ($icons as $icon): ?>
                        <div class="icon-option" data-icon="<?= $icon ?>" onclick="selectIcon(this, 'editPage')">
                            <i class="<?= $icon ?>" style="width:18px;height:18px;"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="editPageIcon">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('editPageModal')">Cancel</button>
            <button class="btn-primary" onclick="updatePageSubmit()">Save Changes</button>
        </div>
    </div>
</div>

<!-- Add Nav Item Modal -->
<div class="modal-overlay" id="addNavItemModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add Navigation Item</h3>
            <button class="modal-close" onclick="closeModal('addNavItemModal')"><i class="lucide-x" style="width:18px;height:18px;"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Label</label>
                <input type="text" id="navItemLabel" placeholder="e.g. Home">
            </div>
            <div class="form-group">
                <label>Target Type</label>
                <select id="navItemTarget" onchange="toggleNavTarget()">
                    <option value="page">Link to Page</option>
                    <option value="url">External URL</option>
                    <option value="deeplink">Deep Link</option>
                </select>
            </div>
            <div class="form-group" id="navItemPageGroup">
                <label>Target Page</label>
                <select id="navItemPage">
                    <option value="">Select a page...</option>
                    <?php foreach ($pages as $pg): ?>
                        <option value="<?= $pg['id'] ?>"><?= htmlspecialchars($pg['name']) ?> (/<?= htmlspecialchars($pg['slug']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="navItemUrlGroup" style="display:none;">
                <label>URL</label>
                <input type="text" id="navItemUrl" placeholder="https://...">
            </div>
            <div class="form-group">
                <label>Icon</label>
                <div class="icon-grid" id="navItemIconGrid">
                    <?php foreach ($icons as $icon): ?>
                        <div class="icon-option" data-icon="<?= $icon ?>" onclick="selectIcon(this, 'navItem')">
                            <i class="<?= $icon ?>" style="width:18px;height:18px;"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="navItemIcon" value="lucide-home">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('addNavItemModal')">Cancel</button>
            <button class="btn-primary" onclick="saveNavItem()">Add Item</button>
        </div>
    </div>
</div>

<!-- Edit Nav Item Modal -->
<div class="modal-overlay" id="editNavItemModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Navigation Item</h3>
            <button class="modal-close" onclick="closeModal('editNavItemModal')"><i class="lucide-x" style="width:18px;height:18px;"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editNavItemId">
            <div class="form-group">
                <label>Label</label>
                <input type="text" id="editNavItemLabel">
            </div>
            <div class="form-group">
                <label>Target Type</label>
                <select id="editNavItemTarget" onchange="toggleEditNavTarget()">
                    <option value="page">Link to Page</option>
                    <option value="url">External URL</option>
                    <option value="deeplink">Deep Link</option>
                </select>
            </div>
            <div class="form-group" id="editNavItemPageGroup">
                <label>Target Page</label>
                <select id="editNavItemPage">
                    <option value="">Select a page...</option>
                    <?php foreach ($pages as $pg): ?>
                        <option value="<?= $pg['id'] ?>"><?= htmlspecialchars($pg['name']) ?> (/<?= htmlspecialchars($pg['slug']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="editNavItemUrlGroup" style="display:none;">
                <label>URL</label>
                <input type="text" id="editNavItemUrl">
            </div>
            <div class="form-group">
                <label>Icon</label>
                <div class="icon-grid" id="editNavItemIconGrid">
                    <?php foreach ($icons as $icon): ?>
                        <div class="icon-option" data-icon="<?= $icon ?>" onclick="selectIcon(this, 'editNavItem')">
                            <i class="<?= $icon ?>" style="width:18px;height:18px;"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="editNavItemIcon">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModal('editNavItemModal')">Cancel</button>
            <button class="btn-primary" onclick="updateNavItemSubmit()">Save Changes</button>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= $csrf ?>';
const PLATFORM = '<?= $activePlatform ?? 'web' ?>';
const NAV_ID = <?= $mainNav ? (int) $mainNav['id'] : 0 ?>;

// ============================================================
// Modal helpers
// ============================================================
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function selectIcon(el, prefix) {
    el.closest('.icon-grid').querySelectorAll('.icon-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById(prefix + 'Icon').value = el.dataset.icon;
}

// ============================================================
// Pages
// ============================================================
function openAddPageModal() {
    document.getElementById('pageName').value = '';
    document.getElementById('pageSlug').value = '';
    document.getElementById('pageType').value = 'custom';
    document.getElementById('pageLayout').value = '';
    document.getElementById('pageIcon').value = 'lucide-home';
    document.querySelectorAll('#pageIconGrid .icon-option').forEach(o => o.classList.remove('selected'));
    openModal('addPageModal');
}

// Auto-generate slug from name
document.getElementById('pageName')?.addEventListener('input', function() {
    document.getElementById('pageSlug').value = this.value.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
});

async function savePage() {
    const name = document.getElementById('pageName').value.trim();
    const slug = document.getElementById('pageSlug').value.trim();
    if (!name || !slug) return showToast('Name and slug are required', 'error');

    const body = new FormData();
    body.append('_token', CSRF);
    body.append('name', name);
    body.append('slug', slug);
    body.append('page_type', document.getElementById('pageType').value);
    body.append('platform', PLATFORM);
    body.append('layout_id', document.getElementById('pageLayout').value);
    body.append('icon', document.getElementById('pageIcon').value);

    try {
        const res = await fetch('/admin/app-layout/pages/store', { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
}

function openEditPageModal(page) {
    document.getElementById('editPageId').value = page.id;
    document.getElementById('editPageName').value = page.name;
    document.getElementById('editPageSlug').value = page.slug;
    document.getElementById('editPageType').value = page.page_type;
    document.getElementById('editPageLayout').value = page.layout_id || '';
    document.getElementById('editPageIcon').value = page.icon || '';

    // Select the icon
    document.querySelectorAll('#editPageIconGrid .icon-option').forEach(o => {
        o.classList.toggle('selected', o.dataset.icon === (page.icon || ''));
    });

    openModal('editPageModal');
}

async function updatePageSubmit() {
    const id = document.getElementById('editPageId').value;
    const body = new FormData();
    body.append('_token', CSRF);
    body.append('name', document.getElementById('editPageName').value);
    body.append('slug', document.getElementById('editPageSlug').value);
    body.append('layout_id', document.getElementById('editPageLayout').value);
    body.append('icon', document.getElementById('editPageIcon').value);

    try {
        const res = await fetch(`/admin/app-layout/pages/${id}/update`, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
}

async function togglePage(id, active) {
    const body = new FormData();
    body.append('_token', CSRF);
    body.append('is_active', active);

    try {
        const res = await fetch(`/admin/app-layout/pages/${id}/update`, { method: 'POST', body });
        const data = await res.json();
        if (data.success) location.reload();
        else showToast(data.message, 'error');
    } catch (e) {
        showToast('Network error', 'error');
    }
}

async function deletePage(id, name) {
    if (!confirm(`Delete page "${name}"?`)) return;

    const body = new FormData();
    body.append('_token', CSRF);

    try {
        const res = await fetch(`/admin/app-layout/pages/${id}/delete`, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
}

// ============================================================
// Navigation
// ============================================================
async function setNavStyle(style) {
    document.querySelectorAll('.nav-style-opt').forEach(o => o.classList.remove('active'));
    event.currentTarget.classList.add('active');

    const body = new FormData();
    body.append('_token', CSRF);
    body.append('platform', PLATFORM);
    body.append('position', 'main');
    body.append('name', 'Main Navigation');
    body.append('settings', JSON.stringify({ style: style, show_icons: true, show_labels: true }));

    try {
        const res = await fetch('/admin/app-layout/navigation/save', { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast('Style updated', 'success');
            setTimeout(() => location.reload(), 500);
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
}

function toggleNavTarget() {
    const target = document.getElementById('navItemTarget').value;
    document.getElementById('navItemPageGroup').style.display = target === 'page' ? '' : 'none';
    document.getElementById('navItemUrlGroup').style.display = target !== 'page' ? '' : 'none';
}

function toggleEditNavTarget() {
    const target = document.getElementById('editNavItemTarget').value;
    document.getElementById('editNavItemPageGroup').style.display = target === 'page' ? '' : 'none';
    document.getElementById('editNavItemUrlGroup').style.display = target !== 'page' ? '' : 'none';
}

function openAddNavItemModal() {
    if (!NAV_ID) {
        showToast('Create navigation first', 'error');
        return;
    }
    document.getElementById('navItemLabel').value = '';
    document.getElementById('navItemTarget').value = 'page';
    document.getElementById('navItemPage').value = '';
    document.getElementById('navItemUrl').value = '';
    document.getElementById('navItemIcon').value = 'lucide-home';
    document.querySelectorAll('#navItemIconGrid .icon-option').forEach(o => o.classList.remove('selected'));
    toggleNavTarget();
    openModal('addNavItemModal');
}

async function saveNavItem() {
    const label = document.getElementById('navItemLabel').value.trim();
    if (!label) return showToast('Label is required', 'error');

    const body = new FormData();
    body.append('_token', CSRF);
    body.append('navigation_id', NAV_ID);
    body.append('label', label);
    body.append('target', document.getElementById('navItemTarget').value);
    body.append('icon', document.getElementById('navItemIcon').value);

    if (document.getElementById('navItemTarget').value === 'page') {
        body.append('page_id', document.getElementById('navItemPage').value);
    } else {
        body.append('url', document.getElementById('navItemUrl').value);
    }

    try {
        const res = await fetch('/admin/app-layout/navigation/items/add', { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
}

function openEditNavItemModal(item) {
    document.getElementById('editNavItemId').value = item.id;
    document.getElementById('editNavItemLabel').value = item.label;
    document.getElementById('editNavItemTarget').value = item.target;
    document.getElementById('editNavItemPage').value = item.page_id || '';
    document.getElementById('editNavItemUrl').value = item.url || '';
    document.getElementById('editNavItemIcon').value = item.icon || '';

    document.querySelectorAll('#editNavItemIconGrid .icon-option').forEach(o => {
        o.classList.toggle('selected', o.dataset.icon === (item.icon || ''));
    });

    toggleEditNavTarget();
    openModal('editNavItemModal');
}

async function updateNavItemSubmit() {
    const id = document.getElementById('editNavItemId').value;
    const body = new FormData();
    body.append('_token', CSRF);
    body.append('label', document.getElementById('editNavItemLabel').value);
    body.append('target', document.getElementById('editNavItemTarget').value);
    body.append('icon', document.getElementById('editNavItemIcon').value);

    if (document.getElementById('editNavItemTarget').value === 'page') {
        body.append('page_id', document.getElementById('editNavItemPage').value);
    } else {
        body.append('url', document.getElementById('editNavItemUrl').value);
    }

    try {
        const res = await fetch(`/admin/app-layout/navigation/items/${id}/update`, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
}

async function toggleNavItem(id, active) {
    const body = new FormData();
    body.append('_token', CSRF);
    body.append('is_active', active);

    try {
        const res = await fetch(`/admin/app-layout/navigation/items/${id}/update`, { method: 'POST', body });
        const data = await res.json();
        if (data.success) location.reload();
    } catch (e) {
        showToast('Network error', 'error');
    }
}

async function removeNavItem(id) {
    if (!confirm('Remove this navigation item?')) return;

    const body = new FormData();
    body.append('_token', CSRF);

    try {
        const res = await fetch(`/admin/app-layout/navigation/items/${id}/remove`, { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 500);
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
}

async function createDefaultNav() {
    const body = new FormData();
    body.append('_token', CSRF);
    body.append('platform', PLATFORM);
    body.append('position', 'main');
    body.append('name', 'Main Navigation');
    body.append('settings', JSON.stringify({ style: 'sidebar', show_icons: true, show_labels: true }));

    try {
        const res = await fetch('/admin/app-layout/navigation/save', { method: 'POST', body });
        const data = await res.json();
        if (data.success) {
            showToast('Navigation created', 'success');
            setTimeout(() => location.reload(), 500);
        }
    } catch (e) {
        showToast('Network error', 'error');
    }
}

// ============================================================
// Drag & drop for pages
// ============================================================
function initPageDragDrop() {
    const list = document.getElementById('pageList');
    if (!list) return;

    let dragItem = null;

    list.querySelectorAll('.page-item').forEach(item => {
        item.setAttribute('draggable', 'true');

        item.addEventListener('dragstart', (e) => {
            dragItem = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            dragItem = null;
            savePageOrder();
        });

        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (dragItem && dragItem !== item) {
                const rect = item.getBoundingClientRect();
                const mid = rect.top + rect.height / 2;
                if (e.clientY < mid) {
                    list.insertBefore(dragItem, item);
                } else {
                    list.insertBefore(dragItem, item.nextSibling);
                }
            }
        });
    });
}

async function savePageOrder() {
    const items = document.querySelectorAll('#pageList .page-item');
    const order = Array.from(items).map(item => item.dataset.id);

    const body = new FormData();
    body.append('_token', CSRF);
    body.append('platform', PLATFORM);
    body.append('order', JSON.stringify(order));

    try {
        await fetch('/admin/app-layout/pages/reorder', { method: 'POST', body });
    } catch (e) {}
}

// ============================================================
// Drag & drop for nav items
// ============================================================
function initNavDragDrop() {
    const list = document.getElementById('navItemList');
    if (!list) return;

    let dragItem = null;

    list.querySelectorAll('.nav-item-row').forEach(item => {
        item.setAttribute('draggable', 'true');

        item.addEventListener('dragstart', (e) => {
            dragItem = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            dragItem = null;
            saveNavOrder();
        });

        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (dragItem && dragItem !== item) {
                const rect = item.getBoundingClientRect();
                const mid = rect.top + rect.height / 2;
                if (e.clientY < mid) {
                    list.insertBefore(dragItem, item);
                } else {
                    list.insertBefore(dragItem, item.nextSibling);
                }
            }
        });
    });
}

async function saveNavOrder() {
    const items = document.querySelectorAll('#navItemList .nav-item-row');
    const order = Array.from(items).map(item => item.dataset.id);

    const body = new FormData();
    body.append('_token', CSRF);
    body.append('navigation_id', NAV_ID);
    body.append('order', JSON.stringify(order));

    try {
        await fetch('/admin/app-layout/navigation/items/reorder', { method: 'POST', body });
    } catch (e) {}
}

// ============================================================
// Toast
// ============================================================
function showToast(msg, type) {
    if (typeof window.showToast === 'function' && window.showToast !== showToast) {
        window.showToast(msg, type);
        return;
    }
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:8px;color:#fff;z-index:9999;font-size:14px;
        background:${type === 'error' ? 'var(--danger)' : 'var(--success)'};`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    initPageDragDrop();
    initNavDragDrop();
});
</script>
