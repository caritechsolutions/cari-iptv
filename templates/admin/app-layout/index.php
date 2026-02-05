<?php
$platforms = [
    'web' => ['label' => 'Web', 'icon' => 'lucide-globe'],
    'mobile' => ['label' => 'Mobile', 'icon' => 'lucide-smartphone'],
    'tv' => ['label' => 'Smart TV', 'icon' => 'lucide-tv'],
    'stb' => ['label' => 'Set-Top Box', 'icon' => 'lucide-hard-drive'],
];

$statusColors = [
    'draft' => 'badge-warning',
    'published' => 'badge-success',
    'archived' => 'badge-secondary',
];

$statusLabels = [
    'draft' => 'Draft',
    'published' => 'Published',
    'archived' => 'Archived',
];
?>

<style>
    .platform-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 0;
    }
    .platform-tab {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        color: var(--text-secondary);
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        transition: var(--transition);
        cursor: pointer;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .platform-tab:hover {
        color: var(--text-primary);
        background: var(--bg-hover);
    }
    .platform-tab.active {
        color: var(--primary-light);
        border-bottom-color: var(--primary);
    }
    .platform-tab .tab-count {
        background: var(--bg-hover);
        padding: 0.1rem 0.5rem;
        border-radius: 10px;
        font-size: 0.7rem;
    }
    .platform-tab.active .tab-count {
        background: rgba(99, 102, 241, 0.2);
    }

    .layout-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1rem;
    }

    .layout-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        transition: var(--transition);
    }
    .layout-card:hover {
        border-color: var(--primary);
        box-shadow: 0 0 0 1px var(--primary);
    }

    .layout-card-preview {
        height: 140px;
        background: var(--bg-dark);
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .layout-card-preview .preview-sections {
        display: flex;
        flex-direction: column;
        gap: 4px;
        width: 80%;
    }
    .preview-bar {
        height: 8px;
        border-radius: 3px;
        background: var(--bg-hover);
    }
    .preview-bar.hero { height: 40px; background: rgba(99, 102, 241, 0.15); border: 1px solid rgba(99, 102, 241, 0.3); }
    .preview-bar.row { width: 100%; }
    .preview-bar.half { width: 60%; }

    .layout-card-status {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
    }
    .layout-card-default {
        position: absolute;
        top: 0.75rem;
        left: 0.75rem;
    }

    .layout-card-body {
        padding: 1rem 1.25rem;
    }
    .layout-card-name {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }
    .layout-card-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-bottom: 0.75rem;
    }
    .layout-card-meta span {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .layout-card-actions {
        display: flex;
        gap: 0.5rem;
    }
    .layout-card-actions .btn {
        flex: 1;
        justify-content: center;
        font-size: 0.8rem;
        padding: 0.5rem;
    }

    .badge-secondary {
        background: rgba(100, 116, 139, 0.15);
        color: var(--text-muted);
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-muted);
    }
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        display: block;
        opacity: 0.5;
    }
    .empty-state h3 {
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
    }

    /* Create Modal */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        box-shadow: var(--shadow-lg);
    }
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
        justify-content: flex-end;
        gap: 0.5rem;
    }

    .platform-select-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    .platform-option {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        cursor: pointer;
        transition: var(--transition);
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    .platform-option:hover {
        border-color: var(--primary);
        color: var(--text-primary);
    }
    .platform-option.selected {
        border-color: var(--primary);
        background: rgba(99, 102, 241, 0.1);
        color: var(--primary-light);
    }
    .platform-option input { display: none; }
</style>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">App Layout</h1>
        <p class="page-subtitle">Design your app's home screen experience for each platform.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openCreateModal()">
            <i class="lucide-plus"></i> New Layout
        </button>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="lucide-layout"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Layouts</div>
            <div class="stat-value"><?= number_format($stats['total_layouts']) ?></div>
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
            <i class="lucide-edit-3"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Drafts</div>
            <div class="stat-value"><?= number_format($stats['drafts']) ?></div>
        </div>
    </div>
</div>

<!-- Platform Tabs -->
<?php
$platformCounts = [];
foreach ($stats['platforms'] as $p) {
    $platformCounts[$p['platform']] = $p['count'];
}
?>
<div class="platform-tabs">
    <a href="/admin/app-layout" class="platform-tab <?= empty($activePlatform) ? 'active' : '' ?>">
        All
        <span class="tab-count"><?= $stats['total_layouts'] ?></span>
    </a>
    <?php foreach ($platforms as $key => $info): ?>
        <a href="/admin/app-layout?platform=<?= $key ?>" class="platform-tab <?= $activePlatform === $key ? 'active' : '' ?>">
            <i class="<?= $info['icon'] ?>"></i>
            <?= $info['label'] ?>
            <span class="tab-count"><?= $platformCounts[$key] ?? 0 ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Layouts Grid -->
<?php if (empty($layouts)): ?>
    <div class="card">
        <div class="empty-state">
            <i class="lucide-layout"></i>
            <h3>No Layouts Yet</h3>
            <p>Create your first app layout to start designing the home screen experience.</p>
            <br>
            <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                <i class="lucide-plus"></i> Create Layout
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="layout-grid">
        <?php foreach ($layouts as $layout): ?>
            <div class="layout-card" id="layout-<?= $layout['id'] ?>">
                <div class="layout-card-preview">
                    <div class="preview-sections">
                        <div class="preview-bar hero"></div>
                        <div class="preview-bar row"></div>
                        <div class="preview-bar row"></div>
                        <div class="preview-bar half"></div>
                    </div>
                    <div class="layout-card-status">
                        <span class="badge <?= $statusColors[$layout['status']] ?? 'badge-secondary' ?>">
                            <?= $statusLabels[$layout['status']] ?? $layout['status'] ?>
                        </span>
                    </div>
                    <?php if ($layout['is_default']): ?>
                        <div class="layout-card-default">
                            <span class="badge badge-primary">Default</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="layout-card-body">
                    <div class="layout-card-name"><?= htmlspecialchars($layout['name']) ?></div>
                    <div class="layout-card-meta">
                        <span><i class="<?= $platforms[$layout['platform']]['icon'] ?? 'lucide-monitor' ?>"></i> <?= $platforms[$layout['platform']]['label'] ?? $layout['platform'] ?></span>
                        <span><i class="lucide-layers"></i> <?= $layout['section_count'] ?> section<?= $layout['section_count'] != 1 ? 's' : '' ?></span>
                        <span title="<?= htmlspecialchars($layout['updated_at']) ?>">
                            <?= date('M j', strtotime($layout['updated_at'])) ?>
                        </span>
                    </div>
                    <div class="layout-card-actions">
                        <a href="/admin/app-layout/<?= $layout['id'] ?>/builder" class="btn btn-primary btn-sm">
                            <i class="lucide-pencil"></i> Edit
                        </a>
                        <button class="btn btn-secondary btn-sm" onclick="duplicateLayout(<?= $layout['id'] ?>)" title="Duplicate">
                            <i class="lucide-copy"></i>
                        </button>
                        <?php if ($layout['status'] === 'draft'): ?>
                            <button class="btn btn-secondary btn-sm" onclick="publishLayout(<?= $layout['id'] ?>)" title="Publish" style="color: var(--success);">
                                <i class="lucide-upload"></i>
                            </button>
                        <?php endif; ?>
                        <?php if ($layout['status'] !== 'published'): ?>
                            <button class="btn btn-secondary btn-sm" onclick="deleteLayout(<?= $layout['id'] ?>, '<?= htmlspecialchars(addslashes($layout['name'])) ?>')" title="Delete" style="color: var(--danger);">
                                <i class="lucide-trash-2"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create Layout Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Create New Layout</h3>
            <button class="modal-close" onclick="closeCreateModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Layout Name</label>
                <input type="text" id="layoutName" class="form-input" placeholder="e.g. Netflix-style Home">
            </div>
            <div class="form-group">
                <label class="form-label">Platform</label>
                <div class="platform-select-grid">
                    <?php foreach ($platforms as $key => $info): ?>
                        <label class="platform-option" onclick="selectPlatform('<?= $key ?>')">
                            <input type="radio" name="platform" value="<?= $key ?>">
                            <i class="<?= $info['icon'] ?>"></i>
                            <?= $info['label'] ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="createLayout()" id="createBtn">Create Layout</button>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $csrf ?>';
let selectedPlatform = '';

function openCreateModal() {
    document.getElementById('createModal').classList.add('active');
    document.getElementById('layoutName').value = '';
    selectedPlatform = '';
    document.querySelectorAll('.platform-option').forEach(el => el.classList.remove('selected'));
    setTimeout(() => document.getElementById('layoutName').focus(), 100);
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('active');
}

function selectPlatform(platform) {
    selectedPlatform = platform;
    document.querySelectorAll('.platform-option').forEach(el => {
        el.classList.toggle('selected', el.querySelector('input').value === platform);
    });
}

async function createLayout() {
    const name = document.getElementById('layoutName').value.trim();
    if (!name) { alert('Please enter a layout name'); return; }
    if (!selectedPlatform) { alert('Please select a platform'); return; }

    const btn = document.getElementById('createBtn');
    btn.disabled = true;
    btn.textContent = 'Creating...';

    const form = new FormData();
    form.append('_token', csrfToken);
    form.append('name', name);
    form.append('platform', selectedPlatform);

    try {
        const res = await fetch('/admin/app-layout/store', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            window.location.href = '/admin/app-layout/' + data.id + '/builder';
        } else {
            alert(data.message || 'Failed to create layout');
            btn.disabled = false;
            btn.textContent = 'Create Layout';
        }
    } catch (e) {
        alert('Network error');
        btn.disabled = false;
        btn.textContent = 'Create Layout';
    }
}

async function deleteLayout(id, name) {
    if (!confirm('Delete layout "' + name + '"? This cannot be undone.')) return;

    const form = new FormData();
    form.append('_token', csrfToken);

    try {
        const res = await fetch('/admin/app-layout/' + id + '/delete', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            const el = document.getElementById('layout-' + id);
            if (el) el.remove();
        } else {
            alert(data.message || 'Failed to delete');
        }
    } catch (e) {
        alert('Network error');
    }
}

async function duplicateLayout(id) {
    const form = new FormData();
    form.append('_token', csrfToken);

    try {
        const res = await fetch('/admin/app-layout/' + id + '/duplicate', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to duplicate');
        }
    } catch (e) {
        alert('Network error');
    }
}

async function publishLayout(id) {
    if (!confirm('Publish this layout? It will become the default for its platform and archive any currently published layout.')) return;

    const form = new FormData();
    form.append('_token', csrfToken);

    try {
        const res = await fetch('/admin/app-layout/' + id + '/publish', { method: 'POST', body: form });
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

// Close modal on overlay click
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) closeCreateModal();
});

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCreateModal();
});
</script>
