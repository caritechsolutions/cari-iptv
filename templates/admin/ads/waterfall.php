<?php $pageTitle = 'Ad Waterfall Chains'; ?>

<style>
    .chain-card { background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
    .chain-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .chain-header h4 { font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
    .chain-zone { font-size: 0.75rem; color: var(--text-muted); background: rgba(99,102,241,0.15); padding: 2px 8px; border-radius: 4px; }
    .step-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .step-row:last-child { border-bottom: none; }
    .step-num { width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600; flex-shrink: 0; }
    .step-type { font-size: 0.8rem; padding: 2px 8px; border-radius: 4px; font-weight: 500; }
    .step-type.direct { background: rgba(34,197,94,0.15); color: #22c55e; }
    .step-type.campaign { background: rgba(99,102,241,0.15); color: #818cf8; }
    .step-type.vast_tag { background: rgba(245,158,11,0.15); color: #f59e0b; }
    .step-type.house { background: rgba(148,163,184,0.15); color: #94a3b8; }
    .step-details { flex: 1; font-size: 0.85rem; color: var(--text-secondary); }
    .step-timeout { font-size: 0.75rem; color: var(--text-muted); }
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #1e293b; border: 1px solid #475569; border-radius: 12px; width: 90%; max-width: 640px; max-height: 90vh; overflow-y: auto; }
    .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .modal-header h3 { font-size: 1rem; font-weight: 600; }
    .modal-body { padding: 1.25rem; }
    .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; }
    .modal-close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem; padding: 0.25rem; }
    .step-builder { margin-top: 1rem; }
    .step-builder-row { display: grid; grid-template-columns: 120px 1fr 100px 80px 40px; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; padding: 0.5rem; background: rgba(0,0,0,0.2); border-radius: 6px; }
    .step-builder-row .form-input { padding: 0.4rem 0.6rem; font-size: 0.8rem; }
    .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
    .empty-state i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; }
</style>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Ad Waterfall Chains</h1>
        <p class="page-subtitle">Configure fallback sequences when direct ads don't fill a zone.</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/admin/ads" class="btn btn-secondary">Back to Campaigns</a>
        <button class="btn btn-primary" onclick="openChainModal()"><i class="lucide-plus"></i> New Chain</button>
    </div>
</div>

<?php if (empty($chains)): ?>
<div class="card"><div class="card-body empty-state">
    <i class="lucide-git-branch"></i>
    <h3>No Waterfall Chains</h3>
    <p>Create a waterfall chain to define fallback ad sources when your direct-sold inventory doesn't fill.</p>
    <button class="btn btn-primary" onclick="openChainModal()" style="margin-top:1rem"><i class="lucide-plus"></i> Create First Chain</button>
</div></div>
<?php else: ?>
<?php foreach ($chains as $chain): ?>
<div class="card"><div class="card-body chain-card">
    <div class="chain-header">
        <h4>
            <i class="lucide-git-branch" style="color:var(--primary)"></i>
            <?= htmlspecialchars($chain['name']) ?>
            <span class="chain-zone"><?= htmlspecialchars($chain['zone_name']) ?> (<?= htmlspecialchars($chain['zone_type']) ?>)</span>
            <?php if (!$chain['is_active']): ?><span class="badge badge-secondary">Inactive</span><?php endif; ?>
        </h4>
        <div style="display:flex;gap:0.5rem">
            <button class="btn btn-sm btn-secondary" onclick="editChain(<?= $chain['id'] ?>)"><i class="lucide-edit-2"></i> Edit</button>
            <button class="btn btn-sm btn-danger" onclick="deleteChain(<?= $chain['id'] ?>)"><i class="lucide-trash-2"></i></button>
        </div>
    </div>
    <?php if (empty($chain['steps'])): ?>
        <div style="text-align:center;padding:0.75rem;color:var(--text-muted);font-size:0.85rem">No steps configured. Edit this chain to add fallback steps.</div>
    <?php else: ?>
        <?php foreach ($chain['steps'] as $step): ?>
        <div class="step-row">
            <div class="step-num"><?= $step['step_order'] ?></div>
            <span class="step-type <?= $step['source_type'] ?>"><?= ucfirst(str_replace('_', ' ', $step['source_type'])) ?></span>
            <div class="step-details">
                <?php if ($step['campaign_name']): ?>Campaign: <?= htmlspecialchars($step['campaign_name']) ?>
                <?php elseif ($step['vast_tag_url']): ?>VAST: <?= htmlspecialchars(substr($step['vast_tag_url'], 0, 60)) ?>...
                <?php else: ?>Any matching ads<?php endif; ?>
            </div>
            <div class="step-timeout">Timeout: <?= $step['timeout_ms'] ?>ms<?php if ($step['floor_cpm']): ?> | Floor: $<?= $step['floor_cpm'] ?><?php endif; ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div></div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Modal -->
<div class="modal-overlay" id="chainModal">
<div class="modal-box">
    <div class="modal-header">
        <h3 id="chainModalTitle">New Waterfall Chain</h3>
        <button class="modal-close" onclick="closeChainModal()">&times;</button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="chainId" value="">
        <div class="form-group">
            <label class="form-label">Chain Name</label>
            <input type="text" class="form-input" id="chainName" placeholder="e.g. Pre-Roll VOD Waterfall">
        </div>
        <div class="form-group">
            <label class="form-label">Zone</label>
            <select class="form-input" id="chainZone">
                <option value="">Select zone...</option>
                <?php foreach ($zones as $z): ?>
                <option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?> (<?= $z['zone_type'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label d-flex align-items-center gap-2">
                <input type="checkbox" id="chainActive" checked> Active
            </label>
        </div>

        <h4 style="margin-top:1rem;font-size:0.9rem;font-weight:600">Fallback Steps <small style="color:var(--text-muted)">(tried in order)</small></h4>
        <div id="stepsContainer"></div>
        <button class="btn btn-sm btn-secondary" onclick="addStep()" style="margin-top:0.5rem"><i class="lucide-plus"></i> Add Step</button>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeChainModal()">Cancel</button>
        <button class="btn btn-primary" onclick="saveChain()">Save Chain</button>
    </div>
</div>
</div>

<script>
var chainsData = <?= json_encode($chains) ?>;
var campaignsList = <?= json_encode($campaigns) ?>;
var csrfToken = '<?= \CariIPTV\Core\Session::csrf() ?>';

function openChainModal(chain) {
    document.getElementById('chainId').value = chain ? chain.id : '';
    document.getElementById('chainName').value = chain ? chain.name : '';
    document.getElementById('chainZone').value = chain ? chain.zone_id : '';
    document.getElementById('chainActive').checked = chain ? !!parseInt(chain.is_active) : true;
    document.getElementById('chainModalTitle').textContent = chain ? 'Edit Waterfall Chain' : 'New Waterfall Chain';
    document.getElementById('stepsContainer').innerHTML = '';
    if (chain && chain.steps) {
        chain.steps.forEach(function(s) { addStep(s); });
    }
    document.getElementById('chainModal').classList.add('active');
}

function closeChainModal() { document.getElementById('chainModal').classList.remove('active'); }

function editChain(id) {
    var chain = chainsData.find(function(c) { return c.id == id; });
    if (chain) openChainModal(chain);
}

function addStep(data) {
    var container = document.getElementById('stepsContainer');
    var idx = container.children.length;
    var div = document.createElement('div');
    div.className = 'step-builder-row';
    div.innerHTML = '<select class="form-input step-source" onchange="toggleStepFields(this)">' +
        '<option value="direct"' + (data && data.source_type === 'direct' ? ' selected' : '') + '>Direct</option>' +
        '<option value="campaign"' + (data && data.source_type === 'campaign' ? ' selected' : '') + '>Campaign</option>' +
        '<option value="vast_tag"' + (data && data.source_type === 'vast_tag' ? ' selected' : '') + '>VAST Tag</option>' +
        '<option value="house"' + (data && data.source_type === 'house' ? ' selected' : '') + '>House Ad</option>' +
        '</select>' +
        '<div class="step-value">' + getStepValueInput(data) + '</div>' +
        '<input type="number" class="form-input step-timeout" value="' + (data ? data.timeout_ms : 3000) + '" placeholder="Timeout ms" title="Timeout (ms)">' +
        '<input type="number" class="form-input step-floor" value="' + (data && data.floor_cpm ? data.floor_cpm : '') + '" placeholder="Floor $" step="0.01" title="Floor CPM">' +
        '<button class="btn btn-sm btn-danger" onclick="this.parentNode.remove()" title="Remove">&times;</button>';
    container.appendChild(div);
}

function getStepValueInput(data) {
    var type = data ? data.source_type : 'direct';
    if (type === 'campaign') {
        var sel = '<select class="form-input step-campaign"><option value="">Any campaign</option>';
        campaignsList.forEach(function(c) {
            sel += '<option value="' + c.id + '"' + (data && data.campaign_id == c.id ? ' selected' : '') + '>' + c.name + '</option>';
        });
        return sel + '</select>';
    } else if (type === 'vast_tag') {
        return '<input type="url" class="form-input step-vast" value="' + (data ? (data.vast_tag_url || '') : '') + '" placeholder="VAST URL">';
    }
    return '<span style="color:var(--text-muted);font-size:0.8rem">Match any active ads</span>';
}

function toggleStepFields(sel) {
    var row = sel.closest('.step-builder-row');
    var valueDiv = row.querySelector('.step-value');
    valueDiv.innerHTML = getStepValueInput({ source_type: sel.value });
}

function collectSteps() {
    var rows = document.querySelectorAll('.step-builder-row');
    var steps = [];
    rows.forEach(function(row) {
        var step = {
            source_type: row.querySelector('.step-source').value,
            timeout_ms: parseInt(row.querySelector('.step-timeout').value) || 3000,
            floor_cpm: parseFloat(row.querySelector('.step-floor').value) || null,
            campaign_id: null,
            vast_tag_url: null,
        };
        var campSel = row.querySelector('.step-campaign');
        if (campSel) step.campaign_id = campSel.value || null;
        var vastInput = row.querySelector('.step-vast');
        if (vastInput) step.vast_tag_url = vastInput.value || null;
        steps.push(step);
    });
    return steps;
}

function saveChain() {
    var id = document.getElementById('chainId').value;
    var url = id ? '/admin/ads/waterfall/' + id + '/update' : '/admin/ads/waterfall/store';
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('name', document.getElementById('chainName').value);
    formData.append('zone_id', document.getElementById('chainZone').value);
    formData.append('is_active', document.getElementById('chainActive').checked ? 1 : 0);
    formData.append('steps', JSON.stringify(collectSteps()));

    fetch(url, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) location.reload();
            else alert(d.message || 'Error saving chain');
        });
}

function deleteChain(id) {
    if (!confirm('Delete this waterfall chain?')) return;
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    fetch('/admin/ads/waterfall/' + id + '/delete', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.success) location.reload(); });
}
</script>
