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
    let lastManifest = null;
    let manifestTimer = null;
    let entitlements = null; // { movies: [], series: [], channels: [], packages: [] }

    // ---- Boot ----

    async function init() {
        // Auth gate
        if (!CariAPI.isAuthenticated()) {
            window.location.href = '/login';
            return;
        }

        setupUser();
        setupSidebar();
        setupRoutes();
        await Promise.all([loadNavigation(), loadEntitlements()]);

        CariRouter.start();
        startManifestPolling();
    }

    async function loadEntitlements() {
        try {
            const res = await CariAPI.getEntitlements();
            entitlements = res?.data || { movies: [], series: [], channels: [], packages: [] };
        } catch {
            entitlements = { movies: [], series: [], channels: [], packages: [] };
        }
    }

    /**
     * Check if content is locked (user doesn't have access)
     * - No active subscription: ALL content is locked
     * - Has subscription: only entitled content is unlocked
     */
    function isContentLocked(item) {
        if (!entitlements) return false;
        if (!item || !item.id) return false;

        // If subscriber has no active subscriptions, all content is locked
        if (!entitlements.has_subscription) {
            return true;
        }

        const type = item.content_type || 'movie';
        const id = item.id;

        // If content is marked as restricted (in a content group), check entitlements
        if (item.is_restricted) {
            const entitled = entitlements[type + 's'] || entitlements[type] || [];
            return !entitled.includes(id);
        }

        // Content not in any content group = accessible to any subscriber with a package
        return false;
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
        // Poll every 30 seconds for changes
        manifestTimer = setInterval(checkForUpdates, 30000);
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
                // but not if user is watching video or live TV (that would be disruptive)
                const path = CariRouter.getCurrentPath();
                if (path.startsWith('/live')) {
                    // Soft-refresh: update EPG/channel data without disrupting playback
                    refreshLiveData();
                } else if (!path.startsWith('/watch/')) {
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
            // Bust API cache on every navigation so content is always fresh
            CariAPI.bustAllCaches();
            updateActiveNav(path);
            // Clean up live TV timer when navigating away
            if (liveEpgTimerInterval) {
                clearInterval(liveEpgTimerInterval);
                liveEpgTimerInterval = null;
            }
            return true;
        });

        CariRouter.addRoute('/', pageHome);
        CariRouter.addRoute('/home', pageHome);
        CariRouter.addRoute('/movies', pageMovies);
        CariRouter.addRoute('/series', pageSeries);
        CariRouter.addRoute('/live', pageLive);
        CariRouter.addRoute('/live/:channelId', pageLive);
        CariRouter.addRoute('/search', pageSearch);
        CariRouter.addRoute('/my-list', pageWatchlist);
        CariRouter.addRoute('/series/:id', pageSeriesDetail);
        CariRouter.addRoute('/watch/:type/:id', pageWatch);
        CariRouter.addRoute('/categories', pageCategories);
        CariRouter.addRoute('/person/:id', pagePerson);
        CariRouter.addRoute('/subscribe', pageSubscribe);
        CariRouter.addRoute('/profile', pageProfile);
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
                    (item) => playContent(item),
                    (item) => CariUI.showDetail(item, playContent, isContentLocked(item)),
                    isContentLocked
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
                    (item) => CariUI.showDetail(item, playContent, isContentLocked(item)),
                    isContentLocked
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
            } else if (type === 'packages_list') {
                renderPackagesSection(el, title, settings);
            }
        });
    }

    function appendContentRow(el, title, items, cardStyle, forceType) {
        if (!items || !items.length) return;

        const section = document.createElement('div');
        section.className = 'content-section';

        const header = document.createElement('div');
        header.className = 'section-header';
        header.innerHTML = `<h3 class="section-title">${CariUI.esc(title)}</h3>`;
        section.appendChild(header);

        const row = document.createElement('div');
        row.className = 'content-row';

        items.forEach(item => {
            const type = forceType || item.content_type || 'movie';
            const locked = isContentLocked(item);
            let card;

            if (cardStyle === 'backdrop') {
                card = CariUI.backdropCard(item, handleItemClick(item, type), locked);
            } else if (cardStyle === 'channel') {
                card = CariUI.channelCard(item, handleItemClick(item, type), locked);
            } else {
                card = CariUI.posterCard(item, handleItemClick(item, type), locked);
            }
            row.appendChild(card);
        });

        section.appendChild(CariUI.wrapWithScrollNav(row));
        el.appendChild(section);
    }

    function handleItemClick(item, type) {
        return () => {
            if (type === 'channel') {
                if (isContentLocked(item)) {
                    CariRouter.navigate('/subscribe');
                } else {
                    CariRouter.navigate('/live/' + item.id);
                }
            } else {
                CariUI.showDetail(item, playContent, isContentLocked(item));
            }
        };
    }

    function renderSpotlight(el, item) {
        const section = document.createElement('div');
        section.className = 'content-section';
        const locked = isContentLocked(item);

        const img = item.poster || item.poster_url || item.image_url || '';
        const title = item.title || item.name || '';
        const desc = item.description || item.synopsis || item.overview || '';
        const year = item.year || '';
        const rating = item.rating || item.vote_average || '';

        section.innerHTML = `
            <div class="spotlight">
                ${img ? '<img class="spotlight-img" src="' + CariUI.esc(img) + '" alt="" loading="lazy">' : ''}
                <div class="spotlight-info">
                    ${locked ? '<div class="spotlight-lock-badge"><i class="lucide-lock"></i> Premium</div>' : ''}
                    <h2 class="spotlight-title">${CariUI.esc(title)}</h2>
                    <div class="spotlight-meta">
                        ${year ? '<span>' + CariUI.esc(year) + '</span>' : ''}
                        ${rating ? '<span class="rating"><i class="lucide-star" style="font-size:.75rem"></i> ' + CariUI.esc(String(rating)) + '</span>' : ''}
                    </div>
                    ${desc ? '<p class="spotlight-desc">' + CariUI.esc(desc) + '</p>' : ''}
                    <div style="display:flex;gap:.75rem">
                        ${locked
                            ? '<button class="btn btn-subscribe" data-action="subscribe"><i class="lucide-credit-card"></i> Subscribe</button>'
                            : '<button class="btn btn-play" data-action="play"><i class="lucide-play"></i> Play</button>'}
                    </div>
                </div>
            </div>
        `;

        if (locked) {
            section.querySelector('[data-action="subscribe"]').addEventListener('click', () => {
                CariRouter.navigate('/subscribe');
            });
        } else {
            section.querySelector('[data-action="play"]').addEventListener('click', () => {
                playContent(item);
            });
        }

        el.appendChild(section);
    }

    async function renderPackagesSection(el, title, settings) {
        const section = document.createElement('div');
        section.className = 'content-section packages-section';
        section.innerHTML = `
            <div class="section-header"><h3 class="section-title">${CariUI.esc(title || 'Choose Your Plan')}</h3></div>
            <div class="packages-grid" id="packagesGrid">${CariUI.loading()}</div>
        `;
        el.appendChild(section);

        // Fetch available packages from entitlements
        try {
            const packages = entitlements?.packages || [];
            const grid = section.querySelector('#packagesGrid');

            if (!packages.length) {
                grid.innerHTML = CariUI.emptyState('lucide-package', 'No Packages', 'No subscription packages available.');
                return;
            }

            grid.innerHTML = '';
            packages.forEach(pkg => {
                const card = document.createElement('div');
                card.className = 'package-card' + (pkg.is_featured ? ' featured' : '');
                card.innerHTML = `
                    ${pkg.is_featured ? '<div class="package-badge">Popular</div>' : ''}
                    <h3 class="package-name">${CariUI.esc(pkg.name)}</h3>
                    <div class="package-price">
                        <span class="price-amount">${CariUI.esc(pkg.price_display || '$' + (pkg.price || '0'))}</span>
                        <span class="price-period">/ ${CariUI.esc(pkg.billing_period || 'month')}</span>
                    </div>
                    ${pkg.description ? '<p class="package-desc">' + CariUI.esc(pkg.description) + '</p>' : ''}
                    ${pkg.features && pkg.features.length ? '<ul class="package-features">' + pkg.features.map(f => '<li><i class="lucide-check"></i> ' + CariUI.esc(f) + '</li>').join('') + '</ul>' : ''}
                    <button class="btn btn-subscribe package-btn" data-pkg-id="${pkg.id}">Subscribe</button>
                `;
                card.querySelector('.package-btn').addEventListener('click', () => {
                    // Navigate to checkout or show payment modal
                    CariRouter.navigate('/subscribe?package=' + pkg.id);
                });
                grid.appendChild(card);
            });
        } catch (err) {
            section.querySelector('#packagesGrid').innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load packages.');
        }
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
                    CariUI.showDetail(item, playContent, isContentLocked(item));
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
                    CariUI.showDetail(item, playContent, isContentLocked(item));
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

                    <div class="detail-cast" id="seriesCast"></div>

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

            // Render cast
            if (show.cast && show.cast.length) {
                CariUI.renderCastRow(document.getElementById('seriesCast'), show.cast, {
                    type: 'page',
                    path: '/series/' + id,
                    title: title
                });
            }

            // Render seasons/episodes
            if (seasons.length) {
                renderEpisodes(seasons[0], show.id);

                document.getElementById('seasonTabs').addEventListener('click', (e) => {
                    const tab = e.target.closest('.season-tab');
                    if (!tab) return;
                    document.querySelectorAll('.season-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    const idx = parseInt(tab.dataset.seasonIdx);
                    renderEpisodes(seasons[idx], show.id);
                });
            }
        } catch (err) {
            console.error('[CariApp] Series detail failed:', err);
            el.innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load series.');
        }
    }

    function renderEpisodes(season, seriesId) {
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
                <div class="episode-card" data-episode-idx="${ep.id}">
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

        // Build a lookup for episodes by id
        const epMap = {};
        episodes.forEach(ep => { epMap[ep.id] = ep; });

        // Make all episode cards clickable — open episode detail modal
        list.querySelectorAll('.episode-card').forEach(card => {
            card.style.cursor = 'pointer';
            card.addEventListener('click', () => {
                const epId = card.dataset.episodeIdx;
                const ep = epMap[epId];
                if (ep) showEpisodeDetail(ep, seriesId, season);
            });
        });
    }

    function showEpisodeDetail(ep, seriesId, season) {
        const overlay = document.getElementById('detailOverlay');
        const modal = document.getElementById('detailModal');

        const thumb = ep.still_url || '';
        const epTitle = ep.title || ep.name || 'Episode ' + ep.episode_number;
        const epNum = ep.episode_number || '';
        const epDesc = ep.synopsis || ep.overview || '';
        const runtime = ep.runtime || '';
        const rating = ep.vote_average || '';
        const airDate = ep.air_date || '';
        const hasStream = !!(ep.stream_url);
        const seasonName = season.name || 'Season ' + (season.season_number || '');

        modal.innerHTML = `
            <button class="detail-close" id="epModalClose"><i class="lucide-x"></i></button>
            ${thumb ? '<img class="detail-backdrop" src="' + CariUI.esc(thumb) + '" alt="" onerror="this.style.display=\'none\'">' : ''}
            <div class="detail-body">
                <div class="ep-modal-badge">${CariUI.esc(seasonName)} &middot; Episode ${CariUI.esc(String(epNum))}</div>
                <h2 class="detail-title">${CariUI.esc(epTitle)}</h2>
                <div class="detail-meta">
                    ${runtime ? '<span><i class="lucide-clock" style="font-size:.75rem"></i> ' + CariUI.esc(String(runtime)) + ' min</span>' : ''}
                    ${rating ? '<span class="rating"><i class="lucide-star" style="font-size:.75rem"></i> ' + CariUI.esc(String(rating)) + '</span>' : ''}
                    ${airDate ? '<span><i class="lucide-calendar" style="font-size:.75rem"></i> ' + CariUI.esc(airDate) + '</span>' : ''}
                </div>
                ${epDesc ? '<p class="detail-desc">' + CariUI.esc(epDesc) + '</p>' : '<p class="detail-desc" style="color:var(--p-text-muted)">No description available.</p>'}
                <div class="detail-actions">
                    ${hasStream
                        ? '<button class="btn btn-play" id="epModalPlay"><i class="lucide-play"></i> Play Episode</button>'
                        : '<div class="episode-no-stream"><i class="lucide-cloud-off"></i> Stream not available yet</div>'}
                    <button class="btn btn-info" id="epModalBack"><i class="lucide-arrow-left"></i> Back to Series</button>
                </div>
            </div>
        `;

        // Close button
        modal.querySelector('#epModalClose').addEventListener('click', () => {
            overlay.classList.remove('visible');
        });

        // Play button
        if (hasStream) {
            modal.querySelector('#epModalPlay').addEventListener('click', () => {
                overlay.classList.remove('visible');
                CariRouter.navigate('/watch/episode/' + ep.id);
            });
        }

        // Back to series button
        modal.querySelector('#epModalBack').addEventListener('click', () => {
            overlay.classList.remove('visible');
        });

        overlay.classList.add('visible');
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.classList.remove('visible');
        });
    }

    // ---- PAGE: Person (Cast Member Filmography) ----

    async function pagePerson(params) {
        const el = content();
        const personId = params.id;
        el.innerHTML = CariUI.loading();

        try {
            const res = await CariAPI.getPerson(personId);
            const person = res?.data;

            if (!person) {
                el.innerHTML = CariUI.emptyState('lucide-user-x', 'Not Found', 'Person not found.');
                return;
            }

            const img = person.profile_image || person.profile_url || '';
            const placeholderAvatar = 'data:image/svg+xml,' + encodeURIComponent(
                '<svg xmlns="http://www.w3.org/2000/svg" width="185" height="278" fill="%231e293b"><rect width="185" height="278"/><text x="92" y="150" text-anchor="middle" fill="%235a5f7a" font-family="sans-serif" font-size="48">?</text></svg>'
            );

            el.innerHTML = `
                <div class="person-page">
                    <div id="personBackNav"></div>
                    <div class="person-header">
                        <img class="person-photo" src="${img ? CariUI.esc(img) : placeholderAvatar}" alt="${CariUI.esc(person.name)}"
                             onerror="this.src='${placeholderAvatar}'">
                        <div class="person-info">
                            <h1 class="person-name">${CariUI.esc(person.name)}</h1>
                            <div class="person-stats">
                                ${person.movies.length ? '<span>' + person.movies.length + ' Movie' + (person.movies.length !== 1 ? 's' : '') + '</span>' : ''}
                                ${person.series.length ? '<span>' + person.series.length + ' TV Show' + (person.series.length !== 1 ? 's' : '') + '</span>' : ''}
                            </div>
                        </div>
                    </div>
                    <div id="personFilmography"></div>
                </div>
            `;

            // Add back navigation if user came from a detail modal or series page
            const castSource = CariUI.getCastNavSource();
            if (castSource) {
                const backNav = document.getElementById('personBackNav');
                const backBtn = document.createElement('button');
                backBtn.className = 'person-back';

                if (castSource.type === 'modal' && castSource.item) {
                    const itemTitle = castSource.item.title || castSource.item.name || 'Details';
                    backBtn.innerHTML = '<i class="lucide-arrow-left"></i> Back to ' + CariUI.esc(itemTitle);
                    backBtn.addEventListener('click', () => {
                        const sourceItem = castSource.item;
                        CariRouter.navigate(castSource.fromPath || '/');
                        CariUI.showDetail(sourceItem, playContent, isContentLocked(sourceItem));
                    });
                } else if (castSource.type === 'page' && castSource.path) {
                    backBtn.innerHTML = '<i class="lucide-arrow-left"></i> Back to ' + CariUI.esc(castSource.title || 'Previous Page');
                    backBtn.addEventListener('click', () => {
                        CariRouter.navigate(castSource.path);
                    });
                }

                backNav.appendChild(backBtn);
                CariUI.clearCastNavSource();
            }

            const filmEl = document.getElementById('personFilmography');

            if (person.movies.length) {
                appendContentRow(filmEl, 'Movies', person.movies.map(m => ({
                    ...m, content_type: 'movie'
                })), 'poster', 'movie');
            }

            if (person.series.length) {
                appendContentRow(filmEl, 'TV Shows', person.series.map(s => ({
                    ...s, content_type: 'series'
                })), 'poster', 'series');
            }

            if (!person.movies.length && !person.series.length) {
                filmEl.innerHTML = CariUI.emptyState('lucide-film', 'No Content', 'No movies or shows found for this person on our platform.');
            }
        } catch (err) {
            console.error('[CariApp] Person page failed:', err);
            el.innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load person details.');
        }
    }

    // ---- PAGE: Live TV ----

    // ---- Live TV State ----
    let liveChannels = [];
    let liveEpgData = {};      // channelId -> [{title, start_time, end_time, ...}]
    let liveCategories = [];
    let liveActiveCategory = 'all';
    let liveActiveChannelId = null;
    let liveEpgTimerInterval = null;
    const REMINDERS_KEY = 'cari_epg_reminders';
    const EPG_HOURS_VISIBLE = 8;  // wider window for scrolling past & future
    const EPG_PX_PER_MINUTE = 7;  // pixels per minute for EPG timeline

    /** Soft-refresh: re-fetch channels + EPG and re-render guide without disrupting video playback */
    async function refreshLiveData() {
        try {
            const [channelsRes, catRes, epgRes] = await Promise.all([
                CariAPI.getChannels({ limit: 200 }),
                CariAPI.getCategories({ type: 'live' }),
                CariAPI.getEpg()
            ]);

            liveChannels = channelsRes?.data || [];
            liveCategories = catRes?.data || [];
            const epgRaw = epgRes?.data || [];
            liveEpgData = buildEpgMap(liveChannels, epgRaw);

            // Re-render guide and category filters (leaves video player untouched)
            renderCategoryFilters();
            renderEpgGrid();

            // Update the now-playing info panel for the active channel
            if (liveActiveChannelId) {
                const active = liveChannels.find(ch => ch.id === liveActiveChannelId);
                if (active) {
                    const nowPlaying = getNowPlaying(active.id);
                    const progEl = document.getElementById('liveChannelProgram');
                    const timeEl = document.getElementById('liveInfoTime');
                    const descEl = document.getElementById('liveInfoDesc');
                    if (progEl) progEl.textContent = nowPlaying ? nowPlaying.title : 'Live';
                    if (timeEl) timeEl.textContent = nowPlaying ? formatTime(nowPlaying.start_time) + ' - ' + formatTime(nowPlaying.end_time) : '';
                    if (descEl) {
                        descEl.textContent = nowPlaying?.description || '';
                        descEl.style.display = nowPlaying?.description ? '' : 'none';
                    }

                    // If video is not playing (stream died, paused, etc.), restart it
                    const video = document.getElementById('liveVideo');
                    if (video && (video.paused || video.ended)) {
                        playLiveChannel(active);
                    }
                }
                updateFsOverlay();
            }

            console.log('[CariApp] Live TV data refreshed (soft)');
        } catch (err) {
            console.error('[CariApp] Live TV soft refresh failed:', err);
        }
    }

    function getLiveReminders() {
        try { return JSON.parse(localStorage.getItem(REMINDERS_KEY) || '{}'); } catch { return {}; }
    }
    function saveLiveReminder(programmeKey, data) {
        const r = getLiveReminders();
        r[programmeKey] = data;
        localStorage.setItem(REMINDERS_KEY, JSON.stringify(r));
    }
    function removeLiveReminder(programmeKey) {
        const r = getLiveReminders();
        delete r[programmeKey];
        localStorage.setItem(REMINDERS_KEY, JSON.stringify(r));
    }
    function hasLiveReminder(programmeKey) {
        return !!getLiveReminders()[programmeKey];
    }

    function generatePlaceholderEpg(channel) {
        const programmes = [];
        const now = new Date();
        const startHour = new Date(now);
        startHour.setHours(startHour.getHours() - 3, 0, 0, 0);
        for (let h = 0; h < 27; h++) {
            const start = new Date(startHour);
            start.setHours(start.getHours() + h);
            const end = new Date(start);
            end.setHours(end.getHours() + 1);
            programmes.push({
                id: 'placeholder_' + channel.id + '_' + h,
                channel_id: channel.id,
                title: (channel.name || 'Channel') + ' Content',
                description: 'Regular programming on ' + (channel.name || 'this channel'),
                start_time: start.toISOString(),
                end_time: end.toISOString(),
                category: channel.category_name || 'General',
                is_placeholder: true
            });
        }
        return programmes;
    }

    function buildEpgMap(channels, epgRaw) {
        const map = {};
        channels.forEach(ch => { map[ch.id] = []; });
        // API returns grouped format: [{channel_id, channel_name, programmes: [...]}, ...]
        // or flat format: [{channel_id, title, start_time, ...}, ...]
        epgRaw.forEach(item => {
            if (item.programmes && Array.isArray(item.programmes)) {
                // Grouped format
                const chId = item.channel_id;
                if (map[chId]) {
                    item.programmes.forEach(p => {
                        p.channel_id = chId;
                        map[chId].push(p);
                    });
                }
            } else if (map[item.channel_id]) {
                // Flat format
                map[item.channel_id].push(item);
            }
        });
        // Fill channels that have no EPG data with placeholders
        channels.forEach(ch => {
            if (!map[ch.id] || map[ch.id].length === 0) {
                map[ch.id] = generatePlaceholderEpg(ch);
            }
        });
        return map;
    }

    function getFilteredChannels() {
        if (liveActiveCategory === 'all') return liveChannels;
        return liveChannels.filter(ch => String(ch.category_id) === String(liveActiveCategory));
    }

    function formatTime(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    }

    function getNowPlaying(channelId) {
        const progs = liveEpgData[channelId] || [];
        const now = Date.now();
        return progs.find(p => new Date(p.start_time).getTime() <= now && new Date(p.end_time).getTime() > now);
    }

    async function pageLive(params) {
        const el = content();
        const preselectedId = params?.channelId;

        el.innerHTML = `
            <div class="live-page">
                <div class="live-top-section">
                    <div class="live-player-section">
                        <div class="live-player-container" id="livePlayerContainer">
                            <video id="liveVideo" autoplay></video>
                            <button class="live-player-fullscreen" id="liveFullscreenBtn" title="Fullscreen"><i class="lucide-maximize"></i></button>
                            <div class="fs-overlay" id="fsOverlay">
                                <div class="fs-overlay-top">
                                    <div class="fs-overlay-channel">
                                        <img class="fs-overlay-logo" id="fsOverlayLogo" src="" alt="" style="display:none">
                                        <div class="fs-overlay-channel-info">
                                            <h3 id="fsOverlayChannel">Channel</h3>
                                            <div class="live-badge">Live</div>
                                        </div>
                                    </div>
                                    <button class="fs-overlay-exit" id="fsOverlayExit" title="Exit fullscreen"><i class="lucide-minimize"></i></button>
                                </div>
                                <div class="fs-overlay-bottom">
                                    <div class="fs-overlay-now">
                                        <div class="fs-overlay-programme">
                                            <span class="fs-overlay-label">Now</span>
                                            <h4 id="fsOverlayTitle">Programme</h4>
                                            <p class="fs-overlay-time" id="fsOverlayTime"></p>
                                        </div>
                                        <div class="fs-overlay-progress" id="fsOverlayProgress">
                                            <div class="fs-overlay-progress-fill" id="fsOverlayProgressFill"></div>
                                        </div>
                                    </div>
                                    <div class="fs-overlay-next" id="fsOverlayNext"></div>
                                    <div class="fs-overlay-nav">
                                        <button class="fs-overlay-nav-btn" id="fsChPrev" title="Previous channel"><i class="lucide-chevron-up"></i></button>
                                        <span class="fs-overlay-nav-label" id="fsChNumber"></span>
                                        <button class="fs-overlay-nav-btn" id="fsChNext" title="Next channel"><i class="lucide-chevron-down"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="live-info-panel" id="liveInfoPanel">
                        <div class="live-info-channel">
                            <img class="live-info-logo" id="liveNowLogo" src="" alt="" style="display:none">
                            <div class="live-info-channel-name">
                                <h3 id="liveChannelTitle">Select a channel</h3>
                                <div class="live-badge">Live</div>
                            </div>
                        </div>
                        <div class="live-info-programme" id="liveInfoProgramme">
                            <div class="live-info-now" id="liveInfoNow">
                                <span class="live-info-label">Now Playing</span>
                                <h4 id="liveChannelProgram">Choose from the guide below</h4>
                                <p class="live-info-time" id="liveInfoTime"></p>
                                <div class="live-info-progress" id="liveInfoProgress" style="display:none">
                                    <div class="live-info-progress-fill" id="liveInfoProgressFill"></div>
                                </div>
                            </div>
                            <div class="live-info-desc" id="liveInfoDesc"></div>
                            <button class="live-info-more-btn" id="liveInfoMoreBtn" style="display:none"><i class="lucide-info"></i> More Info</button>
                            <div class="live-info-next" id="liveInfoNext"></div>
                        </div>
                    </div>
                </div>
                <div class="live-guide-section">
                    <div class="live-guide-header">
                        <h2 class="live-guide-title"><i class="lucide-tv"></i> TV Guide</h2>
                        <div class="live-category-filters" id="liveCategoryFilters">${CariUI.loading()}</div>
                        <div class="epg-nav-controls">
                            <button class="epg-nav-btn" id="epgNavPrev" title="Scroll back"><i class="lucide-chevron-left"></i></button>
                            <button class="epg-nav-btn epg-nav-now" id="epgNavNow" title="Jump to now">Now</button>
                            <button class="epg-nav-btn" id="epgNavNext" title="Scroll forward"><i class="lucide-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="epg-grid-container" id="epgGridContainer">
                        ${CariUI.loading()}
                    </div>
                </div>
            </div>
        `;

        try {
            const [channelsRes, catRes, epgRes] = await Promise.all([
                CariAPI.getChannels({ limit: 200 }),
                CariAPI.getCategories({ type: 'live' }),
                CariAPI.getEpg()
            ]);

            liveChannels = channelsRes?.data || [];
            liveCategories = catRes?.data || [];
            const epgRaw = epgRes?.data || [];
            liveEpgData = buildEpgMap(liveChannels, epgRaw);

            if (!liveChannels.length) {
                document.getElementById('epgGridContainer').innerHTML =
                    CariUI.emptyState('lucide-tv', 'No Channels', 'No live channels available.');
                return;
            }

            renderCategoryFilters();
            renderEpgGrid();
            setupEpgNavControls();
            setupFullscreenButton();

            // Auto-play preselected or first channel
            const startChannel = preselectedId
                ? liveChannels.find(ch => String(ch.id) === String(preselectedId))
                : liveChannels[0];
            if (startChannel) playLiveChannel(startChannel);

            // Update time indicator periodically
            if (liveEpgTimerInterval) clearInterval(liveEpgTimerInterval);
            liveEpgTimerInterval = setInterval(() => {
                updateEpgTimeIndicator();
                checkReminders();
            }, 30000);

        } catch (err) {
            document.getElementById('epgGridContainer').innerHTML =
                CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load live TV data.');
        }
    }

    function renderCategoryFilters() {
        const container = document.getElementById('liveCategoryFilters');
        if (!container) return;

        let html = `<button class="filter-chip ${liveActiveCategory === 'all' ? 'active' : ''}" data-cat="all">All</button>`;
        liveCategories.forEach(cat => {
            html += `<button class="filter-chip ${String(liveActiveCategory) === String(cat.id) ? 'active' : ''}" data-cat="${cat.id}">${CariUI.esc(cat.name)}</button>`;
        });
        container.innerHTML = html;

        container.querySelectorAll('.filter-chip').forEach(btn => {
            btn.addEventListener('click', () => {
                liveActiveCategory = btn.dataset.cat;
                renderCategoryFilters();
                renderEpgGrid();
            });
        });
    }

    function renderEpgGrid() {
        const container = document.getElementById('epgGridContainer');
        if (!container) return;

        const channels = getFilteredChannels();
        if (!channels.length) {
            container.innerHTML = CariUI.emptyState('lucide-tv', 'No Channels', 'No channels in this category.');
            return;
        }

        const now = new Date();
        // EPG window: start 2 hours ago, show EPG_HOURS_VISIBLE hours total
        const windowStart = new Date(now);
        windowStart.setHours(windowStart.getHours() - 2);
        windowStart.setMinutes(windowStart.getMinutes() < 30 ? 0 : 30, 0, 0);
        const windowEnd = new Date(windowStart);
        windowEnd.setMinutes(windowEnd.getMinutes() + EPG_HOURS_VISIBLE * 60);
        const windowMs = windowEnd.getTime() - windowStart.getTime();
        const totalMinutes = windowMs / 60000;
        const timelineWidthPx = totalMinutes * EPG_PX_PER_MINUTE;

        // Build time slots (every 30 minutes)
        const timeSlots = [];
        const slotTime = new Date(windowStart);
        while (slotTime < windowEnd) {
            timeSlots.push(new Date(slotTime));
            slotTime.setMinutes(slotTime.getMinutes() + 30);
        }

        // Time header — fixed pixel widths for scrolling
        let timeHeaderHtml = `<div class="epg-time-header" id="epgTimeHeader"><div class="epg-channel-col"></div><div class="epg-timeline-header" style="width:${timelineWidthPx}px;min-width:${timelineWidthPx}px">`;
        timeSlots.forEach(t => {
            const leftPx = ((t.getTime() - windowStart.getTime()) / windowMs) * timelineWidthPx;
            timeHeaderHtml += `<div class="epg-time-slot" style="left:${leftPx.toFixed(1)}px">${formatTime(t.toISOString())}</div>`;
        });
        timeHeaderHtml += '</div></div>';

        // Channel rows
        let rowsHtml = '';
        channels.forEach(ch => {
            const progs = liveEpgData[ch.id] || [];
            const isActive = ch.id === liveActiveChannelId;

            let progsHtml = '';
            progs.forEach(p => {
                const pStart = new Date(p.start_time).getTime();
                const pEnd = new Date(p.end_time).getTime();
                const wStart = windowStart.getTime();
                const wEnd = windowEnd.getTime();

                // Skip programmes outside the window
                if (pEnd <= wStart || pStart >= wEnd) return;

                const clampStart = Math.max(pStart, wStart);
                const clampEnd = Math.min(pEnd, wEnd);
                const leftPx = ((clampStart - wStart) / windowMs) * timelineWidthPx;
                const widthPx = ((clampEnd - clampStart) / windowMs) * timelineWidthPx;

                if (widthPx < 3) return;

                const isNow = pStart <= now.getTime() && pEnd > now.getTime();
                const isPast = pEnd <= now.getTime();
                const isFuture = pStart > now.getTime();
                let progClass = 'epg-programme';
                if (isNow) progClass += ' epg-programme-now';
                if (isPast) progClass += ' epg-programme-past';
                if (isFuture) progClass += ' epg-programme-future';

                // Progress bar for current programme
                let progressHtml = '';
                if (isNow) {
                    const elapsed = now.getTime() - pStart;
                    const duration = pEnd - pStart;
                    const pct = Math.min(100, (elapsed / duration) * 100);
                    progressHtml = `<div class="epg-progress"><div class="epg-progress-fill" style="width:${pct.toFixed(1)}%"></div></div>`;
                }

                const programmeKey = ch.id + '_' + p.start_time;
                const hasReminder = isFuture && hasLiveReminder(programmeKey);

                progsHtml += `
                    <div class="${progClass}" style="left:${leftPx.toFixed(1)}px;width:${widthPx.toFixed(1)}px"
                         data-channel-id="${ch.id}" data-programme='${JSON.stringify({id: p.id, title: p.title, description: p.description || '', start_time: p.start_time, end_time: p.end_time, category: p.category || '', is_placeholder: p.is_placeholder || false}).replace(/'/g, '&#39;')}'
                         title="${CariUI.esc(p.title)} (${formatTime(p.start_time)} - ${formatTime(p.end_time)})">
                        <span class="epg-programme-title">${CariUI.esc(p.title)}</span>
                        <span class="epg-programme-time">${formatTime(p.start_time)}</span>
                        ${hasReminder ? '<i class="lucide-bell epg-reminder-icon"></i>' : ''}
                        ${progressHtml}
                    </div>
                `;
            });

            rowsHtml += `
                <div class="epg-row ${isActive ? 'epg-row-active' : ''}" data-channel-id="${ch.id}">
                    <div class="epg-channel-col" data-channel-id="${ch.id}">
                        <img src="${CariUI.esc(ch.logo_url || ch.logo || '')}" alt="" onerror="this.style.display='none'">
                        <div class="epg-channel-name">${CariUI.esc(ch.name)}</div>
                    </div>
                    <div class="epg-timeline-row" style="width:${timelineWidthPx}px;min-width:${timelineWidthPx}px">
                        ${progsHtml}
                    </div>
                </div>
            `;
        });

        // Now-line position (px-based)
        const nowPx = ((now.getTime() - windowStart.getTime()) / windowMs) * timelineWidthPx;

        container.innerHTML = `
            ${timeHeaderHtml}
            <div class="epg-grid-body" id="epgGridBody">
                <div class="epg-now-line" id="epgNowLine" style="left:calc(var(--epg-channel-w) + ${nowPx.toFixed(1)}px)"></div>
                ${rowsHtml}
            </div>
        `;

        // Scroll to now (center "now" in viewport)
        // epg-grid-container is now the single scroll container
        if (container) {
            const scrollTarget = nowPx - container.clientWidth / 3;
            container.scrollLeft = Math.max(0, scrollTarget);
        }

        // Event listeners
        container.querySelectorAll('.epg-channel-col[data-channel-id]').forEach(col => {
            col.addEventListener('click', () => {
                const chId = parseInt(col.dataset.channelId);
                const ch = liveChannels.find(c => c.id === chId);
                if (ch) playLiveChannel(ch);
            });
        });

        container.querySelectorAll('.epg-programme').forEach(prog => {
            prog.addEventListener('click', (e) => {
                e.stopPropagation();
                const data = JSON.parse(prog.dataset.programme);
                const chId = parseInt(prog.dataset.channelId);
                const ch = liveChannels.find(c => c.id === chId);
                showProgrammeInfo(data, ch);
            });
        });
    }

    function scrollEpg(direction) {
        const scrollContainer = document.getElementById('epgGridContainer');
        if (!scrollContainer) return;
        const scrollAmount = 60 * EPG_PX_PER_MINUTE; // scroll 1 hour at a time

        if (direction === 'prev') {
            scrollContainer.scrollLeft -= scrollAmount;
        } else if (direction === 'next') {
            scrollContainer.scrollLeft += scrollAmount;
        } else if (direction === 'now') {
            const nowLine = document.getElementById('epgNowLine');
            if (nowLine) {
                const nowLeft = parseFloat(nowLine.style.left.replace(/calc\(var\(--epg-channel-w\) \+ /, '').replace('px)', ''));
                scrollContainer.scrollLeft = Math.max(0, nowLeft - scrollContainer.clientWidth / 3);
            }
        }
    }

    function setupEpgNavControls() {
        const prevBtn = document.getElementById('epgNavPrev');
        const nextBtn = document.getElementById('epgNavNext');
        const nowBtn = document.getElementById('epgNavNow');

        if (prevBtn) prevBtn.onclick = () => scrollEpg('prev');
        if (nextBtn) nextBtn.onclick = () => scrollEpg('next');
        if (nowBtn) nowBtn.onclick = () => scrollEpg('now');
    }

    let fsOverlayTimer = null;

    function setupFullscreenButton() {
        const btn = document.getElementById('liveFullscreenBtn');
        const playerContainer = document.getElementById('livePlayerContainer');
        const overlay = document.getElementById('fsOverlay');
        const exitBtn = document.getElementById('fsOverlayExit');
        const chPrevBtn = document.getElementById('fsChPrev');
        const chNextBtn = document.getElementById('fsChNext');
        if (!btn || !playerContainer) return;

        btn.addEventListener('click', () => {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                playerContainer.requestFullscreen().catch(() => {});
            }
        });

        if (exitBtn) {
            exitBtn.addEventListener('click', () => {
                if (document.fullscreenElement) document.exitFullscreen();
            });
        }

        // Channel up/down in fullscreen
        if (chPrevBtn) {
            chPrevBtn.addEventListener('click', () => {
                switchChannel(-1);
                showFsOverlay();
            });
        }
        if (chNextBtn) {
            chNextBtn.addEventListener('click', () => {
                switchChannel(1);
                showFsOverlay();
            });
        }

        document.addEventListener('fullscreenchange', () => {
            const icon = btn.querySelector('i');
            if (document.fullscreenElement) {
                if (icon) icon.className = 'lucide-minimize';
                updateFsOverlay();
                showFsOverlay();
            } else {
                if (icon) icon.className = 'lucide-maximize';
                hideFsOverlay();
            }
        });

        // Show overlay on mouse movement in fullscreen
        playerContainer.addEventListener('mousemove', () => {
            if (document.fullscreenElement) showFsOverlay();
        });

        // Keyboard controls in fullscreen
        document.addEventListener('keydown', (e) => {
            if (!document.fullscreenElement) return;
            if (e.key === 'ArrowUp') { switchChannel(-1); showFsOverlay(); }
            else if (e.key === 'ArrowDown') { switchChannel(1); showFsOverlay(); }
            else if (e.key === 'Escape') { /* browser handles exit */ }
        });
    }

    function showFsOverlay() {
        const overlay = document.getElementById('fsOverlay');
        if (!overlay) return;
        overlay.classList.add('visible');
        clearTimeout(fsOverlayTimer);
        fsOverlayTimer = setTimeout(() => {
            overlay.classList.remove('visible');
        }, 4000);
    }

    function hideFsOverlay() {
        const overlay = document.getElementById('fsOverlay');
        if (!overlay) return;
        overlay.classList.remove('visible');
        clearTimeout(fsOverlayTimer);
    }

    function updateFsOverlay() {
        if (!liveActiveChannelId) return;
        const channel = liveChannels.find(c => c.id === liveActiveChannelId);
        if (!channel) return;

        const channels = getFilteredChannels();
        const chIndex = channels.findIndex(c => c.id === liveActiveChannelId);

        const logoEl = document.getElementById('fsOverlayLogo');
        const channelEl = document.getElementById('fsOverlayChannel');
        const titleEl = document.getElementById('fsOverlayTitle');
        const timeEl = document.getElementById('fsOverlayTime');
        const progressFill = document.getElementById('fsOverlayProgressFill');
        const nextEl = document.getElementById('fsOverlayNext');
        const chNumEl = document.getElementById('fsChNumber');

        if (channelEl) channelEl.textContent = channel.name || '';
        if (logoEl) {
            const src = channel.logo_url || channel.logo || '';
            if (src) { logoEl.src = src; logoEl.style.display = ''; }
            else { logoEl.style.display = 'none'; }
        }
        if (chNumEl) chNumEl.textContent = (chIndex + 1) + ' / ' + channels.length;

        const nowPlaying = getNowPlaying(channel.id);
        if (titleEl) titleEl.textContent = nowPlaying ? nowPlaying.title : 'Live';
        if (timeEl) timeEl.textContent = nowPlaying ? formatTime(nowPlaying.start_time) + ' - ' + formatTime(nowPlaying.end_time) : '';

        if (progressFill && nowPlaying) {
            const now = Date.now();
            const pStart = new Date(nowPlaying.start_time).getTime();
            const pEnd = new Date(nowPlaying.end_time).getTime();
            const pct = Math.min(100, ((now - pStart) / (pEnd - pStart)) * 100);
            progressFill.style.width = pct.toFixed(1) + '%';
        } else if (progressFill) {
            progressFill.style.width = '0%';
        }

        if (nextEl) {
            const progs = liveEpgData[channel.id] || [];
            const now = Date.now();
            const next = progs.find(p => new Date(p.start_time).getTime() > now);
            if (next) {
                nextEl.innerHTML = `<span class="fs-overlay-label">Next</span> <span class="fs-overlay-next-title">${CariUI.esc(next.title)}</span> <span class="fs-overlay-next-time">${formatTime(next.start_time)}</span>`;
                nextEl.style.display = '';
            } else {
                nextEl.style.display = 'none';
            }
        }
    }

    function switchChannel(direction) {
        const channels = getFilteredChannels();
        if (!channels.length) return;
        const currentIndex = channels.findIndex(c => c.id === liveActiveChannelId);
        let newIndex = currentIndex + direction;
        if (newIndex < 0) newIndex = channels.length - 1;
        if (newIndex >= channels.length) newIndex = 0;
        playLiveChannel(channels[newIndex]);
    }

    function updateEpgTimeIndicator() {
        const nowLine = document.getElementById('epgNowLine');
        if (!nowLine) return;
        // Recalculate now position (px-based)
        const now = new Date();
        const windowStart = new Date(now);
        windowStart.setHours(windowStart.getHours() - 2);
        windowStart.setMinutes(windowStart.getMinutes() < 30 ? 0 : 30, 0, 0);
        const windowEnd = new Date(windowStart);
        windowEnd.setMinutes(windowEnd.getMinutes() + EPG_HOURS_VISIBLE * 60);
        const windowMs = windowEnd.getTime() - windowStart.getTime();
        const totalMinutes = windowMs / 60000;
        const timelineWidthPx = totalMinutes * EPG_PX_PER_MINUTE;
        const nowPx = ((now.getTime() - windowStart.getTime()) / windowMs) * timelineWidthPx;
        nowLine.style.left = `calc(var(--epg-channel-w) + ${nowPx.toFixed(1)}px)`;
    }

    function checkReminders() {
        const reminders = getLiveReminders();
        const now = Date.now();
        Object.entries(reminders).forEach(([key, data]) => {
            const startTime = new Date(data.start_time).getTime();
            // Notify 1 minute before
            if (startTime - now <= 60000 && startTime - now > 0) {
                showReminderNotification(data);
                removeLiveReminder(key);
                renderEpgGrid();
            }
            // Clean up past reminders
            if (startTime < now - 60000) {
                removeLiveReminder(key);
            }
        });
    }

    function showReminderNotification(data) {
        const toast = document.createElement('div');
        toast.className = 'epg-reminder-toast';
        toast.innerHTML = `
            <i class="lucide-bell"></i>
            <div>
                <strong>${CariUI.esc(data.title)}</strong> is starting now
                <div class="epg-reminder-toast-channel">${CariUI.esc(data.channel_name || '')}</div>
            </div>
            <button class="epg-reminder-toast-tune" data-channel-id="${data.channel_id}">Watch</button>
            <button class="epg-reminder-toast-close">&times;</button>
        `;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));

        toast.querySelector('.epg-reminder-toast-tune').addEventListener('click', () => {
            const ch = liveChannels.find(c => c.id === data.channel_id);
            if (ch) playLiveChannel(ch);
            toast.remove();
        });
        toast.querySelector('.epg-reminder-toast-close').addEventListener('click', () => toast.remove());
        setTimeout(() => toast.remove(), 15000);
    }

    function showProgrammeInfo(programme, channel) {
        if (programme.is_placeholder) return;

        const now = Date.now();
        const pStart = new Date(programme.start_time).getTime();
        const pEnd = new Date(programme.end_time).getTime();
        const isNow = pStart <= now && pEnd > now;
        const isFuture = pStart > now;
        const programmeKey = (channel?.id || '') + '_' + programme.start_time;
        const hasReminder = hasLiveReminder(programmeKey);

        // Duration in minutes
        const durationMin = Math.round((pEnd - pStart) / 60000);

        let actionsHtml = '';
        if (isNow && channel) {
            actionsHtml += `<button class="btn btn-primary epg-modal-btn" id="epgModalWatch"><i class="lucide-play"></i> Watch Now</button>`;
        }
        if (isFuture) {
            if (hasReminder) {
                actionsHtml += `<button class="btn btn-secondary epg-modal-btn" id="epgModalReminder"><i class="lucide-bell-off"></i> Remove Reminder</button>`;
            } else {
                actionsHtml += `<button class="btn btn-primary epg-modal-btn" id="epgModalReminder"><i class="lucide-bell"></i> Set Reminder</button>`;
            }
        }

        const overlay = document.createElement('div');
        overlay.className = 'epg-modal-overlay';
        overlay.innerHTML = `
            <div class="epg-modal epg-modal-enhanced">
                <button class="epg-modal-close" id="epgModalClose"><i class="lucide-x"></i></button>
                <div class="epg-modal-backdrop" id="epgModalBackdrop"></div>
                <div class="epg-modal-content">
                    <div class="epg-modal-top">
                        <div class="epg-modal-poster" id="epgModalPoster"></div>
                        <div class="epg-modal-info">
                            <div class="epg-modal-header">
                                ${channel ? `<img src="${CariUI.esc(channel.logo_url || channel.logo || '')}" alt="" class="epg-modal-logo" onerror="this.style.display='none'">` : ''}
                                <div>
                                    <h3>${CariUI.esc(programme.title)}</h3>
                                    <div class="epg-modal-meta">
                                        ${channel ? '<span>' + CariUI.esc(channel.name) + '</span>' : ''}
                                        <span>${formatTime(programme.start_time)} - ${formatTime(programme.end_time)}</span>
                                        <span>${durationMin} min</span>
                                        ${programme.category ? '<span>' + CariUI.esc(programme.category) + '</span>' : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="epg-modal-tmdb-meta" id="epgModalTmdbMeta"></div>
                            <p class="epg-modal-desc" id="epgModalDesc">${programme.description ? CariUI.esc(programme.description) : ''}</p>
                            <div class="epg-modal-actions">${actionsHtml}</div>
                        </div>
                    </div>
                    <div class="epg-modal-cast" id="epgModalCast"></div>
                    <div class="epg-modal-loading" id="epgModalLoading">
                        <div class="epg-modal-spinner"></div>
                        <span>Fetching programme info...</span>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        requestAnimationFrame(() => overlay.classList.add('show'));

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.remove();
        });
        document.getElementById('epgModalClose').addEventListener('click', () => overlay.remove());

        const watchBtn = document.getElementById('epgModalWatch');
        if (watchBtn && channel) {
            watchBtn.addEventListener('click', () => {
                playLiveChannel(channel);
                overlay.remove();
            });
        }

        const reminderBtn = document.getElementById('epgModalReminder');
        if (reminderBtn) {
            reminderBtn.addEventListener('click', () => {
                if (hasReminder) {
                    removeLiveReminder(programmeKey);
                } else {
                    saveLiveReminder(programmeKey, {
                        title: programme.title,
                        start_time: programme.start_time,
                        channel_id: channel?.id,
                        channel_name: channel?.name
                    });
                }
                overlay.remove();
                renderEpgGrid();
            });
        }

        // Fetch TMDB metadata asynchronously
        fetchProgrammeMetadata(programme.title, overlay);
    }

    async function fetchProgrammeMetadata(title, overlay) {
        const loadingEl = overlay.querySelector('#epgModalLoading');
        const posterEl = overlay.querySelector('#epgModalPoster');
        const backdropEl = overlay.querySelector('#epgModalBackdrop');
        const descEl = overlay.querySelector('#epgModalDesc');
        const tmdbMetaEl = overlay.querySelector('#epgModalTmdbMeta');
        const castEl = overlay.querySelector('#epgModalCast');

        try {
            const res = await CariAPI.getProgrammeInfo(title);
            const match = res?.data?.match;

            if (loadingEl) loadingEl.remove();

            if (!match) return;

            // Backdrop
            if (match.backdrop && backdropEl) {
                backdropEl.style.backgroundImage = `url(${match.backdrop})`;
                backdropEl.classList.add('has-image');
            }

            // Poster
            if (match.poster && posterEl) {
                posterEl.innerHTML = `<img src="${CariUI.esc(match.poster)}" alt="" onerror="this.parentElement.style.display='none'">`;
                posterEl.classList.add('has-image');
            }

            // TMDB metadata badges (rating, year, genres)
            if (tmdbMetaEl) {
                let metaHtml = '';
                if (match.vote_average) {
                    metaHtml += `<span class="epg-modal-rating"><i class="lucide-star"></i> ${Number(match.vote_average).toFixed(1)}</span>`;
                }
                if (match.year) {
                    metaHtml += `<span>${CariUI.esc(match.year)}</span>`;
                }
                const mediaType = res?.data?.media_type;
                if (mediaType) {
                    metaHtml += `<span class="epg-modal-type">${mediaType === 'movie' ? 'Movie' : 'TV Show'}</span>`;
                }
                // Genres from detailed result
                if (match.genres && Array.isArray(match.genres)) {
                    const genreNames = match.genres.map(g => typeof g === 'string' ? g : g.name).filter(Boolean);
                    if (genreNames.length) {
                        metaHtml += `<span>${CariUI.esc(genreNames.slice(0, 3).join(', '))}</span>`;
                    }
                }
                if (match.number_of_seasons) {
                    metaHtml += `<span>${match.number_of_seasons} Season${match.number_of_seasons > 1 ? 's' : ''}</span>`;
                }
                if (match.runtime) {
                    metaHtml += `<span>${match.runtime} min</span>`;
                }
                tmdbMetaEl.innerHTML = metaHtml;
            }

            // Overview (prefer TMDB if EPG description is empty or short)
            const tmdbOverview = match.overview || match.synopsis || '';
            if (tmdbOverview && descEl) {
                const currentDesc = descEl.textContent.trim();
                if (!currentDesc || currentDesc.length < tmdbOverview.length) {
                    descEl.textContent = tmdbOverview;
                }
            }

            // Cast
            if (match.cast && match.cast.length && castEl) {
                CariUI.renderCastRow(castEl, match.cast, { type: 'epg-modal' });
            }

            // Directors
            if (match.directors && match.directors.length && castEl) {
                const dirEl = document.createElement('div');
                dirEl.className = 'epg-modal-directors';
                dirEl.innerHTML = `<span class="epg-modal-director-label">Director:</span> ${match.directors.map(d => CariUI.esc(typeof d === 'string' ? d : d.name)).join(', ')}`;
                castEl.insertBefore(dirEl, castEl.firstChild);
            }
        } catch (err) {
            if (loadingEl) loadingEl.remove();
            console.warn('[CariApp] Programme metadata fetch failed:', err);
        }
    }

    async function playLiveChannel(channel) {
        liveActiveChannelId = channel.id;

        // Update URL to preserve channel across refreshes (replaceState to avoid history spam)
        history.replaceState(null, '', '/live/' + channel.id);

        // Update channel info in side panel
        const titleEl = document.getElementById('liveChannelTitle');
        const progEl = document.getElementById('liveChannelProgram');
        const logoEl = document.getElementById('liveNowLogo');
        const timeEl = document.getElementById('liveInfoTime');
        const progressEl = document.getElementById('liveInfoProgress');
        const progressFillEl = document.getElementById('liveInfoProgressFill');
        const descEl = document.getElementById('liveInfoDesc');
        const nextEl = document.getElementById('liveInfoNext');

        if (titleEl) titleEl.textContent = channel.name || '';
        if (logoEl) {
            const src = channel.logo_url || channel.logo || '';
            if (src) { logoEl.src = src; logoEl.style.display = ''; }
            else { logoEl.style.display = 'none'; }
        }

        const nowPlaying = getNowPlaying(channel.id);
        if (progEl) {
            progEl.textContent = nowPlaying ? nowPlaying.title : 'Live';
        }
        if (timeEl) {
            timeEl.textContent = nowPlaying ? formatTime(nowPlaying.start_time) + ' - ' + formatTime(nowPlaying.end_time) : '';
        }

        // Progress bar for current programme
        if (progressEl && progressFillEl && nowPlaying) {
            const now = Date.now();
            const pStart = new Date(nowPlaying.start_time).getTime();
            const pEnd = new Date(nowPlaying.end_time).getTime();
            const pct = Math.min(100, ((now - pStart) / (pEnd - pStart)) * 100);
            progressFillEl.style.width = pct.toFixed(1) + '%';
            progressEl.style.display = '';
        } else if (progressEl) {
            progressEl.style.display = 'none';
        }

        // Description
        if (descEl) {
            descEl.textContent = nowPlaying?.description || '';
            descEl.style.display = nowPlaying?.description ? '' : 'none';
        }

        // Show next up
        if (nextEl) {
            const progs = liveEpgData[channel.id] || [];
            const now = Date.now();
            const next = progs.find(p => new Date(p.start_time).getTime() > now);
            if (next) {
                nextEl.innerHTML = `<div class="live-info-next-header"><span class="live-info-label">Up Next</span><span class="live-info-next-time">${formatTime(next.start_time)}</span></div><span class="live-info-next-title">${CariUI.esc(next.title)}</span>`;
                nextEl.style.display = '';
            } else {
                nextEl.style.display = 'none';
            }
        }

        // "More Info" button — show when playing a programme with real EPG data
        const moreBtn = document.getElementById('liveInfoMoreBtn');
        if (moreBtn) {
            if (nowPlaying && !nowPlaying.is_placeholder) {
                moreBtn.style.display = '';
                moreBtn.onclick = () => showProgrammeInfo(nowPlaying, channel);
            } else {
                moreBtn.style.display = 'none';
            }
        }

        // Highlight active row in EPG
        document.querySelectorAll('.epg-row').forEach(row => {
            row.classList.toggle('epg-row-active', row.dataset.channelId == channel.id);
        });

        // Update fullscreen overlay if active
        updateFsOverlay();

        // Play stream
        const video = document.getElementById('liveVideo');
        const url = channel.stream_url || '';

        if (url && window.shaka) {
            await initShakaPlayer(video, url);
        } else if (url) {
            video.src = url;
            await autoplayWithFallback(video);
        }
    }

    // ---- PAGE: Search ----

    const RECENT_SEARCHES_KEY = 'cari_recent_searches';
    const MAX_RECENT_SEARCHES = 8;
    let searchTimeout = null;

    function getRecentSearches() {
        try {
            return JSON.parse(localStorage.getItem(RECENT_SEARCHES_KEY) || '[]');
        } catch { return []; }
    }

    function saveRecentSearch(query) {
        if (!query || query.length < 2) return;
        let recent = getRecentSearches().filter(s => s !== query);
        recent.unshift(query);
        if (recent.length > MAX_RECENT_SEARCHES) recent = recent.slice(0, MAX_RECENT_SEARCHES);
        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(recent));
    }

    function removeRecentSearch(query) {
        const recent = getRecentSearches().filter(s => s !== query);
        localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(recent));
    }

    function doSearch(query) {
        if (!query || query.length < 2) return;
        CariRouter.navigate('/search?q=' + encodeURIComponent(query));
    }

    function setupPageSearchInput() {
        const input = document.getElementById('pageSearchInput');
        if (!input) return;
        input.focus();

        input.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            const q = input.value.trim();
            if (q.length >= 2) {
                searchTimeout = setTimeout(() => doSearch(q), 500);
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                clearTimeout(searchTimeout);
                doSearch(input.value.trim());
            }
        });

        const clearBtn = document.getElementById('searchClearBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                input.value = '';
                input.focus();
                CariRouter.navigate('/search');
            });
        }
    }

    /**
     * Renders the search input bar that lives on the search page.
     * Shows a clear button when there's a query.
     */
    function searchInputHTML(q) {
        return `
            <div class="search-input-bar">
                <div class="search-input-wrap">
                    <i class="lucide-search search-input-icon"></i>
                    <input type="text" id="pageSearchInput" placeholder="Search movies, shows, channels..."
                           value="${CariUI.esc(q)}" autocomplete="off" spellcheck="false">
                    ${q ? '<button class="search-input-clear" id="searchClearBtn"><i class="lucide-x"></i></button>' : ''}
                </div>
            </div>
        `;
    }

    async function pageSearch() {
        const el = content();
        const params = new URLSearchParams(window.location.search);
        const q = params.get('q') || '';

        if (!q || q.length < 2) {
            renderSearchDiscovery(el, q);
            return;
        }

        saveRecentSearch(q);
        renderSearchResults(el, q);
    }

    /**
     * Pre-search discovery state — shown when no query is entered.
     * Shows search input, quick filters, recent searches, featured promo,
     * trending content, and browse-by-category.
     */
    async function renderSearchDiscovery(el, q) {
        const recent = getRecentSearches();

        el.innerHTML = `
            <div class="search-page">
                ${searchInputHTML(q || '')}
                <div id="searchQuickFilters" class="search-quick-filters"></div>
                ${recent.length ? '<div id="recentSearches" class="search-section"></div>' : ''}
                <div id="searchFeatured" class="search-section"></div>
                <div id="searchTrending" class="search-section"></div>
                <div id="searchNewReleases" class="search-section"></div>
                <div id="searchCategories" class="search-section"></div>
            </div>
        `;

        setupPageSearchInput();

        // Render recent searches
        if (recent.length) {
            const recentEl = document.getElementById('recentSearches');
            recentEl.innerHTML = `
                <div class="search-section-header">
                    <h3><i class="lucide-clock"></i> Recent Searches</h3>
                    <button class="search-clear-all" id="clearAllRecent">Clear All</button>
                </div>
                <div class="recent-search-chips">
                    ${recent.map(s => `
                        <div class="recent-chip">
                            <span class="recent-chip-text" data-query="${CariUI.esc(s)}">${CariUI.esc(s)}</span>
                            <button class="recent-chip-remove" data-remove="${CariUI.esc(s)}"><i class="lucide-x"></i></button>
                        </div>
                    `).join('')}
                </div>
            `;

            recentEl.addEventListener('click', (e) => {
                const chipText = e.target.closest('.recent-chip-text');
                if (chipText) {
                    doSearch(chipText.dataset.query);
                    return;
                }
                const removeBtn = e.target.closest('.recent-chip-remove');
                if (removeBtn) {
                    removeRecentSearch(removeBtn.dataset.remove);
                    removeBtn.closest('.recent-chip').remove();
                    if (!getRecentSearches().length) recentEl.remove();
                    return;
                }
                if (e.target.closest('#clearAllRecent')) {
                    localStorage.removeItem(RECENT_SEARCHES_KEY);
                    recentEl.remove();
                }
            });
        }

        // Load all discovery data in parallel
        try {
            const [moviesRes, seriesRes, catRes, featuredRes, latestRes] = await Promise.all([
                CariAPI.getMovies({ sort: 'popular', limit: 14 }),
                CariAPI.getSeries({ sort: 'popular', limit: 14 }),
                CariAPI.getCategories({ type: 'vod' }),
                CariAPI.getMovies({ featured: 1, limit: 5 }),
                CariAPI.getMovies({ sort: 'latest', limit: 14 }),
            ]);

            // Quick filter chips from categories
            const cats = catRes?.data || [];
            const filtersEl = document.getElementById('searchQuickFilters');
            if (cats.length && filtersEl) {
                filtersEl.innerHTML = `
                    <button class="quick-filter-chip" data-type="movie"><i class="lucide-film"></i> Movies</button>
                    <button class="quick-filter-chip" data-type="series"><i class="lucide-clapperboard"></i> TV Shows</button>
                    <button class="quick-filter-chip" data-type="channel"><i class="lucide-radio"></i> Live TV</button>
                    <span class="quick-filter-divider"></span>
                    ${cats.slice(0, 8).map(c => `<button class="quick-filter-chip" data-category="${c.id}">${CariUI.esc(c.name)}</button>`).join('')}
                `;
                filtersEl.addEventListener('click', (e) => {
                    const chip = e.target.closest('.quick-filter-chip');
                    if (!chip) return;
                    const type = chip.dataset.type;
                    if (type === 'movie') CariRouter.navigate('/movies');
                    else if (type === 'series') CariRouter.navigate('/series');
                    else if (type === 'channel') CariRouter.navigate('/live');
                    else if (chip.dataset.category) CariRouter.navigate('/categories?cat=' + chip.dataset.category);
                });
            }

            // Featured promo banner (from featured movies)
            const featured = featuredRes?.data || [];
            const featuredEl = document.getElementById('searchFeatured');
            if (featured.length && featuredEl) {
                const pick = featured[Math.floor(Math.random() * Math.min(featured.length, 3))];
                const bg = pick.backdrop_url || pick.poster_url || '';
                featuredEl.innerHTML = `
                    <div class="search-promo-banner" id="searchPromoBanner">
                        ${bg ? '<img class="search-promo-bg" src="' + CariUI.esc(bg) + '" alt="" loading="lazy" onerror="this.style.display=\'none\'">' : ''}
                        <div class="search-promo-gradient"></div>
                        <div class="search-promo-content">
                            <div class="search-promo-badge">Featured</div>
                            <h3 class="search-promo-title">${CariUI.esc(pick.title || '')}</h3>
                            <p class="search-promo-desc">${CariUI.esc((pick.synopsis || '').substring(0, 120))}${(pick.synopsis || '').length > 120 ? '...' : ''}</p>
                            <div class="search-promo-meta">
                                ${pick.year ? '<span>' + CariUI.esc(pick.year) + '</span>' : ''}
                                ${pick.vote_average ? '<span><i class="lucide-star" style="font-size:.7rem"></i> ' + CariUI.esc(String(pick.vote_average)) + '</span>' : ''}
                                ${pick.genres ? '<span>' + CariUI.esc(pick.genres) + '</span>' : ''}
                            </div>
                        </div>
                    </div>
                `;
                featuredEl.querySelector('#searchPromoBanner').addEventListener('click', () => {
                    pick.content_type = 'movie';
                    CariUI.showDetail(pick, playContent, isContentLocked(pick));
                });
            }

            // Trending content row
            const trendingEl = document.getElementById('searchTrending');
            const movies = (moviesRes?.data || []).map(m => ({ ...m, content_type: 'movie' }));
            const series = (seriesRes?.data || []).map(s => ({ ...s, content_type: 'series' }));
            const trending = [...movies, ...series]
                .sort((a, b) => (b.vote_average || 0) - (a.vote_average || 0))
                .slice(0, 14);

            if (trending.length && trendingEl) {
                const section = CariUI.renderContentRow('Trending Now', trending, 'poster', (item) => {
                    CariUI.showDetail(item, playContent, isContentLocked(item));
                });
                if (section) {
                    trendingEl.innerHTML = '';
                    trendingEl.appendChild(section);
                }
            }

            // New releases row
            const latest = (latestRes?.data || []).map(m => ({ ...m, content_type: 'movie' }));
            const newReleasesEl = document.getElementById('searchNewReleases');
            if (latest.length && newReleasesEl) {
                const section = CariUI.renderContentRow('New Releases', latest, 'backdrop', (item) => {
                    CariUI.showDetail(item, playContent, isContentLocked(item));
                });
                if (section) {
                    newReleasesEl.innerHTML = '';
                    newReleasesEl.appendChild(section);
                }
            }

            // Browse by category grid
            const catsEl = document.getElementById('searchCategories');
            if (cats.length && catsEl) {
                catsEl.innerHTML = `
                    <div class="search-section-header"><h3><i class="lucide-grid-3x3"></i> Browse by Category</h3></div>
                    <div class="search-category-grid"></div>
                `;
                const grid = catsEl.querySelector('.search-category-grid');
                cats.forEach(cat => {
                    grid.appendChild(CariUI.categoryCard(cat, (c) => {
                        CariRouter.navigate('/categories?cat=' + c.id);
                    }));
                });
            }
        } catch {
            // Silent — discovery is best-effort
        }
    }

    /**
     * Search results state — grouped by content type with filter tabs.
     * Search input stays at top for easy refinement.
     */
    async function renderSearchResults(el, q) {
        el.innerHTML = `
            <div class="search-page">
                ${searchInputHTML(q)}
                <div class="search-results-header">
                    <h2 class="search-results-title">Searching...</h2>
                </div>
                <div id="searchFilterTabs" class="search-filter-tabs"></div>
                <div id="searchResults">${CariUI.loading()}</div>
            </div>
        `;

        setupPageSearchInput();

        try {
            // Fetch all types in parallel for grouped results + counts
            const [moviesRes, seriesRes, channelsRes] = await Promise.all([
                CariAPI.search(q, 'movie'),
                CariAPI.search(q, 'series'),
                CariAPI.search(q, 'channel'),
            ]);

            const movies = (moviesRes?.data || []).map(m => ({ ...m, content_type: 'movie' }));
            const series = (seriesRes?.data || []).map(s => ({ ...s, content_type: 'series' }));
            const channels = (channelsRes?.data || []).map(c => ({ ...c, content_type: 'channel' }));
            const totalCount = movies.length + series.length + channels.length;

            // Update title with count
            const titleEl = document.querySelector('.search-results-title');
            if (titleEl) titleEl.textContent = `Results for "${q}" (${totalCount})`;

            // Render filter tabs
            const tabsEl = document.getElementById('searchFilterTabs');
            const tabs = [
                { key: 'all', label: 'All', count: totalCount },
                { key: 'movie', label: 'Movies', count: movies.length, icon: 'lucide-film' },
                { key: 'series', label: 'TV Shows', count: series.length, icon: 'lucide-clapperboard' },
                { key: 'channel', label: 'Live TV', count: channels.length, icon: 'lucide-radio' },
            ];

            tabsEl.innerHTML = tabs.map(t =>
                `<button class="search-tab${t.key === 'all' ? ' active' : ''}" data-tab="${t.key}">
                    ${t.icon ? '<i class="' + t.icon + '"></i> ' : ''}${CariUI.esc(t.label)}
                    <span class="search-tab-count">${t.count}</span>
                </button>`
            ).join('');

            const resultsContainer = document.getElementById('searchResults');

            function renderGrouped(filter) {
                resultsContainer.innerHTML = '';

                if (totalCount === 0) {
                    resultsContainer.innerHTML = `
                        <div class="search-no-results">
                            <i class="lucide-search-x"></i>
                            <h3>No results found</h3>
                            <p>We couldn't find anything for "${CariUI.esc(q)}". Try a different search term, or browse our categories below.</p>
                        </div>
                    `;
                    loadNoResultsSuggestions(resultsContainer);
                    return;
                }

                const showMovies = filter === 'all' || filter === 'movie';
                const showSeries = filter === 'all' || filter === 'series';
                const showChannels = filter === 'all' || filter === 'channel';

                if (showChannels && channels.length) {
                    renderSearchGroup(resultsContainer, 'Live TV', 'lucide-radio', channels, 'channel');
                }

                if (showMovies && movies.length) {
                    renderSearchGroup(resultsContainer, 'Movies', 'lucide-film', movies, 'poster');
                }

                if (showSeries && series.length) {
                    renderSearchGroup(resultsContainer, 'TV Shows', 'lucide-clapperboard', series, 'poster');
                }

                // If filter selected but that type has no results
                if (!resultsContainer.children.length) {
                    const label = tabs.find(t => t.key === filter)?.label || 'items';
                    resultsContainer.innerHTML = CariUI.emptyState('lucide-search-x', 'No ' + label, 'No ' + label.toLowerCase() + ' matched your search.');
                }
            }

            tabsEl.addEventListener('click', (e) => {
                const tab = e.target.closest('.search-tab');
                if (!tab) return;
                tabsEl.querySelectorAll('.search-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                renderGrouped(tab.dataset.tab);
            });

            renderGrouped('all');
        } catch {
            document.getElementById('searchResults').innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Search failed. Please try again.');
        }
    }

    function renderSearchGroup(container, title, icon, items, cardStyle) {
        const section = document.createElement('div');
        section.className = 'search-result-group';

        const header = document.createElement('div');
        header.className = 'search-group-header';
        header.innerHTML = `<i class="${CariUI.esc(icon)}"></i> <span>${CariUI.esc(title)}</span> <span class="search-group-count">${items.length}</span>`;
        section.appendChild(header);

        if (cardStyle === 'channel') {
            const grid = document.createElement('div');
            grid.className = 'search-channel-grid';
            items.forEach(ch => {
                grid.appendChild(CariUI.channelCard(ch, (channel) => {
                    playContent(channel);
                }, isContentLocked(ch)));
            });
            section.appendChild(grid);
        } else {
            const grid = document.createElement('div');
            grid.className = 'content-grid';
            items.forEach(item => {
                grid.appendChild(CariUI.posterCard(item, (it) => {
                    CariUI.showDetail(it, playContent, isContentLocked(it));
                }, isContentLocked(item)));
            });
            section.appendChild(grid);
        }

        container.appendChild(section);
    }

    async function loadNoResultsSuggestions(container) {
        try {
            const res = await CariAPI.getMovies({ sort: 'popular', limit: 10 });
            const movies = (res?.data || []).map(m => ({ ...m, content_type: 'movie' }));
            if (movies.length) {
                const section = CariUI.renderContentRow('You might enjoy', movies, 'poster', (item) => {
                    CariUI.showDetail(item, playContent, isContentLocked(item));
                });
                if (section) container.appendChild(section);
            }
        } catch {}
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
                        grid.appendChild(CariUI.posterCard(detail, (it) => CariUI.showDetail(it, playContent, isContentLocked(it))));
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

    // ---- PAGE: Subscribe ----

    async function pageSubscribe() {
        const el = content();
        const params = new URLSearchParams(window.location.search);
        const selectedPkgId = params.get('package');

        el.innerHTML = `
            <div class="page-hero">
                <h1 class="page-hero-title">Choose Your Plan</h1>
                <p class="page-hero-subtitle">Unlock premium content with a subscription</p>
            </div>
            <div class="subscribe-page">
                <div class="packages-grid" id="subscribePackages">${CariUI.loading()}</div>
            </div>
        `;

        try {
            const packages = entitlements?.packages || [];
            const grid = document.getElementById('subscribePackages');

            if (!packages.length) {
                grid.innerHTML = CariUI.emptyState('lucide-package', 'No Plans Available', 'Please check back later for subscription options.');
                return;
            }

            grid.innerHTML = '';
            packages.forEach(pkg => {
                const isSelected = selectedPkgId && String(pkg.id) === selectedPkgId;
                const isSubscribed = pkg.is_subscribed;
                const isFree = pkg.is_free || parseFloat(pkg.price || 0) === 0;
                const hasTrial = !isFree && parseInt(pkg.trial_days || 0) > 0;
                const card = document.createElement('div');
                card.className = 'package-card' + (pkg.is_featured ? ' featured' : '') + (isSelected ? ' selected' : '') + (isSubscribed ? ' subscribed' : '');

                let btnHtml;
                if (isSubscribed) {
                    btnHtml = '<button class="btn btn-subscribed package-btn"><i class="lucide-check-circle"></i> Subscribed</button>';
                } else if (isFree) {
                    btnHtml = '<button class="btn btn-subscribe package-btn" data-pkg-id="' + pkg.id + '"><i class="lucide-zap"></i> Get Free</button>';
                } else if (hasTrial) {
                    btnHtml = '<button class="btn btn-subscribe package-btn" data-pkg-id="' + pkg.id + '"><i class="lucide-play"></i> Start ' + pkg.trial_days + '-Day Trial</button>';
                } else {
                    btnHtml = '<button class="btn btn-subscribe package-btn" data-pkg-id="' + pkg.id + '">Select Plan</button>';
                }

                card.innerHTML = `
                    ${pkg.is_featured ? '<div class="package-badge">Most Popular</div>' : ''}
                    ${isSubscribed ? '<div class="package-badge subscribed-badge">Active</div>' : ''}
                    <h3 class="package-name">${CariUI.esc(pkg.name)}</h3>
                    <div class="package-price">
                        <span class="price-amount">${CariUI.esc(pkg.price_display || '$' + (pkg.price || '0'))}</span>
                        <span class="price-period">/ ${CariUI.esc(pkg.billing_period || 'month')}</span>
                    </div>
                    ${pkg.description ? '<p class="package-desc">' + CariUI.esc(pkg.description) + '</p>' : ''}
                    ${pkg.features && pkg.features.length ? '<ul class="package-features">' + pkg.features.map(f => '<li><i class="lucide-check"></i> ' + CariUI.esc(f) + '</li>').join('') + '</ul>' : ''}
                    ${btnHtml}
                `;

                if (!isSubscribed) {
                    card.querySelector('.package-btn').addEventListener('click', () => {
                        showPaymentModal(pkg);
                    });
                }
                grid.appendChild(card);
            });

            // Auto-show payment modal if package selected
            if (selectedPkgId) {
                const pkg = packages.find(p => String(p.id) === selectedPkgId);
                if (pkg) showPaymentModal(pkg);
            }
        } catch (err) {
            document.getElementById('subscribePackages').innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load subscription plans.');
        }
    }

    function showPaymentModal(pkg) {
        // Create payment modal overlay
        let overlay = document.getElementById('paymentOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'paymentOverlay';
            overlay.className = 'modal-overlay';
            document.body.appendChild(overlay);
        }

        const isFree = pkg.is_free || parseFloat(pkg.price || 0) === 0;
        const hasTrial = !isFree && parseInt(pkg.trial_days || 0) > 0;
        const canSelfSubscribe = isFree || hasTrial;

        let actionContent = '';
        if (isFree) {
            actionContent = `
                <div class="payment-action">
                    <p class="payment-info"><i class="lucide-check-circle"></i> This is a free plan. No payment required.</p>
                    <button class="btn btn-play" id="confirmSubscribe"><i class="lucide-check"></i> Activate Free Plan</button>
                </div>
            `;
        } else if (hasTrial) {
            actionContent = `
                <div class="payment-action">
                    <p class="payment-info"><i class="lucide-clock"></i> Start your ${pkg.trial_days}-day free trial. No payment required now.</p>
                    <button class="btn btn-play" id="confirmSubscribe"><i class="lucide-play"></i> Start Free Trial</button>
                </div>
            `;
        } else {
            actionContent = `
                <div class="payment-methods">
                    <p class="payment-methods-label">Payment methods coming soon</p>
                    <div class="payment-placeholder">
                        <i class="lucide-credit-card"></i>
                        <p>Payment gateway integration (Stripe, PayPal) will be available soon.</p>
                        <p class="payment-contact">Please contact support to subscribe manually.</p>
                    </div>
                </div>
            `;
        }

        overlay.innerHTML = `
            <div class="payment-modal">
                <button class="modal-close" id="closePayment"><i class="lucide-x"></i></button>
                <h2 class="payment-modal-title">${canSelfSubscribe ? 'Confirm Subscription' : 'Complete Your Subscription'}</h2>
                <div class="payment-summary">
                    <div class="payment-plan">${CariUI.esc(pkg.name)}</div>
                    <div class="payment-amount">${CariUI.esc(pkg.price_display || '$' + (pkg.price || '0'))} / ${CariUI.esc(pkg.billing_period || 'month')}</div>
                </div>
                ${actionContent}
                <div id="subscribeError" class="payment-error" style="display:none"></div>
                <button class="btn btn-secondary" id="cancelPayment">Cancel</button>
            </div>
        `;

        overlay.classList.add('visible');

        const closeModal = () => overlay.classList.remove('visible');
        document.getElementById('closePayment').addEventListener('click', closeModal);
        document.getElementById('cancelPayment').addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });

        // Handle subscribe action for free/trial packages
        const confirmBtn = document.getElementById('confirmSubscribe');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', async () => {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="lucide-loader"></i> Processing...';
                const errorEl = document.getElementById('subscribeError');
                errorEl.style.display = 'none';

                try {
                    const res = await CariAPI.subscribeTo(pkg.id);
                    if (res?.data?.success) {
                        // Bust content caches so pages reload fresh data
                        CariAPI.bustAllCaches();
                        await loadEntitlements();
                        closeModal();
                        CariRouter.refresh();
                    }
                } catch (err) {
                    errorEl.textContent = err.message || 'Failed to subscribe. Please try again.';
                    errorEl.style.display = 'block';
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = isFree
                        ? '<i class="lucide-check"></i> Activate Free Plan'
                        : '<i class="lucide-play"></i> Start Free Trial';
                }
            });
        }
    }

    // ---- PAGE: Profile ----

    async function pageProfile() {
        const el = content();
        const user = CariAPI.getUser();

        el.innerHTML = `
            <div class="page-hero">
                <h1 class="page-hero-title">My Profile</h1>
            </div>
            <div class="profile-page">
                <div class="profile-section">
                    <h3 class="profile-section-title">Account Information</h3>
                    <div class="profile-info">
                        <div class="profile-avatar">${(user?.first_name?.[0] || user?.username?.[0] || 'U').toUpperCase()}</div>
                        <div class="profile-details">
                            <div class="profile-name">${CariUI.esc([user?.first_name, user?.last_name].filter(Boolean).join(' ') || user?.username || 'User')}</div>
                            <div class="profile-email">${CariUI.esc(user?.email || '')}</div>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="profile-section-title">Subscriptions</h3>
                    <div id="profileSubscriptions">${CariUI.loading()}</div>
                </div>

                <div class="profile-section">
                    <h3 class="profile-section-title">Parental Controls</h3>
                    <div class="profile-option">
                        <label class="toggle-label">
                            <span>Enable Adult Content</span>
                            <input type="checkbox" id="adultToggle" ${entitlements?.adult_enabled ? 'checked' : ''}>
                            <span class="toggle-switch"></span>
                        </label>
                        <p class="profile-option-desc">Shows 18+ rated content. Requires PIN verification.</p>
                    </div>
                    <div class="profile-option" id="pinSection" style="display:${entitlements?.adult_enabled ? 'block' : 'none'}">
                        <button class="btn btn-secondary" id="setPinBtn"><i class="lucide-lock"></i> Set/Change PIN</button>
                    </div>
                </div>

                <div class="profile-section">
                    <button class="btn btn-danger" id="logoutProfileBtn"><i class="lucide-log-out"></i> Sign Out</button>
                </div>
            </div>
        `;

        // Load current subscriptions
        const subsContainer = document.getElementById('profileSubscriptions');
        const userPackages = entitlements?.packages?.filter(p => p.is_subscribed) || [];
        if (userPackages.length) {
            subsContainer.innerHTML = userPackages.map(p => `
                <div class="subscription-item">
                    <div class="subscription-info">
                        <div class="subscription-name">${CariUI.esc(p.name)}</div>
                        <div class="subscription-status">Active</div>
                    </div>
                    <button class="btn btn-secondary btn-sm unsub-btn" data-pkg-id="${p.id}">Cancel</button>
                </div>
            `).join('');

            subsContainer.querySelectorAll('.unsub-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm('Are you sure you want to cancel this subscription?')) return;
                    btn.disabled = true;
                    btn.textContent = 'Cancelling...';
                    try {
                        await CariAPI.unsubscribeFrom(parseInt(btn.dataset.pkgId));
                        CariAPI.bustAllCaches();
                        await loadEntitlements();
                        // Refresh the profile page
                        pageProfile();
                    } catch (err) {
                        btn.disabled = false;
                        btn.textContent = 'Cancel';
                    }
                });
            });
        } else {
            subsContainer.innerHTML = `
                <p class="profile-empty">No active subscriptions.</p>
                <button class="btn btn-subscribe" id="getSubscription">Browse Plans</button>
            `;
            document.getElementById('getSubscription')?.addEventListener('click', () => {
                CariRouter.navigate('/subscribe');
            });
        }

        // Adult content toggle
        document.getElementById('adultToggle').addEventListener('change', async (e) => {
            const enabled = e.target.checked;
            document.getElementById('pinSection').style.display = enabled ? 'block' : 'none';

            try {
                await CariAPI.updateProfile({ adult_enabled: enabled });
                if (entitlements) entitlements.adult_enabled = enabled;
            } catch (err) {
                // Revert on failure
                e.target.checked = !enabled;
                document.getElementById('pinSection').style.display = !enabled ? 'block' : 'none';
            }

            if (enabled) {
                showPinModal('set');
            }
        });

        document.getElementById('setPinBtn')?.addEventListener('click', () => {
            showPinModal('set');
        });

        document.getElementById('logoutProfileBtn').addEventListener('click', () => {
            CariAPI.logout();
        });
    }

    function showPinModal(mode) {
        let overlay = document.getElementById('pinOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'pinOverlay';
            overlay.className = 'modal-overlay';
            document.body.appendChild(overlay);
        }

        const isSet = mode === 'set';
        overlay.innerHTML = `
            <div class="pin-modal">
                <button class="modal-close" id="closePin"><i class="lucide-x"></i></button>
                <h2 class="pin-modal-title">${isSet ? 'Set Parental PIN' : 'Enter PIN'}</h2>
                <p class="pin-modal-desc">${isSet ? 'Create a 4-digit PIN to protect adult content.' : 'Enter your 4-digit PIN to continue.'}</p>
                <div class="pin-input-group">
                    <input type="password" maxlength="1" class="pin-input" data-idx="0" inputmode="numeric" pattern="[0-9]*">
                    <input type="password" maxlength="1" class="pin-input" data-idx="1" inputmode="numeric" pattern="[0-9]*">
                    <input type="password" maxlength="1" class="pin-input" data-idx="2" inputmode="numeric" pattern="[0-9]*">
                    <input type="password" maxlength="1" class="pin-input" data-idx="3" inputmode="numeric" pattern="[0-9]*">
                </div>
                ${isSet ? '<p class="pin-modal-hint">You\'ll need this PIN to view adult content.</p>' : ''}
                <button class="btn btn-play" id="confirmPin">${isSet ? 'Set PIN' : 'Confirm'}</button>
            </div>
        `;

        overlay.classList.add('visible');

        // Auto-focus and auto-advance
        const inputs = overlay.querySelectorAll('.pin-input');
        inputs[0].focus();

        inputs.forEach((input, i) => {
            input.addEventListener('input', (e) => {
                if (e.target.value && i < 3) {
                    inputs[i + 1].focus();
                }
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && i > 0) {
                    inputs[i - 1].focus();
                }
            });
        });

        document.getElementById('closePin').addEventListener('click', () => overlay.classList.remove('visible'));
        document.getElementById('confirmPin').addEventListener('click', async () => {
            const pin = Array.from(inputs).map(i => i.value).join('');
            if (pin.length === 4) {
                const btn = document.getElementById('confirmPin');
                btn.disabled = true;
                btn.textContent = 'Saving...';
                try {
                    await CariAPI.updateProfile({ parental_pin: pin });
                    overlay.classList.remove('visible');
                } catch (err) {
                    btn.disabled = false;
                    btn.textContent = isSet ? 'Set PIN' : 'Confirm';
                }
            }
        });
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
                // Redirect channels to the live TV page
                CariRouter.navigate('/live/' + id);
                return;
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

            // Block playback if content is locked — redirect to subscribe
            if (isContentLocked(item)) {
                el.innerHTML = `
                    <div class="locked-player-page">
                        <div class="locked-player-message">
                            <i class="lucide-lock"></i>
                            <h2>Subscription Required</h2>
                            <p>You need an active subscription to watch this content.</p>
                            <button class="btn btn-subscribe" id="lockedSubscribeBtn"><i class="lucide-credit-card"></i> View Plans</button>
                        </div>
                    </div>
                `;
                document.getElementById('lockedSubscribeBtn').addEventListener('click', () => {
                    CariRouter.navigate('/subscribe');
                });
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

    /**
     * Try to autoplay; if browser blocks unmuted autoplay, mute and retry,
     * then show an unmute button so the user can restore audio with one tap.
     */
    async function autoplayWithFallback(videoEl) {
        try {
            await videoEl.play();
        } catch (e) {
            // Autoplay blocked — mute and retry
            videoEl.muted = true;
            try {
                await videoEl.play();
                showUnmutePrompt(videoEl);
            } catch (e2) {
                console.warn('[CariApp] Autoplay failed even muted:', e2);
            }
        }
    }

    function showUnmutePrompt(videoEl) {
        // Avoid duplicates
        const existing = document.getElementById('unmutePrompt');
        if (existing) existing.remove();

        const container = videoEl.closest('.live-player-container') || videoEl.parentElement;
        const btn = document.createElement('button');
        btn.id = 'unmutePrompt';
        btn.className = 'unmute-prompt';
        btn.innerHTML = '<i class="lucide-volume-x"></i> Tap to unmute';
        btn.addEventListener('click', () => {
            videoEl.muted = false;
            btn.remove();
        });
        container.appendChild(btn);

        // Also remove on any user interaction with the video
        videoEl.addEventListener('volumechange', () => {
            if (!videoEl.muted) btn.remove();
        }, { once: true });
    }

    async function initShakaPlayer(videoEl, url) {
        // Destroy existing instance
        if (shakaPlayer) {
            try { await shakaPlayer.destroy(); } catch {}
            shakaPlayer = null;
        }

        if (!window.shaka) {
            videoEl.src = url;
            await autoplayWithFallback(videoEl);
            return;
        }

        shaka.polyfill.installAll();

        if (!shaka.Player.isBrowserSupported()) {
            videoEl.src = url;
            await autoplayWithFallback(videoEl);
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
            await autoplayWithFallback(videoEl);
        } catch (err) {
            console.error('Shaka load error:', err);
            // Fallback to direct source
            try { await shakaPlayer.destroy(); } catch {}
            shakaPlayer = null;
            videoEl.src = url;
            await autoplayWithFallback(videoEl);
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
        if (isContentLocked(item)) {
            CariRouter.navigate('/subscribe');
            return;
        }
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
