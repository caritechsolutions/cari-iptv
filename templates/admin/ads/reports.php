<?php $pageTitle = 'Ad Reports'; ?>

<style>
    .report-filters { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
    .chart-container { position: relative; height: 300px; margin: 1rem 0; }
    .report-table th, .report-table td { padding: 0.75rem 1rem; }
    .type-badge { font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 500; }
    .type-badge.text_scroller { background: rgba(59, 130, 246, 0.15); color: var(--info); }
    .type-badge.banner { background: rgba(34, 197, 94, 0.15); color: var(--success); }
    .type-badge.pre_roll { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
    .type-badge.mid_roll { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
    .metric-row { display: flex; gap: 1rem; margin-bottom: 1rem; }
    .metric-box { flex: 1; background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; text-align: center; }
    .metric-box .value { font-size: 1.5rem; font-weight: 700; }
    .metric-box .label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }
</style>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Ad Reports</h1>
        <p class="page-subtitle">Performance analytics across all advertising campaigns.</p>
    </div>
    <div>
        <a href="/admin/ads" class="btn btn-secondary">Back to Campaigns</a>
    </div>
</div>

<!-- Key Metrics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="lucide-megaphone"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Active Campaigns</div>
            <div class="stat-value"><?= number_format($stats['active']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="lucide-eye"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Impressions</div>
            <div class="stat-value"><?= number_format($stats['total_impressions']) ?></div>
            <?php if ($stats['impressions_today'] > 0): ?>
                <div class="stat-change positive">+<?= number_format($stats['impressions_today']) ?> today</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="lucide-mouse-pointer-click"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Clicks</div>
            <div class="stat-value"><?= number_format($stats['total_clicks']) ?></div>
            <?php if ($stats['clicks_today'] > 0): ?>
                <div class="stat-change positive">+<?= number_format($stats['clicks_today']) ?> today</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="lucide-percent"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Overall CTR</div>
            <div class="stat-value"><?= $stats['ctr'] ?>%</div>
        </div>
    </div>
</div>

<!-- Date Range Filter -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/ads/reports" class="report-filters">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">From</label>
                <input type="date" name="start_date" class="form-input" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">To</label>
                <input type="date" name="end_date" class="form-input" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end;">Apply</button>
        </form>
    </div>
</div>

<!-- Impressions Chart -->
<div class="card mb-3">
    <div class="card-header">
        <div class="card-title">Daily Impressions</div>
    </div>
    <div class="card-body">
        <?php if (empty($report['daily'])): ?>
            <div style="text-align:center;padding:2rem;color:var(--text-muted);">No impression data for this period.</div>
        <?php else: ?>
            <div class="chart-container">
                <canvas id="impressionsChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Performance by Ad Type -->
<div class="card mb-3">
    <div class="card-header">
        <div class="card-title">Performance by Ad Type</div>
    </div>
    <div class="card-body">
        <?php if (empty($report['by_type'])): ?>
            <div style="text-align:center;padding:1rem;color:var(--text-muted);">No data yet.</div>
        <?php else: ?>
            <div class="metric-row">
                <?php foreach ($report['by_type'] as $typeRow): ?>
                    <?php $typeInfo = $adTypes[$typeRow['type']] ?? null; ?>
                    <div class="metric-box">
                        <span class="type-badge <?= $typeRow['type'] ?>"><?= $typeInfo ? $typeInfo['name'] : $typeRow['type'] ?></span>
                        <div class="value" style="margin-top:0.5rem;"><?= number_format($typeRow['impressions']) ?></div>
                        <div class="label">impressions</div>
                        <div style="font-size:0.8rem;margin-top:0.25rem;">
                            <?= number_format($typeRow['clicks']) ?> clicks
                            (<?= $typeRow['impressions'] > 0 ? round(($typeRow['clicks'] / $typeRow['impressions']) * 100, 2) : 0 ?>% CTR)
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Campaign Performance Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Campaign Performance</div>
    </div>
    <div class="table-container">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Status</th>
                    <th>Impressions</th>
                    <th>Clicks</th>
                    <th>CTR</th>
                    <th>Spend</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($report['by_campaign'])): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">No campaign data yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($report['by_campaign'] as $camp): ?>
                        <tr>
                            <td>
                                <a href="/admin/ads/<?= $camp['id'] ?>/edit" style="font-weight:600;"><?= htmlspecialchars($camp['name']) ?></a>
                            </td>
                            <td>
                                <span class="badge badge-<?= $camp['status'] === 'active' ? 'success' : ($camp['status'] === 'paused' ? 'warning' : 'info') ?>">
                                    <?= ucfirst($camp['status']) ?>
                                </span>
                            </td>
                            <td><?= number_format($camp['total_impressions']) ?></td>
                            <td><?= number_format($camp['total_clicks']) ?></td>
                            <td><?= $camp['ctr'] ?>%</td>
                            <td>$<?= number_format($camp['total_spend'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($report['daily'])): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const dailyData = <?= json_encode($report['daily']) ?>;

new Chart(document.getElementById('impressionsChart'), {
    type: 'bar',
    data: {
        labels: dailyData.map(d => d.date),
        datasets: [{
            label: 'Impressions',
            data: dailyData.map(d => parseInt(d.impressions)),
            backgroundColor: 'rgba(99, 102, 241, 0.3)',
            borderColor: 'rgba(99, 102, 241, 1)',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
        },
        scales: {
            x: {
                grid: { color: 'rgba(255,255,255,0.05)' },
                ticks: { color: '#94a3b8', font: { size: 11 } },
            },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(255,255,255,0.05)' },
                ticks: { color: '#94a3b8', font: { size: 11 } },
            }
        }
    }
});
</script>
<?php endif; ?>
