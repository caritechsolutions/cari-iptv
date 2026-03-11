<?php $pageTitle = 'Analytics'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<!-- TopoJSON client for geo map -->
<script src="https://cdn.jsdelivr.net/npm/topojson-client@3/dist/topojson-client.min.js"></script>
<!-- Marked.js for markdown rendering -->
<script src="https://cdn.jsdelivr.net/npm/marked@15.0.0/marked.min.js"></script>

<style>
.analytics-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.analytics-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0;
}
.analytics-header h1 i { margin-right: 8px; opacity: 0.7; }

.analytics-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.focus-select {
    background: var(--bg-card);
    border: 1px solid var(--border-color, #334155);
    color: #f1f5f9;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
}
.focus-select option { background: var(--bg-card); }

.btn-generate {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}
.btn-generate:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-generate:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.btn-sm {
    background: var(--bg-card);
    border: 1px solid var(--border-color, #334155);
    color: #94a3b8;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 0.8rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.btn-sm:hover { color: #f1f5f9; border-color: #6366f1; }

/* KPI Cards */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.kpi-card {
    background: var(--bg-card, #1e293b);
    border: 1px solid rgba(99,102,241,0.15);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.kpi-card .kpi-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #f1f5f9;
    line-height: 1;
    margin-bottom: 6px;
}
.kpi-card .kpi-label {
    font-size: 0.8rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.kpi-card .kpi-sub {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 4px;
}
.kpi-card.highlight { border-color: rgba(99,102,241,0.4); }

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}
.chart-card {
    background: var(--bg-card, #1e293b);
    border: 1px solid rgba(99,102,241,0.12);
    border-radius: 12px;
    padding: 20px;
    overflow: hidden;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}
.chart-card h3 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #e2e8f0;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.chart-card h3 i { color: #6366f1; font-size: 1rem; }
.chart-card canvas { max-height: 280px; }
.chart-card table { width: 100%; word-wrap: break-word; }
.chart-card > div { min-width: 0; overflow-x: auto; }

/* Geo Map */
.geo-map-section {
    margin-bottom: 24px;
}
.geo-map-card {
    background: var(--bg-card, #1e293b);
    border: 1px solid rgba(99,102,241,0.12);
    border-radius: 12px;
    padding: 20px;
}
.geo-map-card h3 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #e2e8f0;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.geo-map-card h3 i { color: #6366f1; font-size: 1rem; }
.geo-view-btn {
    background: rgba(15,23,42,0.5);
    border: 1px solid rgba(99,102,241,0.2);
    color: #94a3b8;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.geo-view-btn:hover { color: #e2e8f0; border-color: rgba(99,102,241,0.5); }
.geo-view-btn.active {
    background: rgba(99,102,241,0.15);
    border-color: rgba(99,102,241,0.5);
    color: #a5b4fc;
}
.geo-map-container {
    position: relative;
    width: 100%;
}
.geo-tooltip {
    position: absolute;
    background: rgba(15,23,42,0.95);
    border: 1px solid rgba(99,102,241,0.3);
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.8rem;
    color: #e2e8f0;
    pointer-events: none;
    z-index: 100;
    max-width: 260px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
.geo-tooltip .tt-country { font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; }
.geo-tooltip .tt-stats { color: #94a3b8; font-size: 0.75rem; line-height: 1.5; }
.geo-tooltip .tt-stats span { color: #6366f1; font-weight: 600; }
#geoMapSvg .country-path {
    transition: opacity 0.15s, stroke-width 0.15s;
    cursor: pointer;
}
#geoMapSvg .country-path:hover {
    opacity: 0.85;
    stroke-width: 1.5;
    stroke: #a5b4fc;
}
#geoMapSvg .country-label {
    font-size: 8px;
    fill: #94a3b8;
    text-anchor: middle;
    pointer-events: none;
}
.geo-map-legend {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    font-size: 0.75rem;
    color: #94a3b8;
    flex-wrap: wrap;
}
.geo-country-details {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
    margin-top: 16px;
}
.country-detail-card {
    background: rgba(15,23,42,0.5);
    border: 1px solid rgba(99,102,241,0.1);
    border-radius: 8px;
    padding: 14px;
}
.country-detail-card .country-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.country-detail-card .country-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #e2e8f0;
}
.country-detail-card .country-viewers {
    font-size: 0.75rem;
    color: #6366f1;
    background: rgba(99,102,241,0.1);
    padding: 2px 8px;
    border-radius: 10px;
}
.country-detail-card .country-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 6px;
    margin-bottom: 8px;
}
.country-detail-card .stat-item {
    text-align: center;
}
.country-detail-card .stat-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: #f1f5f9;
}
.country-detail-card .stat-label {
    font-size: 0.65rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.country-detail-card .country-breakdown {
    font-size: 0.75rem;
    color: #94a3b8;
    border-top: 1px solid rgba(99,102,241,0.1);
    padding-top: 8px;
    margin-top: 4px;
}
.country-detail-card .breakdown-row {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
}
.country-detail-card .breakdown-label { color: #64748b; }

/* AI Report Area */
.report-section {
    margin-bottom: 24px;
}
.report-card {
    background: var(--bg-card, #1e293b);
    border: 1px solid rgba(99,102,241,0.15);
    border-radius: 12px;
    overflow: hidden;
}
.report-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(99,102,241,0.1);
}
.report-card-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #e2e8f0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.report-card-header h3 i { color: #8b5cf6; }
.report-body {
    padding: 24px;
    max-height: 600px;
    overflow-y: auto;
}
.report-body::-webkit-scrollbar { width: 6px; }
.report-body::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

/* Markdown content styling */
.md-content h2 { font-size: 1.15rem; color: #e2e8f0; margin: 20px 0 10px; border-bottom: 1px solid #334155; padding-bottom: 6px; }
.md-content h3 { font-size: 1rem; color: #cbd5e1; margin: 16px 0 8px; }
.md-content p { color: #94a3b8; line-height: 1.7; margin: 8px 0; }
.md-content ul, .md-content ol { color: #94a3b8; padding-left: 20px; line-height: 1.7; }
.md-content li { margin-bottom: 4px; }
.md-content strong { color: #f1f5f9; }
.md-content code { background: #0f172a; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; color: #a5b4fc; }
.md-content blockquote {
    border-left: 3px solid #6366f1;
    padding: 8px 16px;
    margin: 12px 0;
    background: rgba(99,102,241,0.08);
    border-radius: 0 8px 8px 0;
    color: #94a3b8;
}
.md-content table { width: 100%; border-collapse: collapse; margin: 12px 0; }
.md-content th, .md-content td { padding: 8px 12px; border: 1px solid #334155; text-align: left; color: #94a3b8; }
.md-content th { background: #0f172a; color: #e2e8f0; font-weight: 600; }

/* Chat Interface */
.chat-section {
    margin-top: 0;
}
.chat-card {
    background: var(--bg-card, #1e293b);
    border: 1px solid rgba(99,102,241,0.15);
    border-radius: 12px;
    overflow: hidden;
}
.chat-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 20px;
    border-bottom: 1px solid rgba(99,102,241,0.1);
}
.chat-card-header h3 {
    margin: 0; font-size: 0.95rem; font-weight: 600; color: #e2e8f0;
    display: flex; align-items: center; gap: 8px;
}
.chat-card-header h3 i { color: #8b5cf6; }

.chat-messages {
    height: 400px;
    overflow-y: auto;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.chat-messages::-webkit-scrollbar { width: 6px; }
.chat-messages::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

.chat-msg {
    max-width: 85%;
    padding: 12px 16px;
    border-radius: 12px;
    line-height: 1.6;
    font-size: 0.875rem;
}
.chat-msg.user {
    align-self: flex-end;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.chat-msg.assistant {
    align-self: flex-start;
    background: #0f172a;
    color: #cbd5e1;
    border-bottom-left-radius: 4px;
    max-width: 92%;
}
.chat-msg.assistant .md-content h2 { font-size: 1rem; }
.chat-msg.assistant .md-content h3 { font-size: 0.9rem; }
.chat-msg.assistant .md-content p { font-size: 0.875rem; }

.chat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #475569;
    text-align: center;
    gap: 8px;
}
.chat-empty i { font-size: 2.5rem; opacity: 0.4; }
.chat-empty p { margin: 0; font-size: 0.875rem; }

.chat-input-area {
    display: flex;
    gap: 8px;
    padding: 14px 20px;
    border-top: 1px solid rgba(99,102,241,0.1);
    background: rgba(15,23,42,0.5);
}
.chat-input {
    flex: 1;
    background: var(--bg-card, #1e293b);
    border: 1px solid var(--border-color, #334155);
    color: #f1f5f9;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 0.875rem;
    resize: none;
    min-height: 42px;
    max-height: 120px;
    font-family: inherit;
}
.chat-input:focus { outline: none; border-color: #6366f1; }
.chat-input::placeholder { color: #475569; }

.btn-send {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border: none;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
    transition: all 0.2s;
}
.btn-send:hover { opacity: 0.9; }
.btn-send:disabled { opacity: 0.5; cursor: not-allowed; }

/* Quick suggestions */
.chat-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 0 20px 12px;
}
.suggestion-chip {
    background: rgba(99,102,241,0.1);
    border: 1px solid rgba(99,102,241,0.2);
    color: #a5b4fc;
    padding: 5px 12px;
    border-radius: 16px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}
.suggestion-chip:hover { background: rgba(99,102,241,0.2); color: #c7d2fe; }

/* Loading state */
.loading-dots {
    display: inline-flex;
    gap: 4px;
    padding: 8px 0;
}
.loading-dots span {
    width: 8px; height: 8px;
    background: #6366f1;
    border-radius: 50%;
    animation: dotPulse 1.4s ease-in-out infinite;
}
.loading-dots span:nth-child(2) { animation-delay: 0.2s; }
.loading-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes dotPulse {
    0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
    40% { opacity: 1; transform: scale(1); }
}

/* AI status bar */
.ai-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: rgba(99,102,241,0.08);
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 0.8rem;
    color: #94a3b8;
}
.ai-status .dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.ai-status .dot.online { background: #22c55e; }
.ai-status .dot.offline { background: #ef4444; }

.no-data-overlay {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: #475569;
    text-align: center;
    gap: 12px;
}
.no-data-overlay i { font-size: 3rem; opacity: 0.3; }

/* Two-col layout for report + chat */
.report-chat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
@media (max-width: 1200px) {
    .report-chat-grid { grid-template-columns: 1fr; }
    .charts-grid { grid-template-columns: 1fr; }
}

/* Spinner for generate button */
.spinner {
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<!-- AI Status Bar -->
<div class="ai-status">
    <span class="dot <?= ($aiStatus['available'] ?? false) ? 'online' : 'offline' ?>"></span>
    <span>AI Provider: <strong><?= htmlspecialchars($aiStatus['provider_name'] ?? 'Not configured') ?></strong></span>
    <span>&mdash; <?= ($aiStatus['available'] ?? false) ? 'Connected' : 'Unavailable &mdash; configure in <a href="/admin/settings" style="color:#a5b4fc">Settings > AI</a>' ?></span>
</div>

<!-- Header -->
<div class="analytics-header">
    <h1><i class="lucide-bar-chart-2"></i> Analytics &amp; AI Insights</h1>
    <div class="analytics-actions">
        <select class="focus-select" id="focusArea">
            <option value="general">Full Report</option>
            <option value="engagement">Engagement Focus</option>
            <option value="growth">Growth Focus</option>
            <option value="content">Content Focus</option>
            <option value="revenue">Revenue Focus</option>
        </select>
        <button class="btn-generate" id="btnGenerate" <?= !($aiStatus['available'] ?? false) ? 'disabled' : '' ?>>
            <i class="lucide-sparkles"></i>
            <span>Generate AI Report</span>
        </button>
        <button class="btn-sm" id="btnRefresh" title="Refresh data">
            <i class="lucide-refresh-cw"></i> Refresh
        </button>
        <button class="btn-sm" id="btnAiTest" title="Test AI connectivity and dump prompt" style="background:var(--card-bg);border:1px solid #334155;color:#94a3b8;">
            <i class="lucide-stethoscope"></i> Test AI
        </button>
    </div>
</div>

<!-- AI Diagnostics Panel (hidden by default) -->
<div id="aiDiagPanel" style="display:none;margin-bottom:1.5rem;">
    <div style="background:var(--card-bg);border:1px solid #334155;border-radius:8px;padding:1.25rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 style="margin:0;font-size:0.95rem;color:#e2e8f0;"><i class="lucide-stethoscope"></i> AI Diagnostics</h3>
            <button onclick="document.getElementById('aiDiagPanel').style.display='none'" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1.2rem;">&times;</button>
        </div>
        <div id="aiDiagContent" style="font-family:monospace;font-size:0.8rem;color:#cbd5e1;white-space:pre-wrap;max-height:500px;overflow-y:auto;">Running diagnostics...</div>
        <div style="margin-top:1rem;padding-top:0.75rem;border-top:1px solid #334155;">
            <p style="margin:0 0 0.5rem;font-size:0.8rem;color:#64748b;">To test the prompt at the command line:</p>
            <code id="aiDiagCurlCmd" style="display:block;font-size:0.75rem;color:#94a3b8;background:#0f172a;padding:0.75rem;border-radius:4px;word-break:break-all;max-height:100px;overflow-y:auto;"></code>
            <button onclick="navigator.clipboard.writeText(document.getElementById('aiDiagCurlCmd').textContent).then(()=>this.textContent='Copied!')" style="margin-top:0.5rem;padding:0.25rem 0.75rem;background:#334155;color:#e2e8f0;border:none;border-radius:4px;cursor:pointer;font-size:0.75rem;">Copy curl command</button>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid" id="kpiGrid">
    <div class="kpi-card highlight">
        <div class="kpi-value" id="kpiSubscribers">--</div>
        <div class="kpi-label">Total Subscribers</div>
        <div class="kpi-sub" id="kpiSubNew"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" id="kpiActiveViewers">--</div>
        <div class="kpi-label">Active Viewers (30d)</div>
        <div class="kpi-sub" id="kpiViewersSub"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" id="kpiCompletionRate">--</div>
        <div class="kpi-label">Completion Rate</div>
        <div class="kpi-sub" id="kpiCompletionSub"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" id="kpiContent">--</div>
        <div class="kpi-label">Content Library</div>
        <div class="kpi-sub" id="kpiContentSub"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" id="kpiAdImpressions">--</div>
        <div class="kpi-label">Ad Impressions (30d)</div>
        <div class="kpi-sub" id="kpiAdSub"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" id="kpiSearches">--</div>
        <div class="kpi-label">Searches (30d)</div>
        <div class="kpi-sub" id="kpiSearchSub"></div>
    </div>
    <div class="kpi-card" style="border-color:rgba(99,102,241,0.3)">
        <div class="kpi-value" id="kpiOnline" style="color:#6366f1">--</div>
        <div class="kpi-label">Online Now</div>
        <div class="kpi-sub" id="kpiOnlineSub"></div>
    </div>
    <div class="kpi-card" style="border-color:rgba(34,197,94,0.3)">
        <div class="kpi-value" id="kpiConcurrent" style="color:#22c55e">--</div>
        <div class="kpi-label">Watching Now</div>
        <div class="kpi-sub" id="kpiConcurrentSub"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" id="kpiEngagement">--</div>
        <div class="kpi-label">Engagement Score</div>
        <div class="kpi-sub" id="kpiEngagementSub"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" id="kpiStartup">--</div>
        <div class="kpi-label">Avg Startup Time</div>
        <div class="kpi-sub" id="kpiStartupSub"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" id="kpiBounceRate">--</div>
        <div class="kpi-label">Bounce Rate</div>
        <div class="kpi-sub" id="kpiBounceSub"></div>
    </div>
</div>

<!-- Charts -->
<div class="charts-grid" id="chartsGrid">
    <div class="chart-card">
        <h3><i class="lucide-trending-up"></i> Daily Watch Activity</h3>
        <canvas id="chartDailyWatches"></canvas>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-clock"></i> Peak Viewing Hours</h3>
        <canvas id="chartPeakHours"></canvas>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-film"></i> Top Genres</h3>
        <canvas id="chartGenres"></canvas>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-users"></i> Subscriber Growth</h3>
        <canvas id="chartGrowth"></canvas>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-monitor-smartphone"></i> Platform Distribution</h3>
        <canvas id="chartPlatforms"></canvas>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-package"></i> Package Distribution</h3>
        <canvas id="chartPackages"></canvas>
    </div>
</div>

<!-- QoE & Session Analytics -->
<div class="charts-grid">
    <div class="chart-card">
        <h3><i class="lucide-activity"></i> QoE — Buffering &amp; Errors Trend</h3>
        <canvas id="chartQoeTrend"></canvas>
        <div id="qoeErrorsTable" style="margin-top:12px;color:#94a3b8;font-size:0.85rem"></div>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-monitor-smartphone"></i> Device &amp; Browser Breakdown</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><canvas id="chartDevices"></canvas></div>
            <div><canvas id="chartBrowsers"></canvas></div>
        </div>
        <div id="connectionTable" style="margin-top:12px;color:#94a3b8;font-size:0.85rem"></div>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-shield-alert"></i> Churn Risk &amp; Engagement</h3>
        <canvas id="chartChurnRisk"></canvas>
        <div id="engagementSegments" style="margin-top:12px;color:#94a3b8;font-size:0.85rem"></div>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-eye"></i> Content Discovery — Impression CTR</h3>
        <canvas id="chartImpressionCtr"></canvas>
        <div id="discoveryGapTable" style="margin-top:12px;color:#94a3b8;font-size:0.85rem"></div>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-users"></i> Retention Cohorts</h3>
        <div id="retentionCohortTable" style="color:#94a3b8;font-size:0.85rem">Loading...</div>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-flame"></i> Binge Watching &amp; Sharing</h3>
        <div id="bingeStatsTable" style="color:#94a3b8;font-size:0.85rem">Loading...</div>
        <div id="shareStatsTable" style="margin-top:16px;color:#94a3b8;font-size:0.85rem"></div>
    </div>
</div>

<!-- At-Risk Subscribers -->
<div class="charts-grid" style="grid-template-columns:1fr">
    <div class="chart-card">
        <h3><i class="lucide-alert-triangle"></i> At-Risk Subscribers — Churn Prevention</h3>
        <div id="atRiskTable" style="color:#94a3b8;font-size:0.85rem">Loading...</div>
    </div>
</div>

<!-- AI Report + Chat side by side -->
<div class="report-chat-grid">
    <!-- Report Section -->
    <div class="report-section">
        <div class="report-card">
            <div class="report-card-header">
                <h3><i class="lucide-sparkles"></i> AI Business Report</h3>
            </div>
            <div class="report-body" id="reportBody">
                <div class="no-data-overlay" id="reportPlaceholder">
                    <i class="lucide-sparkles"></i>
                    <p>Click <strong>"Generate AI Report"</strong> to analyze your platform data</p>
                    <p style="font-size:0.75rem">The AI will review subscriber activity, content performance, and trends to provide actionable business insights.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Section -->
    <div class="chat-section">
        <div class="chat-card">
            <div class="chat-card-header">
                <h3><i class="lucide-message-circle"></i> Chat with AI Analyst</h3>
                <button class="btn-sm" id="btnResetChat" style="padding:5px 10px;font-size:0.75rem">
                    <i class="lucide-rotate-ccw"></i> New Chat
                </button>
            </div>
            <div class="chat-messages" id="chatMessages">
                <div class="chat-empty" id="chatPlaceholder">
                    <i class="lucide-message-circle"></i>
                    <p>Generate a report first, then ask follow-up questions</p>
                    <p style="font-size:0.75rem;color:#334155">e.g. "What content should we acquire?" or "How to reduce churn?"</p>
                </div>
            </div>
            <div class="chat-suggestions" id="chatSuggestions" style="display:none">
                <span class="suggestion-chip" data-q="What content should we prioritize acquiring next?">Content strategy</span>
                <span class="suggestion-chip" data-q="How can we reduce subscriber churn?">Reduce churn</span>
                <span class="suggestion-chip" data-q="What are the best times to schedule new content releases?">Release timing</span>
                <span class="suggestion-chip" data-q="How can we improve ad revenue without hurting user experience?">Ad optimization</span>
                <span class="suggestion-chip" data-q="Analyze the failed search terms and suggest content gaps to fill">Content gaps</span>
                <span class="suggestion-chip" data-q="Create a 90-day action plan based on these insights">Action plan</span>
            </div>
            <div class="chat-input-area">
                <textarea class="chat-input" id="chatInput" placeholder="Ask a follow-up question..." rows="1" disabled></textarea>
                <button class="btn-send" id="btnSend" disabled>
                    <i class="lucide-send"></i> Send
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Top Content Tables -->
<div class="charts-grid">
    <div class="chart-card">
        <h3><i class="lucide-trophy"></i> Top Movies (30 days)</h3>
        <div id="topMoviesTable" style="color:#94a3b8;font-size:0.85rem">Loading...</div>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-tv"></i> Top TV Shows (30 days)</h3>
        <div id="topSeriesTable" style="color:#94a3b8;font-size:0.85rem">Loading...</div>
        <div id="seriesInfoTable" style="color:#94a3b8;font-size:0.85rem;margin-top:12px"></div>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-radio"></i> Top Channels (30 days)</h3>
        <div id="topChannelsTable" style="color:#94a3b8;font-size:0.85rem">Loading...</div>
        <div id="channelInfoTable" style="color:#94a3b8;font-size:0.85rem;margin-top:12px"></div>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-search"></i> Top Searches</h3>
        <div id="topSearchesTable" style="color:#94a3b8;font-size:0.85rem">Loading...</div>
    </div>
    <div class="chart-card">
        <h3><i class="lucide-globe"></i> Subscriber Countries</h3>
        <canvas id="chartCountries"></canvas>
    </div>
</div>

<!-- Geo Map Audience Visualization -->
<div class="geo-map-section">
    <div class="geo-map-card">
        <h3><i class="lucide-map"></i> Audience Geo-Map &mdash; Viewer Distribution</h3>
        <div style="display:flex;gap:8px;margin-bottom:12px">
            <button class="geo-view-btn active" data-view="caribbean" onclick="switchGeoView('caribbean')">
                <i class="lucide-compass"></i> Caribbean &amp; Americas
            </button>
            <button class="geo-view-btn" data-view="world" onclick="switchGeoView('world')">
                <i class="lucide-globe"></i> World View
            </button>
        </div>
        <div class="geo-map-container" id="geoMapContainer">
            <div id="geoMapSvgWrap" style="width:100%;overflow:hidden;position:relative">
                <svg id="geoMapSvg" viewBox="0 0 1000 600" style="width:100%;height:auto;background:rgba(15,23,42,0.5);border-radius:8px"></svg>
            </div>
            <div id="geoMapTooltip" class="geo-tooltip" style="display:none"></div>
        </div>
        <div class="geo-map-legend">
            <span>No viewers</span>
            <div style="width:16px;height:10px;border-radius:2px;background:rgba(51,65,85,0.5)"></div>
            <span style="margin-left:12px">Active viewers</span>
            <div style="width:16px;height:10px;border-radius:2px;background:rgba(99,102,241,0.4)"></div>
            <div style="width:16px;height:10px;border-radius:2px;background:rgba(99,102,241,0.7)"></div>
            <div style="width:16px;height:10px;border-radius:2px;background:rgba(99,102,241,1)"></div>
            <span>Most viewers</span>
        </div>
        <div class="geo-country-details" id="geoCountryDetails">
            <p style="color:#475569;text-align:center;grid-column:1/-1;padding:20px">Loading viewer data...</p>
        </div>
    </div>
</div>

<script>
(function() {
    const CSRF = '<?= $csrf ?>';
    const chartInstances = {};
    let platformData = null;
    let reportGenerated = false;

    // Chart.js global defaults for dark theme
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(51,65,85,0.5)';
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.padding = 12;

    const COLORS = ['#6366f1','#8b5cf6','#a855f7','#ec4899','#f43f5e','#f59e0b','#22c55e','#14b8a6','#3b82f6','#06b6d4'];

    // ---- Data Loading ----
    async function loadData() {
        try {
            const resp = await fetch('/admin/analytics/data');
            if (!resp.ok) {
                console.error('[Analytics] HTTP error:', resp.status, resp.statusText);
                return;
            }
            const json = await resp.json();
            if (json.success) {
                platformData = json.data;
                console.log('[Analytics] Data loaded:', Object.keys(json.data));
                renderKPIs(json.data);
                renderCharts(json.data);
                renderAdvancedCharts(json.data);
                renderTables(json.data);
                renderAdvancedTables(json.data);
                renderGeoMap(json.data);
            } else {
                console.error('[Analytics] API error:', json);
            }
        } catch (e) {
            console.error('Failed to load analytics data:', e);
        }
    }

    function fmt(n) {
        if (n === null || n === undefined) return '0';
        n = parseInt(n) || 0;
        if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
        return n.toLocaleString();
    }

    function pct(a, b) {
        if (!b || b == 0) return '0%';
        return Math.round((a / b) * 100) + '%';
    }

    // ---- KPIs ----
    function renderKPIs(d) {
        const sub = d.subscribers || {};
        document.getElementById('kpiSubscribers').textContent = fmt(sub.total);
        document.getElementById('kpiSubNew').textContent = '+' + fmt(sub.new_30d) + ' this month, +' + fmt(sub.new_7d) + ' this week';

        const wa = d.watch_activity || {};
        document.getElementById('kpiActiveViewers').textContent = fmt(wa.unique_viewers);
        document.getElementById('kpiViewersSub').textContent = fmt(wa.starts) + ' watch starts';

        const starts = parseInt(wa.starts) || 0;
        const comps = parseInt(wa.completions) || 0;
        document.getElementById('kpiCompletionRate').textContent = pct(comps, starts);
        document.getElementById('kpiCompletionSub').textContent = fmt(comps) + ' of ' + fmt(starts) + ' completed';

        const c = d.content || {};
        const totalContent = (c.movies || 0) + (c.series || 0);
        document.getElementById('kpiContent').textContent = fmt(totalContent);
        document.getElementById('kpiContentSub').textContent = fmt(c.movies) + ' movies, ' + fmt(c.series) + ' series, ' + fmt(c.channels) + ' channels';

        const ad = d.ad_performance || {};
        document.getElementById('kpiAdImpressions').textContent = fmt(ad.total_impressions);
        const rev = parseFloat(ad.total_revenue) || 0;
        document.getElementById('kpiAdSub').textContent = '$' + rev.toFixed(2) + ' revenue, ' + fmt(d.ad_clicks) + ' clicks';

        document.getElementById('kpiSearches').textContent = fmt(parseInt(wa.searches) + parseInt(wa.failed_searches || 0));
        const failedPct = pct(parseInt(wa.failed_searches || 0), parseInt(wa.searches) + parseInt(wa.failed_searches || 0));
        document.getElementById('kpiSearchSub').textContent = failedPct + ' returned no results';

        // Online users
        const online = d.online_users || {};
        document.getElementById('kpiOnline').textContent = fmt(online.total);
        const byPlatform = (online.by_platform || []).map(p => p.platform + ': ' + p.online).join(', ');
        document.getElementById('kpiOnlineSub').textContent = byPlatform || 'No users online';

        // Concurrent viewers
        const conc = d.concurrent || {};
        document.getElementById('kpiConcurrent').textContent = fmt(conc.total);
        const byType = (conc.by_type || []).map(t => t.content_type + ': ' + t.concurrent).join(', ');
        document.getElementById('kpiConcurrentSub').textContent = byType || 'No active viewers';

        const eng = d.engagement || {};
        document.getElementById('kpiEngagement').textContent = eng.avg_score ? eng.avg_score + '/100' : '--';
        const churnDist = (eng.churn_distribution || []);
        const highRisk = churnDist.filter(c => c.churn_risk === 'high' || c.churn_risk === 'critical').reduce((sum, c) => sum + parseInt(c.count || 0), 0);
        document.getElementById('kpiEngagementSub').textContent = highRisk ? highRisk + ' at high/critical churn risk' : 'No engagement data yet';

        const qoe = d.qoe || {};
        const avgStartup = Math.round(parseFloat((qoe.startup || {}).avg_startup_ms) || 0);
        document.getElementById('kpiStartup').textContent = avgStartup ? avgStartup + 'ms' : '--';
        document.getElementById('kpiStartupSub').textContent = (qoe.total_errors || 0) + ' errors, ' + ((qoe.buffering || {}).buffer_events || 0) + ' buffer events';

        const sess = (d.sessions || {}).summary || {};
        const bounceRate = parseFloat(sess.bounce_rate) || 0;
        document.getElementById('kpiBounceRate').textContent = bounceRate ? bounceRate + '%' : '--';
        document.getElementById('kpiBounceSub').textContent = fmt(sess.total_sessions) + ' sessions, avg ' + Math.round((parseInt(sess.avg_duration_seconds) || 0) / 60) + 'min';
    }

    // ---- Charts ----
    function createChart(id, config) {
        if (chartInstances[id]) chartInstances[id].destroy();
        const ctx = document.getElementById(id);
        if (!ctx) return;
        chartInstances[id] = new Chart(ctx.getContext('2d'), config);
    }

    function renderCharts(d) {
        // Daily watches line chart
        const daily = d.daily_watches || [];
        createChart('chartDailyWatches', {
            type: 'line',
            data: {
                labels: daily.map(r => { const dt = new Date(r.day); return dt.toLocaleDateString('en', {month:'short',day:'numeric'}); }),
                datasets: [
                    {
                        label: 'Starts',
                        data: daily.map(r => parseInt(r.starts) || 0),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.1)',
                        fill: true,
                        tension: 0.3,
                    },
                    {
                        label: 'Completions',
                        data: daily.map(r => parseInt(r.completions) || 0),
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34,197,94,0.1)',
                        fill: true,
                        tension: 0.3,
                    },
                ],
            },
            options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } },
        });

        // Peak hours bar chart
        const hours = d.peak_hours || [];
        const hourLabels = Array.from({length: 24}, (_, i) => i + ':00');
        const hourData = new Array(24).fill(0);
        hours.forEach(r => { hourData[parseInt(r.hour)] = parseInt(r.events) || 0; });
        createChart('chartPeakHours', {
            type: 'bar',
            data: {
                labels: hourLabels,
                datasets: [{
                    label: 'Watch Starts',
                    data: hourData,
                    backgroundColor: hourData.map((v, i) => {
                        const max = Math.max(...hourData);
                        const intensity = max ? v / max : 0;
                        return `rgba(99,102,241,${0.2 + intensity * 0.8})`;
                    }),
                    borderRadius: 4,
                }],
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
        });

        // Top genres doughnut
        const genres = d.top_genres || [];
        createChart('chartGenres', {
            type: 'doughnut',
            data: {
                labels: genres.map(r => r.genre),
                datasets: [{
                    data: genres.map(r => parseInt(r.views) || 0),
                    backgroundColor: COLORS,
                    borderWidth: 0,
                }],
            },
            options: { responsive: true, plugins: { legend: { position: 'right' } } },
        });

        // Subscriber growth
        const growth = d.subscriber_growth || [];
        createChart('chartGrowth', {
            type: 'bar',
            data: {
                labels: growth.map(r => r.month),
                datasets: [{
                    label: 'New Subscribers',
                    data: growth.map(r => parseInt(r.count) || 0),
                    backgroundColor: 'rgba(99,102,241,0.6)',
                    borderRadius: 6,
                }],
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
        });

        // Platform distribution
        const plat = d.platforms || [];
        createChart('chartPlatforms', {
            type: 'doughnut',
            data: {
                labels: plat.map(r => r.platform),
                datasets: [{
                    data: plat.map(r => parseInt(r.events) || 0),
                    backgroundColor: COLORS,
                    borderWidth: 0,
                }],
            },
            options: { responsive: true, plugins: { legend: { position: 'right' } } },
        });

        // Package distribution
        const pkgs = d.packages || [];
        createChart('chartPackages', {
            type: 'pie',
            data: {
                labels: pkgs.map(r => r.name),
                datasets: [{
                    data: pkgs.map(r => parseInt(r.subscribers) || 0),
                    backgroundColor: COLORS,
                    borderWidth: 0,
                }],
            },
            options: { responsive: true, plugins: { legend: { position: 'right' } } },
        });

        // Countries bar
        const countries = d.countries || [];
        createChart('chartCountries', {
            type: 'bar',
            data: {
                labels: countries.map(r => r.country),
                datasets: [{
                    label: 'Subscribers',
                    data: countries.map(r => parseInt(r.count) || 0),
                    backgroundColor: 'rgba(139,92,246,0.6)',
                    borderRadius: 6,
                }],
            },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } },
        });
    }

    // ---- Advanced Charts (QoE, Engagement, Impressions, Devices) ----
    function renderAdvancedCharts(d) {
        // QoE Trend chart
        var qoeTrend = (d.qoe || {}).trend || [];
        if (qoeTrend.length) {
            createChart('chartQoeTrend', {
                type: 'line',
                data: {
                    labels: qoeTrend.map(function(r) { var dt = new Date(r.day); return dt.toLocaleDateString('en', {month:'short',day:'numeric'}); }),
                    datasets: [
                        {
                            label: 'Buffer Events',
                            data: qoeTrend.map(function(r) { return parseInt(r.buffer_events) || 0; }),
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245,158,11,0.1)',
                            fill: true, tension: 0.3,
                        },
                        {
                            label: 'Errors',
                            data: qoeTrend.map(function(r) { return parseInt(r.error_events) || 0; }),
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239,68,68,0.1)',
                            fill: true, tension: 0.3,
                        },
                        {
                            label: 'Avg Startup (ms)',
                            data: qoeTrend.map(function(r) { return Math.round(parseFloat(r.avg_startup_ms) || 0); }),
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34,197,94,0.05)',
                            fill: false, tension: 0.3, yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Count' } },
                        y1: { position: 'right', beginAtZero: true, title: { display: true, text: 'ms' }, grid: { drawOnChartArea: false } },
                    },
                },
            });
        }

        // Device type chart
        var devices = (d.sessions || {}).devices || [];
        if (devices.length) {
            createChart('chartDevices', {
                type: 'doughnut',
                data: {
                    labels: devices.map(function(r) { return r.device_type; }),
                    datasets: [{ data: devices.map(function(r) { return parseInt(r.sessions); }), backgroundColor: COLORS }],
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } }, title: { display: true, text: 'Devices', font: { size: 11 } } } },
            });
        }

        // Browser chart
        var browsers = (d.sessions || {}).browsers || [];
        if (browsers.length) {
            createChart('chartBrowsers', {
                type: 'doughnut',
                data: {
                    labels: browsers.map(function(r) { return r.browser; }),
                    datasets: [{ data: browsers.map(function(r) { return parseInt(r.sessions); }), backgroundColor: COLORS.slice().reverse() }],
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } }, title: { display: true, text: 'Browsers', font: { size: 11 } } } },
            });
        }

        // Churn risk chart
        var churnDist = (d.engagement || {}).churn_distribution || [];
        if (churnDist.length) {
            var churnColors = { low: '#22c55e', medium: '#f59e0b', high: '#f97316', critical: '#ef4444' };
            createChart('chartChurnRisk', {
                type: 'bar',
                data: {
                    labels: churnDist.map(function(r) { return r.churn_risk.charAt(0).toUpperCase() + r.churn_risk.slice(1); }),
                    datasets: [{
                        label: 'Subscribers',
                        data: churnDist.map(function(r) { return parseInt(r.count); }),
                        backgroundColor: churnDist.map(function(r) { return churnColors[r.churn_risk] || '#6366f1'; }),
                        borderRadius: 6,
                    }],
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
            });
        }

        // Impression CTR by section
        var sectionCtr = (d.impressions || {}).section_ctr || [];
        if (sectionCtr.length) {
            createChart('chartImpressionCtr', {
                type: 'bar',
                data: {
                    labels: sectionCtr.map(function(r) { return (r.section_type || 'unknown').replace(/_/g, ' '); }),
                    datasets: [
                        {
                            label: 'Impressions',
                            data: sectionCtr.map(function(r) { return parseInt(r.impressions); }),
                            backgroundColor: 'rgba(99,102,241,0.5)',
                            borderRadius: 4,
                        },
                        {
                            label: 'CTR %',
                            data: sectionCtr.map(function(r) { return parseFloat(r.ctr) || 0; }),
                            backgroundColor: 'rgba(34,197,94,0.5)',
                            borderRadius: 4,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Impressions' } },
                        y1: { position: 'right', beginAtZero: true, title: { display: true, text: 'CTR %' }, grid: { drawOnChartArea: false } },
                    },
                },
            });
        }
    }

    // ---- Advanced Tables (Engagement, Cohorts, Binge, Shares, At-Risk) ----
    function renderAdvancedTables(d) {
        // QoE errors table
        var errors = (d.qoe || {}).errors || [];
        if (errors.length) {
            document.getElementById('qoeErrorsTable').innerHTML =
                '<div style="color:#94a3b8;font-weight:600;margin-bottom:6px;font-size:0.8rem">Error Breakdown</div>' +
                buildTable(['Error Code', 'Count'], errors.map(function(r) { return [esc(r.error_code || 'unknown'), fmt(r.count)]; }));
        }

        // Connection types
        var connections = (d.sessions || {}).connections || [];
        if (connections.length) {
            document.getElementById('connectionTable').innerHTML =
                '<div style="color:#94a3b8;font-weight:600;margin-bottom:6px;font-size:0.8rem">Connection Types</div>' +
                buildTable(['Type', 'Sessions'], connections.map(function(r) { return [esc(r.connection_type), fmt(r.sessions)]; }));
        }

        // Engagement segments
        var segments = (d.engagement || {}).score_segments || [];
        if (segments.length) {
            document.getElementById('engagementSegments').innerHTML =
                buildTable(['Segment', 'Count', 'Avg Score', 'Avg Watch Hrs/30d'], segments.map(function(r) {
                    return [esc(r.segment), fmt(r.count), r.avg_score, r.avg_watch_hours + 'h'];
                }));
        }

        // Discovery gap (seen but not clicked)
        var gap = (d.impressions || {}).discovery_gap || [];
        if (gap.length) {
            document.getElementById('discoveryGapTable').innerHTML =
                '<div style="color:#94a3b8;font-weight:600;margin-bottom:6px;font-size:0.8rem">Low CTR Content (seen but not clicked)</div>' +
                buildTable(['Title', 'Type', 'Impressions', 'CTR'], gap.map(function(r) {
                    return [esc(r.title || 'ID:' + r.content_id), esc(r.content_type), fmt(r.impressions), r.ctr + '%'];
                }));
        }

        // Retention cohort heatmap table
        var cohorts = d.retention_cohorts || [];
        if (cohorts.length) {
            var months = {};
            cohorts.forEach(function(r) {
                if (!months[r.cohort_month]) months[r.cohort_month] = { size: r.cohort_size, data: {} };
                months[r.cohort_month].data[r.months_after] = r.retention_rate;
            });
            var maxAfter = Math.max.apply(null, cohorts.map(function(r) { return parseInt(r.months_after); }));
            var html = '<table style="width:100%;border-collapse:collapse;font-size:0.75rem"><thead><tr>';
            html += '<th style="padding:6px 8px;border-bottom:1px solid #334155;text-align:left;color:#e2e8f0">Cohort</th>';
            html += '<th style="padding:6px 8px;border-bottom:1px solid #334155;text-align:right;color:#e2e8f0">Size</th>';
            for (var i = 0; i <= Math.min(maxAfter, 11); i++) {
                html += '<th style="padding:6px 8px;border-bottom:1px solid #334155;text-align:center;color:#e2e8f0">M+' + i + '</th>';
            }
            html += '</tr></thead><tbody>';
            Object.keys(months).sort().forEach(function(month) {
                html += '<tr><td style="padding:5px 8px;border-bottom:1px solid rgba(51,65,85,0.3);color:#e2e8f0">' + month + '</td>';
                html += '<td style="padding:5px 8px;border-bottom:1px solid rgba(51,65,85,0.3);text-align:right">' + months[month].size + '</td>';
                for (var j = 0; j <= Math.min(maxAfter, 11); j++) {
                    var rate = months[month].data[j];
                    var color = 'transparent';
                    if (rate !== undefined) {
                        var intensity = Math.max(0.1, parseFloat(rate) / 100);
                        color = 'rgba(99,102,241,' + intensity.toFixed(2) + ')';
                    }
                    html += '<td style="padding:5px 8px;border-bottom:1px solid rgba(51,65,85,0.3);text-align:center;background:' + color + ';border-radius:4px">';
                    html += rate !== undefined ? parseFloat(rate).toFixed(0) + '%' : '-';
                    html += '</td>';
                }
                html += '</tr>';
            });
            html += '</tbody></table>';
            document.getElementById('retentionCohortTable').innerHTML = html;
        } else {
            document.getElementById('retentionCohortTable').innerHTML = '<p style="text-align:center;padding:20px;color:#475569">No cohort data yet. Compute engagement scores to generate cohort data.</p>';
        }

        // Binge stats
        var binge = d.binge || {};
        var bingeSummary = binge.summary || {};
        var bingeHtml = '';
        if (parseInt(bingeSummary.total_binges) > 0) {
            bingeHtml += '<div style="display:flex;gap:20px;margin-bottom:12px">';
            bingeHtml += '<div><span style="font-size:1.2rem;font-weight:700;color:#f59e0b">' + fmt(bingeSummary.total_binges) + '</span><div style="font-size:0.7rem;color:#64748b">Binge Sessions</div></div>';
            bingeHtml += '<div><span style="font-size:1.2rem;font-weight:700;color:#f59e0b">' + fmt(bingeSummary.unique_bingers) + '</span><div style="font-size:0.7rem;color:#64748b">Binge Watchers</div></div>';
            bingeHtml += '<div><span style="font-size:1.2rem;font-weight:700;color:#f59e0b">' + (Math.round(parseFloat(bingeSummary.avg_episodes_per_binge) * 10) / 10 || 0) + '</span><div style="font-size:0.7rem;color:#64748b">Avg Eps/Binge</div></div>';
            bingeHtml += '</div>';
            var binged = binge.top_binged_series || [];
            if (binged.length) {
                bingeHtml += buildTable(['Series', 'Binges', 'Avg Episodes', 'Avg Duration'], binged.map(function(r) {
                    return [esc(r.series_title), fmt(r.binge_count), Math.round(parseFloat(r.avg_episodes) * 10) / 10, Math.round(parseFloat(r.avg_duration_min)) + ' min'];
                }));
            }
        } else {
            bingeHtml = '<p style="text-align:center;padding:10px;color:#475569">No binge watching data yet</p>';
        }
        document.getElementById('bingeStatsTable').innerHTML = bingeHtml;

        // Share stats
        var shares = d.shares || {};
        var byMethod = shares.by_method || [];
        var topShared = shares.top_shared || [];
        var shareHtml = '';
        if (byMethod.length || topShared.length) {
            shareHtml += '<div style="color:#94a3b8;font-weight:600;margin-bottom:6px;font-size:0.85rem"><i class="lucide-share-2" style="font-size:0.85rem"></i> Content Sharing</div>';
            if (byMethod.length) {
                shareHtml += buildTable(['Method', 'Count'], byMethod.map(function(r) { return [esc(r.share_method || 'unknown'), fmt(r.count)]; }));
            }
            if (topShared.length) {
                shareHtml += '<div style="margin-top:8px;color:#94a3b8;font-weight:600;margin-bottom:4px;font-size:0.8rem">Most Shared</div>';
                shareHtml += buildTable(['Title', 'Type', 'Shares'], topShared.map(function(r) { return [esc(r.title || 'ID:' + r.content_id), esc(r.content_type), fmt(r.shares)]; }));
            }
        } else {
            shareHtml = '<p style="text-align:center;padding:10px;color:#475569">No sharing data yet</p>';
        }
        document.getElementById('shareStatsTable').innerHTML = shareHtml;

        // At-risk subscribers table
        var atRisk = (d.engagement || {}).at_risk || [];
        if (atRisk.length) {
            var riskColors = { critical: '#ef4444', high: '#f97316' };
            document.getElementById('atRiskTable').innerHTML = buildTable(
                ['Username', 'Email', 'Score', 'Days Inactive', 'Sessions (30d)', 'Watch Hours', 'Risk'],
                atRisk.map(function(r) {
                    var riskColor = riskColors[r.churn_risk] || '#f59e0b';
                    return [
                        esc(r.username || 'N/A'),
                        esc(r.email || 'N/A'),
                        '<span style="font-weight:700">' + parseFloat(r.score).toFixed(0) + '</span>',
                        r.days_since_last_activity + ' days',
                        fmt(r.sessions_30d),
                        parseFloat(r.watch_hours_30d).toFixed(1) + 'h',
                        '<span style="color:' + riskColor + ';font-weight:600">' + r.churn_risk.toUpperCase() + '</span>'
                    ];
                })
            );
        } else {
            document.getElementById('atRiskTable').innerHTML = '<p style="text-align:center;padding:20px;color:#475569">No at-risk subscriber data. Run engagement score computation to identify churn risks.</p>';
        }
    }

    // ---- Geo Map (SVG-based with Caribbean focus) ----
    let geoTopoData = null;
    let geoCurrentView = 'caribbean';
    let geoViewerData = {};

    // Country code/name to numeric ID mapping
    const C2N = {
        'AF':4,'AL':8,'DZ':12,'AD':20,'AO':24,'AG':28,'AR':32,'AM':51,'AU':36,'AT':40,
        'AZ':31,'BS':44,'BH':48,'BD':50,'BB':52,'BY':112,'BE':56,'BZ':84,'BJ':204,'BT':64,
        'BO':68,'BA':70,'BW':72,'BR':76,'BN':96,'BG':100,'BF':854,'BI':108,'KH':116,
        'CM':120,'CA':124,'CV':132,'CF':140,'TD':148,'CL':152,'CN':156,'CO':170,'KM':174,
        'CG':178,'CD':180,'CR':188,'HR':191,'CU':192,'CY':196,'CZ':203,'DK':208,'DJ':262,
        'DM':212,'DO':214,'TL':626,'EC':218,'EG':818,'SV':222,'GQ':226,'ER':232,'EE':233,
        'ET':231,'FJ':242,'FI':246,'FR':250,'GA':266,'GM':270,'GE':268,'DE':276,'GH':288,
        'GR':300,'GD':308,'GT':320,'GN':324,'GW':624,'GY':328,'HT':332,'HN':340,'HU':348,
        'IS':352,'IN':356,'ID':360,'IR':364,'IQ':368,'IE':372,'IL':376,'IT':380,'CI':384,
        'JM':388,'JP':392,'JO':400,'KZ':398,'KE':404,'KI':296,'KW':414,'KG':417,'LA':418,
        'LV':428,'LB':422,'LS':426,'LR':430,'LY':434,'LI':438,'LT':440,'LU':442,
        'MG':450,'MW':454,'MY':458,'MV':462,'ML':466,'MT':470,'MH':584,'MR':478,'MU':480,
        'MX':484,'FM':583,'MD':498,'MC':492,'MN':496,'ME':499,'MA':504,'MZ':508,'MM':104,
        'NA':516,'NR':520,'NP':524,'NL':528,'NZ':554,'NI':558,'NE':562,'NG':566,'KP':408,
        'MK':807,'NO':578,'OM':512,'PK':586,'PW':585,'PS':275,'PA':591,'PG':598,'PY':600,
        'PE':604,'PH':608,'PL':616,'PT':620,'QA':634,'RO':642,'RU':643,'RW':646,'KN':659,
        'LC':662,'VC':670,'WS':882,'SM':674,'ST':678,'SA':682,'SN':686,'RS':688,'SC':690,
        'SL':694,'SG':702,'SK':703,'SI':705,'SB':90,'SO':706,'ZA':710,'KR':410,'SS':728,
        'ES':724,'LK':144,'SD':729,'SR':740,'SE':752,'CH':756,'SY':760,'TW':158,'TJ':762,
        'TZ':834,'TH':764,'TG':768,'TO':776,'TT':780,'TN':788,'TR':792,'TM':795,'TV':798,
        'UG':800,'UA':804,'AE':784,'GB':826,'US':840,'UY':858,'UZ':860,'VU':548,'VA':336,
        'VE':862,'VN':704,'YE':887,'ZM':894,'ZW':716,
        // Full names
        'Afghanistan':4,'Albania':8,'Algeria':12,'Angola':24,'Antigua and Barbuda':28,
        'Argentina':32,'Armenia':51,'Australia':36,'Austria':40,'Azerbaijan':31,'Bahamas':44,
        'Bahrain':48,'Bangladesh':50,'Barbados':52,'Belarus':112,'Belgium':56,'Belize':84,
        'Bolivia':68,'Bosnia and Herzegovina':70,'Botswana':72,'Brazil':76,'Bulgaria':100,
        'Burkina Faso':854,'Burundi':108,'Cambodia':116,'Cameroon':120,'Canada':124,
        'Central African Republic':140,'Chad':148,'Chile':152,'China':156,'Colombia':170,
        'Congo':178,'Democratic Republic of the Congo':180,'Costa Rica':188,'Croatia':191,
        'Cuba':192,'Cyprus':196,'Czech Republic':203,'Czechia':203,'Denmark':208,
        'Dominica':212,'Dominican Republic':214,'Ecuador':218,'Egypt':818,'El Salvador':222,
        'Estonia':233,'Ethiopia':231,'Fiji':242,'Finland':246,'France':250,'Gabon':266,
        'Georgia':268,'Germany':276,'Ghana':288,'Greece':300,'Grenada':308,'Guatemala':320,
        'Guinea':324,'Guyana':328,'Haiti':332,'Honduras':340,'Hungary':348,'Iceland':352,
        'India':356,'Indonesia':360,'Iran':364,'Iraq':368,'Ireland':372,'Israel':376,
        'Italy':380,'Ivory Coast':384,"Cote d'Ivoire":384,'Jamaica':388,'Japan':392,
        'Jordan':400,'Kazakhstan':398,'Kenya':404,'Kuwait':414,'Laos':418,'Latvia':428,
        'Lebanon':422,'Libya':434,'Lithuania':440,'Luxembourg':442,'Madagascar':450,
        'Malaysia':458,'Mali':466,'Mexico':484,'Moldova':498,'Mongolia':496,'Montenegro':499,
        'Morocco':504,'Mozambique':508,'Myanmar':104,'Namibia':516,'Nepal':524,
        'Netherlands':528,'New Zealand':554,'Nicaragua':558,'Niger':562,'Nigeria':566,
        'North Korea':408,'North Macedonia':807,'Norway':578,'Oman':512,'Pakistan':586,
        'Palestine':275,'Panama':591,'Papua New Guinea':598,'Paraguay':600,'Peru':604,
        'Philippines':608,'Poland':616,'Portugal':620,'Qatar':634,'Romania':642,'Russia':643,
        'Rwanda':646,'Saint Kitts and Nevis':659,'Saint Lucia':662,
        'Saint Vincent and the Grenadines':670,'Saudi Arabia':682,'Senegal':686,'Serbia':688,
        'Sierra Leone':694,'Singapore':702,'Slovakia':703,'Slovenia':705,'Somalia':706,
        'South Africa':710,'South Korea':410,'South Sudan':728,'Spain':724,'Sri Lanka':144,
        'Sudan':729,'Suriname':740,'Sweden':752,'Switzerland':756,'Syria':760,'Taiwan':158,
        'Tanzania':834,'Thailand':764,'Trinidad and Tobago':780,'Tunisia':788,'Turkey':792,
        'Uganda':800,'Ukraine':804,'United Arab Emirates':784,'United Kingdom':826,
        'United States':840,'Uruguay':858,'Uzbekistan':860,'Venezuela':862,'Vietnam':704,
        'Yemen':887,'Zambia':894,'Zimbabwe':716
    };

    // Numeric ID to display name
    const N2D = {
        4:'Afghanistan',8:'Albania',12:'Algeria',20:'Andorra',24:'Angola',28:'Antigua & Barbuda',
        32:'Argentina',36:'Australia',40:'Austria',44:'Bahamas',48:'Bahrain',50:'Bangladesh',
        52:'Barbados',56:'Belgium',84:'Belize',68:'Bolivia',70:'Bosnia & Herzegovina',
        72:'Botswana',76:'Brazil',100:'Bulgaria',120:'Cameroon',124:'Canada',
        140:'Central African Republic',148:'Chad',152:'Chile',156:'China',170:'Colombia',
        178:'Congo',180:'DR Congo',188:'Costa Rica',191:'Croatia',192:'Cuba',196:'Cyprus',
        203:'Czechia',208:'Denmark',212:'Dominica',214:'Dominican Republic',218:'Ecuador',
        818:'Egypt',222:'El Salvador',233:'Estonia',231:'Ethiopia',242:'Fiji',246:'Finland',
        250:'France',276:'Germany',288:'Ghana',300:'Greece',308:'Grenada',320:'Guatemala',
        324:'Guinea',328:'Guyana',332:'Haiti',340:'Honduras',348:'Hungary',352:'Iceland',
        356:'India',360:'Indonesia',364:'Iran',368:'Iraq',372:'Ireland',376:'Israel',
        380:'Italy',384:'Ivory Coast',388:'Jamaica',392:'Japan',400:'Jordan',398:'Kazakhstan',
        404:'Kenya',414:'Kuwait',428:'Latvia',422:'Lebanon',434:'Libya',440:'Lithuania',
        442:'Luxembourg',450:'Madagascar',458:'Malaysia',466:'Mali',484:'Mexico',498:'Moldova',
        496:'Mongolia',499:'Montenegro',504:'Morocco',508:'Mozambique',104:'Myanmar',
        516:'Namibia',524:'Nepal',528:'Netherlands',554:'New Zealand',558:'Nicaragua',
        562:'Niger',566:'Nigeria',578:'Norway',512:'Oman',586:'Pakistan',591:'Panama',
        598:'Papua New Guinea',600:'Paraguay',604:'Peru',608:'Philippines',616:'Poland',
        620:'Portugal',634:'Qatar',642:'Romania',643:'Russia',646:'Rwanda',
        659:'St Kitts & Nevis',662:'Saint Lucia',670:'St Vincent & Grenadines',
        682:'Saudi Arabia',686:'Senegal',688:'Serbia',702:'Singapore',703:'Slovakia',
        705:'Slovenia',706:'Somalia',710:'South Africa',410:'South Korea',728:'South Sudan',
        724:'Spain',144:'Sri Lanka',729:'Sudan',740:'Suriname',752:'Sweden',756:'Switzerland',
        760:'Syria',158:'Taiwan',834:'Tanzania',764:'Thailand',780:'Trinidad & Tobago',
        788:'Tunisia',792:'Turkey',800:'Uganda',804:'Ukraine',784:'UAE',826:'United Kingdom',
        840:'United States',858:'Uruguay',862:'Venezuela',704:'Vietnam',887:'Yemen',
        894:'Zambia',716:'Zimbabwe'
    };

    // Mercator projection helpers
    function mercX(lon, w, lonMin, lonMax) {
        return ((lon - lonMin) / (lonMax - lonMin)) * w;
    }
    function mercY(lat, h, latMin, latMax) {
        function toMerc(l) { return Math.log(Math.tan(Math.PI / 4 + (l * Math.PI / 180) / 2)); }
        var yMin = toMerc(latMin), yMax = toMerc(latMax);
        return h - ((toMerc(lat) - yMin) / (yMax - yMin)) * h;
    }

    // Convert GeoJSON coordinates to SVG path with Mercator projection
    function geoToSvgPath(coords, w, h, lonMin, lonMax, latMin, latMax) {
        var d = '';
        coords.forEach(function(ring) {
            ring.forEach(function(pt, i) {
                var x = mercX(pt[0], w, lonMin, lonMax);
                var y = mercY(pt[1], h, latMin, latMax);
                d += (i === 0 ? 'M' : 'L') + x.toFixed(1) + ',' + y.toFixed(1);
            });
            d += 'Z';
        });
        return d;
    }

    // View configurations
    const GEO_VIEWS = {
        caribbean: { lonMin: -100, lonMax: -55, latMin: 5, latMax: 35, label: 'Caribbean & Central America' },
        world: { lonMin: -180, lonMax: 180, latMin: -60, latMax: 85, label: 'World' }
    };

    function renderSvgMap(viewKey) {
        var svg = document.getElementById('geoMapSvg');
        if (!svg || !geoTopoData) return;

        var view = GEO_VIEWS[viewKey];
        var W = 1000, H = viewKey === 'world' ? 500 : 600;
        svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);

        // Clear previous
        svg.innerHTML = '';

        // Draw ocean background
        var ocean = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        ocean.setAttribute('width', W);
        ocean.setAttribute('height', H);
        ocean.setAttribute('fill', 'rgba(15,23,42,0.3)');
        svg.appendChild(ocean);

        var features = topojson.feature(geoTopoData, geoTopoData.objects.countries).features;
        var maxViewers = Math.max(1, ...Object.values(geoViewerData).map(function(v) { return v.viewers; }));
        var tooltip = document.getElementById('geoMapTooltip');

        features.forEach(function(f) {
            var numId = parseInt(f.id);
            var name = N2D[numId] || f.properties.name || '';
            var vData = geoViewerData[numId];
            var viewers = vData ? vData.viewers : 0;

            // Get color
            var fill;
            if (viewers > 0) {
                var intensity = Math.max(0.3, viewers / maxViewers);
                fill = 'rgba(99,102,241,' + intensity.toFixed(2) + ')';
            } else {
                fill = 'rgba(51,65,85,0.5)';
            }

            // Process geometry
            var geom = f.geometry;
            var allCoords = [];
            if (geom.type === 'Polygon') {
                allCoords = [geom.coordinates];
            } else if (geom.type === 'MultiPolygon') {
                allCoords = geom.coordinates;
            }

            allCoords.forEach(function(polyCoords) {
                var d = geoToSvgPath(polyCoords, W, H, view.lonMin, view.lonMax, view.latMin, view.latMax);
                if (!d || d.length < 5) return;

                var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', d);
                path.setAttribute('fill', fill);
                path.setAttribute('stroke', viewers > 0 ? 'rgba(165,180,252,0.6)' : 'rgba(71,85,105,0.4)');
                path.setAttribute('stroke-width', viewers > 0 ? '1' : '0.5');
                path.setAttribute('class', 'country-path');
                path.setAttribute('data-id', numId);
                path.setAttribute('data-name', name);

                // Tooltip events
                path.addEventListener('mouseenter', function(e) {
                    var html = '<div class="tt-country">' + esc(name) + '</div>';
                    if (vData) {
                        html += '<div class="tt-stats">';
                        html += '<span>' + vData.viewers + '</span> viewer' + (vData.viewers !== 1 ? 's' : '') + '<br>';
                        html += 'Live TV: <span>' + vData.live_tv + '</span> &bull; ';
                        html += 'Movies: <span>' + vData.movies + '</span> &bull; ';
                        html += 'Series: <span>' + vData.series + '</span>';
                        html += '</div>';
                    } else {
                        html += '<div class="tt-stats">No viewer data</div>';
                    }
                    tooltip.innerHTML = html;
                    tooltip.style.display = 'block';
                });
                path.addEventListener('mousemove', function(e) {
                    var rect = svg.closest('.geo-map-container').getBoundingClientRect();
                    tooltip.style.left = (e.clientX - rect.left + 12) + 'px';
                    tooltip.style.top = (e.clientY - rect.top - 10) + 'px';
                });
                path.addEventListener('mouseleave', function() {
                    tooltip.style.display = 'none';
                });

                svg.appendChild(path);
            });

            // Add labels for Caribbean view countries with viewers
            if (viewKey === 'caribbean' && viewers > 0 && f.geometry) {
                var centroid = getCentroid(f.geometry);
                if (centroid) {
                    var lx = mercX(centroid[0], W, view.lonMin, view.lonMax);
                    var ly = mercY(centroid[1], H, view.latMin, view.latMax);
                    if (lx > 0 && lx < W && ly > 0 && ly < H) {
                        var label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                        label.setAttribute('x', lx);
                        label.setAttribute('y', ly - 6);
                        label.setAttribute('class', 'country-label');
                        label.setAttribute('style', 'font-size:9px;fill:#a5b4fc;font-weight:600');
                        label.textContent = name;
                        svg.appendChild(label);

                        // Viewer count badge
                        var badge = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                        badge.setAttribute('x', lx);
                        badge.setAttribute('y', ly + 6);
                        badge.setAttribute('class', 'country-label');
                        badge.setAttribute('style', 'font-size:7px;fill:#6366f1');
                        badge.textContent = viewers + ' viewer' + (viewers !== 1 ? 's' : '');
                        svg.appendChild(badge);
                    }
                }
            }
        });

        // Add grid lines for Caribbean view
        if (viewKey === 'caribbean') {
            for (var lon = -100; lon <= -55; lon += 5) {
                var x = mercX(lon, W, view.lonMin, view.lonMax);
                var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('x1', x); line.setAttribute('y1', 0);
                line.setAttribute('x2', x); line.setAttribute('y2', H);
                line.setAttribute('stroke', 'rgba(51,65,85,0.15)');
                line.setAttribute('stroke-width', '0.5');
                svg.insertBefore(line, svg.children[1]);
            }
            for (var lat = 5; lat <= 35; lat += 5) {
                var y = mercY(lat, H, view.latMin, view.latMax);
                var line2 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line2.setAttribute('x1', 0); line2.setAttribute('y1', y);
                line2.setAttribute('x2', W); line2.setAttribute('y2', y);
                line2.setAttribute('stroke', 'rgba(51,65,85,0.15)');
                line2.setAttribute('stroke-width', '0.5');
                svg.insertBefore(line2, svg.children[1]);
            }
        }
    }

    function getCentroid(geometry) {
        var coords = [];
        if (geometry.type === 'Polygon') {
            coords = geometry.coordinates[0];
        } else if (geometry.type === 'MultiPolygon') {
            // Use the largest polygon
            var maxLen = 0;
            geometry.coordinates.forEach(function(poly) {
                if (poly[0].length > maxLen) { maxLen = poly[0].length; coords = poly[0]; }
            });
        }
        if (coords.length === 0) return null;
        var sumX = 0, sumY = 0;
        coords.forEach(function(p) { sumX += p[0]; sumY += p[1]; });
        return [sumX / coords.length, sumY / coords.length];
    }

    // Global function for view switching
    window.switchGeoView = function(viewKey) {
        geoCurrentView = viewKey;
        document.querySelectorAll('.geo-view-btn').forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-view') === viewKey);
        });
        renderSvgMap(viewKey);
    };

    async function renderGeoMap(d) {
        var viewerCountries = d.viewer_countries || [];
        var countryChannels = d.country_top_channels || [];
        var countryCats = d.country_top_categories || [];
        var detailsEl = document.getElementById('geoCountryDetails');

        // Build per-country channel/category lookups
        var channelsByCountry = {}, catsByCountry = {};
        countryChannels.forEach(function(r) {
            if (!channelsByCountry[r.country]) channelsByCountry[r.country] = [];
            channelsByCountry[r.country].push({ name: r.channel_name, watches: parseInt(r.watches) || 0 });
        });
        countryCats.forEach(function(r) {
            if (!catsByCountry[r.country]) catsByCountry[r.country] = [];
            catsByCountry[r.country].push({ name: r.category, watches: parseInt(r.watches) || 0 });
        });

        // Render country detail cards
        if (viewerCountries.length === 0) {
            detailsEl.innerHTML = '<p style="color:#475569;text-align:center;grid-column:1/-1;padding:20px">No viewer data yet. Data appears once subscribers watch content.</p>';
        } else {
            var html = '';
            viewerCountries.forEach(function(c) {
                var viewers = parseInt(c.viewers) || 0;
                var liveTv = parseInt(c.live_tv_watches) || 0;
                var movies = parseInt(c.movie_watches) || 0;
                var series = parseInt(c.series_watches) || 0;
                var topChans = (channelsByCountry[c.country] || []).slice(0, 3);
                var topCats = (catsByCountry[c.country] || []).slice(0, 3);

                var numId = C2N[c.country];
                var displayName = (numId !== undefined && N2D[numId]) ? N2D[numId] : c.country;

                html += '<div class="country-detail-card">';
                html += '<div class="country-header">';
                html += '<span class="country-name">' + esc(displayName) + '</span>';
                html += '<span class="country-viewers">' + viewers + ' viewer' + (viewers !== 1 ? 's' : '') + '</span>';
                html += '</div>';
                html += '<div class="country-stats">';
                html += '<div class="stat-item"><div class="stat-value" style="color:#6366f1">' + liveTv + '</div><div class="stat-label">Live TV</div></div>';
                html += '<div class="stat-item"><div class="stat-value" style="color:#8b5cf6">' + movies + '</div><div class="stat-label">Movies</div></div>';
                html += '<div class="stat-item"><div class="stat-value" style="color:#a855f7">' + series + '</div><div class="stat-label">Series</div></div>';
                html += '</div>';
                if (topChans.length) {
                    html += '<div class="country-breakdown"><div style="color:#94a3b8;font-weight:600;margin-bottom:4px">Top Channels</div>';
                    topChans.forEach(function(ch) { html += '<div class="breakdown-row"><span>' + esc(ch.name) + '</span><span style="color:#6366f1">' + ch.watches + '</span></div>'; });
                    html += '</div>';
                }
                if (topCats.length) {
                    html += '<div class="country-breakdown" style="border-top:1px solid rgba(99,102,241,0.1);padding-top:8px;margin-top:6px"><div style="color:#94a3b8;font-weight:600;margin-bottom:4px">Top Categories</div>';
                    topCats.forEach(function(cat) { html += '<div class="breakdown-row"><span>' + esc(cat.name) + '</span><span style="color:#8b5cf6">' + cat.watches + '</span></div>'; });
                    html += '</div>';
                }
                html += '</div>';
            });
            detailsEl.innerHTML = html;
        }

        // Build viewer data indexed by numeric ID
        geoViewerData = {};
        viewerCountries.forEach(function(c) {
            var numId = C2N[c.country];
            if (numId !== undefined) {
                geoViewerData[numId] = {
                    viewers: parseInt(c.viewers) || 0,
                    live_tv: parseInt(c.live_tv_watches) || 0,
                    movies: parseInt(c.movie_watches) || 0,
                    series: parseInt(c.series_watches) || 0
                };
            }
        });

        // Load topojson (higher resolution 50m for better Caribbean islands)
        try {
            var resp = await fetch('https://cdn.jsdelivr.net/npm/world-atlas@2/countries-50m.json');
            if (!resp.ok) throw new Error('Failed to load map data');
            geoTopoData = await resp.json();
            renderSvgMap(geoCurrentView);
        } catch (e) {
            console.error('[Analytics] Geo map failed:', e);
            document.getElementById('geoMapSvgWrap').innerHTML =
                '<div style="text-align:center;padding:40px;color:#475569">Map visualization unavailable. Country data shown below.</div>';
        }
    }

    // ---- Tables ----
    function renderTables(d) {
        const movies = d.top_movies || [];
        document.getElementById('topMoviesTable').innerHTML = movies.length ? buildTable(
            ['Title', 'Views', 'Completions', 'Rate'],
            movies.map(r => [esc(r.title), fmt(r.views), fmt(r.completions), pct(parseInt(r.completions)||0, parseInt(r.views)||0)])
        ) : '<p style="text-align:center;padding:20px;color:#475569">No watch data yet</p>';

        // Top TV Shows
        const series = d.top_series || [];
        const seriesInfo = d.series_info || [];
        if (series.length) {
            document.getElementById('topSeriesTable').innerHTML = buildTable(
                ['Title', 'Episodes', 'Views', 'Completions', 'Viewers', 'Rate'],
                series.map(r => [
                    esc(r.title),
                    fmt(r.episodes_watched),
                    fmt(r.views),
                    fmt(r.completions),
                    fmt(r.unique_viewers),
                    pct(parseInt(r.completions)||0, parseInt(r.views)||0)
                ])
            );
            document.getElementById('seriesInfoTable').innerHTML = '';
        } else if (seriesInfo.length) {
            document.getElementById('topSeriesTable').innerHTML =
                '<p style="text-align:center;padding:10px;color:#64748b;font-size:0.8rem">No watch data yet</p>';
            document.getElementById('seriesInfoTable').innerHTML =
                '<h4 style="font-size:0.85rem;color:#94a3b8;margin:8px 0">Series Library</h4>' +
                buildTable(
                    ['Title', 'Seasons', 'Episodes', 'Year', 'Category', 'Status'],
                    seriesInfo.map(r => [
                        esc(r.title),
                        fmt(r.total_seasons),
                        fmt(r.total_episodes),
                        esc(r.year || '-'),
                        esc(r.category),
                        '<span style="color:' + (r.status === 'published' ? '#22c55e' : '#f59e0b') + '">' + esc(r.status) + '</span>'
                    ])
                );
        } else {
            document.getElementById('topSeriesTable').innerHTML = '<p style="text-align:center;padding:20px;color:#475569">No TV shows added yet</p>';
            document.getElementById('seriesInfoTable').innerHTML = '';
        }

        // Channel viewing data — prefer watch history (has duration), fall back to events
        const channelViews = d.channel_views || [];
        const channels = d.top_channels || [];

        function fmtDuration(secs) {
            secs = parseInt(secs) || 0;
            if (secs < 60) return secs + 's';
            if (secs < 3600) return Math.floor(secs / 60) + 'm ' + (secs % 60) + 's';
            var h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60);
            return h + 'h ' + m + 'm';
        }

        if (channelViews.length) {
            document.getElementById('topChannelsTable').innerHTML = buildTable(
                ['Channel', 'Viewers', 'Sessions', 'Total Watch Time', 'Avg Session', 'Last Watched'],
                channelViews.map(r => [
                    esc(r.title),
                    fmt(r.unique_viewers),
                    fmt(r.total_sessions),
                    fmtDuration(r.total_watch_seconds),
                    fmtDuration(r.avg_watch_seconds),
                    r.last_watched ? new Date(r.last_watched).toLocaleDateString() : '-'
                ])
            );
            document.getElementById('channelInfoTable').innerHTML = '';
        } else if (channels.length) {
            document.getElementById('topChannelsTable').innerHTML = buildTable(
                ['Channel', 'Views'],
                channels.map(r => [esc(r.title), fmt(r.views)])
            );
            document.getElementById('channelInfoTable').innerHTML = '';
        } else {
            const chCount = (d.content || {}).channels || 0;
            document.getElementById('topChannelsTable').innerHTML = '<p style="text-align:center;padding:20px;color:#475569">' +
                (chCount > 0 ? 'No viewing data yet — ' + chCount + ' channels available. Data will appear as subscribers watch live TV.' : 'No channels configured yet') + '</p>';
            document.getElementById('channelInfoTable').innerHTML = '';
        }

        const searches = d.top_searches || [];
        document.getElementById('topSearchesTable').innerHTML = searches.length ? buildTable(
            ['Term', 'Count', 'No Results'],
            searches.map(r => [esc(r.term || '(empty)'), fmt(r.count), parseInt(r.no_results) ? '<span style="color:#ef4444">' + fmt(r.no_results) + '</span>' : '0'])
        ) : '<p style="text-align:center;padding:20px;color:#475569">No search data yet</p>';
    }

    function buildTable(headers, rows) {
        let h = '<table style="width:100%;border-collapse:collapse"><thead><tr>';
        headers.forEach(th => h += '<th style="padding:8px 10px;border-bottom:1px solid #334155;text-align:left;color:#e2e8f0;font-size:0.8rem">' + th + '</th>');
        h += '</tr></thead><tbody>';
        rows.forEach(row => {
            h += '<tr>';
            row.forEach(td => h += '<td style="padding:7px 10px;border-bottom:1px solid rgba(51,65,85,0.4);font-size:0.8rem">' + td + '</td>');
            h += '</tr>';
        });
        return h + '</tbody></table>';
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // ---- Report Generation ----
    const btnGenerate = document.getElementById('btnGenerate');
    const reportBody = document.getElementById('reportBody');
    const reportPlaceholder = document.getElementById('reportPlaceholder');

    btnGenerate.addEventListener('click', async () => {
        const focus = document.getElementById('focusArea').value;
        btnGenerate.disabled = true;
        btnGenerate.innerHTML = '<div class="spinner"></div><span>Analyzing...</span>';
        reportPlaceholder.style.display = 'none';

        reportBody.innerHTML = '<div style="display:flex;align-items:center;gap:12px;padding:20px;color:#94a3b8"><div class="loading-dots"><span></span><span></span><span></span></div> Generating AI report...</div>';

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 150000); // 2.5 min timeout
            const resp = await fetch('/admin/analytics/report', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(CSRF) + '&focus=' + encodeURIComponent(focus),
                signal: controller.signal,
            });
            clearTimeout(timeoutId);
            const json = await resp.json();
            if (json.success) {
                reportBody.innerHTML = '<div class="md-content">' + marked.parse(json.report) + '</div>';
                reportGenerated = true;
                enableChat();
            } else {
                const errMsg = json.message || 'Unknown error';
                const isTimeout = errMsg.toLowerCase().includes('timed out') || errMsg.toLowerCase().includes('timeout');
                reportBody.innerHTML = '<div style="color:#ef4444;padding:20px"><i class="lucide-alert-circle"></i> ' +
                    (isTimeout
                        ? 'AI request timed out. Your AI provider (Ollama) may be slow or overloaded.<br><br>Suggestions:<br>&bull; Try again — the model may need to warm up<br>&bull; Use a smaller/faster Ollama model<br>&bull; Switch to a cloud provider (OpenAI or Anthropic) in <a href="/admin/settings" style="color:#a5b4fc">Settings > AI</a>'
                        : esc(errMsg)) +
                    '</div>';
                enableChat();
            }
        } catch (e) {
            const isAbort = e.name === 'AbortError';
            reportBody.innerHTML = '<div style="color:#ef4444;padding:20px"><i class="lucide-alert-circle"></i> ' +
                (isAbort
                    ? 'Request timed out after 2.5 minutes. Your AI provider is too slow for report generation.<br><br>Consider switching to a cloud provider (OpenAI or Anthropic) in <a href="/admin/settings" style="color:#a5b4fc">Settings > AI</a>'
                    : 'Network error. Please check your connection and try again.') +
                '</div>';
            enableChat();
        }

        btnGenerate.disabled = false;
        btnGenerate.innerHTML = '<i class="lucide-sparkles"></i><span>Generate AI Report</span>';
    });

    // ---- Chat ----
    const chatMessages = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    const btnSend = document.getElementById('btnSend');
    const chatPlaceholder = document.getElementById('chatPlaceholder');
    const chatSuggestions = document.getElementById('chatSuggestions');

    function enableChat() {
        chatInput.disabled = false;
        btnSend.disabled = false;
        chatSuggestions.style.display = 'flex';
        chatPlaceholder.innerHTML = '<i class="lucide-message-circle"></i><p>Ask a question about the report or your platform data</p>';
    }

    // Suggestion chips
    document.querySelectorAll('.suggestion-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            chatInput.value = chip.dataset.q;
            sendMessage();
        });
    });

    btnSend.addEventListener('click', sendMessage);
    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-resize textarea
    chatInput.addEventListener('input', () => {
        chatInput.style.height = 'auto';
        chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
    });

    async function sendMessage() {
        const msg = chatInput.value.trim();
        if (!msg || btnSend.disabled) return;

        chatPlaceholder.style.display = 'none';
        chatSuggestions.style.display = 'none';

        // Add user message
        appendMessage('user', msg);
        chatInput.value = '';
        chatInput.style.height = 'auto';

        // Show loading
        const loadingEl = document.createElement('div');
        loadingEl.className = 'chat-msg assistant';
        loadingEl.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div>';
        chatMessages.appendChild(loadingEl);
        chatMessages.scrollTop = chatMessages.scrollHeight;

        btnSend.disabled = true;
        chatInput.disabled = true;

        try {
            const chatController = new AbortController();
            const chatTimeout = setTimeout(() => chatController.abort(), 150000); // 2.5 min
            const resp = await fetch('/admin/analytics/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(CSRF) + '&message=' + encodeURIComponent(msg),
                signal: chatController.signal,
            });
            clearTimeout(chatTimeout);
            const json = await resp.json();
            loadingEl.remove();
            if (json.success) {
                appendMessage('assistant', json.reply);
            } else {
                const errMsg = json.message || 'Unknown error';
                const isTimeout = errMsg.toLowerCase().includes('timed out') || errMsg.toLowerCase().includes('timeout');
                appendMessage('assistant', isTimeout
                    ? 'The AI request timed out. Your AI provider may be slow or overloaded. Try a shorter question, or switch to a faster provider in Settings > AI.'
                    : 'Error: ' + errMsg);
            }
        } catch (e) {
            loadingEl.remove();
            const isAbort = e.name === 'AbortError';
            appendMessage('assistant', isAbort
                ? 'Request timed out after 2.5 minutes. Your AI provider may be too slow. Consider switching to a cloud provider (OpenAI/Anthropic) in Settings > AI.'
                : 'Network error. Please try again.');
        }

        btnSend.disabled = false;
        chatInput.disabled = false;
        chatInput.focus();
    }

    function appendMessage(role, content) {
        const el = document.createElement('div');
        el.className = 'chat-msg ' + role;
        if (role === 'assistant') {
            el.innerHTML = '<div class="md-content">' + marked.parse(content) + '</div>';
        } else {
            el.textContent = content;
        }
        chatMessages.appendChild(el);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Reset chat
    document.getElementById('btnResetChat').addEventListener('click', async () => {
        try {
            await fetch('/admin/analytics/chat/reset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(CSRF),
            });
        } catch (e) {
            // Continue with UI reset even if server call fails
        }
        // Re-create the placeholder since innerHTML destroys child nodes
        chatMessages.innerHTML = '<div class="chat-empty" id="chatPlaceholder">' +
            '<i class="lucide-message-circle"></i>' +
            '<p>Ask a question about your platform data</p>' +
            '<p style="font-size:0.75rem;color:#334155">e.g. "What content should we acquire?" or "How to reduce churn?"</p>' +
            '</div>';
        // Enable chat — allow chatting with AI about platform data even without a report
        chatInput.disabled = false;
        btnSend.disabled = false;
        chatSuggestions.style.display = 'flex';
    });

    // Refresh
    document.getElementById('btnRefresh').addEventListener('click', loadData);

    // Initial load
    loadData();

    // ---- AI Diagnostics ----
    document.getElementById('btnAiTest').addEventListener('click', async function() {
        const panel = document.getElementById('aiDiagPanel');
        const content = document.getElementById('aiDiagContent');
        const curlCmd = document.getElementById('aiDiagCurlCmd');
        panel.style.display = 'block';
        content.textContent = 'Running diagnostics...\n\n1. Testing AI connectivity (simple ping)...';
        curlCmd.textContent = '';

        try {
            const focus = document.getElementById('focusArea').value;
            const resp = await fetch('/admin/analytics/ai-test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(CSRF) + '&focus=' + encodeURIComponent(focus),
            });
            const json = await resp.json();

            if (!json.success) {
                content.textContent = 'Error: ' + (json.message || 'Unknown error');
                return;
            }

            const d = json.diagnostics;
            let out = '=== AI PROVIDER ===\n';
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

            out += '\n=== REPORT PROMPT STATS ===\n';
            out += 'Characters: ' + d.prompt.length_chars.toLocaleString() + '\n';
            out += 'Words: ' + d.prompt.length_words.toLocaleString() + '\n';
            out += 'Lines: ' + d.prompt.length_lines + '\n';
            out += 'Approx tokens: ~' + Math.round(d.prompt.length_words * 1.3).toLocaleString() + '\n';
            out += '\n=== FULL PROMPT ===\n' + d.prompt.text;

            content.textContent = out;

            // Build curl command for CLI testing
            const escapedPrompt = d.prompt.text.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n');
            const curl = 'curl -s http://localhost:11434/api/generate -d \'{"model":"' + d.model + '","prompt":"' + escapedPrompt.replace(/'/g, "'\\''") + '","stream":false,"options":{"temperature":0.6,"num_predict":2000}}\' | python3 -m json.tool';
            curlCmd.textContent = curl;
        } catch (e) {
            content.textContent = 'Network error: ' + e.message;
        }
    });
})();
</script>
