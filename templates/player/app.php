<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06060e">
    <title><?= htmlspecialchars($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fonts/lucide/lucide.css">
    <link rel="stylesheet" href="/assets/css/player.css">
</head>
<body>
    <div class="app-layout" id="appLayout">
        <!-- Sidebar (desktop) -->
        <aside class="app-sidebar" id="appSidebar">
            <div class="sidebar-brand">
                <?php if (!empty($siteLogo)): ?>
                    <img src="<?= htmlspecialchars($siteLogo) ?>" alt="" class="sidebar-brand-img">
                <?php else: ?>
                    <div class="sidebar-brand-icon"><?= strtoupper(substr($siteName, 0, 1)) ?></div>
                <?php endif; ?>
                <span class="sidebar-brand-name"><?= htmlspecialchars($siteName) ?></span>
            </div>
            <nav class="sidebar-nav" id="sidebarNav">
                <!-- Populated by JavaScript from navigation API -->
            </nav>
            <div class="sidebar-user" id="sidebarUser">
                <div class="sidebar-user-avatar" id="userAvatar"></div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name" id="userName"></div>
                    <div class="sidebar-user-email" id="userEmail"></div>
                </div>
                <button class="sidebar-logout" id="logoutBtn" title="Sign out">
                    <i class="lucide-log-out"></i>
                </button>
            </div>
        </aside>

        <!-- Sidebar backdrop (tablet) -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <!-- Main Content -->
        <main class="app-main">
            <!-- Header -->
            <header class="app-header">
                <button class="header-menu-btn" id="menuBtn">
                    <i class="lucide-menu"></i>
                </button>
                <div class="header-search">
                    <i class="lucide-search header-search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search movies, shows, channels..." autocomplete="off">
                </div>
                <div class="header-spacer"></div>
            </header>

            <!-- Top navigation bar (used when nav style is top_bar) -->
            <nav class="top-nav" id="topNav"></nav>

            <!-- Dynamic content area -->
            <div class="app-content" id="appContent">
                <div class="loading-screen">
                    <div class="loading-spinner"></div>
                </div>
            </div>
        </main>

        <!-- Mobile bottom tabs -->
        <nav class="mobile-tabs" id="mobileTabs">
            <!-- Populated by JavaScript -->
        </nav>
    </div>

    <!-- Detail modal -->
    <div class="detail-overlay" id="detailOverlay">
        <div class="detail-modal" id="detailModal"></div>
    </div>

    <!-- Shaka Player (loaded from CDN; host locally in production) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.12.4/shaka-player.compiled.min.js" crossorigin="anonymous"></script>

    <!-- App JavaScript -->
    <script src="/assets/js/player/api.js"></script>
    <script src="/assets/js/player/ui.js"></script>
    <script src="/assets/js/player/router.js"></script>
    <script src="/assets/js/player/app.js"></script>
</body>
</html>
