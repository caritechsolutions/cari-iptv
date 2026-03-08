<?php $pageTitle = 'Ad Pods & Breaks'; ?>

<style>
    .pod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1rem; }
    .pod-card { background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; }
    .pod-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
    .pod-header h4 { font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
    .pod-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
    .pod-stat { font-size: 0.8rem; color: var(--text-muted); }
    .pod-stat strong { color: var(--text-secondary); display: block; }
    .badge-pre_roll { background: rgba(245,158,11,0.15); color: #f59e0b; }
    .badge-mid_roll { background: rgba(239,68,68,0.15); color: #ef4444; }
    .badge-post_roll { background: rgba(99,102,241,0.15); color: #818cf8; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #1e293b; border: 1px solid #475569; border-radius: 12px; width: 90%; max-width: 540px; max-height: 90vh; overflow-y: auto; }
    .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .modal-body { padding: 1.25rem; }
    .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; }
    .modal-close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem; }
    .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
    .empty-state i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; }
    .info-callout { background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--text-secondary); }
    .info-callout i { color: var(--primary); margin-right: 0.5rem; }
</style>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Ad Pods & Breaks</h1>
        <p class="page-subtitle">Configure multi-ad break sequences like TV commercial breaks. Integrates with video cue points.</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/admin/ads" class="btn btn-secondary">Back to Campaigns</a>
        <button class="btn btn-primary" onclick="openPodModal()"><i class="lucide-plus"></i> New Pod</button>
    </div>
</div>

<div class="info-callout">
    <i class="lucide-info"></i>
    <strong>How it works:</strong> Ad pods group multiple ads into a single break. For mid-rolls, pods trigger at <strong>cue points</strong> you set in the movie/episode editor.
    The system respects max ads, total duration, and competitor separation rules. Pre-roll pods play before content starts.
</div>

<?php if (empty($pods)): ?>
<div class="card"><div class="card-body empty-state">
    <i class="lucide-layers"></i>
    <h3>No Ad Pods Configured</h3>
    <p>Create an ad pod to serve multiple ads in a single break, like a TV commercial break.</p>
    <button class="btn btn-primary" onclick="openPodModal()" style="margin-top:1rem"><i class="lucide-plus"></i> Create First Pod</button>
</div></div>
<?php else: ?>
<div class="pod-grid">
<?php foreach ($pods as $pod): ?>
<div class="card"><div class="card-body pod-card">
    <div class="pod-header">
        <h4>
            <i class="lucide-layers" style="color:var(--primary)"></i>
            <?= htmlspecialchars($pod['name']) ?>
            <span class="badge badge-<?= $pod['pod_type'] ?>"><?= ucfirst(str_replace('_', ' ', $pod['pod_type'])) ?></span>
            <?php if (!$pod['is_active']): ?><span class="badge badge-secondary">Inactive</span><?php endif; ?>
        </h4>
        <div style="display:flex;gap:0.25rem">
            <button class="btn btn-sm btn-secondary" onclick='editPod(<?= json_encode($pod) ?>)'><i class="lucide-edit-2"></i></button>
            <button class="btn btn-sm btn-danger" onclick="deletePod(<?= $pod['id'] ?>)"><i class="lucide-trash-2"></i></button>
        </div>
    </div>
    <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.75rem"><?= htmlspecialchars($pod['zone_name']) ?></div>
    <div class="pod-stats">
        <div class="pod-stat"><strong><?= $pod['max_ads'] ?></strong>Max Ads</div>
        <div class="pod-stat"><strong><?= $pod['max_duration_seconds'] ?>s</strong>Max Duration</div>
        <div class="pod-stat"><strong><?= $pod['separation_seconds'] ?>s</strong>Min Gap Between</div>
        <div class="pod-stat"><strong><?= $pod['allow_competitor_ads'] ? 'Yes' : 'No' ?></strong>Allow Competitors</div>
        <?php if ($pod['min_content_duration']): ?>
        <div class="pod-stat"><strong><?= round($pod['min_content_duration'] / 60) ?>min</strong>Min Content Length</div>
        <?php endif; ?>
    </div>
</div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal -->
<div class="modal-overlay" id="podModal">
<div class="modal-box">
    <div class="modal-header">
        <h3 id="podModalTitle">New Ad Pod</h3>
        <button class="modal-close" onclick="closePodModal()">&times;</button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="podId" value="">
        <div class="form-group">
            <label class="form-label">Pod Name</label>
            <input type="text" class="form-control" id="podName" placeholder="e.g. Standard Pre-Roll Pod">
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
            <div class="form-group">
                <label class="form-label">Zone</label>
                <select class="form-control" id="podZone">
                    <?php foreach ($zones as $z): ?>
                    <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Pod Type</label>
                <select class="form-control" id="podType">
                    <option value="pre_roll">Pre-Roll</option>
                    <option value="mid_roll">Mid-Roll (cue point)</option>
                    <option value="post_roll">Post-Roll</option>
                </select>
            </div>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
            <div class="form-group">
                <label class="form-label">Max Ads in Pod</label>
                <input type="number" class="form-control" id="podMaxAds" value="3" min="1" max="10">
            </div>
            <div class="form-group">
                <label class="form-label">Max Total Duration (sec)</label>
                <input type="number" class="form-control" id="podMaxDuration" value="90" min="5" max="600">
            </div>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
            <div class="form-group">
                <label class="form-label">Min Gap Between Pods (sec)</label>
                <input type="number" class="form-control" id="podSeparation" value="300" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">Min Content Duration (sec)</label>
                <input type="number" class="form-control" id="podMinContent" value="" placeholder="Optional">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label d-flex align-items-center gap-2">
                <input type="checkbox" id="podCompetitors"> Allow competing advertisers in same pod
            </label>
        </div>
        <div class="form-group">
            <label class="form-label d-flex align-items-center gap-2">
                <input type="checkbox" id="podActive" checked> Active
            </label>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closePodModal()">Cancel</button>
        <button class="btn btn-primary" onclick="savePod()">Save Pod</button>
    </div>
</div>
</div>

<script>
var csrfToken = '<?= \CariIPTV\Core\Session::csrf() ?>';

function openPodModal(pod) {
    document.getElementById('podId').value = pod ? pod.id : '';
    document.getElementById('podName').value = pod ? pod.name : '';
    document.getElementById('podZone').value = pod ? pod.zone_id : '';
    document.getElementById('podType').value = pod ? pod.pod_type : 'pre_roll';
    document.getElementById('podMaxAds').value = pod ? pod.max_ads : 3;
    document.getElementById('podMaxDuration').value = pod ? pod.max_duration_seconds : 90;
    document.getElementById('podSeparation').value = pod ? pod.separation_seconds : 300;
    document.getElementById('podMinContent').value = pod ? (pod.min_content_duration || '') : '';
    document.getElementById('podCompetitors').checked = pod ? !!parseInt(pod.allow_competitor_ads) : false;
    document.getElementById('podActive').checked = pod ? !!parseInt(pod.is_active) : true;
    document.getElementById('podModalTitle').textContent = pod ? 'Edit Ad Pod' : 'New Ad Pod';
    document.getElementById('podModal').classList.add('active');
}

function closePodModal() { document.getElementById('podModal').classList.remove('active'); }
function editPod(pod) { openPodModal(pod); }

function savePod() {
    var id = document.getElementById('podId').value;
    var url = id ? '/admin/ads/pods/' + id + '/update' : '/admin/ads/pods/store';
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('name', document.getElementById('podName').value);
    formData.append('zone_id', document.getElementById('podZone').value);
    formData.append('pod_type', document.getElementById('podType').value);
    formData.append('max_ads', document.getElementById('podMaxAds').value);
    formData.append('max_duration_seconds', document.getElementById('podMaxDuration').value);
    formData.append('separation_seconds', document.getElementById('podSeparation').value);
    formData.append('min_content_duration', document.getElementById('podMinContent').value || '');
    formData.append('allow_competitor_ads', document.getElementById('podCompetitors').checked ? 1 : 0);
    formData.append('is_active', document.getElementById('podActive').checked ? 1 : 0);

    fetch(url, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) location.reload();
            else alert(d.message || 'Error');
        });
}

function deletePod(id) {
    if (!confirm('Delete this ad pod?')) return;
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    fetch('/admin/ads/pods/' + id + '/delete', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.success) location.reload(); });
}
</script>
