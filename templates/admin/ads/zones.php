<?php $pageTitle = 'Ad Zones'; ?>

<style>
    .zone-card { background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; }
    .zone-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
    .zone-type-group { margin-bottom: 1.5rem; }
    .zone-type-group h3 { font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
    .zone-slug { font-size: 0.75rem; color: var(--text-muted); font-family: monospace; background: rgba(0,0,0,0.2); padding: 0.15rem 0.4rem; border-radius: 3px; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #1e293b; border: 1px solid #475569; border-radius: 12px; width: 90%; max-width: 540px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); }
    .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #1e293b; }
    .modal-header h3 { font-size: 1rem; font-weight: 600; }
    .modal-body { padding: 1.25rem; background: #1e293b; }
    .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; background: #1e293b; }
    .modal-close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem; padding: 0.25rem; }
</style>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Ad Zones</h1>
        <p class="page-subtitle">Manage where ads can appear in your app and player.</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/admin/ads" class="btn btn-secondary">Back to Campaigns</a>
        <button class="btn btn-primary" onclick="openZoneModal()">
            <i class="lucide-plus"></i> Add Zone
        </button>
    </div>
</div>

<?php
// Group zones by type
$grouped = [];
foreach ($zones as $zone) {
    $grouped[$zone['zone_type']][] = $zone;
}
?>

<?php foreach ($adTypes as $typeKey => $typeInfo): ?>
    <div class="zone-type-group">
        <h3>
            <i class="<?= $typeInfo['icon'] ?>"></i>
            <?= $typeInfo['name'] ?> Zones
        </h3>
        <div class="card">
            <div class="card-body">
                <?php if (empty($grouped[$typeKey])): ?>
                    <div style="text-align:center;padding:1rem;color:var(--text-muted);">
                        No zones defined for <?= $typeInfo['name'] ?>.
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped[$typeKey] as $zone): ?>
                        <div class="zone-card">
                            <div class="zone-card-header">
                                <div>
                                    <strong><?= htmlspecialchars($zone['name']) ?></strong>
                                    <span class="zone-slug"><?= htmlspecialchars($zone['slug']) ?></span>
                                    <span class="badge badge-<?= $zone['is_active'] ? 'success' : 'warning' ?>" style="margin-left:0.5rem;">
                                        <?= $zone['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                <div style="display:flex;gap:0.25rem;">
                                    <button class="btn btn-secondary btn-sm" onclick='editZone(<?= json_encode($zone) ?>)'>Edit</button>
                                    <button class="btn btn-<?= $zone['is_active'] ? 'warning' : 'success' ?> btn-sm" onclick="toggleZone(<?= $zone['id'] ?>)">
                                        <?= $zone['is_active'] ? 'Disable' : 'Enable' ?>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteZone(<?= $zone['id'] ?>, '<?= htmlspecialchars(addslashes($zone['name'])) ?>')">Delete</button>
                                </div>
                            </div>
                            <?php if ($zone['description']): ?>
                                <div style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($zone['description']) ?></div>
                            <?php endif; ?>
                            <div style="display:flex;gap:1rem;margin-top:0.5rem;font-size:0.75rem;">
                                <?php if (!empty($zone['floor_cpm'])): ?>
                                <span style="color:#f59e0b"><strong>Floor CPM:</strong> $<?= $zone['floor_cpm'] ?></span>
                                <?php endif; ?>
                                <?php if (!empty($zone['fallback_vast_url'])): ?>
                                <span style="color:#818cf8" title="<?= htmlspecialchars($zone['fallback_vast_url']) ?>"><strong>Fallback:</strong> VAST configured</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Zone Modal -->
<div class="modal-overlay" id="zoneModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="zoneModalTitle">Add Zone</h3>
            <button class="modal-close" onclick="closeModal('zoneModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="zoneId" value="">

            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" id="zoneName" class="form-input" placeholder="e.g. VOD Pre-Roll">
            </div>
            <div class="form-group">
                <label class="form-label">Slug *</label>
                <input type="text" id="zoneSlug" class="form-input" placeholder="e.g. vod-preroll">
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">Unique identifier used in API calls</div>
            </div>
            <div class="form-group">
                <label class="form-label">Type *</label>
                <select id="zoneType" class="form-input">
                    <option value="">Select type...</option>
                    <?php foreach ($adTypes as $key => $type): ?>
                        <option value="<?= $key ?>"><?= $type['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea id="zoneDescription" class="form-input" rows="2" placeholder="Where this zone appears..."></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
                <div class="form-group">
                    <label class="form-label">Floor CPM ($)</label>
                    <input type="number" id="zoneFloorCpm" class="form-input" step="0.01" min="0" placeholder="Min CPM">
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.25rem;">Minimum CPM to serve ads in this zone</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Fallback VAST URL</label>
                    <input type="url" id="zoneFallbackVast" class="form-input" placeholder="https://...">
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.25rem;">3rd-party VAST when no direct ads fill</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('zoneModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveZone()">Save Zone</button>
        </div>
    </div>
</div>

<script>
const csrf = '<?= $csrf ?>';

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function openZoneModal() {
    document.getElementById('zoneId').value = '';
    document.getElementById('zoneName').value = '';
    document.getElementById('zoneSlug').value = '';
    document.getElementById('zoneType').value = '';
    document.getElementById('zoneDescription').value = '';
    document.getElementById('zoneFloorCpm').value = '';
    document.getElementById('zoneFallbackVast').value = '';
    document.getElementById('zoneModalTitle').textContent = 'Add Zone';
    openModal('zoneModal');
}

function editZone(zone) {
    document.getElementById('zoneId').value = zone.id;
    document.getElementById('zoneName').value = zone.name;
    document.getElementById('zoneSlug').value = zone.slug;
    document.getElementById('zoneType').value = zone.zone_type;
    document.getElementById('zoneDescription').value = zone.description || '';
    document.getElementById('zoneFloorCpm').value = zone.floor_cpm || '';
    document.getElementById('zoneFallbackVast').value = zone.fallback_vast_url || '';
    document.getElementById('zoneModalTitle').textContent = 'Edit Zone';
    openModal('zoneModal');
}

function saveZone() {
    const id = document.getElementById('zoneId').value;
    const data = new URLSearchParams();
    data.append('_token', csrf);
    data.append('name', document.getElementById('zoneName').value);
    data.append('slug', document.getElementById('zoneSlug').value);
    data.append('zone_type', document.getElementById('zoneType').value);
    data.append('description', document.getElementById('zoneDescription').value);

    const url = id ? `/admin/ads/zones/${id}/update` : '/admin/ads/zones/store';

    fetch(url, { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // Save floor price if editing
                if (id) {
                    const fpData = new URLSearchParams();
                    fpData.append('csrf_token', csrf);
                    fpData.append('floor_cpm', document.getElementById('zoneFloorCpm').value);
                    fpData.append('fallback_vast_url', document.getElementById('zoneFallbackVast').value);
                    fetch(`/admin/ads/zones/${id}/floor-price`, { method: 'POST', body: fpData })
                        .then(() => location.reload());
                } else {
                    location.reload();
                }
            } else {
                alert(d.message || 'Error saving zone');
            }
        });
}

function toggleZone(id) {
    fetch(`/admin/ads/zones/${id}/toggle`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + csrf
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
}

function deleteZone(id, name) {
    if (!confirm(`Delete zone "${name}"? Any placements using this zone will also be deleted.`)) return;

    fetch(`/admin/ads/zones/${id}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + csrf
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert(d.message); });
}

// Auto-generate slug from name
document.getElementById('zoneName').addEventListener('input', function() {
    if (!document.getElementById('zoneId').value) {
        const slug = this.value.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
        document.getElementById('zoneSlug').value = slug;
    }
});
</script>
