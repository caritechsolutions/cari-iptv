<div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Welcome back, <?= htmlspecialchars($user['first_name'] ?? 'Admin') ?>! Here's what's happening with your platform.</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="lucide-users"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Subscribers</div>
            <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
            <div class="stat-change <?= $stats['user_growth'] >= 0 ? 'positive' : 'negative' ?>">
                <?= $stats['user_growth'] >= 0 ? '+' : '' ?><?= $stats['user_growth'] ?>% from last month
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <i class="lucide-play-circle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Active Streams</div>
            <div class="stat-value"><?= number_format($stats['active_streams']) ?></div>
            <div class="stat-change positive">Live now</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon info">
            <i class="lucide-tv"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Channels</div>
            <div class="stat-value"><?= number_format($stats['total_channels']) ?></div>
            <div class="stat-change"><?= number_format($stats['total_vod']) ?> VOD assets</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="lucide-dollar-sign"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Monthly Revenue</div>
            <div class="stat-value">$<?= number_format($stats['monthly_revenue'], 2) ?></div>
            <div class="stat-change"><?= number_format($stats['active_subscriptions']) ?> active subs</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-2 mb-3">
    <!-- Views Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Views (Last 7 Days)</h3>
            <span class="badge badge-info"><?= number_format($stats['today_views']) ?> today</span>
        </div>
        <div class="card-body">
            <canvas id="viewsChart" height="200"></canvas>
        </div>
    </div>

    <!-- New Users Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">New Subscribers (Last 7 Days)</h3>
            <span class="badge badge-success"><?= number_format($stats['new_users_month']) ?> this month</span>
        </div>
        <div class="card-body">
            <canvas id="usersChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="grid grid-2 mb-3">
    <!-- Active Streams -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Active Streams</h3>
            <a href="/admin/streams" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Content</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activeStreams)): ?>
                            <tr>
                                <td colspan="3" class="text-muted" style="text-align: center; padding: 2rem;">
                                    No active streams
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activeStreams as $stream): ?>
                                <tr>
                                    <td>
                                        <div class="text-sm"><?= htmlspecialchars($stream['first_name'] . ' ' . $stream['last_name']) ?></div>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($stream['email']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $stream['content_type'] === 'channel' ? 'primary' : 'info' ?>">
                                            <?= ucfirst($stream['content_type']) ?>
                                        </span>
                                        <span class="text-sm"><?= htmlspecialchars($stream['content_name'] ?? 'Unknown') ?></span>
                                    </td>
                                    <td class="text-sm text-muted">
                                        <?php
                                        $duration = time() - strtotime($stream['started_at']);
                                        $hours = floor($duration / 3600);
                                        $mins = floor(($duration % 3600) / 60);
                                        echo $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Popular Content -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Popular Content (7 Days)</h3>
            <a href="/admin/analytics" class="btn btn-secondary btn-sm">Analytics</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Content</th>
                            <th>Type</th>
                            <th>Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($popularContent)): ?>
                            <tr>
                                <td colspan="3" class="text-muted" style="text-align: center; padding: 2rem;">
                                    No data available
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($popularContent as $content): ?>
                                <tr>
                                    <td class="text-sm"><?= htmlspecialchars($content['content_name'] ?? 'Unknown') ?></td>
                                    <td>
                                        <span class="badge badge-<?= $content['content_type'] === 'channel' ? 'primary' : 'info' ?>">
                                            <?= ucfirst($content['content_type']) ?>
                                        </span>
                                    </td>
                                    <td class="text-sm"><?= number_format($content['view_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Recent Users & Activity Row -->
<div class="grid grid-2">
    <!-- Recent Users -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Subscribers</h3>
            <a href="/admin/users" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Package</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentUsers)): ?>
                            <tr>
                                <td colspan="3" class="text-muted" style="text-align: center; padding: 2rem;">
                                    No users yet
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>
                                        <div class="text-sm"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($user['email']) ?></div>
                                    </td>
                                    <td class="text-sm">
                                        <?= htmlspecialchars($user['package_name'] ?? 'None') ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Activity</h3>
            <a href="/admin/activity" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentActivity)): ?>
                            <tr>
                                <td colspan="3" class="text-muted" style="text-align: center; padding: 2rem;">
                                    No activity yet
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td class="text-sm">
                                        <?= htmlspecialchars($activity['username'] ?? 'System') ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?= htmlspecialchars($activity['module']) ?></span>
                                        <span class="text-sm"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $activity['action']))) ?></span>
                                    </td>
                                    <td class="text-xs text-muted">
                                        <?php
                                        $diff = time() - strtotime($activity['created_at']);
                                        if ($diff < 60) {
                                            echo 'Just now';
                                        } elseif ($diff < 3600) {
                                            echo floor($diff / 60) . 'm ago';
                                        } elseif ($diff < 86400) {
                                            echo floor($diff / 3600) . 'h ago';
                                        } else {
                                            echo date('M j, g:i a', strtotime($activity['created_at']));
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mt-3" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="flex gap-2" style="flex-wrap: wrap;">
            <a href="/admin/channels/create" class="btn btn-primary">
                <i class="lucide-plus"></i> Add Channel
            </a>
            <a href="/admin/vod/create" class="btn btn-secondary">
                <i class="lucide-film"></i> Add VOD
            </a>
            <a href="/admin/users/create" class="btn btn-secondary">
                <i class="lucide-user-plus"></i> Add User
            </a>
            <a href="/admin/epg/import" class="btn btn-secondary">
                <i class="lucide-upload"></i> Import EPG
            </a>
            <a href="/admin/packages" class="btn btn-secondary">
                <i class="lucide-package"></i> Manage Packages
            </a>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // Chart.js configuration
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = '#334155';
    Chart.defaults.font.family = "'Inter', sans-serif";

    const chartData = <?= json_encode($chartData) ?>;

    // Views Chart
    new Chart(document.getElementById('viewsChart'), {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Views',
                data: chartData.views,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#334155' },
                    ticks: { precision: 0 }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // Users Chart
    new Chart(document.getElementById('usersChart'), {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'New Users',
                data: chartData.new_users,
                backgroundColor: '#22c55e',
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#334155' },
                    ticks: { precision: 0 }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>
