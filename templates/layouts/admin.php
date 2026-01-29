<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> - <?= htmlspecialchars($siteName ?? 'CARI-IPTV') ?> Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide-static@0.263.1/font/lucide.min.css">

    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary: #64748b;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;

            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-hover: #334155;
            --bg-input: #1e293b;

            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;

            --border-color: #334155;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.3);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.4);

            --sidebar-width: 260px;
            --header-height: 64px;
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        a {
            color: var(--primary-light);
            text-decoration: none;
            transition: var(--transition);
        }

        a:hover {
            color: var(--primary);
        }

        /* Layout */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-card);
            border-right: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: var(--transition);
        }

        .sidebar-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .sidebar-logo-img {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            object-fit: contain;
        }

        .sidebar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .sidebar-brand span {
            color: var(--primary-light);
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .nav-section-title {
            padding: 0.5rem 1.25rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            color: var(--text-secondary);
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .nav-item.active {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-light);
            border-left-color: var(--primary);
        }

        .nav-item i {
            font-size: 1.25rem;
            width: 24px;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--primary);
            color: white;
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            border-radius: 10px;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        /* Header */
        .admin-header {
            height: var(--header-height);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .breadcrumb a {
            color: var(--text-secondary);
        }

        .breadcrumb a:hover {
            color: var(--primary-light);
        }

        .breadcrumb-separator {
            color: var(--text-muted);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--bg-hover);
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            position: relative;
        }

        .header-btn:hover {
            background: var(--bg-input);
            color: var(--text-primary);
        }

        .header-btn .badge {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 18px;
            height: 18px;
            background: var(--danger);
            border-radius: 50%;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
        }

        .user-menu:hover {
            background: var(--bg-hover);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-avatar-img {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            object-fit: cover;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Page Content */
        .page-content {
            padding: 1.5rem;
        }

        .page-header {
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.25rem;
        }

        .card-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--border-color);
            background: rgba(0,0,0,0.1);
        }

        /* Stat Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.primary { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); }
        .stat-icon.success { background: rgba(34, 197, 94, 0.15); color: var(--success); }
        .stat-icon.warning { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .stat-icon.info { background: rgba(59, 130, 246, 0.15); color: var(--info); }
        .stat-icon.danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .stat-change {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .stat-change.positive { color: var(--success); }
        .stat-change.negative { color: var(--danger); }

        /* Grid */
        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); }

        @media (max-width: 1200px) {
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.875rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
            background: rgba(0,0,0,0.2);
        }

        tr:hover td {
            background: var(--bg-hover);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--border-color);
        }

        .btn-warning {
            background: var(--warning);
            color: #000;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success { background: rgba(34, 197, 94, 0.15); color: var(--success); }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); }
        .badge-info { background: rgba(59, 130, 246, 0.15); color: var(--info); }
        .badge-primary { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success { background: rgba(34, 197, 94, 0.15); color: var(--success); border: 1px solid rgba(34, 197, 94, 0.3); }
        .alert-error { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.3); }
        .alert-warning { background: rgba(245, 158, 11, 0.15); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.3); }
        .alert-info { background: rgba(59, 130, 246, 0.15); color: var(--info); border: 1px solid rgba(59, 130, 246, 0.3); }

        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        /* Dropdown */
        .dropdown {
            position: relative;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            min-width: 200px;
            box-shadow: var(--shadow-lg);
            display: none;
            z-index: 100;
        }

        .dropdown.open .dropdown-menu {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .dropdown-divider {
            border-top: 1px solid var(--border-color);
            margin: 0.5rem 0;
        }

        /* Utilities */
        .text-muted { color: var(--text-muted); }
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .text-warning { color: var(--warning); }
        .text-primary { color: var(--primary-light); }

        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
        .mb-3 { margin-bottom: 1.5rem; }

        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }

        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-1 { gap: 0.5rem; }
        .gap-2 { gap: 1rem; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <?php if (!empty($siteLogo)): ?>
                    <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName ?? 'Logo') ?>" class="sidebar-logo-img">
                <?php else: ?>
                    <div class="sidebar-logo"><?= strtoupper(substr($siteName ?? 'C', 0, 1)) ?></div>
                <?php endif; ?>
                <div class="sidebar-brand"><?= htmlspecialchars($siteName ?? 'CARI-IPTV') ?></div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="/admin" class="nav-item <?= ($pageTitle ?? '') === 'Dashboard' ? 'active' : '' ?>">
                        <i class="lucide-layout-dashboard"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="/admin/analytics" class="nav-item">
                        <i class="lucide-bar-chart-2"></i>
                        <span>Analytics</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <a href="/admin/channels" class="nav-item <?= in_array($pageTitle ?? '', ['Channels', 'Add Channel', 'Edit Channel']) ? 'active' : '' ?>">
                        <i class="lucide-tv"></i>
                        <span>Channels</span>
                    </a>
                    <a href="/admin/movies" class="nav-item <?= in_array($pageTitle ?? '', ['Movies', 'Add Movie', 'Edit Movie', 'Browse Free Content']) ? 'active' : '' ?>">
                        <i class="lucide-film"></i>
                        <span>Movies</span>
                    </a>
                    <a href="/admin/series" class="nav-item <?= in_array($pageTitle ?? '', ['Series', 'Add Series', 'Edit Series']) ? 'active' : '' ?>">
                        <i class="lucide-clapperboard"></i>
                        <span>Series</span>
                    </a>
                    <a href="/admin/epg" class="nav-item <?= in_array($pageTitle ?? '', ['EPG', 'EPG Management']) ? 'active' : '' ?>">
                        <i class="lucide-calendar"></i>
                        <span>EPG</span>
                    </a>
                    <a href="/admin/categories" class="nav-item <?= in_array($pageTitle ?? '', ['Categories', 'Add Category', 'Edit Category']) ? 'active' : '' ?>">
                        <i class="lucide-folder"></i>
                        <span>Categories</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Subscribers</div>
                    <a href="/admin/users" class="nav-item">
                        <i class="lucide-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="/admin/subscriptions" class="nav-item">
                        <i class="lucide-credit-card"></i>
                        <span>Subscriptions</span>
                    </a>
                    <a href="/admin/packages" class="nav-item">
                        <i class="lucide-package"></i>
                        <span>Packages</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="/admin/activity" class="nav-item">
                        <i class="lucide-history"></i>
                        <span>Activity Log</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-left">
                    <div class="breadcrumb">
                        <a href="/admin">Admin</a>
                        <?php if (($pageTitle ?? 'Dashboard') !== 'Dashboard'): ?>
                            <span class="breadcrumb-separator">/</span>
                            <span><?= htmlspecialchars($pageTitle ?? '') ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="header-right">
                    <button class="header-btn" title="Notifications">
                        <i class="lucide-bell"></i>
                        <span class="badge">3</span>
                    </button>

                    <div class="dropdown" id="userDropdown">
                        <div class="user-menu" onclick="toggleDropdown('userDropdown')">
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($user['first_name'] ?? 'Admin') ?></div>
                                <div class="user-role"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role'] ?? 'admin'))) ?></div>
                            </div>
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar']) ?>" class="user-avatar-img" alt="Avatar">
                            <?php else: ?>
                                <div class="user-avatar">
                                    <?= strtoupper(substr($user['first_name'] ?? 'A', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-menu">
                            <a href="/admin/profile" class="dropdown-item <?= ($pageTitle ?? '') === 'My Profile' ? 'active' : '' ?>">
                                <i class="lucide-user"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="/admin/admins" class="dropdown-item <?= ($pageTitle ?? '') === 'Admin Users' ? 'active' : '' ?>">
                                <i class="lucide-shield"></i>
                                <span>Admin Users</span>
                            </a>
                            <a href="/admin/settings" class="dropdown-item <?= ($pageTitle ?? '') === 'Settings' ? 'active' : '' ?>">
                                <i class="lucide-settings"></i>
                                <span>Settings</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="/admin/logout" class="dropdown-item text-danger">
                                <i class="lucide-log-out"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                <?php
                // Flash messages
                $success = \CariIPTV\Core\Session::getFlash('success');
                $error = \CariIPTV\Core\Session::getFlash('error');
                $warning = \CariIPTV\Core\Session::getFlash('warning');

                if ($success): ?>
                    <div class="alert alert-success">
                        <i class="lucide-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif;

                if ($error): ?>
                    <div class="alert alert-error">
                        <i class="lucide-alert-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif;

                if ($warning): ?>
                    <div class="alert alert-warning">
                        <i class="lucide-alert-triangle"></i>
                        <?= htmlspecialchars($warning) ?>
                    </div>
                <?php endif; ?>

                <?= $content ?>
            </div>
        </main>
    </div>

    <script>
        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            dropdown.classList.toggle('open');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('open');
                }
            });
        });
    </script>
</body>
</html>
