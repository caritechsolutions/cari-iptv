<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Demo - CARI-IPTV</title>

    <!-- Video.js -->
    <link href="https://vjs.zencdn.net/8.6.1/video-js.css" rel="stylesheet" />

    <style>
        :root {
            --primary: #6366f1;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --bg-hover: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: #334155;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .player-layout {
            display: flex;
            height: 100vh;
        }

        .channel-sidebar {
            width: 320px;
            background: var(--bg-card);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .search-box {
            width: 100%;
            padding: 0.625rem 1rem;
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--primary);
        }

        .channel-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .channel-category {
            padding: 0.5rem 0.75rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .channel-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .channel-item:hover {
            background: var(--bg-hover);
        }

        .channel-item.active {
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .channel-logo {
            width: 40px;
            height: 40px;
            background: var(--bg-hover);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .channel-info {
            flex: 1;
            min-width: 0;
        }

        .channel-name {
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .channel-now {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .channel-number {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .player-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .player-container {
            flex: 1;
            background: #000;
            position: relative;
        }

        .video-js {
            width: 100%;
            height: 100%;
        }

        .player-info {
            background: var(--bg-card);
            padding: 1.25rem;
            border-top: 1px solid var(--border-color);
        }

        .player-info h3 {
            font-size: 1.125rem;
            margin-bottom: 0.25rem;
        }

        .player-info p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .now-playing {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .live-badge {
            background: #ef4444;
            color: white;
            font-size: 0.625rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .epg-bar {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .epg-item {
            flex: 1;
            background: var(--bg-hover);
            padding: 0.75rem;
            border-radius: 8px;
        }

        .epg-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }

        .epg-title {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .epg-item.current {
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .demo-notice {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #f59e0b;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .channel-sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="demo-notice">
        This is a demo player. Configure your streaming sources in the admin panel to enable live playback.
    </div>

    <div class="player-layout">
        <!-- Channel Sidebar -->
        <aside class="channel-sidebar">
            <div class="sidebar-header">
                <h2>Live TV</h2>
                <input type="text" class="search-box" placeholder="Search channels...">
            </div>

            <div class="channel-list">
                <div class="channel-category">Sports</div>

                <div class="channel-item active">
                    <div class="channel-logo">CS1</div>
                    <div class="channel-info">
                        <div class="channel-name">Caribbean Sports 1</div>
                        <div class="channel-now">Live: Cricket Match</div>
                    </div>
                    <div class="channel-number">101</div>
                </div>

                <div class="channel-item">
                    <div class="channel-logo">CS2</div>
                    <div class="channel-info">
                        <div class="channel-name">Caribbean Sports 2</div>
                        <div class="channel-now">Football Highlights</div>
                    </div>
                    <div class="channel-number">102</div>
                </div>

                <div class="channel-category">News</div>

                <div class="channel-item">
                    <div class="channel-logo">IN24</div>
                    <div class="channel-info">
                        <div class="channel-name">Island News 24</div>
                        <div class="channel-now">Evening Bulletin</div>
                    </div>
                    <div class="channel-number">201</div>
                </div>

                <div class="channel-category">Entertainment</div>

                <div class="channel-item">
                    <div class="channel-logo">TE</div>
                    <div class="channel-info">
                        <div class="channel-name">Tropical Entertainment</div>
                        <div class="channel-now">Movie: Island Adventure</div>
                    </div>
                    <div class="channel-number">301</div>
                </div>

                <div class="channel-item">
                    <div class="channel-logo">CK</div>
                    <div class="channel-info">
                        <div class="channel-name">Caribbean Kids</div>
                        <div class="channel-now">Cartoon Time</div>
                    </div>
                    <div class="channel-number">501</div>
                </div>

                <div class="channel-category">Music</div>

                <div class="channel-item">
                    <div class="channel-logo">RV</div>
                    <div class="channel-info">
                        <div class="channel-name">Reggae Vibes</div>
                        <div class="channel-now">Top 20 Countdown</div>
                    </div>
                    <div class="channel-number">601</div>
                </div>
            </div>
        </aside>

        <!-- Player Area -->
        <main class="player-main">
            <div class="player-container">
                <video
                    id="player"
                    class="video-js vjs-big-play-centered vjs-theme-forest"
                    controls
                    preload="auto"
                    poster="/assets/images/player-placeholder.jpg"
                >
                    <p class="vjs-no-js">
                        To view this video please enable JavaScript, and consider upgrading to a
                        web browser that supports HTML5 video.
                    </p>
                </video>
            </div>

            <div class="player-info">
                <div class="now-playing">
                    <span class="live-badge">Live</span>
                    <span>Channel 101</span>
                </div>
                <h3>Caribbean Sports 1</h3>
                <p>Live Cricket: West Indies vs England - Day 2</p>

                <div class="epg-bar">
                    <div class="epg-item">
                        <div class="epg-time">14:00 - 16:00</div>
                        <div class="epg-title">Pre-Match Analysis</div>
                    </div>
                    <div class="epg-item current">
                        <div class="epg-time">16:00 - 22:00</div>
                        <div class="epg-title">Live Cricket</div>
                    </div>
                    <div class="epg-item">
                        <div class="epg-time">22:00 - 23:00</div>
                        <div class="epg-title">Post-Match Show</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://vjs.zencdn.net/8.6.1/video.min.js"></script>
    <script>
        const player = videojs('player', {
            fluid: false,
            fill: true,
            responsive: true,
            controls: true,
            autoplay: false,
            preload: 'auto',
        });

        // Channel selection (demo)
        document.querySelectorAll('.channel-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.channel-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');

                // In production, this would load the stream URL from the API
                console.log('Loading channel:', this.querySelector('.channel-name').textContent);
            });
        });
    </script>
</body>
</html>
