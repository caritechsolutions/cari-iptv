<?php $pageTitle = 'Analytics'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
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
            const json = await resp.json();
            if (json.success) {
                platformData = json.data;
                renderKPIs(json.data);
                renderCharts(json.data);
                renderTables(json.data);
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

    // ---- Tables ----
    function renderTables(d) {
        const movies = d.top_movies || [];
        document.getElementById('topMoviesTable').innerHTML = movies.length ? buildTable(
            ['Title', 'Views', 'Completions', 'Rate'],
            movies.map(r => [esc(r.title), fmt(r.views), fmt(r.completions), pct(parseInt(r.completions)||0, parseInt(r.views)||0)])
        ) : '<p style="text-align:center;padding:20px;color:#475569">No watch data yet</p>';

        const channels = d.top_channels || [];
        const channelInfo = d.channel_info || [];
        const channelStatus = d.channel_status || [];

        if (channels.length) {
            document.getElementById('topChannelsTable').innerHTML = buildTable(
                ['Channel', 'Views'],
                channels.map(r => [esc(r.title), fmt(r.views)])
            );
            document.getElementById('channelInfoTable').innerHTML = '';
        } else if (channelInfo.length) {
            // No watch data but channels exist — show channel listing
            const statusCounts = {};
            channelStatus.forEach(r => { statusCounts[r.status] = parseInt(r.count) || 0; });
            const statusSummary = Object.entries(statusCounts).map(([s, c]) => c + ' ' + s).join(', ');
            document.getElementById('topChannelsTable').innerHTML =
                '<p style="text-align:center;padding:10px;color:#64748b;font-size:0.8rem">No watch data yet' +
                (statusSummary ? ' &mdash; ' + statusSummary + ' channels' : '') + '</p>';
            document.getElementById('channelInfoTable').innerHTML =
                '<h4 style="font-size:0.85rem;color:#94a3b8;margin:8px 0">Channel Listing</h4>' +
                buildTable(
                    ['Channel', 'Category', 'Status'],
                    channelInfo.map(r => [
                        esc(r.name),
                        esc(r.category),
                        '<span style="color:' + (r.status === 'active' ? '#22c55e' : '#f59e0b') + '">' + esc(r.status) + '</span>'
                    ])
                );
        } else {
            document.getElementById('topChannelsTable').innerHTML = '<p style="text-align:center;padding:20px;color:#475569">No channels configured yet</p>';
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
                reportBody.innerHTML = '<div style="color:#ef4444;padding:20px"><i class="lucide-alert-circle"></i> ' + esc(json.message) + '</div>';
                // Enable chat even on report error — AI can still answer using platform data
                enableChat();
            }
        } catch (e) {
            reportBody.innerHTML = '<div style="color:#ef4444;padding:20px"><i class="lucide-alert-circle"></i> Request timed out or network error. Try again or use the chat to ask specific questions.</div>';
            // Enable chat even on report failure — AI can still answer using platform data
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
            const resp = await fetch('/admin/analytics/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(CSRF) + '&message=' + encodeURIComponent(msg),
            });
            const json = await resp.json();
            loadingEl.remove();
            if (json.success) {
                appendMessage('assistant', json.reply);
            } else {
                appendMessage('assistant', 'Error: ' + (json.message || 'Unknown error'));
            }
        } catch (e) {
            loadingEl.remove();
            appendMessage('assistant', 'Network error. Please try again.');
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
})();
</script>
