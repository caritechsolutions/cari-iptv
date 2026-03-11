<?php $pageTitle = 'Revenue Forecast'; ?>

<style>
    .forecast-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .forecast-kpi { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; }
    .forecast-kpi .label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
    .forecast-kpi .value { font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem; }
    .chart-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; }
    .chart-card h3 { font-size: 0.95rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    .chart-card h3 i { color: var(--primary); }
    .forecast-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    .forecast-table th { padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border-color); text-align: left; color: var(--text-secondary); font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em; background: rgba(0,0,0,0.2); }
    .forecast-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-muted); }
    .forecast-table tr:hover td { background: var(--bg-hover); }
    .accuracy-good { color: #22c55e; font-weight: 600; }
    .accuracy-ok { color: #f59e0b; font-weight: 600; }
    .accuracy-poor { color: #ef4444; font-weight: 600; }
    .ai-analysis { background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2); border-radius: 8px; padding: 1rem; margin-top: 1rem; font-size: 0.85rem; line-height: 1.6; color: var(--text-secondary); }
    .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @media (max-width: 768px) {
        .forecast-grid { grid-template-columns: 1fr 1fr; }
        .charts-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Revenue Forecast</h1>
        <p class="page-subtitle">AI-powered ad revenue predictions based on historical performance.</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <a href="/admin/ads" class="btn btn-secondary">Back to Campaigns</a>
        <button class="btn btn-primary" id="btnGenerate" onclick="generateForecast()">
            <i class="lucide-sparkles"></i> Generate Forecast
        </button>
        <button class="btn btn-secondary" id="btnAiTest" onclick="testForecastAi()" title="Test AI connectivity and dump prompt" style="border:1px solid #334155;">
            <i class="lucide-stethoscope"></i> Test AI
        </button>
    </div>
</div>

<!-- AI Diagnostics Panel (hidden by default) -->
<div id="aiDiagPanel" style="display:none;margin-bottom:1.5rem;">
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 style="margin:0;font-size:0.95rem;color:#e2e8f0;"><i class="lucide-stethoscope"></i> AI Diagnostics</h3>
            <button onclick="document.getElementById('aiDiagPanel').style.display='none'" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1.2rem;">&times;</button>
        </div>
        <div id="aiDiagContent" style="font-family:monospace;font-size:0.8rem;color:#cbd5e1;white-space:pre-wrap;max-height:500px;overflow-y:auto;">Running diagnostics...</div>
        <div style="margin-top:1rem;padding-top:0.75rem;border-top:1px solid #334155;">
            <p style="margin:0 0 0.5rem;font-size:0.8rem;color:#64748b;">To test the prompt at the command line:</p>
            <code id="aiDiagCurlCmd" style="display:block;font-size:0.75rem;color:#94a3b8;background:#0f172a;padding:0.75rem;border-radius:4px;word-break:break-all;max-height:100px;overflow-y:auto;"></code>
            <button onclick="navigator.clipboard.writeText(document.getElementById('aiDiagCurlCmd').textContent).then(function(){this.textContent='Copied!'}.bind(this))" style="margin-top:0.5rem;padding:0.25rem 0.75rem;background:#334155;color:#e2e8f0;border:none;border-radius:4px;cursor:pointer;font-size:0.75rem;">Copy curl command</button>
        </div>
    </div>
</div>

<div class="forecast-grid">
    <div class="forecast-kpi">
        <div class="label">Avg Daily Impressions</div>
        <div class="value" style="color:var(--primary)"><?= number_format($historical['avg_daily_impressions'] ?? 0) ?></div>
    </div>
    <div class="forecast-kpi">
        <div class="label">Avg Daily Revenue</div>
        <div class="value" style="color:#22c55e">$<?= number_format($historical['avg_daily_revenue'] ?? 0, 2) ?></div>
    </div>
    <div class="forecast-kpi">
        <div class="label">Active Campaigns</div>
        <div class="value" style="color:#f59e0b"><?= $historical['active_campaigns'] ?? 0 ?></div>
    </div>
    <div class="forecast-kpi">
        <div class="label">Data Points (90d)</div>
        <div class="value"><?= count($historical['daily'] ?? []) ?></div>
    </div>
</div>

<div class="charts-grid">
    <div class="chart-card">
        <h3><i class="lucide-trending-up"></i> Historical vs Forecast</h3>
        <canvas id="chartForecast" height="200"></canvas>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-bar-chart-3"></i> Revenue by Zone Type</h3>
        <canvas id="chartZones" height="200"></canvas>
    </div>
</div>

<div class="chart-card">
    <h3><i class="lucide-calendar"></i> Forecast Details</h3>
    <div id="forecastAnalysis"></div>
    <?php if (!empty($forecasts)): ?>
    <div class="table-container">
    <table class="forecast-table">
        <thead><tr>
            <th>Date</th><th>Predicted Impressions</th><th>Predicted Revenue</th><th>Fill Rate</th><th>Confidence</th>
            <th>Actual Impressions</th><th>Actual Revenue</th><th>Accuracy</th>
        </tr></thead>
        <tbody>
        <?php foreach ($forecasts as $f): ?>
        <?php
            $accuracy = '';
            $accClass = '';
            if ($f['actual_impressions'] !== null && $f['predicted_impressions'] > 0) {
                $acc = (1 - abs($f['actual_impressions'] - $f['predicted_impressions']) / $f['predicted_impressions']) * 100;
                $accuracy = round(max(0, $acc)) . '%';
                $accClass = $acc >= 80 ? 'accuracy-good' : ($acc >= 50 ? 'accuracy-ok' : 'accuracy-poor');
            }
        ?>
        <tr>
            <td><?= date('M j', strtotime($f['forecast_date'])) ?></td>
            <td><?= number_format($f['predicted_impressions']) ?></td>
            <td>$<?= number_format($f['predicted_revenue'], 2) ?></td>
            <td><?= $f['predicted_fill_rate'] ? $f['predicted_fill_rate'] . '%' : '-' ?></td>
            <td><?= $f['confidence_level'] ? $f['confidence_level'] . '%' : '-' ?></td>
            <td><?= $f['actual_impressions'] !== null ? number_format($f['actual_impressions']) : '-' ?></td>
            <td><?= $f['actual_revenue'] !== null ? '$' . number_format($f['actual_revenue'], 2) : '-' ?></td>
            <td class="<?= $accClass ?>"><?= $accuracy ?: '-' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <p style="text-align:center;padding:2rem;color:var(--text-muted)">
        No forecasts yet. Click <strong>"Generate Forecast"</strong> to create AI-powered predictions.
    </p>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
var historical = <?= json_encode($historical['daily'] ?? []) ?>;
var forecastData = <?= json_encode($forecasts) ?>;
var zoneData = <?= json_encode($historical['by_zone_type'] ?? []) ?>;
var csrfToken = '<?= \CariIPTV\Core\Session::csrf() ?>';

// Historical + Forecast chart
(function() {
    var labels = historical.map(function(d) { return d.date; });
    var impressions = historical.map(function(d) { return parseInt(d.impressions); });
    var revenue = historical.map(function(d) { return parseFloat(d.revenue || 0); });

    // Append forecast data
    forecastData.forEach(function(f) {
        labels.push(f.forecast_date);
        impressions.push(null);
        revenue.push(null);
    });

    var forecastImpressions = new Array(historical.length).fill(null);
    var forecastRevenue = new Array(historical.length).fill(null);
    forecastData.forEach(function(f) {
        forecastImpressions.push(parseInt(f.predicted_impressions));
        forecastRevenue.push(parseFloat(f.predicted_revenue || 0));
    });

    new Chart(document.getElementById('chartForecast'), {
        type: 'line',
        data: {
            labels: labels.map(function(d) { return new Date(d).toLocaleDateString('en', {month:'short',day:'numeric'}); }),
            datasets: [
                { label: 'Impressions', data: impressions, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.3 },
                { label: 'Forecast', data: forecastImpressions, borderColor: '#6366f1', borderDash: [5,5], backgroundColor: 'transparent', tension: 0.3 },
                { label: 'Revenue $', data: revenue, borderColor: '#22c55e', backgroundColor: 'transparent', yAxisID: 'y1', tension: 0.3 },
                { label: 'Forecast $', data: forecastRevenue, borderColor: '#22c55e', borderDash: [5,5], backgroundColor: 'transparent', yAxisID: 'y1', tension: 0.3 },
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: '#94a3b8', font: { size: 10 } } } },
            scales: {
                x: { ticks: { color: '#64748b', maxTicksLimit: 15 }, grid: { color: 'rgba(255,255,255,0.05)' } },
                y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                y1: { position: 'right', ticks: { color: '#22c55e', callback: function(v) { return '$' + v; } }, grid: { display: false } }
            }
        }
    });
})();

// Zone breakdown chart
if (zoneData.length) {
    new Chart(document.getElementById('chartZones'), {
        type: 'doughnut',
        data: {
            labels: zoneData.map(function(z) { return z.zone_type; }),
            datasets: [{ data: zoneData.map(function(z) { return parseFloat(z.revenue || 0); }), backgroundColor: ['#6366f1','#22c55e','#f59e0b','#ef4444','#8b5cf6'] }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } } }
    });
}

function testForecastAi() {
    var panel = document.getElementById('aiDiagPanel');
    var content = document.getElementById('aiDiagContent');
    var curlCmd = document.getElementById('aiDiagCurlCmd');
    panel.style.display = 'block';
    content.textContent = 'Running diagnostics...\n\n1. Testing AI connectivity (simple ping)...';
    curlCmd.textContent = '';

    var formData = new FormData();
    formData.append('csrf_token', csrfToken);

    fetch('/admin/ads/forecast/ai-test', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (!json.success) {
                content.textContent = 'Error: ' + (json.message || 'Unknown error');
                return;
            }

            var d = json.diagnostics;
            var out = '=== AI PROVIDER ===\n';
            out += 'Provider: ' + d.provider_name + '\n';
            out += 'Model: ' + d.model + '\n';
            out += 'Available: ' + (d.available ? 'YES' : 'NO') + '\n\n';

            out += '=== PING TEST (tiny prompt) ===\n';
            if (d.ping.response) {
                out += 'Status: OK\n';
                out += 'Response: ' + d.ping.response + '\n';
                out += 'Time: ' + d.ping.time_seconds + 's\n';
            } else {
                out += 'Status: FAILED\n';
                out += 'Error: ' + (d.ping.error || 'No response') + '\n';
                out += 'Time: ' + d.ping.time_seconds + 's\n';
            }

            out += '\n=== FORECAST PROMPT STATS ===\n';
            out += 'Characters: ' + d.prompt.length_chars.toLocaleString() + '\n';
            out += 'Words: ' + d.prompt.length_words.toLocaleString() + '\n';
            out += 'Lines: ' + d.prompt.length_lines + '\n';
            out += 'Approx tokens: ~' + Math.round(d.prompt.length_words * 1.3).toLocaleString() + '\n';
            out += '\n=== FULL PROMPT ===\n' + d.prompt.text;

            content.textContent = out;

            // Build curl command for CLI testing
            var escapedPrompt = d.prompt.text.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n');
            var curl = "curl -s http://localhost:11434/api/generate -d '{\"model\":\"" + d.model + "\",\"prompt\":\"" + escapedPrompt.replace(/'/g, "'\\''") + "\",\"stream\":false,\"options\":{\"temperature\":0.6,\"num_predict\":2000}}' | python3 -m json.tool";
            curlCmd.textContent = curl;
        })
        .catch(function(e) {
            content.textContent = 'Network error: ' + e.message;
        });
}

function generateForecast() {
    var btn = document.getElementById('btnGenerate');
    btn.disabled = true;
    btn.innerHTML = '<i class="lucide-loader" style="animation:spin 1s linear infinite"></i> Generating...';

    var formData = new FormData();
    formData.append('csrf_token', csrfToken);

    fetch('/admin/ads/forecast/generate', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                if (d.analysis) {
                    document.getElementById('forecastAnalysis').innerHTML = '<div class="ai-analysis"><strong>AI Analysis:</strong> ' + d.analysis + '</div>';
                }
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                alert(d.message || 'Forecast generation failed');
                btn.disabled = false;
                btn.innerHTML = '<i class="lucide-sparkles"></i> Generate Forecast';
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="lucide-sparkles"></i> Generate Forecast';
        });
}
</script>
