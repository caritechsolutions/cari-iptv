<?php $pageTitle = 'A/B Tests'; ?>

<style>
    .test-card { background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
    .test-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .test-header h4 { font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
    .variant-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem; }
    .variant-card { background: rgba(0,0,0,0.2); border-radius: 8px; padding: 0.75rem; border: 1px solid rgba(255,255,255,0.05); }
    .variant-card.winner { border-color: #22c55e; background: rgba(34,197,94,0.08); }
    .variant-card.control { border-color: var(--primary); }
    .variant-name { font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; }
    .variant-stat { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem; }
    .variant-stat strong { color: var(--text-secondary); }
    .confidence-bar { height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; margin-top: 0.75rem; overflow: hidden; }
    .confidence-bar-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
    .badge-running { background: rgba(34,197,94,0.15); color: #22c55e; }
    .badge-completed { background: rgba(99,102,241,0.15); color: #818cf8; }
    .badge-draft { background: rgba(148,163,184,0.15); color: #94a3b8; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #1e293b; border: 1px solid #475569; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
    .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .modal-body { padding: 1.25rem; }
    .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; }
    .modal-close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem; }
    .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
    .empty-state i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; }
</style>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">A/B Tests</h1>
        <p class="page-subtitle">Split-test ad creatives to find top performers. Tests auto-promote winners when statistically significant.</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/admin/ads" class="btn btn-secondary">Back to Campaigns</a>
    </div>
</div>

<?php if (empty($tests)): ?>
<div class="card"><div class="card-body empty-state">
    <i class="lucide-flask-conical"></i>
    <h3>No A/B Tests</h3>
    <p>Create A/B tests from the campaign edit page to compare creative variants and find the best performers.</p>
</div></div>
<?php else: ?>
<?php foreach ($tests as $test): ?>
<div class="card"><div class="card-body test-card">
    <div class="test-header">
        <h4>
            <i class="lucide-flask-conical" style="color:var(--primary)"></i>
            <?= htmlspecialchars($test['name']) ?>
            <span class="badge badge-<?= $test['status'] ?>"><?= ucfirst($test['status']) ?></span>
            <span style="color:var(--text-muted);font-size:0.8rem;font-weight:400">
                <?= htmlspecialchars($test['campaign_name']) ?> | Metric: <?= strtoupper(str_replace('_', ' ', $test['metric'])) ?> | Min: <?= number_format($test['min_impressions']) ?> impressions
            </span>
        </h4>
        <div style="display:flex;gap:0.5rem">
            <?php if ($test['status'] === 'draft'): ?>
            <button class="btn btn-sm btn-success" onclick="updateTestStatus(<?= $test['id'] ?>, 'running')"><i class="lucide-play"></i> Start</button>
            <?php elseif ($test['status'] === 'running'): ?>
            <button class="btn btn-sm btn-warning" onclick="updateTestStatus(<?= $test['id'] ?>, 'paused')"><i class="lucide-pause"></i> Pause</button>
            <button class="btn btn-sm btn-secondary" onclick="evaluateTest(<?= $test['id'] ?>)"><i class="lucide-bar-chart-3"></i> Evaluate</button>
            <?php elseif ($test['status'] === 'paused'): ?>
            <button class="btn btn-sm btn-success" onclick="updateTestStatus(<?= $test['id'] ?>, 'running')"><i class="lucide-play"></i> Resume</button>
            <?php endif; ?>
            <button class="btn btn-sm btn-danger" onclick="deleteTest(<?= $test['id'] ?>)"><i class="lucide-trash-2"></i></button>
        </div>
    </div>

    <div class="variant-grid">
        <?php foreach ($test['variants'] as $v): ?>
        <?php
            $isWinner = $test['winner_creative_id'] && $v['creative_id'] == $test['winner_creative_id'];
            $ctr = $v['impressions'] > 0 ? round(($v['clicks'] / $v['impressions']) * 100, 2) : 0;
            $completionRate = $v['impressions'] > 0 ? round(($v['completions'] / $v['impressions']) * 100, 2) : 0;
            $convRate = $v['clicks'] > 0 ? round(($v['conversions'] / $v['clicks']) * 100, 2) : 0;
        ?>
        <div class="variant-card <?= $isWinner ? 'winner' : '' ?> <?= $v['is_control'] ? 'control' : '' ?>">
            <div class="variant-name">
                <span><?= htmlspecialchars($v['creative_name']) ?></span>
                <?php if ($v['is_control']): ?><span class="badge badge-secondary" style="font-size:0.65rem">CONTROL</span><?php endif; ?>
                <?php if ($isWinner): ?><span class="badge badge-success" style="font-size:0.65rem">WINNER</span><?php endif; ?>
            </div>
            <div class="variant-stat"><span>Traffic</span><strong><?= $v['traffic_weight'] ?>%</strong></div>
            <div class="variant-stat"><span>Impressions</span><strong><?= number_format($v['impressions']) ?></strong></div>
            <div class="variant-stat"><span>CTR</span><strong><?= $ctr ?>%</strong></div>
            <div class="variant-stat"><span>Completions</span><strong><?= $completionRate ?>%</strong></div>
            <div class="variant-stat"><span>Conversions</span><strong><?= $convRate ?>%</strong></div>
            <?php if ($test['min_impressions'] > 0): ?>
            <div class="confidence-bar">
                <div class="confidence-bar-fill" style="width:<?= min(100, ($v['impressions'] / $test['min_impressions']) * 100) ?>%;background:<?= $v['impressions'] >= $test['min_impressions'] ? '#22c55e' : '#f59e0b' ?>"></div>
            </div>
            <div style="font-size:0.7rem;color:var(--text-muted);text-align:right;margin-top:2px"><?= number_format($v['impressions']) ?>/<?= number_format($test['min_impressions']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($test['status'] === 'completed' && $test['completed_at']): ?>
    <div style="margin-top:0.75rem;font-size:0.8rem;color:var(--text-muted)">
        Completed: <?= date('M j, Y g:ia', strtotime($test['completed_at'])) ?>
    </div>
    <?php endif; ?>
</div></div>
<?php endforeach; ?>
<?php endif; ?>

<script>
var csrfToken = '<?= \CariIPTV\Core\Session::csrf() ?>';

function updateTestStatus(id, status) {
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('status', status);
    fetch('/admin/ads/ab-tests/' + id + '/update', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.success) location.reload(); });
}

function evaluateTest(id) {
    fetch('/admin/ads/ab-tests/' + id + '/evaluate')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success && d.result) {
                var msg = 'Status: ' + d.result.status + '\n';
                msg += 'Z-score: ' + (d.result.z_score || 'N/A') + '\n';
                msg += 'Confidence: ' + (d.result.confidence || 'N/A') + '%\n';
                if (d.result.winner_id) msg += 'Winner found! Refreshing...';
                alert(msg);
                if (d.result.status === 'significant') location.reload();
            } else {
                alert('Could not evaluate test. Ensure it has enough data.');
            }
        });
}

function deleteTest(id) {
    if (!confirm('Delete this A/B test?')) return;
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    fetch('/admin/ads/ab-tests/' + id + '/delete', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.success) location.reload(); });
}
</script>
