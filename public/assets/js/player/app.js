/**
 * CARI-IPTV Web Player App
 * Main application logic — boots the SPA, renders pages
 */
const CariApp = (function() {
    let appConfig = null;
    let navigation = [];
    let navStyle = 'sidebar';
    let navSettings = {};
    let pageLayouts = {}; // page_type → layout_id map from navigation
    let pages = [];
    let shakaPlayer = null;
    let searchTimeout = null;
    let lastManifest = null;
    let manifestTimer = null;

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
        startManifestPolling();
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

        // Build page_type → layout_id map from navigation items
        pageLayouts = {};
        (navigation || []).forEach(item => {
            const pt = item.page_type;
            const lid = item.layout_id;
            if (pt && lid) pageLayouts[pt] = lid;
        });

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

    // ---- Manifest Polling (detect admin changes at runtime) ----

    function startManifestPolling() {
        // Fetch initial manifest to establish baseline versions
        checkForUpdates();
        // Poll every 60 seconds for changes
        manifestTimer = setInterval(checkForUpdates, 60000);
    }

    async function checkForUpdates() {
        try {
            const res = await CariAPI.getManifest();
            const manifest = res?.data;
            if (!manifest) return;

            // First call — just store baseline, no comparisons
            if (!lastManifest) {
                lastManifest = manifest;
                return;
            }

            let hasChanges = false;
            let navChanged = false;

            // Compare navigation version (covers nav items, pages, page-layout links)
            const oldNav = lastManifest.navigation?.web?.version;
            const newNav = manifest.navigation?.web?.version;
            if (oldNav && newNav && oldNav !== newNav) {
                navChanged = true;
                hasChanges = true;
            }

            // Compare layout versions (covers all published layouts for this platform)
            const oldLayout = lastManifest.layouts?.web?.version;
            const newLayout = manifest.layouts?.web?.version;
            if (oldLayout && newLayout && oldLayout !== newLayout) {
                hasChanges = true;
            }

            // Compare content versions (movies, series, channels, categories)
            for (const key of ['movies', 'series', 'channels', 'categories']) {
                const oldVer = lastManifest[key]?.version;
                const newVer = manifest[key]?.version;
                if (oldVer && newVer && oldVer !== newVer) {
                    hasChanges = true;
                }
            }

            // Store new baseline
            lastManifest = manifest;

            if (hasChanges) {
                console.log('[CariApp] Backend changes detected, refreshing data...');

                // Bust API cache so all subsequent fetches bypass browser cache
                CariAPI.bustAllCaches();

                // Navigation changed — reload nav immediately (non-disruptive)
                if (navChanged) {
                    await loadNavigation();
                }

                // Re-render current page to show fresh content,
                // but only if user is not watching a video (that would be disruptive)
                const path = CariRouter.getCurrentPath();
                if (!path.startsWith('/watch/')) {
                    CariRouter.refresh();
                }
            }
        } catch (err) {
            // Silent — don't disrupt user experience on network errors
        }
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
        CariRouter.addRoute('/series/:id', pageSeriesDetail);
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
            // Use the layout linked to the home page, or fall back to default
            const layoutId = pageLayouts.home || null;
            const res = await CariAPI.getLayout(layoutId);
            const layout = res?.data;

            if (!layout || !layout.sections || !layout.sections.length) {
                el.innerHTML = '';
                await renderFallbackHome(el);
                return;
            }

            el.innerHTML = '';
            renderLayoutSections(el, layout.sections);
        } catch (err) {
            console.error('[CariApp] Home layout failed:', err);
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
                content_id: item.content_id,
            };
        });
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

        // Check if a layout is configured for the movies page
        const layoutId = pageLayouts.movies || null;
        if (layoutId) {
            el.innerHTML = CariUI.skeletonRow(6) + CariUI.skeletonRow(6) + CariUI.skeletonRow(6, 'backdrop');
            try {
                const res = await CariAPI.getLayout(layoutId);
                const layout = res?.data;
                if (layout && layout.sections && layout.sections.length) {
                    el.innerHTML = '';
                    renderLayoutSections(el, layout.sections);
                    return;
                }
            } catch (err) {
                console.error('[CariApp] Movies layout failed:', err);
            }
        }

        // Fallback: default movies grid with filters
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

        // Check if a layout is configured for the series page
        const layoutId = pageLayouts.series || null;
        if (layoutId) {
            el.innerHTML = CariUI.skeletonRow(6) + CariUI.skeletonRow(6) + CariUI.skeletonRow(6, 'backdrop');
            try {
                const res = await CariAPI.getLayout(layoutId);
                const layout = res?.data;
                if (layout && layout.sections && layout.sections.length) {
                    el.innerHTML = '';
                    renderLayoutSections(el, layout.sections);
                    return;
                }
            } catch (err) {
                console.error('[CariApp] Series layout failed:', err);
            }
        }

        // Fallback: default series grid
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

    // ---- PAGE: Series Detail ----

    async function pageSeriesDetail(params) {
        const el = content();
        const id = params.id;
        el.innerHTML = CariUI.loading();

        try {
            const res = await CariAPI.getSeriesDetail(id);
            const show = res?.data;

            if (!show) {
                el.innerHTML = CariUI.emptyState('lucide-alert-circle', 'Not Found', 'Series not found.');
                return;
            }

            const backdrop = show.backdrop_url || '';
            const poster = show.poster_url || '';
            const title = show.title || show.name || '';
            const year = show.year || '';
            const rating = show.vote_average || '';
            const genres = show.genres || '';
            const desc = show.synopsis || show.overview || '';
            const seasons = show.seasons || [];
            const trailers = show.trailers || [];
            const numSeasons = show.number_of_seasons || seasons.length || '';
            const numEpisodes = show.number_of_episodes || '';

            el.innerHTML = `
                <div class="series-detail">
                    <div class="series-hero">
                        ${backdrop ? '<img class="series-hero-backdrop" src="' + CariUI.esc(backdrop) + '" alt="" onerror="this.style.display=\'none\'">' : ''}
                        <div class="series-hero-gradient"></div>
                        <div class="series-hero-content">
                            ${poster ? '<img class="series-hero-poster" src="' + CariUI.esc(poster) + '" alt="" onerror="this.style.display=\'none\'">' : ''}
                            <div class="series-hero-info">
                                <h1 class="series-hero-title">${CariUI.esc(title)}</h1>
                                <div class="series-hero-meta">
                                    ${year ? '<span>' + CariUI.esc(String(year)) + '</span>' : ''}
                                    ${numSeasons ? '<span>' + CariUI.esc(String(numSeasons)) + ' Season' + (numSeasons > 1 ? 's' : '') + '</span>' : ''}
                                    ${numEpisodes ? '<span>' + CariUI.esc(String(numEpisodes)) + ' Episodes</span>' : ''}
                                    ${rating ? '<span class="rating"><i class="lucide-star" style="font-size:.75rem"></i> ' + CariUI.esc(String(rating)) + '</span>' : ''}
                                    ${genres ? '<span>' + CariUI.esc(genres) + '</span>' : ''}
                                </div>
                                ${desc ? '<p class="series-hero-desc">' + CariUI.esc(desc) + '</p>' : ''}
                                <div class="series-hero-actions">
                                    ${trailers.length ? '<button class="btn btn-secondary" id="seriesTrailerBtn"><i class="lucide-clapperboard"></i> Watch Trailer</button>' : ''}
                                    <button class="btn btn-icon" id="seriesWatchlist" title="Add to Watchlist"><i class="lucide-plus"></i></button>
                                </div>
                                <div class="trailer-embed" id="seriesTrailerEmbed" style="display:none"></div>
                            </div>
                        </div>
                    </div>

                    ${seasons.length ? `
                    <div class="series-seasons">
                        <div class="season-tabs" id="seasonTabs">
                            ${seasons.map((s, i) => `<button class="season-tab${i === 0 ? ' active' : ''}" data-season-idx="${i}">${CariUI.esc(s.name || 'Season ' + s.season_number)}</button>`).join('')}
                        </div>
                        <div class="episode-list" id="episodeList"></div>
                    </div>
                    ` : '<div class="empty-state"><i class="lucide-tv"></i><h3>No Seasons</h3><p>No episodes available yet.</p></div>'}
                </div>
            `;

            // Trailer toggle
            if (trailers.length) {
                const tBtn = document.getElementById('seriesTrailerBtn');
                if (tBtn) {
                    let trailerOpen = false;
                    tBtn.addEventListener('click', () => {
                        const embed = document.getElementById('seriesTrailerEmbed');
                        if (!embed) return;
                        trailerOpen = !trailerOpen;
                        if (trailerOpen) {
                            const t = trailers[0];
                            const key = t.video_key || '';
                            if (key) {
                                embed.innerHTML = `<iframe src="https://www.youtube.com/embed/${CariUI.esc(key)}?autoplay=1&rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
                            } else if (t.url) {
                                embed.innerHTML = `<iframe src="${CariUI.esc(t.url)}" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
                            }
                            embed.style.display = '';
                            tBtn.innerHTML = '<i class="lucide-x"></i> Close Trailer';
                        } else {
                            embed.innerHTML = '';
                            embed.style.display = 'none';
                            tBtn.innerHTML = '<i class="lucide-clapperboard"></i> Watch Trailer';
                        }
                    });
                }
            }

            // Watchlist toggle
            const wBtn = document.getElementById('seriesWatchlist');
            if (wBtn) {
                wBtn.addEventListener('click', async () => {
                    try {
                        const r = await CariAPI.toggleWatchlist('series', show.id);
                        wBtn.querySelector('i').className = r?.data?.in_watchlist ? 'lucide-check' : 'lucide-plus';
                    } catch {}
                });
            }

            // Render seasons/episodes
            if (seasons.length) {
                renderEpisodes(seasons[0]);

                document.getElementById('seasonTabs').addEventListener('click', (e) => {
                    const tab = e.target.closest('.season-tab');
                    if (!tab) return;
                    document.querySelectorAll('.season-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    const idx = parseInt(tab.dataset.seasonIdx);
                    renderEpisodes(seasons[idx]);
                });
            }
        } catch (err) {
            console.error('[CariApp] Series detail failed:', err);
            el.innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load series.');
        }
    }

    function renderEpisodes(season) {
        const list = document.getElementById('episodeList');
        if (!list) return;

        const episodes = season.episodes || [];
        if (!episodes.length) {
            list.innerHTML = '<div class="empty-state"><i class="lucide-film"></i><h3>No Episodes</h3><p>No episodes in this season yet.</p></div>';
            return;
        }

        list.innerHTML = episodes.map(ep => {
            const thumb = ep.still_url || '';
            const epTitle = ep.title || ep.name || 'Episode ' + ep.episode_number;
            const epNum = ep.episode_number || '';
            const epDesc = ep.synopsis || ep.overview || '';
            const runtime = ep.runtime || '';
            const rating = ep.vote_average || '';
            const hasStream = !!(ep.stream_url);

            return `
                <div class="episode-card${hasStream ? ' playable' : ''}" data-episode-id="${ep.id}" data-stream="${CariUI.esc(ep.stream_url || '')}">
                    <div class="episode-thumb">
                        ${thumb ? '<img src="' + CariUI.esc(thumb) + '" alt="" loading="lazy" onerror="this.style.display=\'none\'">' : ''}
                        ${hasStream ? '<div class="episode-play-overlay"><i class="lucide-play"></i></div>' : ''}
                        <span class="episode-number">E${CariUI.esc(String(epNum))}</span>
                    </div>
                    <div class="episode-info">
                        <div class="episode-title">${CariUI.esc(epTitle)}</div>
                        <div class="episode-meta">
                            ${runtime ? '<span>' + CariUI.esc(String(runtime)) + ' min</span>' : ''}
                            ${rating ? '<span class="rating"><i class="lucide-star" style="font-size:.65rem"></i> ' + CariUI.esc(String(rating)) + '</span>' : ''}
                        </div>
                        ${epDesc ? '<div class="episode-desc">' + CariUI.esc(epDesc) + '</div>' : ''}
                    </div>
                </div>
            `;
        }).join('');

        // Attach play handlers to playable episodes
        list.querySelectorAll('.episode-card.playable').forEach(card => {
            card.addEventListener('click', () => {
                const epId = card.dataset.episodeId;
                CariRouter.navigate('/watch/episode/' + epId);
            });
        });
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
            let displayTitle = '';
            let displayMeta = '';
            if (type === 'channel') {
                const res = await CariAPI.getChannel(id);
                item = res?.data;
            } else if (type === 'episode') {
                const res = await CariAPI.getEpisode(id);
                item = res?.data;
                if (item) {
                    displayTitle = item.series_title + ' — ' + (item.title || 'Episode ' + item.episode_number);
                    displayMeta = 'S' + (item.season_number || '') + ' E' + (item.episode_number || '');
                }
            } else if (type === 'series') {
                // Redirect to series detail page instead of playing directly
                CariRouter.navigate('/series/' + id);
                return;
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
            if (type === 'movie' || type === 'episode') {
                setupProgressTracking(video, type === 'episode' ? 'episode' : 'movie', item.id);
            }

            // Render details
            const title = displayTitle || item.title || item.name || '';
            const details = document.getElementById('playerDetails');
            details.innerHTML = `
                <h2 class="player-details-title">${CariUI.esc(title)}</h2>
                <div class="player-details-meta">
                    ${displayMeta ? '<span>' + CariUI.esc(displayMeta) + '</span>' : ''}
                    ${item.year ? '<span>' + CariUI.esc(item.year) + '</span>' : ''}
                    ${item.runtime ? '<span>' + CariUI.esc(item.runtime) + ' min</span>' : ''}
                    ${item.vote_average ? '<span class="rating"><i class="lucide-star" style="font-size:.75rem"></i> ' + CariUI.esc(String(item.vote_average)) + '</span>' : ''}
                </div>
                <p class="player-details-desc">${CariUI.esc(item.description || item.synopsis || item.overview || '')}</p>
                ${type === 'episode' && item.series_id ? '<button class="btn btn-secondary" id="backToSeries" style="margin-top:1rem"><i class="lucide-arrow-left"></i> Back to Series</button>' : ''}
            `;

            // Back to series link
            if (type === 'episode' && item.series_id) {
                document.getElementById('backToSeries')?.addEventListener('click', () => {
                    CariRouter.navigate('/series/' + item.series_id);
                });
            }
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
