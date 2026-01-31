<?php
$platformLabels = ['web' => 'Web', 'mobile' => 'Mobile', 'tv' => 'Smart TV', 'stb' => 'Set-Top Box'];
$statusColors = ['draft' => 'badge-warning', 'published' => 'badge-success', 'archived' => 'badge-secondary'];
?>

<style>
    /* Builder Layout */
    .builder-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .builder-toolbar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .builder-toolbar-left .back-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-secondary);
        font-size: 0.875rem;
    }
    .builder-toolbar-left .back-link:hover { color: var(--primary-light); }
    .layout-title-edit {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .layout-title-edit h2 {
        font-size: 1.25rem;
        font-weight: 600;
    }
    .layout-title-edit .badge { font-size: 0.7rem; }

    .builder-toolbar-right {
        display: flex;
        gap: 0.5rem;
    }

    /* Section List */
    .section-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .section-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        transition: border-color 0.2s;
    }
    .section-card:hover {
        border-color: rgba(99, 102, 241, 0.3);
    }
    .section-card.dragging {
        opacity: 0.5;
        border-color: var(--primary);
    }
    .section-card.drag-over {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
    }
    .section-card.inactive {
        opacity: 0.5;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1.25rem;
        background: rgba(0,0,0,0.15);
        cursor: pointer;
        user-select: none;
    }
    .section-drag-handle {
        cursor: grab;
        color: var(--text-muted);
        font-size: 1.1rem;
        display: flex;
        padding: 0.25rem;
    }
    .section-drag-handle:active { cursor: grabbing; }
    .section-type-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        background: rgba(99, 102, 241, 0.15);
        color: var(--primary-light);
        flex-shrink: 0;
    }
    .section-header-info {
        flex: 1;
        min-width: 0;
    }
    .section-header-name {
        font-weight: 600;
        font-size: 0.875rem;
    }
    .section-header-type {
        font-size: 0.75rem;
        color: var(--text-muted);
    }
    .section-header-actions {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .section-header-actions button {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding: 0.35rem;
        border-radius: 6px;
        transition: var(--transition);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
    }
    .section-header-actions button:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
    }
    .section-header-actions button.danger:hover { color: var(--danger); }

    .section-body {
        padding: 1.25rem;
        display: none;
        border-top: 1px solid var(--border-color);
    }
    .section-card.expanded .section-body { display: block; }
    .section-header .expand-icon { transition: transform 0.2s; }
    .section-card.expanded .section-header .expand-icon { transform: rotate(180deg); }

    /* Settings Form */
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 0.75rem;
    }
    .settings-grid .form-group { margin-bottom: 0; }
    .form-select {
        width: 100%;
        padding: 0.625rem 0.75rem;
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 0.8rem;
    }
    .form-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    .form-check {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    .form-check input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--primary);
    }
    .settings-section-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
        margin-top: 0.75rem;
    }
    .settings-section-label:first-child { margin-top: 0; }

    /* Items List */
    .items-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 1rem;
        margin-bottom: 0.75rem;
    }
    .items-header h4 {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .items-list {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .item-card {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 0.75rem;
        background: var(--bg-dark);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        min-width: 200px;
        max-width: 280px;
    }
    .item-card-image {
        width: 40px;
        height: 56px;
        border-radius: 4px;
        object-fit: cover;
        background: var(--bg-hover);
        flex-shrink: 0;
    }
    .item-card-info {
        flex: 1;
        min-width: 0;
    }
    .item-card-name {
        font-size: 0.8rem;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .item-card-type {
        font-size: 0.7rem;
        color: var(--text-muted);
    }
    .item-card-remove {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 4px;
        font-size: 0.8rem;
    }
    .item-card-remove:hover { color: var(--danger); }

    /* Add Section Button */
    .add-section-card {
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        padding: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
        color: var(--text-muted);
        gap: 0.75rem;
        font-size: 0.9rem;
    }
    .add-section-card:hover {
        border-color: var(--primary);
        color: var(--primary-light);
        background: rgba(99, 102, 241, 0.05);
    }

    /* Section Type Picker Modal */
    .type-picker-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 0.75rem;
    }
    .type-picker-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1.25rem 0.75rem;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        cursor: pointer;
        transition: var(--transition);
        text-align: center;
    }
    .type-picker-item:hover {
        border-color: var(--primary);
        background: rgba(99, 102, 241, 0.05);
    }
    .type-picker-item.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }
    .type-picker-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(99, 102, 241, 0.15);
        color: var(--primary-light);
        font-size: 1.2rem;
    }
    .type-picker-name {
        font-weight: 600;
        font-size: 0.85rem;
    }
    .type-picker-desc {
        font-size: 0.7rem;
        color: var(--text-muted);
        line-height: 1.3;
    }

    /* Content Picker Modal */
    .picker-source-tabs {
        display: flex;
        gap: 0;
        margin-bottom: 1rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
    }
    .picker-source-tab {
        flex: 1;
        padding: 0.6rem 0.75rem;
        font-size: 0.8rem;
        cursor: pointer;
        background: transparent;
        color: var(--text-secondary);
        border: none;
        border-right: 1px solid var(--border-color);
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
    }
    .picker-source-tab:last-child { border-right: none; }
    .picker-source-tab:hover { background: var(--bg-hover); color: var(--text-primary); }
    .picker-source-tab.active { background: var(--primary); color: white; }
    .picker-source-tab i { font-size: 0.85rem; }

    .picker-panel { display: none; }
    .picker-panel.active { display: block; }

    .content-search {
        margin-bottom: 1rem;
        display: flex;
        gap: 0.5rem;
    }
    .content-search input { flex: 1; }
    .content-type-tabs {
        display: flex;
        gap: 0.25rem;
        margin-bottom: 1rem;
    }
    .content-type-tab {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.8rem;
        cursor: pointer;
        background: var(--bg-hover);
        color: var(--text-secondary);
        border: none;
        transition: var(--transition);
    }
    .content-type-tab:hover { color: var(--text-primary); }
    .content-type-tab.active { background: var(--primary); color: white; }

    .content-results {
        max-height: 400px;
        overflow-y: auto;
    }
    .content-result-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.6rem 0.5rem;
        border-radius: 8px;
        cursor: pointer;
        transition: var(--transition);
        border: 1px solid transparent;
    }
    .content-result-item:hover { background: var(--bg-hover); border-color: var(--border-color); }
    .content-result-img {
        width: 48px;
        height: 72px;
        border-radius: 6px;
        object-fit: cover;
        background: var(--bg-hover);
        flex-shrink: 0;
    }
    .content-result-info { flex: 1; min-width: 0; }
    .content-result-name { font-size: 0.85rem; font-weight: 500; }
    .content-result-meta { font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; }
    .content-result-desc {
        font-size: 0.7rem; color: var(--text-muted); margin-top: 3px;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .content-result-rating {
        display: inline-flex; align-items: center; gap: 3px;
        font-size: 0.7rem; color: var(--warning); margin-left: 6px;
    }
    .content-result-badge {
        font-size: 0.6rem; padding: 1px 6px; border-radius: 4px;
        background: rgba(99,102,241,0.2); color: var(--primary-light);
        font-weight: 600; text-transform: uppercase;
    }

    /* Upload area */
    .upload-area {
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        margin-bottom: 1rem;
    }
    .upload-area:hover, .upload-area.drag-over {
        border-color: var(--primary);
        background: rgba(99,102,241,0.05);
    }
    .upload-area i { font-size: 2rem; color: var(--text-muted); margin-bottom: 0.5rem; }
    .upload-area p { color: var(--text-muted); font-size: 0.85rem; margin-top: 0.25rem; }
    .upload-area .text-xs { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem; }
    .upload-preview {
        display: none; align-items: center; gap: 1rem;
        padding: 0.75rem; background: var(--bg-dark); border-radius: 8px;
        border: 1px solid var(--border-color); margin-bottom: 1rem;
    }
    .upload-preview.active { display: flex; }
    .upload-preview img {
        width: 80px; height: 120px; object-fit: cover; border-radius: 6px;
    }
    .upload-preview-info { flex: 1; }
    .upload-preview-name { font-size: 0.85rem; font-weight: 500; }
    .upload-preview-size { font-size: 0.7rem; color: var(--text-muted); }

    .tmdb-search-info {
        font-size: 0.75rem; color: var(--text-muted);
        padding: 0.5rem 0; display: flex; align-items: center; gap: 0.4rem;
    }

    /* Modal overlay */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
    .modal-lg { max-width: 700px; }
    .modal-header {
        padding: 1.25rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .modal-header h3 { font-size: 1rem; font-weight: 600; }
    .modal-close {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 1.25rem;
        padding: 0.25rem;
    }
    .modal-close:hover { color: var(--text-primary); }
    .modal-body { padding: 1.25rem; }
    .modal-footer {
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border-color);
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
    }

    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<!-- Builder Toolbar -->
<div class="builder-toolbar">
    <div class="builder-toolbar-left">
        <a href="/admin/app-layout" class="back-link">
            <i class="lucide-arrow-left"></i> All Layouts
        </a>
        <div class="layout-title-edit">
            <h2 id="layoutTitle"><?= htmlspecialchars($appLayout['name']) ?></h2>
            <span class="badge <?= $statusColors[$appLayout['status']] ?? '' ?>"><?= ucfirst($appLayout['status']) ?></span>
            <span class="badge badge-info"><?= $platformLabels[$appLayout['platform']] ?? $appLayout['platform'] ?></span>
        </div>
    </div>
    <div class="builder-toolbar-right">
        <button class="btn btn-secondary btn-sm" onclick="openRenameModal()">
            <i class="lucide-pencil"></i> Rename
        </button>
        <?php if ($appLayout['status'] === 'draft'): ?>
            <button class="btn btn-primary btn-sm" onclick="publishLayout()">
                <i class="lucide-upload"></i> Publish
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Section List -->
<div class="section-list" id="sectionList">
    <?php foreach ($sections as $index => $section):
        $typeDef = $sectionTypes[$section['section_type']] ?? null;
    ?>
        <div class="section-card <?= !$section['is_active'] ? 'inactive' : '' ?>"
             data-section-id="<?= $section['id'] ?>"
             data-section-type="<?= htmlspecialchars($section['section_type']) ?>"
             draggable="true">
            <div class="section-header" onclick="toggleSection(<?= $section['id'] ?>)">
                <div class="section-drag-handle" title="Drag to reorder" onclick="event.stopPropagation()">
                    <i class="lucide-grip-vertical"></i>
                </div>
                <div class="section-type-icon">
                    <i class="<?= $typeDef['icon'] ?? 'lucide-square' ?>"></i>
                </div>
                <div class="section-header-info">
                    <div class="section-header-name"><?= htmlspecialchars($section['title'] ?: ($typeDef['name'] ?? $section['section_type'])) ?></div>
                    <div class="section-header-type"><?= $typeDef['name'] ?? $section['section_type'] ?>
                        <?php if ($typeDef && $typeDef['supports_items']): ?>
                            &middot; <?= count($section['items']) ?> item<?= count($section['items']) != 1 ? 's' : '' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="section-header-actions" onclick="event.stopPropagation()">
                    <button onclick="toggleActive(<?= $section['id'] ?>, <?= $section['is_active'] ? 0 : 1 ?>)"
                            title="<?= $section['is_active'] ? 'Disable' : 'Enable' ?>">
                        <i class="lucide-<?= $section['is_active'] ? 'eye' : 'eye-off' ?>"></i>
                    </button>
                    <button class="danger" onclick="deleteSection(<?= $section['id'] ?>)" title="Delete">
                        <i class="lucide-trash-2"></i>
                    </button>
                    <button class="expand-icon">
                        <i class="lucide-chevron-down"></i>
                    </button>
                </div>
            </div>

            <div class="section-body" id="sectionBody-<?= $section['id'] ?>">
                <!-- Section Title -->
                <div class="form-group">
                    <label class="form-label">Section Title</label>
                    <input type="text" class="form-input" value="<?= htmlspecialchars($section['title'] ?? '') ?>"
                           onchange="updateSectionField(<?= $section['id'] ?>, 'title', this.value)"
                           placeholder="Section heading (shown to users)">
                </div>

                <!-- Type-specific Settings -->
                <div class="settings-section-label">Settings</div>
                <div class="settings-grid">
                    <?= renderSectionSettings($section, $typeDef) ?>
                </div>

                <!-- Items (for types that support them) -->
                <?php if ($typeDef && $typeDef['supports_items']): ?>
                    <div class="items-header">
                        <h4>Content Items</h4>
                        <button class="btn btn-sm btn-secondary" onclick="openContentPicker(<?= $section['id'] ?>)">
                            <i class="lucide-plus"></i> Add Content
                        </button>
                    </div>
                    <div class="items-list" id="itemsList-<?= $section['id'] ?>">
                        <?php if (empty($section['items'])): ?>
                            <div class="text-muted text-sm" style="padding: 1rem 0;">No items added yet. Click "Add Content" to get started.</div>
                        <?php else: ?>
                            <?php foreach ($section['items'] as $item):
                                $content = $item['content'] ?? null;
                            ?>
                                <div class="item-card" data-item-id="<?= $item['id'] ?>">
                                    <?php if (!empty($content['image']) || !empty($content['poster_url']) || !empty($content['logo_url'])): ?>
                                        <img class="item-card-image" src="<?= htmlspecialchars($content['image'] ?? $content['poster_url'] ?? $content['logo_url'] ?? '') ?>" alt="">
                                    <?php else: ?>
                                        <div class="item-card-image" style="display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:0.7rem;"><?= ucfirst($item['content_type']) ?></div>
                                    <?php endif; ?>
                                    <div class="item-card-info">
                                        <div class="item-card-name"><?= htmlspecialchars($content['name'] ?? $content['title'] ?? 'Unknown') ?></div>
                                        <div class="item-card-type"><?= ucfirst($item['content_type']) ?><?= !empty($content['year']) ? ' &middot; ' . $content['year'] : '' ?></div>
                                    </div>
                                    <button class="item-card-remove" onclick="removeItem(<?= $section['id'] ?>, <?= $item['id'] ?>)" title="Remove">
                                        <i class="lucide-x"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
                    <button class="btn btn-primary btn-sm" onclick="saveSection(<?= $section['id'] ?>)">
                        <i class="lucide-check"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add Section Button -->
<div class="add-section-card" onclick="openAddSectionModal()">
    <i class="lucide-plus"></i>
    Add Section
</div>

<!-- Add Section Type Picker Modal -->
<div class="modal-overlay" id="addSectionModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Add Section</h3>
            <button class="modal-close" onclick="closeModal('addSectionModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-muted" style="margin-bottom: 1rem;">Choose a component to add to your layout.</p>
            <div class="type-picker-grid">
                <?php foreach ($sectionTypes as $typeKey => $typeDef):
                    $existing = 0;
                    foreach ($sections as $s) {
                        if ($s['section_type'] === $typeKey) $existing++;
                    }
                    $disabled = $existing >= $typeDef['max_per_layout'];
                ?>
                    <div class="type-picker-item <?= $disabled ? 'disabled' : '' ?>"
                         onclick="<?= $disabled ? '' : "addSection('{$typeKey}')" ?>">
                        <div class="type-picker-icon">
                            <i class="<?= $typeDef['icon'] ?>"></i>
                        </div>
                        <div class="type-picker-name"><?= $typeDef['name'] ?></div>
                        <div class="type-picker-desc"><?= $typeDef['description'] ?></div>
                        <?php if ($disabled): ?>
                            <div class="text-xs text-muted">Max reached</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Content Picker Modal -->
<div class="modal-overlay" id="contentPickerModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>Add Content</h3>
            <button class="modal-close" onclick="closeModal('contentPickerModal')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Source tabs: Library / TMDB / Upload -->
            <div class="picker-source-tabs">
                <button class="picker-source-tab active" onclick="switchPickerSource('library', this)">
                    <i class="lucide-database"></i> My Library
                </button>
                <button class="picker-source-tab" onclick="switchPickerSource('tmdb', this)">
                    <i class="lucide-globe"></i> TMDB Search
                </button>
                <button class="picker-source-tab" onclick="switchPickerSource('upload', this)">
                    <i class="lucide-upload"></i> Upload Image
                </button>
            </div>

            <!-- Library Panel -->
            <div class="picker-panel active" id="pickerLibrary">
                <div class="content-type-tabs">
                    <button class="content-type-tab active" onclick="switchContentType('movie', this)">Movies</button>
                    <button class="content-type-tab" onclick="switchContentType('series', this)">TV Shows</button>
                    <button class="content-type-tab" onclick="switchContentType('channel', this)">Channels</button>
                    <button class="content-type-tab" onclick="switchContentType('category', this)">Categories</button>
                </div>
                <div class="content-search">
                    <input type="text" id="contentSearchInput" class="form-input" placeholder="Search your library..." oninput="debounceSearch()">
                </div>
                <div class="content-results" id="contentResults">
                    <div class="text-muted text-sm" style="padding: 2rem; text-align: center;">Type to search or browse content</div>
                </div>
            </div>

            <!-- TMDB Panel -->
            <div class="picker-panel" id="pickerTmdb">
                <div class="content-type-tabs">
                    <button class="content-type-tab active" onclick="switchTmdbType('movie', this)">Movies</button>
                    <button class="content-type-tab" onclick="switchTmdbType('series', this)">TV Shows</button>
                </div>
                <div class="content-search">
                    <input type="text" id="tmdbSearchInput" class="form-input" placeholder="Search TMDB by title..." oninput="debounceTmdbSearch()">
                </div>
                <div class="tmdb-search-info">
                    <i class="lucide-info" style="width:12px;height:12px;"></i>
                    Search for movies or TV shows on TMDB. Content will be imported to your library when added.
                </div>
                <div class="content-results" id="tmdbResults">
                    <div class="text-muted text-sm" style="padding: 2rem; text-align: center;">Enter a title to search TMDB</div>
                </div>
            </div>

            <!-- Upload Panel -->
            <div class="picker-panel" id="pickerUpload">
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('uploadInput').click()">
                    <i class="lucide-image-plus"></i>
                    <p>Click to upload or drag & drop an image</p>
                    <div class="text-xs">JPG, PNG, WebP up to 5MB</div>
                </div>
                <input type="file" id="uploadInput" accept="image/*" style="display:none" onchange="handleFileSelect(this)">

                <div class="upload-preview" id="uploadPreview">
                    <img id="uploadPreviewImg" src="" alt="Preview">
                    <div class="upload-preview-info">
                        <div class="upload-preview-name" id="uploadFileName">image.jpg</div>
                        <div class="upload-preview-size" id="uploadFileSize">0 KB</div>
                        <button class="btn btn-sm btn-secondary" onclick="clearUpload()" style="margin-top:0.5rem;">
                            <i class="lucide-x"></i> Remove
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Title (optional)</label>
                    <input type="text" id="uploadTitle" class="form-input" placeholder="Image title or label">
                </div>
                <div class="form-group">
                    <label class="form-label">Link URL (optional)</label>
                    <input type="text" id="uploadLinkUrl" class="form-input" placeholder="Where should this link to?">
                </div>
                <div style="display:flex;justify-content:flex-end;">
                    <button class="btn btn-primary" id="uploadSubmitBtn" onclick="submitUpload()" disabled>
                        <i class="lucide-upload"></i> Upload & Add
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal-overlay" id="renameModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Rename Layout</h3>
            <button class="modal-close" onclick="closeModal('renameModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Layout Name</label>
                <input type="text" id="renameInput" class="form-input" value="<?= htmlspecialchars($appLayout['name']) ?>">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('renameModal')">Cancel</button>
            <button class="btn btn-primary" onclick="renameLayout()">Save</button>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $csrf ?>';
const layoutId = <?= $appLayout['id'] ?>;
let activeSectionForPicker = null;
let currentContentType = 'movie';
let searchTimeout = null;

// ======================================
// Section expand/collapse
// ======================================
function toggleSection(sectionId) {
    const card = document.querySelector(`[data-section-id="${sectionId}"]`);
    if (card) card.classList.toggle('expanded');
}

// ======================================
// Drag and Drop
// ======================================
let draggedEl = null;

document.addEventListener('dragstart', function(e) {
    const card = e.target.closest('.section-card');
    if (!card) return;
    draggedEl = card;
    card.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
});

document.addEventListener('dragend', function(e) {
    if (draggedEl) {
        draggedEl.classList.remove('dragging');
        draggedEl = null;
    }
    document.querySelectorAll('.section-card').forEach(c => c.classList.remove('drag-over'));
});

document.addEventListener('dragover', function(e) {
    e.preventDefault();
    const card = e.target.closest('.section-card');
    if (!card || card === draggedEl) return;
    card.classList.add('drag-over');
});

document.addEventListener('dragleave', function(e) {
    const card = e.target.closest('.section-card');
    if (card) card.classList.remove('drag-over');
});

document.addEventListener('drop', function(e) {
    e.preventDefault();
    const target = e.target.closest('.section-card');
    if (!target || target === draggedEl || !draggedEl) return;

    const list = document.getElementById('sectionList');
    const cards = [...list.querySelectorAll('.section-card')];
    const fromIdx = cards.indexOf(draggedEl);
    const toIdx = cards.indexOf(target);

    if (fromIdx < toIdx) {
        target.after(draggedEl);
    } else {
        target.before(draggedEl);
    }

    target.classList.remove('drag-over');
    saveSectionOrder();
});

async function saveSectionOrder() {
    const cards = document.querySelectorAll('#sectionList .section-card');
    const order = [...cards].map(c => c.dataset.sectionId);

    const form = new FormData();
    form.append('_token', csrfToken);
    order.forEach(id => form.append('order[]', id));

    await fetch(`/admin/app-layout/${layoutId}/sections/reorder`, { method: 'POST', body: form });
}

// ======================================
// Section CRUD
// ======================================
function openAddSectionModal() {
    document.getElementById('addSectionModal').classList.add('active');
}

async function addSection(type) {
    closeModal('addSectionModal');

    const form = new FormData();
    form.append('_token', csrfToken);
    form.append('section_type', type);

    try {
        const res = await fetch(`/admin/app-layout/${layoutId}/sections/add`, { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to add section');
        }
    } catch (e) {
        alert('Network error');
    }
}

async function deleteSection(sectionId) {
    if (!confirm('Delete this section and all its content?')) return;

    const form = new FormData();
    form.append('_token', csrfToken);

    try {
        const res = await fetch(`/admin/app-layout/${layoutId}/sections/${sectionId}/delete`, { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            const card = document.querySelector(`[data-section-id="${sectionId}"]`);
            if (card) card.remove();
        } else {
            alert(data.message || 'Failed to delete');
        }
    } catch (e) {
        alert('Network error');
    }
}

async function toggleActive(sectionId, newState) {
    const form = new FormData();
    form.append('_token', csrfToken);
    form.append('is_active', newState);

    try {
        const res = await fetch(`/admin/app-layout/${layoutId}/sections/${sectionId}/update`, { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        }
    } catch (e) {}
}

async function saveSection(sectionId) {
    const card = document.querySelector(`[data-section-id="${sectionId}"]`);
    if (!card) return;

    const title = card.querySelector('.form-input[onchange*="title"]')?.value || '';
    const settings = {};

    card.querySelectorAll('[data-setting]').forEach(el => {
        const key = el.dataset.setting;
        if (el.type === 'checkbox') {
            settings[key] = el.checked;
        } else if (el.type === 'number') {
            settings[key] = parseInt(el.value) || 0;
        } else {
            settings[key] = el.value;
        }
    });

    const form = new FormData();
    form.append('_token', csrfToken);
    form.append('title', title);
    form.append('settings', JSON.stringify(settings));

    try {
        const res = await fetch(`/admin/app-layout/${layoutId}/sections/${sectionId}/update`, { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            // Update header name
            const nameEl = card.querySelector('.section-header-name');
            if (nameEl && title) nameEl.textContent = title;
            // Brief visual feedback
            const btn = card.querySelector('.btn-primary');
            if (btn) {
                btn.innerHTML = '<i class="lucide-check"></i> Saved';
                setTimeout(() => btn.innerHTML = '<i class="lucide-check"></i> Save Changes', 1500);
            }
        } else {
            alert(data.message || 'Failed to save');
        }
    } catch (e) {
        alert('Network error');
    }
}

function updateSectionField(sectionId, field, value) {
    // Updated on save - no immediate AJAX needed
}

// ======================================
// Content Picker
// ======================================
let currentPickerSource = 'library';
let tmdbSearchType = 'movie';
let tmdbSearchTimeout = null;

function openContentPicker(sectionId) {
    activeSectionForPicker = sectionId;
    currentContentType = 'movie';
    currentPickerSource = 'library';
    tmdbSearchType = 'movie';

    // Reset all panels
    document.querySelectorAll('.picker-source-tab').forEach((t, i) => t.classList.toggle('active', i === 0));
    document.querySelectorAll('.picker-panel').forEach((p, i) => p.classList.toggle('active', i === 0));

    // Reset library search
    document.getElementById('contentSearchInput').value = '';
    document.getElementById('contentResults').innerHTML = '<div class="text-muted text-sm" style="padding:2rem;text-align:center;">Type to search or browse content</div>';
    document.querySelectorAll('#pickerLibrary .content-type-tab').forEach((t, i) => t.classList.toggle('active', i === 0));

    // Reset TMDB search
    document.getElementById('tmdbSearchInput').value = '';
    document.getElementById('tmdbResults').innerHTML = '<div class="text-muted text-sm" style="padding:2rem;text-align:center;">Enter a title to search TMDB</div>';

    // Reset upload
    clearUpload();

    document.getElementById('contentPickerModal').classList.add('active');
    searchContent('');
}

function switchPickerSource(source, btn) {
    currentPickerSource = source;
    document.querySelectorAll('.picker-source-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.picker-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('picker' + source.charAt(0).toUpperCase() + source.slice(1)).classList.add('active');

    if (source === 'tmdb') {
        document.getElementById('tmdbSearchInput').focus();
    } else if (source === 'library') {
        document.getElementById('contentSearchInput').focus();
    }
}

// --- Library search ---
function switchContentType(type, btn) {
    currentContentType = type;
    btn.closest('.content-type-tabs').querySelectorAll('.content-type-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    searchContent(document.getElementById('contentSearchInput').value);
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchContent(document.getElementById('contentSearchInput').value);
    }, 300);
}

async function searchContent(query) {
    const container = document.getElementById('contentResults');
    container.innerHTML = '<div class="text-muted text-sm" style="padding:2rem;text-align:center;"><i class="lucide-loader-2" style="animation:spin 1s linear infinite;width:16px;height:16px;display:inline-block;"></i> Searching...</div>';

    try {
        const res = await fetch(`/admin/app-layout/search-content?type=${currentContentType}&q=${encodeURIComponent(query)}`);
        const data = await res.json();

        if (!data.results || data.results.length === 0) {
            container.innerHTML = `<div class="text-muted text-sm" style="padding:2rem;text-align:center;">
                No results in your library.
                <br><a href="#" onclick="event.preventDefault();switchPickerSource('tmdb',document.querySelectorAll('.picker-source-tab')[1])" style="color:var(--primary-light);margin-top:0.5rem;display:inline-block;">Search TMDB instead</a>
            </div>`;
            return;
        }

        let html = '';
        data.results.forEach(item => {
            const img = item.image || item.poster_url || item.logo_url || '';
            const imgHtml = img
                ? `<img class="content-result-img" src="${escapeHtml(img)}" alt="" onerror="this.style.display='none'">`
                : `<div class="content-result-img" style="display:flex;align-items:center;justify-content:center;font-size:0.6rem;color:var(--text-muted)">${escapeHtml(currentContentType)}</div>`;

            html += `<div class="content-result-item" onclick="addContentItem(${item.id}, '${escapeHtml(item.name || '')}')">
                ${imgHtml}
                <div class="content-result-info">
                    <div class="content-result-name">${escapeHtml(item.name || 'Untitled')}</div>
                    <div class="content-result-meta">${escapeHtml(item.meta || item.year || '')}</div>
                </div>
            </div>`;
        });

        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="text-muted text-sm" style="padding:2rem;text-align:center;">Search failed</div>';
    }
}

async function addContentItem(contentId, name) {
    if (!activeSectionForPicker) return;

    const form = new FormData();
    form.append('_token', csrfToken);
    form.append('content_type', currentContentType);
    form.append('content_id', contentId);

    try {
        const res = await fetch(`/admin/app-layout/${layoutId}/sections/${activeSectionForPicker}/items/add`, { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            closeModal('contentPickerModal');
            window.location.reload();
        } else {
            alert(data.message || 'Failed to add item');
        }
    } catch (e) {
        alert('Network error');
    }
}

// --- TMDB search ---
function switchTmdbType(type, btn) {
    tmdbSearchType = type;
    btn.closest('.content-type-tabs').querySelectorAll('.content-type-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    const q = document.getElementById('tmdbSearchInput').value;
    if (q.trim()) searchTmdb(q);
}

function debounceTmdbSearch() {
    clearTimeout(tmdbSearchTimeout);
    tmdbSearchTimeout = setTimeout(() => {
        searchTmdb(document.getElementById('tmdbSearchInput').value);
    }, 400);
}

async function searchTmdb(query) {
    if (!query.trim()) {
        document.getElementById('tmdbResults').innerHTML = '<div class="text-muted text-sm" style="padding:2rem;text-align:center;">Enter a title to search TMDB</div>';
        return;
    }

    const container = document.getElementById('tmdbResults');
    container.innerHTML = '<div class="text-muted text-sm" style="padding:2rem;text-align:center;"><i class="lucide-loader-2" style="animation:spin 1s linear infinite;width:16px;height:16px;display:inline-block;"></i> Searching TMDB...</div>';

    try {
        const res = await fetch(`/admin/app-layout/search-tmdb?type=${tmdbSearchType}&q=${encodeURIComponent(query)}`);
        const data = await res.json();

        if (!data.success) {
            container.innerHTML = `<div class="text-muted text-sm" style="padding:2rem;text-align:center;">${escapeHtml(data.message || 'Search failed')}</div>`;
            return;
        }

        if (!data.results || data.results.length === 0) {
            container.innerHTML = '<div class="text-muted text-sm" style="padding:2rem;text-align:center;">No results found on TMDB</div>';
            return;
        }

        let html = '';
        data.results.forEach(item => {
            const posterHtml = item.poster
                ? `<img class="content-result-img" src="${escapeHtml(item.poster)}" alt="" loading="lazy">`
                : `<div class="content-result-img" style="display:flex;align-items:center;justify-content:center;font-size:0.6rem;color:var(--text-muted)">No poster</div>`;

            const rating = item.vote_average ? `<span class="content-result-rating"><i class="lucide-star" style="width:10px;height:10px;"></i> ${Number(item.vote_average).toFixed(1)}</span>` : '';
            const badge = `<span class="content-result-badge">${item.type === 'series' ? 'TV' : 'Movie'}</span>`;
            const desc = item.overview ? `<div class="content-result-desc">${escapeHtml(item.overview)}</div>` : '';

            html += `<div class="content-result-item" onclick="importTmdbItem(${item.tmdb_id}, '${escapeHtml(item.type)}')">
                ${posterHtml}
                <div class="content-result-info">
                    <div class="content-result-name">${escapeHtml(item.name)} ${rating} ${badge}</div>
                    <div class="content-result-meta">${escapeHtml(item.year || 'Unknown year')}</div>
                    ${desc}
                </div>
            </div>`;
        });

        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="text-muted text-sm" style="padding:2rem;text-align:center;">Search failed - check network connection</div>';
    }
}

async function importTmdbItem(tmdbId, contentType) {
    if (!activeSectionForPicker) return;

    // Show importing state on the clicked item
    event.currentTarget.style.opacity = '0.5';
    event.currentTarget.style.pointerEvents = 'none';

    const form = new FormData();
    form.append('_token', csrfToken);
    form.append('section_id', activeSectionForPicker);
    form.append('tmdb_id', tmdbId);
    form.append('content_type', contentType);

    try {
        const res = await fetch('/admin/app-layout/import-tmdb-item', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            closeModal('contentPickerModal');
            window.location.reload();
        } else {
            alert(data.message || 'Failed to import');
            event.currentTarget.style.opacity = '';
            event.currentTarget.style.pointerEvents = '';
        }
    } catch (e) {
        alert('Network error');
    }
}

// --- Upload ---
let selectedFile = null;

function handleFileSelect(input) {
    const file = input.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        alert('File too large. Maximum 5MB.');
        return;
    }

    if (!file.type.startsWith('image/')) {
        alert('Please select an image file.');
        return;
    }

    selectedFile = file;
    document.getElementById('uploadFileName').textContent = file.name;
    document.getElementById('uploadFileSize').textContent = (file.size / 1024).toFixed(0) + ' KB';
    document.getElementById('uploadSubmitBtn').disabled = false;

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('uploadPreviewImg').src = e.target.result;
        document.getElementById('uploadPreview').classList.add('active');
        document.getElementById('uploadArea').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function clearUpload() {
    selectedFile = null;
    document.getElementById('uploadInput').value = '';
    document.getElementById('uploadPreview').classList.remove('active');
    document.getElementById('uploadArea').style.display = '';
    document.getElementById('uploadSubmitBtn').disabled = true;
    document.getElementById('uploadTitle').value = '';
    document.getElementById('uploadLinkUrl').value = '';
}

async function submitUpload() {
    if (!selectedFile || !activeSectionForPicker) return;

    const btn = document.getElementById('uploadSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="lucide-loader-2" style="animation:spin 1s linear infinite"></i> Uploading...';

    const form = new FormData();
    form.append('_token', csrfToken);
    form.append('section_id', activeSectionForPicker);
    form.append('image', selectedFile);
    form.append('title', document.getElementById('uploadTitle').value);
    form.append('link_url', document.getElementById('uploadLinkUrl').value);

    try {
        const res = await fetch('/admin/app-layout/upload-item-image', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            closeModal('contentPickerModal');
            window.location.reload();
        } else {
            alert(data.message || 'Upload failed');
            btn.disabled = false;
            btn.innerHTML = '<i class="lucide-upload"></i> Upload & Add';
        }
    } catch (e) {
        alert('Network error');
        btn.disabled = false;
        btn.innerHTML = '<i class="lucide-upload"></i> Upload & Add';
    }
}

// Upload drag & drop
document.addEventListener('DOMContentLoaded', function() {
    const area = document.getElementById('uploadArea');
    if (!area) return;

    ['dragenter','dragover'].forEach(e => area.addEventListener(e, function(ev) {
        ev.preventDefault();
        area.classList.add('drag-over');
    }));
    ['dragleave','drop'].forEach(e => area.addEventListener(e, function(ev) {
        ev.preventDefault();
        area.classList.remove('drag-over');
    }));
    area.addEventListener('drop', function(ev) {
        const file = ev.dataTransfer.files[0];
        if (file) {
            const input = document.getElementById('uploadInput');
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            handleFileSelect(input);
        }
    });
});

// --- Remove item ---
async function removeItem(sectionId, itemId) {
    const form = new FormData();
    form.append('_token', csrfToken);

    try {
        const res = await fetch(`/admin/app-layout/${layoutId}/sections/${sectionId}/items/${itemId}/remove`, { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            const el = document.querySelector(`[data-item-id="${itemId}"]`);
            if (el) el.remove();
        }
    } catch (e) {}
}

// ======================================
// Layout Actions
// ======================================
function openRenameModal() {
    document.getElementById('renameInput').value = document.getElementById('layoutTitle').textContent;
    document.getElementById('renameModal').classList.add('active');
    setTimeout(() => document.getElementById('renameInput').focus(), 100);
}

async function renameLayout() {
    const name = document.getElementById('renameInput').value.trim();
    if (!name) return;

    const form = new FormData();
    form.append('_token', csrfToken);
    form.append('name', name);

    try {
        const res = await fetch(`/admin/app-layout/${layoutId}/update`, { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            document.getElementById('layoutTitle').textContent = name;
            closeModal('renameModal');
        }
    } catch (e) {}
}

async function publishLayout() {
    if (!confirm('Publish this layout? It will become the default for its platform.')) return;

    const form = new FormData();
    form.append('_token', csrfToken);

    try {
        const res = await fetch(`/admin/app-layout/${layoutId}/publish`, { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to publish');
        }
    } catch (e) {
        alert('Network error');
    }
}

// ======================================
// Utility
// ======================================
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Close modals on overlay click or Escape
['addSectionModal', 'contentPickerModal', 'renameModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        ['addSectionModal', 'contentPickerModal', 'renameModal'].forEach(id => closeModal(id));
    }
});
</script>

<?php
/**
 * Render setting fields based on section type
 */
function renderSectionSettings(array $section, ?array $typeDef): string
{
    if (!$typeDef) return '<div class="text-muted text-sm">Unknown section type</div>';

    $settings = $section['settings'] ?? [];
    $defaults = $typeDef['default_settings'] ?? [];
    $html = '';

    switch ($section['section_type']) {
        case 'hero_slideshow':
            $html .= settingCheckbox('auto_rotate', 'Auto-rotate slides', $settings, $defaults);
            $html .= settingNumber('interval', 'Rotation interval (seconds)', $settings, $defaults, 3, 30);
            $html .= settingSelect('height', 'Hero Height', $settings, $defaults, [
                'small' => 'Small (300px)', 'medium' => 'Medium (450px)', 'large' => 'Large (600px)'
            ]);
            $html .= settingCheckbox('show_description', 'Show description', $settings, $defaults);
            $html .= settingCheckbox('show_play_button', 'Show play button', $settings, $defaults);
            $html .= settingCheckbox('show_info_button', 'Show info button', $settings, $defaults);
            break;

        case 'content_row':
            $html .= settingSelect('source', 'Content Source', $settings, $defaults, [
                'curated' => 'Curated (manual)', 'latest' => 'Latest Added', 'popular' => 'Most Popular',
                'top_rated' => 'Top Rated', 'featured' => 'Featured', 'category' => 'By Category'
            ]);
            $html .= settingSelect('content_type', 'Content Type', $settings, $defaults, [
                'movie' => 'Movies', 'series' => 'TV Shows', 'mixed' => 'Mixed'
            ]);
            $html .= settingSelect('card_style', 'Card Style', $settings, $defaults, [
                'poster' => 'Poster (2:3)', 'backdrop' => 'Backdrop (16:9)', 'square' => 'Square (1:1)'
            ]);
            $html .= settingNumber('max_items', 'Max Items', $settings, $defaults, 1, 50);
            $html .= settingCheckbox('auto_scroll', 'Auto-scroll', $settings, $defaults);
            break;

        case 'live_now':
            $html .= settingNumber('max_channels', 'Max Channels', $settings, $defaults, 1, 30);
            $html .= settingCheckbox('show_progress', 'Show progress bar', $settings, $defaults);
            $html .= settingCheckbox('show_next', 'Show next programme', $settings, $defaults);
            break;

        case 'epg_schedule':
            $html .= settingNumber('hours_ahead', 'Hours Ahead', $settings, $defaults, 1, 24);
            $html .= settingNumber('max_channels', 'Max Channels', $settings, $defaults, 1, 20);
            break;

        case 'banner':
            $html .= settingText('image_url', 'Banner Image URL', $settings, $defaults);
            $html .= settingText('link_url', 'Link URL', $settings, $defaults);
            $html .= settingSelect('link_type', 'Link Type', $settings, $defaults, [
                'url' => 'External URL', 'movie' => 'Movie', 'series' => 'TV Show', 'channel' => 'Channel'
            ]);
            $html .= settingSelect('aspect_ratio', 'Aspect Ratio', $settings, $defaults, [
                '21:9' => 'Ultra-wide (21:9)', '16:9' => 'Widescreen (16:9)', '3:1' => 'Banner (3:1)'
            ]);
            break;

        case 'category_grid':
            $html .= settingSelect('content_type', 'Show Categories For', $settings, $defaults, [
                'all' => 'All Types', 'live' => 'Live TV', 'vod' => 'Movies', 'series' => 'TV Shows'
            ]);
            $html .= settingNumber('columns', 'Columns', $settings, $defaults, 2, 6);
            $html .= settingNumber('max_items', 'Max Categories', $settings, $defaults, 1, 24);
            $html .= settingCheckbox('show_count', 'Show item count', $settings, $defaults);
            break;

        case 'channel_grid':
            $html .= settingSelect('source', 'Source', $settings, $defaults, [
                'curated' => 'Curated (manual)', 'popular' => 'Most Watched', 'category' => 'By Category'
            ]);
            $html .= settingNumber('columns', 'Columns', $settings, $defaults, 2, 8);
            $html .= settingNumber('max_items', 'Max Channels', $settings, $defaults, 1, 30);
            $html .= settingCheckbox('show_now_playing', 'Show now playing', $settings, $defaults);
            break;

        case 'continue_watching':
            $html .= settingNumber('max_items', 'Max Items', $settings, $defaults, 1, 20);
            $html .= settingSelect('card_style', 'Card Style', $settings, $defaults, [
                'backdrop' => 'Backdrop (16:9)', 'poster' => 'Poster (2:3)'
            ]);
            $html .= settingCheckbox('show_progress', 'Show progress bar', $settings, $defaults);
            break;

        case 'spotlight':
            $html .= settingSelect('style', 'Display Style', $settings, $defaults, [
                'card' => 'Card', 'full_width' => 'Full Width', 'side_by_side' => 'Side by Side'
            ]);
            $html .= settingCheckbox('show_trailer', 'Show trailer', $settings, $defaults);
            $html .= settingCheckbox('show_description', 'Show description', $settings, $defaults);
            break;

        case 'text_divider':
            $html .= settingText('text', 'Heading Text', $settings, $defaults);
            $html .= settingSelect('style', 'Style', $settings, $defaults, [
                'heading' => 'Heading', 'subheading' => 'Subheading', 'divider' => 'Line Divider'
            ]);
            $html .= settingSelect('alignment', 'Alignment', $settings, $defaults, [
                'left' => 'Left', 'center' => 'Center', 'right' => 'Right'
            ]);
            break;
    }

    return $html;
}

function settingText(string $key, string $label, array $settings, array $defaults): string
{
    $value = htmlspecialchars($settings[$key] ?? $defaults[$key] ?? '');
    return "<div class=\"form-group\">
        <label class=\"form-label\">{$label}</label>
        <input type=\"text\" class=\"form-input\" data-setting=\"{$key}\" value=\"{$value}\">
    </div>";
}

function settingNumber(string $key, string $label, array $settings, array $defaults, int $min = 0, int $max = 100): string
{
    $value = (int) ($settings[$key] ?? $defaults[$key] ?? 0);
    return "<div class=\"form-group\">
        <label class=\"form-label\">{$label}</label>
        <input type=\"number\" class=\"form-input\" data-setting=\"{$key}\" value=\"{$value}\" min=\"{$min}\" max=\"{$max}\">
    </div>";
}

function settingSelect(string $key, string $label, array $settings, array $defaults, array $options): string
{
    $current = $settings[$key] ?? $defaults[$key] ?? '';
    $opts = '';
    foreach ($options as $val => $text) {
        $selected = ($val == $current) ? ' selected' : '';
        $opts .= "<option value=\"{$val}\"{$selected}>{$text}</option>";
    }
    return "<div class=\"form-group\">
        <label class=\"form-label\">{$label}</label>
        <select class=\"form-select\" data-setting=\"{$key}\">{$opts}</select>
    </div>";
}

function settingCheckbox(string $key, string $label, array $settings, array $defaults): string
{
    $checked = ($settings[$key] ?? $defaults[$key] ?? false) ? ' checked' : '';
    return "<div class=\"form-group\">
        <label class=\"form-check\">
            <input type=\"checkbox\" data-setting=\"{$key}\"{$checked}>
            {$label}
        </label>
    </div>";
}
?>
