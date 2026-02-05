/**
 * CARI-IPTV Web Player App
 * Main application logic — boots the SPA, renders pages
 */
const CariApp = (function() {
    let appConfig = null;
    let navigation = [];
    let navStyle = 'sidebar';
    let navSettings = {};
    let pages = [];
    let shakaPlayer = null;
    let searchTimeout = null;

    // ---- Boot ----

    async function init() {
        // Auth gate
        if (!CariAPI.isAuthenticated()) {
            window.location.href = '/login';
            return;
        }

        setupUser();
        setupSidebar();
        setupSearch();
        setupRoutes();
        await loadNavigation();

        CariRouter.start();
    }

    function setupUser() {
        const user = CariAPI.getUser();
        if (!user) return;

        const name = [user.first_name, user.last_name].filter(Boolean).join(' ') || user.username || 'User';
        const initial = (name[0] || 'U').toUpperCase();

        document.getElementById('userName').textContent = name;
        document.getElementById('userEmail').textContent = user.email || '';
        document.getElementById('userAvatar').textContent = initial;

        document.getElementById('logoutBtn').addEventListener('click', () => CariAPI.logout());
    }

    function setupSidebar() {
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('appSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');

        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            backdrop.classList.toggle('visible');
        });

        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('open');
            backdrop.classList.remove('visible');
        });
    }

    function setupSearch() {
        const input = document.getElementById('searchInput');
        input.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            const q = input.value.trim();
            if (q.length >= 2) {
                searchTimeout = setTimeout(() => CariRouter.navigate('/search?q=' + encodeURIComponent(q)), 400);
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const q = input.value.trim();
                if (q.length >= 2) {
                    CariRouter.navigate('/search?q=' + encodeURIComponent(q));
                }
            }
        });
    }

    // ---- Navigation ----

    async function loadNavigation() {
        try {
            const res = await CariAPI.getNavigation();
            const data = res?.data || {};
            navigation = data.items || [];
            navSettings = data.settings || {};
            navStyle = navSettings.style || 'sidebar';
        } catch {
            // Fallback navigation
            navigation = [
                { label: 'Home', icon: 'lucide-home', slug: '', page_type: 'home' },
                { label: 'Movies', icon: 'lucide-film', slug: 'movies', page_type: 'movies' },
                { label: 'TV Shows', icon: 'lucide-clapperboard', slug: 'series', page_type: 'series' },
                { label: 'Live TV', icon: 'lucide-tv', slug: 'live', page_type: 'live_tv' },
                { label: 'My List', icon: 'lucide-bookmark', slug: 'my-list', page_type: 'watchlist' },
            ];
            navStyle = 'sidebar';
            navSettings = {};
        }

        // Apply navigation style to layout element
        const layout = document.getElementById('appLayout');
        layout.dataset.navStyle = navStyle;
        if (navSettings.show_icons === false) layout.dataset.navIcons = 'false';
        if (navSettings.show_labels === false) layout.dataset.navLabels = 'false';

        // Render the appropriate navigation based on style
        if (navStyle === 'top_bar') {
            renderTopNav();
        } else if (navStyle === 'bottom_tab') {
            renderBottomTabs();
        } else {
            // sidebar (default): sidebar on desktop, bottom tabs on mobile
            renderSidebarNav();
            renderMobileTabs();
        }
    }

    function renderSidebarNav() {
        const nav = document.getElementById('sidebarNav');
        const items = normalizeNav(navigation);

        let html = '<div class="nav-section-label">Menu</div>';
        items.forEach(item => {
            const path = item.url || ('/' + (item.slug || ''));
            const icon = item.icon || 'lucide-circle';
            html += `<a class="nav-item" href="${CariUI.esc(path)}" data-nav-slug="${CariUI.esc(item.slug || '')}">
                <i class="${CariUI.esc(icon)}"></i>
                <span>${CariUI.esc(item.label)}</span>
            </a>`;
        });

        nav.innerHTML = html;
    }

    function renderMobileTabs() {
        const tabs = document.getElementById('mobileTabs');
        const items = normalizeNav(navigation).slice(0, 5);

        tabs.innerHTML = items.map(item => {
            const path = item.url || ('/' + (item.slug || ''));
            const icon = item.icon || 'lucide-circle';
            return `<button class="mobile-tab" data-nav-slug="${CariUI.esc(item.slug || '')}" onclick="CariRouter.navigate('${CariUI.esc(path)}')">
                <i class="${CariUI.esc(icon)}"></i>
                <span>${CariUI.esc(item.label)}</span>
            </button>`;
        }).join('');
    }

    function renderBottomTabs() {
        const tabs = document.getElementById('mobileTabs');
        const maxItems = navSettings.max_items || 5;
        const items = normalizeNav(navigation).slice(0, maxItems);

        tabs.innerHTML = items.map(item => {
            const path = item.url || ('/' + (item.slug || ''));
            const icon = item.icon || 'lucide-circle';
            return `<button class="mobile-tab" data-nav-slug="${CariUI.esc(item.slug || '')}" onclick="CariRouter.navigate('${CariUI.esc(path)}')">
                <i class="${CariUI.esc(icon)}"></i>
                <span>${CariUI.esc(item.label)}</span>
            </button>`;
        }).join('');
    }

    function renderTopNav() {
        const nav = document.getElementById('topNav');
        if (!nav) return;
        const items = normalizeNav(navigation);

        nav.innerHTML = items.map(item => {
            const path = item.url || ('/' + (item.slug || ''));
            const icon = item.icon || 'lucide-circle';
            return `<a class="top-nav-item" href="${CariUI.esc(path)}" data-nav-slug="${CariUI.esc(item.slug || '')}" onclick="event.preventDefault(); CariRouter.navigate('${CariUI.esc(path)}')">
                <i class="${CariUI.esc(icon)}"></i>
                <span>${CariUI.esc(item.label)}</span>
            </a>`;
        }).join('');
    }

    function normalizeNav(items) {
        if (!items || !items.length) {
            return [
                { label: 'Home', icon: 'lucide-home', slug: '' },
                { label: 'Movies', icon: 'lucide-film', slug: 'movies' },
                { label: 'TV Shows', icon: 'lucide-clapperboard', slug: 'series' },
                { label: 'Live TV', icon: 'lucide-tv', slug: 'live' },
                { label: 'My List', icon: 'lucide-bookmark', slug: 'my-list' },
            ];
        }
        return items.map(item => {
            let slug = item.slug || item.page_slug || '';
            // Home page should navigate to root /
            if (item.page_type === 'home' || slug === 'home') slug = '';
            return {
                label: item.label || item.name || '',
                icon: item.icon || 'lucide-circle',
                slug,
                url: item.url || null,
                page_type: item.page_type || null,
            };
        });
    }

    function updateActiveNav(path) {
        const slug = path === '/' ? '' : path.replace(/^\//, '').split('/')[0].split('?')[0];

        document.querySelectorAll('.nav-item').forEach(el => {
            el.classList.toggle('active', el.dataset.navSlug === slug);
        });
        document.querySelectorAll('.mobile-tab').forEach(el => {
            el.classList.toggle('active', el.dataset.navSlug === slug);
        });
        document.querySelectorAll('.top-nav-item').forEach(el => {
            el.classList.toggle('active', el.dataset.navSlug === slug);
        });

        // Close mobile sidebar
        document.getElementById('appSidebar').classList.remove('open');
        document.getElementById('sidebarBackdrop').classList.remove('visible');
    }

    // ---- Routes ----

    function setupRoutes() {
        CariRouter.beforeEach((path) => {
            updateActiveNav(path);
            return true;
        });

        CariRouter.addRoute('/', pageHome);
        CariRouter.addRoute('/home', pageHome);
        CariRouter.addRoute('/movies', pageMovies);
        CariRouter.addRoute('/series', pageSeries);
        CariRouter.addRoute('/live', pageLive);
        CariRouter.addRoute('/search', pageSearch);
        CariRouter.addRoute('/my-list', pageWatchlist);
        CariRouter.addRoute('/watch/:type/:id', pageWatch);
        CariRouter.addRoute('/categories', pageCategories);
    }

    function content() {
        return document.getElementById('appContent');
    }

    // ---- PAGE: Home ----

    async function pageHome() {
        const el = content();
        el.innerHTML = CariUI.skeletonRow(6) + CariUI.skeletonRow(6) + CariUI.skeletonRow(6, 'backdrop');

        try {
            const res = await CariAPI.getLayout();
            const layout = res?.data;

            if (!layout || !layout.sections || !layout.sections.length) {
                el.innerHTML = '';
                await renderFallbackHome(el);
                return;
            }

            el.innerHTML = '';
            renderLayoutSections(el, layout.sections);
        } catch {
            el.innerHTML = '';
            await renderFallbackHome(el);
        }
    }

    async function renderFallbackHome(el) {
        // If no layout configured, build a home page from content API
        try {
            const [moviesRes, seriesRes, channelsRes] = await Promise.all([
                CariAPI.getMovies({ sort: 'latest', limit: 20 }),
                CariAPI.getSeries({ sort: 'latest', limit: 20 }),
                CariAPI.getChannels({ limit: 20 }),
            ]);

            const movies = moviesRes?.data || [];
            const series = seriesRes?.data || [];
            const channels = channelsRes?.data || [];

            // Featured hero from first few movies
            if (movies.length) {
                const hero = CariUI.renderHero(
                    movies.slice(0, 5),
                    (item) => CariRouter.navigate('/watch/movie/' + item.id),
                    (item) => CariUI.showDetail(item, (it) => CariRouter.navigate('/watch/movie/' + it.id))
                );
                el.appendChild(hero);
            }

            appendContentRow(el, 'Popular Movies', movies, 'poster', 'movie');
            appendContentRow(el, 'TV Shows', series, 'poster', 'series');
            appendContentRow(el, 'Live Channels', channels, 'channel', 'channel');
        } catch {
            el.innerHTML = CariUI.emptyState('lucide-home', 'Welcome', 'Content will appear here once configured.');
        }
    }

    /**
     * Flatten layout items — the API nests resolved content under item.content,
     * but renderers expect fields (title, backdrop_url, etc.) at the top level.
     */
    function flattenLayoutItems(rawItems) {
        return rawItems.map(item => {
            const content = item.content || {};
            return {
                ...content,
                content_type: item.content_type,
            };
        }).filter(item => item.id || item.title || item.image_url);
    }

    function renderLayoutSections(el, sections) {
        sections.forEach(section => {
            const type = section.section_type;
            const items = flattenLayoutItems(section.items || []);
            const settings = section.settings || {};
            const title = section.title || '';

            if (type === 'hero_slideshow' && items.length) {
                const hero = CariUI.renderHero(
                    items,
                    (item) => playContent(item),
                    (item) => CariUI.showDetail(item, playContent)
                );
                el.appendChild(hero);
            } else if (type === 'content_row') {
                const cardStyle = settings.card_style || 'poster';
                appendContentRow(el, title, items, cardStyle);
            } else if (type === 'channel_grid') {
                appendContentRow(el, title, items, 'channel');
            } else if (type === 'banner') {
                const banner = CariUI.renderBanner(settings);
                if (banner) el.appendChild(banner);
            } else if (type === 'spotlight' && items.length) {
                renderSpotlight(el, items[0]);
            } else if (type === 'text_divider') {
                const divider = CariUI.renderTextDivider(title);
                el.appendChild(divider);
            } else if (type === 'category_grid') {
                renderCategorySection(el, title);
            }
        });
    }

    function appendContentRow(el, title, items, cardStyle, forceType) {
        if (!items || !items.length) return;
        const row = CariUI.renderContentRow(title, items, cardStyle, (item) => {
            const type = forceType || item.content_type || 'movie';
            if (type === 'channel') {
                CariRouter.navigate('/watch/channel/' + item.id);
            } else {
                CariUI.showDetail(item, playContent);
            }
        });
        if (row) el.appendChild(row);
    }

    function renderSpotlight(el, item) {
        const section = document.createElement('div');
        section.className = 'content-section';

        const img = item.poster || item.poster_url || item.image_url || '';
        const title = item.title || item.name || '';
        const desc = item.description || item.synopsis || item.overview || '';
        const year = item.year || '';
        const rating = item.rating || item.vote_average || '';

        section.innerHTML = `
            <div class="spotlight">
                ${img ? '<img class="spotlight-img" src="' + CariUI.esc(img) + '" alt="" loading="lazy">' : ''}
                <div class="spotlight-info">
                    <h2 class="spotlight-title">${CariUI.esc(title)}</h2>
                    <div class="spotlight-meta">
                        ${year ? '<span>' + CariUI.esc(year) + '</span>' : ''}
                        ${rating ? '<span class="rating"><i class="lucide-star" style="font-size:.75rem"></i> ' + CariUI.esc(String(rating)) + '</span>' : ''}
                    </div>
                    ${desc ? '<p class="spotlight-desc">' + CariUI.esc(desc) + '</p>' : ''}
                    <div style="display:flex;gap:.75rem">
                        <button class="btn btn-play" onclick="CariApp.playContent(${JSON.stringify(item).replace(/"/g, '&quot;')})"><i class="lucide-play"></i> Play</button>
                    </div>
                </div>
            </div>
        `;
        el.appendChild(section);
    }

    async function renderCategorySection(el, title) {
        try {
            const res = await CariAPI.getCategories();
            const cats = res?.data || [];
            if (!cats.length) return;

            const section = document.createElement('div');
            section.className = 'content-section';
            section.innerHTML = `<div class="section-header"><h3 class="section-title">${CariUI.esc(title || 'Categories')}</h3></div>`;

            const grid = document.createElement('div');
            grid.className = 'category-grid';
            cats.forEach(cat => grid.appendChild(CariUI.categoryCard(cat, (c) => {
                CariRouter.navigate('/movies?category=' + c.id);
            })));

            section.appendChild(grid);
            el.appendChild(section);
        } catch {}
    }

    // ---- PAGE: Movies ----

    async function pageMovies() {
        const el = content();
        el.innerHTML = `
            <div class="page-hero"><h1 class="page-hero-title">Movies</h1><p class="page-hero-subtitle">Browse our collection</p></div>
            <div id="movieFilters" class="filter-bar"></div>
            <div id="movieGrid" class="content-grid">${CariUI.loading()}</div>
        `;

        // Load categories for filters
        try {
            const catRes = await CariAPI.getCategories({ type: 'vod' });
            const cats = catRes?.data || [];
            const filterBar = document.getElementById('movieFilters');
            filterBar.innerHTML = '<button class="filter-chip active" data-cat="">All</button>' +
                cats.map(c => `<button class="filter-chip" data-cat="${c.id}">${CariUI.esc(c.name)}</button>`).join('');

            filterBar.addEventListener('click', (e) => {
                const chip = e.target.closest('.filter-chip');
                if (!chip) return;
                filterBar.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                loadMovies(chip.dataset.cat);
            });
        } catch {}

        loadMovies();
    }

    async function loadMovies(categoryId) {
        const grid = document.getElementById('movieGrid');
        if (!grid) return;
        grid.innerHTML = CariUI.loading();

        try {
            const params = { limit: 60, sort: 'latest' };
            if (categoryId) params.category_id = categoryId;
            const res = await CariAPI.getMovies(params);
            const movies = res?.data || [];

            if (!movies.length) {
                grid.innerHTML = CariUI.emptyState('lucide-film', 'No Movies', 'No movies found in this category.');
                return;
            }

            grid.innerHTML = '';
            movies.forEach(m => {
                grid.appendChild(CariUI.posterCard(m, (item) => {
                    CariUI.showDetail(item, playContent);
                }));
            });
        } catch {
            grid.innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load movies.');
        }
    }

    // ---- PAGE: Series ----

    async function pageSeries() {
        const el = content();
        el.innerHTML = `
            <div class="page-hero"><h1 class="page-hero-title">TV Shows</h1><p class="page-hero-subtitle">Discover series to binge</p></div>
            <div id="seriesGrid" class="content-grid">${CariUI.loading()}</div>
        `;

        try {
            const res = await CariAPI.getSeries({ limit: 60, sort: 'latest' });
            const series = res?.data || [];
            const grid = document.getElementById('seriesGrid');

            if (!series.length) {
                grid.innerHTML = CariUI.emptyState('lucide-clapperboard', 'No TV Shows', 'No series available yet.');
                return;
            }

            grid.innerHTML = '';
            series.forEach(s => {
                grid.appendChild(CariUI.posterCard(s, (item) => {
                    CariUI.showDetail(item, playContent);
                }));
            });
        } catch {
            document.getElementById('seriesGrid').innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load series.');
        }
    }

    // ---- PAGE: Live TV ----

    async function pageLive() {
        const el = content();
        el.innerHTML = `
            <div class="live-layout">
                <div class="live-player-area">
                    <div class="live-player-container" id="livePlayerContainer">
                        <video id="liveVideo" autoplay></video>
                    </div>
                    <div class="live-channel-info">
                        <div class="live-badge">Live</div>
                        <h3 class="live-channel-title" id="liveChannelTitle">Select a channel</h3>
                        <p class="live-channel-program" id="liveChannelProgram">Choose from the list to start watching</p>
                    </div>
                </div>
                <div class="live-channel-list">
                    <div class="live-channel-list-header">Channels</div>
                    <div id="liveChannelItems">${CariUI.loading()}</div>
                </div>
            </div>
        `;

        try {
            const res = await CariAPI.getChannels({ limit: 200 });
            const channels = res?.data || [];
            const list = document.getElementById('liveChannelItems');

            if (!channels.length) {
                list.innerHTML = CariUI.emptyState('lucide-tv', 'No Channels', 'No live channels available.');
                return;
            }

            list.innerHTML = '';
            channels.forEach(ch => {
                const item = document.createElement('div');
                item.className = 'live-channel-item';
                item.dataset.id = ch.id;
                item.innerHTML = `
                    <img src="${CariUI.esc(ch.logo_url || ch.logo || '')}" alt="" onerror="this.style.display='none'">
                    <div class="live-channel-item-info">
                        <div class="live-channel-item-name">${CariUI.esc(ch.name)}</div>
                        <div class="live-channel-item-program">${CariUI.esc(ch.category_name || '')}</div>
                    </div>
                `;
                item.addEventListener('click', () => playLiveChannel(ch, channels));
                list.appendChild(item);
            });

            // Auto-play first channel
            if (channels[0]) playLiveChannel(channels[0], channels);
        } catch {
            document.getElementById('liveChannelItems').innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load channels.');
        }
    }

    async function playLiveChannel(channel, allChannels) {
        document.getElementById('liveChannelTitle').textContent = channel.name || '';
        document.getElementById('liveChannelProgram').textContent = channel.category_name || 'Live';

        // Update active state
        document.querySelectorAll('.live-channel-item').forEach(el => {
            el.classList.toggle('active', el.dataset.id == channel.id);
        });

        // Play stream
        const video = document.getElementById('liveVideo');
        const url = channel.stream_url || '';

        if (url && window.shaka) {
            await initShakaPlayer(video, url);
        } else if (url) {
            video.src = url;
            video.play().catch(() => {});
        }
    }

    // ---- PAGE: Search ----

    async function pageSearch() {
        const el = content();
        const params = new URLSearchParams(window.location.search);
        const q = params.get('q') || '';

        document.getElementById('searchInput').value = q;

        if (!q || q.length < 2) {
            el.innerHTML = `<div class="search-page">${CariUI.emptyState('lucide-search', 'Search', 'Enter at least 2 characters to search.')}</div>`;
            return;
        }

        el.innerHTML = `<div class="search-page"><div class="search-results-title">Searching for "${CariUI.esc(q)}"...</div><div id="searchResults">${CariUI.loading()}</div></div>`;

        try {
            const res = await CariAPI.search(q);
            const results = res?.data || [];
            const container = document.getElementById('searchResults');

            document.querySelector('.search-results-title').textContent = `Results for "${q}" (${results.length})`;

            if (!results.length) {
                container.innerHTML = CariUI.emptyState('lucide-search-x', 'No Results', 'Try a different search term.');
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'content-grid';
            results.forEach(item => {
                grid.appendChild(CariUI.posterCard(item, (it) => {
                    CariUI.showDetail(it, playContent);
                }));
            });
            container.innerHTML = '';
            container.appendChild(grid);
        } catch {
            document.getElementById('searchResults').innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Search failed. Please try again.');
        }
    }

    // ---- PAGE: Watchlist ----

    async function pageWatchlist() {
        const el = content();
        el.innerHTML = `
            <div class="page-hero"><h1 class="page-hero-title">My List</h1><p class="page-hero-subtitle">Your saved content</p></div>
            <div id="watchlistGrid" class="content-grid">${CariUI.loading()}</div>
        `;

        try {
            const res = await CariAPI.getWatchlist();
            const items = res?.data || [];
            const grid = document.getElementById('watchlistGrid');

            if (!items.length) {
                grid.innerHTML = CariUI.emptyState('lucide-bookmark', 'Empty Watchlist', 'Add content to your list to watch later.');
                return;
            }

            grid.innerHTML = '';
            // Resolve content items
            for (const item of items) {
                try {
                    let detail;
                    if (item.content_type === 'movie') {
                        detail = (await CariAPI.getMovie(item.content_id))?.data;
                    } else if (item.content_type === 'series') {
                        detail = (await CariAPI.getSeriesDetail(item.content_id))?.data;
                    }
                    if (detail) {
                        detail.content_type = item.content_type;
                        grid.appendChild(CariUI.posterCard(detail, (it) => CariUI.showDetail(it, playContent)));
                    }
                } catch {}
            }

            if (!grid.children.length) {
                grid.innerHTML = CariUI.emptyState('lucide-bookmark', 'Empty Watchlist', 'Add content to your list to watch later.');
            }
        } catch {
            document.getElementById('watchlistGrid').innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load watchlist.');
        }
    }

    // ---- PAGE: Categories ----

    async function pageCategories() {
        const el = content();
        el.innerHTML = `
            <div class="page-hero"><h1 class="page-hero-title">Categories</h1></div>
            <div class="content-section"><div id="categoryGrid" class="category-grid">${CariUI.loading()}</div></div>
        `;

        try {
            const res = await CariAPI.getCategories();
            const cats = res?.data || [];
            const grid = document.getElementById('categoryGrid');
            grid.innerHTML = '';

            cats.forEach(cat => {
                grid.appendChild(CariUI.categoryCard(cat, (c) => {
                    CariRouter.navigate('/movies?category=' + c.id);
                }));
            });
        } catch {
            document.getElementById('categoryGrid').innerHTML = CariUI.emptyState('lucide-folder', 'No Categories', 'No categories available.');
        }
    }

    // ---- PAGE: Watch (Player) ----

    async function pageWatch(params) {
        const el = content();
        const type = params.type;
        const id = params.id;

        el.innerHTML = `
            <div class="player-page">
                <div class="player-container" id="playerContainer">
                    <video id="mainVideo" autoplay controls></video>
                </div>
                <div class="player-details" id="playerDetails">${CariUI.loading()}</div>
            </div>
        `;

        try {
            let item;
            if (type === 'channel') {
                const res = await CariAPI.getChannel(id);
                item = res?.data;
            } else if (type === 'series') {
                const res = await CariAPI.getSeriesDetail(id);
                item = res?.data;
            } else {
                const res = await CariAPI.getMovie(id);
                item = res?.data;
            }

            if (!item) {
                document.getElementById('playerDetails').innerHTML = CariUI.emptyState('lucide-alert-circle', 'Not Found', 'Content not found.');
                return;
            }

            // Play the stream
            const streamUrl = item.stream_url || item.video_url || '';
            const video = document.getElementById('mainVideo');

            if (streamUrl && window.shaka) {
                await initShakaPlayer(video, streamUrl);
            } else if (streamUrl) {
                video.src = streamUrl;
                video.play().catch(() => {});
            }

            // Track progress for VOD
            if (type === 'movie' || type === 'series') {
                setupProgressTracking(video, type === 'series' ? 'episode' : 'movie', item.id);
            }

            // Render details
            const details = document.getElementById('playerDetails');
            details.innerHTML = `
                <h2 class="player-details-title">${CariUI.esc(item.title || item.name)}</h2>
                <div class="player-details-meta">
                    ${item.year ? '<span>' + CariUI.esc(item.year) + '</span>' : ''}
                    ${item.runtime ? '<span>' + CariUI.esc(item.runtime) + ' min</span>' : ''}
                    ${item.vote_average ? '<span class="rating"><i class="lucide-star" style="font-size:.75rem"></i> ' + CariUI.esc(String(item.vote_average)) + '</span>' : ''}
                </div>
                <p class="player-details-desc">${CariUI.esc(item.description || item.synopsis || item.overview || '')}</p>
            `;
        } catch (err) {
            document.getElementById('playerDetails').innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load content.');
        }
    }

    // ---- Shaka Player ----

    async function initShakaPlayer(videoEl, url) {
        // Destroy existing instance
        if (shakaPlayer) {
            try { await shakaPlayer.destroy(); } catch {}
            shakaPlayer = null;
        }

        if (!window.shaka) {
            videoEl.src = url;
            videoEl.play().catch(() => {});
            return;
        }

        shaka.polyfill.installAll();

        if (!shaka.Player.isBrowserSupported()) {
            videoEl.src = url;
            videoEl.play().catch(() => {});
            return;
        }

        shakaPlayer = new shaka.Player();
        await shakaPlayer.attach(videoEl);

        shakaPlayer.configure({
            streaming: {
                bufferingGoal: 30,
                rebufferingGoal: 2,
                retryParameters: { maxAttempts: 3, baseDelay: 1000 },
            },
        });

        shakaPlayer.addEventListener('error', (e) => {
            console.error('Shaka error:', e.detail);
        });

        try {
            await shakaPlayer.load(url);
            videoEl.play().catch(() => {});
        } catch (err) {
            console.error('Shaka load error:', err);
            // Fallback to direct source
            try { await shakaPlayer.destroy(); } catch {}
            shakaPlayer = null;
            videoEl.src = url;
            videoEl.play().catch(() => {});
        }
    }

    function setupProgressTracking(video, contentType, contentId) {
        let lastSaved = 0;
        video.addEventListener('timeupdate', () => {
            const now = Math.floor(video.currentTime);
            // Save every 10 seconds
            if (now > 0 && now - lastSaved >= 10) {
                lastSaved = now;
                CariAPI.updateWatchProgress(contentType, contentId, now, Math.floor(video.duration || 0)).catch(() => {});
            }
        });
    }

    // ---- Helpers ----

    function playContent(item) {
        if (!item || !item.id) return;
        const type = item.content_type || (item.stream_url && !item.runtime ? 'channel' : 'movie');
        CariRouter.navigate('/watch/' + type + '/' + item.id);
    }

    // ---- Init on DOM ready ----

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { playContent };
})();
