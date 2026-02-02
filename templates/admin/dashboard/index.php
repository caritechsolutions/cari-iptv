<?php $pageTitle = 'Dashboard'; ?>
<style>
    .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .dash-header h1 { font-size: 1.5rem; font-weight: 700; }
    .dash-header p { font-size: 0.85rem; color: #94a3b8; margin-top: 0.25rem; }

    .widget-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
    @media (max-width: 1200px) { .widget-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 900px) { .widget-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px) { .widget-grid { grid-template-columns: 1fr; } }

    .widget-card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 1.25rem; position: relative; transition: border-color 0.2s; }
    .widget-card:hover { border-color: #475569; }
    .widget-card.size-full { grid-column: 1 / -1; }
    .widget-card.size-medium { grid-column: span 2; }

    .widget-remove { position: absolute; top: 0.6rem; right: 0.6rem; background: none; border: none; color: #64748b; cursor: pointer; font-size: 1rem; padding: 0.2rem; border-radius: 4px; line-height: 1; opacity: 0; transition: opacity 0.2s; }
    .widget-card:hover .widget-remove { opacity: 1; }
    .widget-remove:hover { color: #ef4444; background: rgba(239,68,68,0.1); }

    .widget-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }
    .widget-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
    .widget-title { font-size: 0.8rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.03em; }

    .widget-value { font-size: 2rem; font-weight: 700; line-height: 1.1; margin-bottom: 0.25rem; }
    .widget-sub { font-size: 0.8rem; color: #64748b; }

    /* Gauge bar */
    .gauge-track { height: 6px; background: #334155; border-radius: 3px; margin-top: 0.75rem; overflow: hidden; }
    .gauge-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }

    /* Info list widget */
    .info-list { list-style: none; padding: 0; margin: 0; }
    .info-list li { display: flex; justify-content: space-between; padding: 0.45rem 0; border-bottom: 1px solid #334155; font-size: 0.82rem; }
    .info-list li:last-child { border-bottom: none; }
    .info-list .il-label { color: #94a3b8; }
    .info-list .il-value { color: #f1f5f9; font-weight: 500; }

    /* Activity list widget */
    .act-list { list-style: none; padding: 0; margin: 0; max-height: 260px; overflow-y: auto; }
    .act-item { display: flex; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid #334155; font-size: 0.82rem; }
    .act-item:last-child { border-bottom: none; }
    .act-dot { width: 8px; height: 8px; border-radius: 50%; background: #6366f1; margin-top: 0.35rem; flex-shrink: 0; }
    .act-text { color: #cbd5e1; }
    .act-time { color: #64748b; font-size: 0.75rem; margin-top: 0.15rem; }

    /* Chart container */
    .chart-container { position: relative; height: 200px; }

    /* Add panel modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: #1e293b; border: 1px solid #475569; border-radius: 12px; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8); }
    .modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; background: #1e293b; position: sticky; top: 0; z-index: 10; border-radius: 12px 12px 0 0; }
    .modal-header h3 { font-size: 1rem; font-weight: 600; color: #f1f5f9; }
    .modal-body { padding: 1.25rem; background: #1e293b; }
    .modal-close { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.25rem; }

    .widget-picker-category { font-size: 0.75rem; font-weight: 600; color: #818cf8; text-transform: uppercase; letter-spacing: 0.05em; margin: 1rem 0 0.5rem; padding-bottom: 0.3rem; border-bottom: 1px solid #334155; }
    .widget-picker-category:first-child { margin-top: 0; }
    .widget-picker-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
    .widget-pick-btn { display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.75rem; background: #0f172a; border: 1px solid #334155; border-radius: 8px; cursor: pointer; color: #cbd5e1; font-size: 0.85rem; transition: all 0.15s; }
    .widget-pick-btn:hover { border-color: #6366f1; background: rgba(99,102,241,0.08); }
    .widget-pick-btn.added { opacity: 0.4; pointer-events: none; }
    .widget-pick-btn i { font-size: 1rem; }

    .empty-dash { text-align: center; padding: 3rem 1rem; color: #64748b; }
    .empty-dash i { font-size: 3rem; display: block; margin-bottom: 1rem; }
</style>

<!-- Page Header -->
<div class="dash-header">
    <div>
        <h1>Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($user['first_name'] ?? 'Admin') ?>. Here's your platform overview.</p>
    </div>
    <button class="btn btn-primary" onclick="openAddPanel()">
        <i class="lucide-plus"></i> Add Panel
    </button>
</div>

<!-- Widget Grid -->
<div class="widget-grid" id="widgetGrid">
    <?php if (empty($activeWidgets)): ?>
        <div class="empty-dash" style="grid-column:1/-1;">
            <i class="lucide-layout-dashboard"></i>
            <div style="font-size:1.1rem;font-weight:600;margin-bottom:0.5rem;">No panels added yet</div>
            <p>Click "Add Panel" to start building your dashboard.</p>
        </div>
    <?php else: ?>
        <?php foreach ($activeWidgets as $wKey):
            $wMeta = $allWidgets[$wKey] ?? null;
            $wData = $widgetData[$wKey] ?? [];
            if (!$wMeta) continue;
            $sizeClass = $wMeta['size'] === 'full' ? 'size-full' : ($wMeta['size'] === 'medium' ? 'size-medium' : '');
        ?>
            <div class="widget-card <?= $sizeClass ?>" id="widget-<?= $wKey ?>" data-widget="<?= $wKey ?>">
                <button class="widget-remove" onclick="removeWidget('<?= $wKey ?>')" title="Remove panel">&times;</button>
                <div class="widget-header">
                    <div class="widget-icon" style="background:<?= htmlspecialchars($wData['color'] ?? '#6366f1') ?>22;color:<?= htmlspecialchars($wData['color'] ?? '#6366f1') ?>;">
                        <i class="<?= htmlspecialchars($wMeta['icon']) ?>"></i>
                    </div>
                    <div class="widget-title"><?= htmlspecialchars($wMeta['name']) ?></div>
                </div>

                <?php if (isset($wData['error'])): ?>
                    <div style="color:#ef4444;font-size:0.82rem;"><?= htmlspecialchars($wData['error']) ?></div>

                <?php elseif (isset($wData['type']) && $wData['type'] === 'info_list'): ?>
                    <ul class="info-list">
                        <?php foreach ($wData['items'] as $item): ?>
                            <li><span class="il-label"><?= htmlspecialchars($item['label']) ?></span><span class="il-value"><?= htmlspecialchars($item['value']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>

                <?php elseif (isset($wData['type']) && $wData['type'] === 'chart'): ?>
                    <div class="chart-container">
                        <canvas id="chart-<?= $wKey ?>"></canvas>
                    </div>

                <?php elseif (isset($wData['type']) && $wData['type'] === 'activity_list'): ?>
                    <?php if (empty($wData['items'])): ?>
                        <div style="color:#64748b;font-size:0.85rem;text-align:center;padding:1rem;">No recent activity</div>
                    <?php else: ?>
                        <ul class="act-list">
                            <?php foreach ($wData['items'] as $act): ?>
                                <li class="act-item">
                                    <span class="act-dot"></span>
                                    <div>
                                        <div class="act-text">
                                            <strong><?= htmlspecialchars(($act['first_name'] ?? '') . ' ' . ($act['last_name'] ?? '')) ?></strong>
                                            <?= htmlspecialchars($act['action'] ?? '') ?>
                                            <?php if (!empty($act['target_type'])): ?>
                                                <span style="color:#818cf8;"><?= htmlspecialchars($act['target_type']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="act-time"><?= htmlspecialchars($act['created_at'] ?? '') ?></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                <?php elseif (isset($wData['gauge'])): ?>
                    <div class="widget-value" style="color:<?= htmlspecialchars($wData['color'] ?? '#f1f5f9') ?>;"><?= htmlspecialchars($wData['value']) ?></div>
                    <div class="widget-sub"><?= htmlspecialchars($wData['sub'] ?? '') ?></div>
                    <div class="gauge-track">
                        <div class="gauge-fill" style="width:<?= (float)$wData['gauge'] ?>%;background:<?= htmlspecialchars($wData['color'] ?? '#6366f1') ?>;"></div>
                    </div>

                <?php else: ?>
                    <div class="widget-value"><?= htmlspecialchars($wData['value'] ?? '0') ?></div>
                    <div class="widget-sub"><?= htmlspecialchars($wData['sub'] ?? '') ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Panel Modal -->
<div class="modal-overlay" id="addPanelModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add Panel</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="panelPickerBody">
            <?php
            $grouped = [];
            foreach ($allWidgets as $key => $w) {
                $grouped[$w['category']][$key] = $w;
            }
            foreach ($grouped as $cat => $widgets):
            ?>
                <div class="widget-picker-category"><?= htmlspecialchars($cat) ?></div>
                <div class="widget-picker-grid">
                    <?php foreach ($widgets as $key => $w):
                        $already = in_array($key, $activeWidgets);
                    ?>
                        <div class="widget-pick-btn <?= $already ? 'added' : '' ?>" id="pick-<?= $key ?>" onclick="addWidget('<?= $key ?>')">
                            <i class="<?= htmlspecialchars($w['icon']) ?>"></i>
                            <span><?= htmlspecialchars($w['name']) ?></span>
                            <?php if ($already): ?><span style="margin-left:auto;font-size:0.7rem;color:#22c55e;">Added</span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const csrfToken = '<?= $csrf ?>';
const allWidgets = <?= json_encode($allWidgets) ?>;
const widgetData = <?= json_encode($widgetData) ?>;

function showToast(message, type = 'info') {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;top:1.5rem;right:1.5rem;z-index:9999;padding:0.75rem 1.25rem;border-radius:8px;color:#fff;font-size:0.875rem;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,0.4);max-width:400px;transition:opacity 0.3s;';
    t.style.background = type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#3b82f6';
    t.textContent = message;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
}

function openAddPanel() { document.getElementById('addPanelModal').classList.add('active'); }
function closeModal() { document.getElementById('addPanelModal').classList.remove('active'); }

// Close modal on overlay click or Escape
document.getElementById('addPanelModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

function addWidget(key) {
    const pickBtn = document.getElementById('pick-' + key);
    if (pickBtn && pickBtn.classList.contains('added')) return;

    fetch('/admin/dashboard/widgets/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _token: csrfToken, widget: key })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            showToast('Panel added', 'success');
            // Mark as added in picker
            if (pickBtn) {
                pickBtn.classList.add('added');
                pickBtn.innerHTML += '<span style="margin-left:auto;font-size:0.7rem;color:#22c55e;">Added</span>';
            }
            // Remove empty state if present
            const empty = document.querySelector('.empty-dash');
            if (empty) empty.remove();
            // Append widget card to grid
            appendWidgetCard(key, data.meta, data.data);
        } else {
            showToast(data.message, 'error');
        }
    }).catch(() => showToast('Error adding panel', 'error'));
}

function removeWidget(key) {
    fetch('/admin/dashboard/widgets/remove', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _token: csrfToken, widget: key })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            const card = document.getElementById('widget-' + key);
            if (card) card.remove();
            // Update picker
            const pickBtn = document.getElementById('pick-' + key);
            if (pickBtn) {
                pickBtn.classList.remove('added');
                const addedSpan = pickBtn.querySelector('span[style*="color:#22c55e"]');
                if (addedSpan) addedSpan.remove();
            }
            showToast('Panel removed', 'success');
        } else {
            showToast(data.message, 'error');
        }
    }).catch(() => showToast('Error removing panel', 'error'));
}

function appendWidgetCard(key, meta, data) {
    const grid = document.getElementById('widgetGrid');
    const sizeClass = meta.size === 'full' ? 'size-full' : (meta.size === 'medium' ? 'size-medium' : '');
    const color = data.color || '#6366f1';

    let bodyHtml = '';

    if (data.error) {
        bodyHtml = `<div style="color:#ef4444;font-size:0.82rem;">${escHtml(data.error)}</div>`;
    } else if (data.type === 'info_list') {
        bodyHtml = '<ul class="info-list">' + data.items.map(i =>
            `<li><span class="il-label">${escHtml(i.label)}</span><span class="il-value">${escHtml(i.value)}</span></li>`
        ).join('') + '</ul>';
    } else if (data.type === 'chart') {
        bodyHtml = `<div class="chart-container"><canvas id="chart-${key}"></canvas></div>`;
    } else if (data.type === 'activity_list') {
        if (!data.items || data.items.length === 0) {
            bodyHtml = '<div style="color:#64748b;font-size:0.85rem;text-align:center;padding:1rem;">No recent activity</div>';
        } else {
            bodyHtml = '<ul class="act-list">' + data.items.map(a =>
                `<li class="act-item"><span class="act-dot"></span><div><div class="act-text"><strong>${escHtml((a.first_name||'')+ ' ' +(a.last_name||''))}</strong> ${escHtml(a.action||'')}</div><div class="act-time">${escHtml(a.created_at||'')}</div></div></li>`
            ).join('') + '</ul>';
        }
    } else if (data.gauge !== undefined) {
        bodyHtml = `<div class="widget-value" style="color:${escHtml(color)};">${escHtml(String(data.value))}</div>
            <div class="widget-sub">${escHtml(data.sub||'')}</div>
            <div class="gauge-track"><div class="gauge-fill" style="width:${parseFloat(data.gauge)}%;background:${escHtml(color)};"></div></div>`;
    } else {
        bodyHtml = `<div class="widget-value">${escHtml(String(data.value||'0'))}</div><div class="widget-sub">${escHtml(data.sub||'')}</div>`;
    }

    const card = document.createElement('div');
    card.className = 'widget-card ' + sizeClass;
    card.id = 'widget-' + key;
    card.dataset.widget = key;
    card.innerHTML = `
        <button class="widget-remove" onclick="removeWidget('${key}')" title="Remove panel">&times;</button>
        <div class="widget-header">
            <div class="widget-icon" style="background:${escHtml(color)}22;color:${escHtml(color)};"><i class="${escHtml(meta.icon)}"></i></div>
            <div class="widget-title">${escHtml(meta.name)}</div>
        </div>
        ${bodyHtml}`;
    grid.appendChild(card);

    // Initialize chart if needed
    if (data.type === 'chart') {
        setTimeout(() => initChart(key, data), 100);
    }
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// ==================== CHARTS ====================

function initChart(key, data) {
    const canvas = document.getElementById('chart-' + key);
    if (!canvas) return;

    new Chart(canvas.getContext('2d'), {
        type: data.chart_type || 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: data.colors || ['#6366f1'],
                borderColor: data.colors || ['#6366f1'],
                borderWidth: 1,
                borderRadius: 4,
                barThickness: 40,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { color: '#64748b', font: { size: 11 } }, grid: { color: '#1e293b' } },
                x: { ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { display: false } }
            }
        }
    });
}

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($activeWidgets as $wKey):
        if (isset($widgetData[$wKey]['type']) && $widgetData[$wKey]['type'] === 'chart'):
    ?>
    initChart('<?= $wKey ?>', <?= json_encode($widgetData[$wKey]) ?>);
    <?php endif; endforeach; ?>
});
</script>
