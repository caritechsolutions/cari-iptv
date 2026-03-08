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
    let ccEnabled = localStorage.getItem('cari_cc_enabled') === '1';
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

        // Initialize behavioral event tracker
        if (typeof CariTracker !== 'undefined') CariTracker.init();
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
            renderLayoutSections(el, layout.sections, 'home');
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

    function renderLayoutSections(el, sections, pageType) {
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
            } else if (type === 'continue_watching') {
                const cwPlaceholder = document.createElement('div');
                el.appendChild(cwPlaceholder);
                // Filter by page context: movies page → movie only, series page → series only, home → all
                const cwFilter = pageType === 'movies' ? 'movie' : pageType === 'series' ? 'series' : null;
                renderContinueWatchingRow(cwPlaceholder, cwFilter);
            } else if (type === 'category_grid') {
                const catPlaceholder = document.createElement('div');
                el.appendChild(catPlaceholder);
                renderCategorySection(catPlaceholder, title);
            } else if (type === 'packages_list') {
                renderPackagesSection(el, title, settings);
            } else if (type === 'recommended_for_you') {
                const recPlaceholder = document.createElement('div');
                el.appendChild(recPlaceholder);
                renderRecommendationSection(recPlaceholder, 'for_you', settings, pageType);
            } else if (type === 'because_you_watched') {
                const bywPlaceholder = document.createElement('div');
                el.appendChild(bywPlaceholder);
                renderRecommendationSection(bywPlaceholder, 'because_watched', settings, pageType);
            } else if (type === 'trending_now') {
                const trendPlaceholder = document.createElement('div');
                el.appendChild(trendPlaceholder);
                renderRecommendationSection(trendPlaceholder, 'trending', settings, pageType);
            } else if (type === 'top_picks') {
                const tpPlaceholder = document.createElement('div');
                el.appendChild(tpPlaceholder);
                renderRecommendationSection(tpPlaceholder, 'top_picks', settings, pageType);
            } else if (type === 'hidden_gems') {
                const hgPlaceholder = document.createElement('div');
                el.appendChild(hgPlaceholder);
                renderRecommendationSection(hgPlaceholder, 'hidden_gems', settings, pageType);
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

    /**
     * Render recommendation sections from the AI-powered recommendation engine.
     * Fetches pre-computed recommendations and renders them as content rows.
     */
    async function renderRecommendationSection(el, setType, settings, pageType) {
        if (!CariAPI.isAuthenticated()) return;
        try {
            const filterType = pageType === 'movies' ? 'movie' : pageType === 'series' ? 'series' : null;
            const res = await CariAPI.getRecommendations(filterType);
            const allSets = res?.data || [];

            // Filter to requested set type
            const matchingSets = allSets.filter(s => s.set_type === setType);
            if (!matchingSets.length) return;

            const cardStyle = settings?.card_style || 'poster';
            const maxItems = settings?.max_items || 15;
            const showMatch = settings?.show_match_percent !== false;

            matchingSets.forEach(set => {
                const items = (set.items || []).slice(0, maxItems);
                if (!items.length) return;

                // Add match_percent badge to items if enabled
                if (showMatch) {
                    items.forEach(item => {
                        if (item.match_percent) {
                            item._matchBadge = item.match_percent + '% Match';
                        }
                    });
                }

                const rowTitle = set.title || 'Recommended';
                appendContentRow(el, rowTitle, items, cardStyle);
            });
        } catch (err) {
            console.error('[CariApp] Recommendations failed:', err);
        }
    }

    async function renderContinueWatchingRow(el, filterType) {
        if (!CariAPI.isAuthenticated()) return;
        try {
            const res = await CariAPI.getContinueWatching(filterType || null);
            const items = res?.data || [];
            if (!items.length) return;

            // Enrich series items with resume info
            items.forEach(item => {
                if (item.content_type === 'series' && item.resume_season_number) {
                    item.year = 'S' + (item.resume_season_number || '1') + ' E' + (item.resume_episode_number || '');
                }
            });

            appendContentRow(el, 'Continue Watching', items, 'backdrop');
        } catch (err) {
            console.error('[CariApp] Continue watching failed:', err);
        }
    }

    function handleItemClick(item, type) {
        return () => {
            if (type === 'channel') {
                if (isContentLocked(item)) {
                    CariRouter.navigate('/subscribe');
                } else {
                    CariRouter.navigate('/live/' + item.id);
                }
            } else if (type === 'series') {
                // Navigate to series detail page — include season hash if resuming
                let path = '/series/' + item.id;
                if (item.resume_season_number) {
                    path += '?season=' + item.resume_season_number;
                }
                CariRouter.navigate(path);
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
                    renderLayoutSections(el, layout.sections, 'movies');
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
                    renderLayoutSections(el, layout.sections, 'series');
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
                                <div class="series-rating" id="seriesRating"></div>
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

            // Star rating widget
            CariUI.renderStarRating(document.getElementById('seriesRating'), 'series', show.id);

            // Render cast
            if (show.cast && show.cast.length) {
                CariUI.renderCastRow(document.getElementById('seriesCast'), show.cast, {
                    type: 'page',
                    path: '/series/' + id,
                    title: title
                });
            }

            // Render seasons/episodes — check for ?season= param to preselect
            if (seasons.length) {
                let initialIdx = 0;
                const urlParams = new URLSearchParams(window.location.search);
                const requestedSeason = parseInt(urlParams.get('season'));
                if (requestedSeason) {
                    const found = seasons.findIndex(s => s.season_number == requestedSeason);
                    if (found >= 0) initialIdx = found;
                }

                renderEpisodes(seasons[initialIdx], show.id);

                // Activate the correct tab
                if (initialIdx > 0) {
                    document.querySelectorAll('.season-tab').forEach((t, i) => {
                        t.classList.toggle('active', i === initialIdx);
                    });
                }

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

        // Render episodes (progress bars added async below)
        function renderEpisodeCards(progressMap) {
            list.innerHTML = episodes.map(ep => {
                const thumb = ep.still_url || '';
                const epTitle = ep.title || ep.name || 'Episode ' + ep.episode_number;
                const epNum = ep.episode_number || '';
                const epDesc = ep.synopsis || ep.overview || '';
                const runtime = ep.runtime || '';
                const rating = ep.vote_average || '';
                const hasStream = !!(ep.stream_url);

                // Progress bar
                const prog = progressMap[ep.id];
                let progressHtml = '';
                if (prog && prog.progress_seconds > 0 && prog.duration_seconds > 0) {
                    const pct = Math.min(Math.round((prog.progress_seconds / prog.duration_seconds) * 100), 100);
                    progressHtml = `<div class="episode-progress-bar"><div class="episode-progress-fill" style="width:${pct}%"></div></div>`;
                }

                return `
                    <div class="episode-card" data-episode-idx="${ep.id}">
                        <div class="episode-thumb">
                            ${thumb ? '<img src="' + CariUI.esc(thumb) + '" alt="" loading="lazy" onerror="this.style.display=\'none\'">' : ''}
                            ${hasStream ? '<div class="episode-play-overlay"><i class="lucide-play"></i></div>' : ''}
                            <span class="episode-number">E${CariUI.esc(String(epNum))}</span>
                            ${progressHtml}
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

        // First render without progress, then fetch progress async
        renderEpisodeCards({});

        if (CariAPI.isAuthenticated()) {
            const episodeIds = episodes.map(ep => ep.id).filter(Boolean);
            if (episodeIds.length) {
                CariAPI.getBatchWatchProgress('episode', episodeIds).then(res => {
                    const progressMap = res?.data || {};
                    if (Object.keys(progressMap).length > 0) {
                        renderEpisodeCards(progressMap);
                    }
                }).catch(() => {});
            }
        }
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

            const epg = person.epg || [];

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
                                ${epg.length ? '<span>' + epg.length + ' Upcoming Airing' + (epg.length !== 1 ? 's' : '') + '</span>' : ''}
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
                } else if (castSource.type === 'epg-modal') {
                    backBtn.innerHTML = '<i class="lucide-arrow-left"></i> Back to Live TV';
                    backBtn.addEventListener('click', () => {
                        CariRouter.navigate(castSource.fromPath || '/live');
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

            // EPG Upcoming Airings
            if (epg.length) {
                const epgSection = document.createElement('div');
                epgSection.className = 'person-epg-section';
                epgSection.innerHTML = `<h2 class="content-row-title"><i class="lucide-tv"></i> Upcoming on TV</h2>`;

                const epgList = document.createElement('div');
                epgList.className = 'person-epg-list';

                epg.forEach(item => {
                    const startDate = new Date(item.start_time);
                    const endDate = new Date(item.end_time);
                    const now = new Date();
                    const isLive = startDate <= now && endDate > now;
                    const dateStr = startDate.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
                    const timeStr = startDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
                    const endStr = endDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
                    const durationMin = Math.round((endDate - startDate) / 60000);

                    const card = document.createElement('div');
                    card.className = 'person-epg-card' + (isLive ? ' is-live' : '');
                    card.innerHTML = `
                        <div class="person-epg-channel">
                            <img src="${CariUI.esc(item.channel_logo || '')}" alt="" class="person-epg-channel-logo" onerror="this.style.display='none'">
                            <span class="person-epg-channel-name">${CariUI.esc(item.channel_name)}</span>
                        </div>
                        <div class="person-epg-details">
                            <div class="person-epg-title">${CariUI.esc(item.title)}${item.year ? ' <span class="person-epg-year">(' + CariUI.esc(item.year) + ')</span>' : ''}</div>
                            <div class="person-epg-time">
                                ${isLive ? '<span class="person-epg-live-badge">LIVE</span>' : ''}
                                <span>${CariUI.esc(dateStr)}</span>
                                <span>${CariUI.esc(timeStr)} - ${CariUI.esc(endStr)}</span>
                                <span>${durationMin} min</span>
                            </div>
                        </div>
                        <button class="person-epg-tune" title="Watch on ${CariUI.esc(item.channel_name)}"><i class="lucide-play"></i></button>
                    `;

                    // Tune to channel on click
                    card.querySelector('.person-epg-tune').addEventListener('click', (e) => {
                        e.stopPropagation();
                        CariRouter.navigate('/live/' + item.channel_id);
                    });
                    card.addEventListener('click', () => {
                        CariRouter.navigate('/live/' + item.channel_id);
                    });

                    epgList.appendChild(card);
                });

                epgSection.appendChild(epgList);
                filmEl.appendChild(epgSection);
            }

            if (!person.movies.length && !person.series.length && !epg.length) {
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
                            <div class="live-player-controls">
                                <button class="live-player-btn cc-toggle-btn${ccEnabled ? ' cc-active' : ''}" id="liveCCBtn" title="${ccEnabled ? 'Captions On' : 'Captions Off'}">CC</button>
                                <button class="live-player-btn live-player-fullscreen" id="liveFullscreenBtn" title="Fullscreen"><i class="lucide-maximize"></i></button>
                            </div>
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

            // CC toggle for live TV
            const liveCCBtn = document.getElementById('liveCCBtn');
            if (liveCCBtn) liveCCBtn.addEventListener('click', toggleCC);

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
                        <div class="epg-now-playing-icon"><div class="epg-eq-bar"><span></span><span></span><span></span></div></div>
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
            else if (e.key === 'c' || e.key === 'C') { toggleCC(); }
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

        // Fetch TMDB metadata asynchronously (with channel + description context)
        fetchProgrammeMetadata(programme.title, overlay, programme.description, channel?.id);
    }

    async function fetchProgrammeMetadata(title, overlay, description, channelId) {
        const posterEl = overlay.querySelector('#epgModalPoster');
        const backdropEl = overlay.querySelector('#epgModalBackdrop');
        const descEl = overlay.querySelector('#epgModalDesc');
        const tmdbMetaEl = overlay.querySelector('#epgModalTmdbMeta');
        const castEl = overlay.querySelector('#epgModalCast');

        try {
            const res = await CariAPI.getProgrammeInfo(title, description, channelId);
            const match = res?.data?.match;

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

            // Cast — map TMDB field names to what renderCastRow expects
            if (match.cast && match.cast.length && castEl) {
                const mappedCast = match.cast.map(c => ({
                    name: c.name,
                    character_name: c.character_name || c.character || '',
                    profile_image: c.profile_image || c.profile || c.profile_url || '',
                    tmdb_person_id: c.tmdb_person_id || '',
                }));
                CariUI.renderCastRow(castEl, mappedCast, { type: 'epg-modal', fromPath: CariRouter.getCurrentPath() });
            }

            // Directors
            if (match.directors && match.directors.length && castEl) {
                const dirEl = document.createElement('div');
                dirEl.className = 'epg-modal-directors';
                dirEl.innerHTML = `<span class="epg-modal-director-label">Director:</span> ${match.directors.map(d => CariUI.esc(typeof d === 'string' ? d : d.name)).join(', ')}`;
                castEl.insertBefore(dirEl, castEl.firstChild);
            }
        } catch (err) {
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

        // Clean up previous channel tracking
        if (video._liveTrackingCleanup) {
            video._liveTrackingCleanup();
            video._liveTrackingCleanup = null;
        }

        if (url && window.shaka) {
            await initShakaPlayer(video, url);
        } else if (url) {
            video.src = url;
            await autoplayWithFallback(video);
        }

        // Track live TV viewing — uses wall-clock elapsed time
        if (url) {
            const chId = channel.id;
            const startedAt = Date.now();
            let lastSaved = 0;
            let watchStartTracked = false;
            const handler = () => {
                const elapsed = Math.floor((Date.now() - startedAt) / 1000);
                if (!watchStartTracked && elapsed > 2) {
                    watchStartTracked = true;
                    if (typeof CariTracker !== 'undefined') CariTracker.watchStart('channel', chId, { duration: 0 });
                }
                if (elapsed > 0 && elapsed - lastSaved >= 10) {
                    lastSaved = elapsed;
                    CariAPI.updateWatchProgress('channel', chId, elapsed, 0).catch(() => {});
                }
            };
            video.addEventListener('timeupdate', handler);
            video._liveTrackingCleanup = () => video.removeEventListener('timeupdate', handler);
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

            // Track search event
            if (typeof CariTracker !== 'undefined') CariTracker.searchQuery(q, totalCount);

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

    // Active marker overlay state (cleaned up on navigation)
    let _markerCleanup = null;
    // Current watch item data (for pause overlay and in-place episode swap)
    let _currentWatchItem = null;
    let _currentWatchType = null;
    let _currentWatchMeta = { title: '', meta: '' };
    // Progress tracking cleanup
    let _progressTrackingCleanup = null;

    /**
     * Swap to a new episode in-place without leaving fullscreen.
     * This avoids the browser blocking requestFullscreen() on non-user-gesture contexts
     * (e.g. when the auto-play countdown timer fires).
     */
    async function _swapEpisodeInPlace(episodeId) {
        const video = document.getElementById('mainVideo');
        const container = document.getElementById('playerContainer');
        if (!video || !container) {
            // Fallback to normal navigation
            CariRouter.navigate('/watch/episode/' + episodeId);
            return;
        }

        try {
            // Fetch new episode data
            const res = await CariAPI.getEpisode(episodeId);
            const item = res?.data;
            if (!item || !item.stream_url) {
                CariRouter.navigate('/watch/episode/' + episodeId);
                return;
            }

            // Clean up old marker overlays and ads
            if (_markerCleanup) { _markerCleanup(); _markerCleanup = null; }
            if (typeof CariAdManager !== 'undefined') { CariAdManager.destroy(); }
            resetSubtitleOffset();

            // Remove any existing pause overlay
            const existingPauseOverlay = container.querySelector('.pause-info-overlay');
            if (existingPauseOverlay) existingPauseOverlay.remove();

            // Update URL bar without triggering route handler
            history.pushState(null, '', '/watch/episode/' + episodeId);

            // Load new stream
            const streamUrl = item.stream_url || item.video_url || '';
            const drmConfig = item.drm || null;
            if (streamUrl && window.shaka) {
                await initShakaPlayer(video, streamUrl, drmConfig);
            } else if (streamUrl) {
                video.src = streamUrl;
                video.play().catch(() => {});
            }

            // Load subtitles
            const subtitles = item.subtitles || [];
            if (subtitles.length > 0) {
                loadExternalSubtitles(video, subtitles);
            }

            // Update stored content info for pause overlay
            const displayTitle = item.series_title + ' \u2014 ' + (item.title || 'Episode ' + item.episode_number);
            const displayMeta = 'S' + (item.season_number || '') + ' E' + (item.episode_number || '');
            _currentWatchItem = item;
            _currentWatchType = 'episode';
            _currentWatchMeta = { title: displayTitle, meta: displayMeta };

            // Update top bar with new episode title
            _updateTopBar();

            // Update next/prev episode buttons
            _setupEpisodeNavButtons(item, 'episode');

            // Set up progress tracking for new episode
            if (_progressTrackingCleanup) { _progressTrackingCleanup(); }
            _progressTrackingCleanup = setupProgressTrackingWithCleanup(video, 'episode', item.id);

            // Set up new markers/auto-play
            const markers = item.markers || [];
            const nextEpisode = item.next_episode || null;
            if (markers.length || nextEpisode) {
                _markerCleanup = setupMarkerOverlays(video, markers, nextEpisode, 'episode', item);
            }

            // Update the details section below
            const nextEpHtml = _buildNextEpisodeHtml(nextEpisode);
            const details = document.getElementById('playerDetails');
            if (details) {
                details.innerHTML = `
                    <h2 class="player-details-title">${CariUI.esc(displayTitle)}</h2>
                    <div class="player-details-meta">
                        <span>${CariUI.esc(displayMeta)}</span>
                        ${item.year ? '<span>' + CariUI.esc(item.year) + '</span>' : ''}
                        ${item.runtime ? '<span>' + CariUI.esc(item.runtime) + ' min</span>' : ''}
                        ${item.vote_average ? '<span class="rating"><i class="lucide-star" style="font-size:.75rem"></i> ' + CariUI.esc(String(item.vote_average)) + '</span>' : ''}
                    </div>
                    <p class="player-details-desc">${CariUI.esc(item.description || item.synopsis || item.overview || '')}</p>
                    ${item.series_id ? '<button class="btn btn-secondary" id="backToSeries" style="margin-top:1rem"><i class="lucide-arrow-left"></i> Back to Series</button>' : ''}
                    ${nextEpHtml}
                `;

                if (item.series_id) {
                    document.getElementById('backToSeries')?.addEventListener('click', () => {
                        CariRouter.navigate('/series/' + item.series_id);
                    });
                }
                if (nextEpisode) {
                    document.getElementById('nextEpCard')?.addEventListener('click', () => {
                        CariRouter.navigate('/watch/episode/' + nextEpisode.id);
                    });
                }
            }
        } catch (err) {
            console.error('[CariApp] In-place episode swap failed, falling back to navigation:', err);
            CariRouter.navigate('/watch/episode/' + episodeId);
        }
    }

    function _buildNextEpisodeHtml(nextEpisode) {
        if (!nextEpisode) return '';
        const nextTitle = nextEpisode.title || 'Episode ' + nextEpisode.episode_number;
        const nextThumb = nextEpisode.still_url || '';
        return `
            <div class="next-episode-preview" id="nextEpPreview">
                <div class="next-ep-label">Next Episode</div>
                <div class="next-ep-card" id="nextEpCard">
                    ${nextThumb ? '<img class="next-ep-thumb" src="' + CariUI.esc(nextThumb) + '" alt="" onerror="this.style.display=\'none\'">' : ''}
                    <div class="next-ep-info">
                        <div class="next-ep-title">${CariUI.esc(nextTitle)}</div>
                        <div class="next-ep-meta">
                            ${nextEpisode.season_number ? 'S' + CariUI.esc(String(nextEpisode.season_number)) + ' ' : ''}E${CariUI.esc(String(nextEpisode.episode_number))}
                            ${nextEpisode.runtime ? ' &middot; ' + CariUI.esc(String(nextEpisode.runtime)) + ' min' : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Navigate to next episode: if in fullscreen, swap in-place; otherwise use normal route.
     */
    function _navigateToNextEpisode(episodeId) {
        const isFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
        if (isFs) {
            _swapEpisodeInPlace(episodeId);
        } else {
            CariRouter.navigate('/watch/episode/' + episodeId);
        }
    }

    /** Show/hide and wire up next/previous episode navigation buttons */
    function _setupEpisodeNavButtons(item, type) {
        const prevBtn = document.getElementById('vodPrevEp');
        const nextBtn = document.getElementById('vodNextEp');
        if (!prevBtn || !nextBtn) return;

        const prevEp = (type === 'episode' && item.prev_episode) ? item.prev_episode : null;
        const nextEp = (type === 'episode' && item.next_episode) ? item.next_episode : null;

        prevBtn.style.display = prevEp ? '' : 'none';
        nextBtn.style.display = nextEp ? '' : 'none';

        if (prevEp) {
            const newPrevBtn = prevBtn.cloneNode(true);
            prevBtn.parentNode.replaceChild(newPrevBtn, prevBtn);
            newPrevBtn.addEventListener('click', () => _navigateToNextEpisode(prevEp.id));
        }
        if (nextEp) {
            const newNextBtn = nextBtn.cloneNode(true);
            nextBtn.parentNode.replaceChild(newNextBtn, nextBtn);
            newNextBtn.addEventListener('click', () => _navigateToNextEpisode(nextEp.id));
        }
    }

    /** Update the top bar with current content title */
    function _updateTopBar() {
        const topBar = document.getElementById('vodTopBar');
        if (!topBar) return;
        const title = _currentWatchMeta.title || '';
        const meta = _currentWatchMeta.meta || '';
        topBar.innerHTML = `
            <div class="vod-top-bar-inner">
                <span class="vod-top-title">${CariUI.esc(title)}</span>
                ${meta ? '<span class="vod-top-meta">' + CariUI.esc(meta) + '</span>' : ''}
            </div>
        `;
    }

    async function pageWatch(params) {
        const el = content();
        const type = params.type;
        const id = params.id;

        // Clean up previous state
        if (_markerCleanup) { _markerCleanup(); _markerCleanup = null; }
        resetSubtitleOffset();

        el.innerHTML = `
            <div class="player-page">
                <div class="player-container" id="playerContainer">
                    <video id="mainVideo" autoplay playsinline></video>
                    <div class="vod-top-bar" id="vodTopBar"></div>
                    <div class="vod-controls visible" id="vodControls">
                        <div class="vod-progress-wrap" id="vodProgressWrap">
                            <div class="vod-progress-bar">
                                <div class="vod-progress-buffered" id="vodBuffered"></div>
                                <div class="vod-progress-filled" id="vodProgressFilled"></div>
                            </div>
                            <div class="vod-progress-thumb" id="vodProgressThumb"></div>
                            <div class="vod-progress-tooltip" id="vodProgressTooltip">0:00</div>
                        </div>
                        <div class="vod-controls-bar">
                            <button class="vod-ctrl-btn" id="vodPlayPause" title="Play"><i class="lucide-play"></i></button>
                            <button class="vod-ctrl-btn vod-ep-nav" id="vodPrevEp" title="Previous Episode" style="display:none"><i class="lucide-skip-back"></i></button>
                            <button class="vod-ctrl-btn" id="vodRewind" title="Rewind 10s"><i class="lucide-rotate-ccw"></i></button>
                            <button class="vod-ctrl-btn" id="vodForward" title="Forward 10s"><i class="lucide-rotate-cw"></i></button>
                            <button class="vod-ctrl-btn vod-ep-nav" id="vodNextEp" title="Next Episode" style="display:none"><i class="lucide-skip-forward"></i></button>
                            <div class="vod-volume-wrap">
                                <button class="vod-ctrl-btn" id="vodMuteBtn" title="Mute"><i class="lucide-volume-2"></i></button>
                                <input type="range" class="vod-volume-slider" id="vodVolumeSlider" min="0" max="1" step="0.05" value="1">
                            </div>
                            <span class="vod-time" id="vodTimeDisplay">0:00 / 0:00</span>
                            <div class="vod-controls-spacer"></div>
                            <button class="vod-ctrl-btn cc-toggle-btn${ccEnabled ? ' cc-active' : ''}" id="vodCCBtn" title="${ccEnabled ? 'Captions On' : 'Captions Off'}">CC</button>
                            <button class="vod-ctrl-btn" id="vodFsBtn" title="Fullscreen"><i class="lucide-maximize"></i></button>
                        </div>
                    </div>
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
            const drmConfig = item.drm || null;

            // Check for saved watch progress (movies/episodes only)
            let savedProgress = null;
            if ((type === 'movie' || type === 'episode') && CariAPI.isAuthenticated()) {
                try {
                    const contentType = type === 'episode' ? 'episode' : 'movie';
                    const res = await CariAPI.getWatchProgress(contentType, item.id);
                    if (res?.data && res.data.progress_seconds > 30 && !res.data.completed) {
                        savedProgress = res.data;
                    }
                } catch {}
            }

            // Initialize Ad Manager for this content
            const adContentType = type === 'episode' ? 'episode' : 'movie';
            if (typeof CariAdManager !== 'undefined') {
                CariAdManager.init({
                    video: video,
                    container: document.getElementById('playerContainer'),
                    platform: 'web',
                    packageId: CariAPI.getUser()?.package_id || null,
                    userId: CariAPI.getUser()?.id || null,
                    contentType: adContentType === 'movie' ? 'vod' : adContentType,
                    contentId: item.id,
                    categoryId: item.category_id || null,
                    onAdStart: function() { video.pause(); },
                    onAdEnd: function() { video.play().catch(function() {}); },
                });
            }

            // Play pre-roll ads, then start content
            const startContent = async () => {
                if (streamUrl && window.shaka) {
                    await initShakaPlayer(video, streamUrl, drmConfig);
                } else if (streamUrl) {
                    video.src = streamUrl;
                    video.play().catch(() => {});
                }

                // Load external subtitle tracks from API
                const subtitles = item.subtitles || [];
                if (subtitles.length > 0) {
                    loadExternalSubtitles(video, subtitles);
                }

                // Show resume prompt if we have saved progress
                if (savedProgress) {
                    showResumePrompt(video, savedProgress.progress_seconds, savedProgress.duration_seconds);
                }

                // Set up mid-roll ads at cue points
                if (typeof CariAdManager !== 'undefined' && item.runtime) {
                    CariAdManager.setupMidRolls(video, adContentType, item.id, item.runtime * 60);
                }

                // Load overlay ads (banners, text scrollers) after a delay
                if (typeof CariAdManager !== 'undefined') {
                    CariAdManager.loadOverlayAds();
                }
            };

            if (typeof CariAdManager !== 'undefined' && !savedProgress) {
                CariAdManager.playPreRoll(startContent);
            } else {
                await startContent();
            }

            // Set up custom VOD controls (play/pause, seek, volume, CC, fullscreen, auto-hide)
            setupVodControls(video, document.getElementById('playerContainer'));

            // Store current content info for pause overlay
            _currentWatchItem = item;
            _currentWatchType = type;
            _currentWatchMeta = { title: displayTitle || item.title || item.name || '', meta: displayMeta || '' };

            // Show next/prev episode buttons for series episodes
            _setupEpisodeNavButtons(item, type);

            // Populate top bar with title
            _updateTopBar();

            // Set up pause info overlay (shows content details when paused in fullscreen)
            setupPauseOverlay(video, document.getElementById('playerContainer'));

            // Track progress and watch events
            if (type === 'movie' || type === 'episode' || type === 'channel') {
                const trackType = type === 'episode' ? 'episode' : (type === 'channel' ? 'channel' : 'movie');
                setupProgressTracking(video, trackType, item.id);
            }

            // Set up skip intro / credits countdown / auto-play from markers
            const markers = item.markers || [];
            const nextEpisode = item.next_episode || null;
            if (markers.length || nextEpisode) {
                _markerCleanup = setupMarkerOverlays(video, markers, nextEpisode, type, item);
            }

            // Restore fullscreen if transitioning between episodes
            _restoreFullscreenState(document.getElementById('playerContainer'));

            // Render details
            const title = displayTitle || item.title || item.name || '';
            const details = document.getElementById('playerDetails');

            // Next episode info
            const nextEpHtml = _buildNextEpisodeHtml(type === 'episode' ? nextEpisode : null);

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
                ${nextEpHtml}
            `;

            // Back to series link
            if (type === 'episode' && item.series_id) {
                document.getElementById('backToSeries')?.addEventListener('click', () => {
                    CariRouter.navigate('/series/' + item.series_id);
                });
            }

            // Next episode card click
            if (nextEpisode) {
                document.getElementById('nextEpCard')?.addEventListener('click', () => {
                    CariRouter.navigate('/watch/episode/' + nextEpisode.id);
                });
            }
        } catch (err) {
            document.getElementById('playerDetails').innerHTML = CariUI.emptyState('lucide-alert-circle', 'Error', 'Failed to load content.');
        }
    }

    /**
     * Set up pause info overlay — when paused in fullscreen, shows a cinematic overlay
     * with content artwork, title and metadata so the viewer knows what they're watching.
     */
    function setupPauseOverlay(video, container) {
        if (!video || !container) return;

        let overlay = null;
        let showTimer = null;

        function buildOverlay() {
            const item = _currentWatchItem;
            if (!item) return null;

            const backdrop = item.backdrop_url || item.still_url || item.poster_url || '';
            const poster = item.poster_url || item.still_url || '';
            const logo = item.logo_url || '';
            const title = _currentWatchMeta.title || item.title || item.name || '';
            const meta = _currentWatchMeta.meta || '';
            const year = item.year || '';
            const runtime = item.runtime ? item.runtime + ' min' : '';
            const rating = item.vote_average ? item.vote_average : '';
            const genres = item.genres || item.genre || '';
            const desc = item.description || item.synopsis || item.overview || '';

            const el = document.createElement('div');
            el.className = 'pause-info-overlay';
            el.innerHTML = `
                ${backdrop ? '<div class="pause-backdrop" style="background-image:url(' + CariUI.esc(backdrop) + ')"></div>' : ''}
                <div class="pause-gradient"></div>
                <div class="pause-content">
                    <div class="pause-content-inner">
                        ${logo ? '<img class="pause-logo" src="' + CariUI.esc(logo) + '" alt="" onerror="this.style.display=\'none\'">' : ''}
                        ${!logo ? '<h2 class="pause-title">' + CariUI.esc(title) + '</h2>' : ''}
                        <div class="pause-meta">
                            ${meta ? '<span class="pause-meta-tag">' + CariUI.esc(meta) + '</span>' : ''}
                            ${year ? '<span class="pause-meta-item">' + CariUI.esc(year) + '</span>' : ''}
                            ${runtime ? '<span class="pause-meta-item">' + CariUI.esc(runtime) + '</span>' : ''}
                            ${rating ? '<span class="pause-meta-rating"><i class="lucide-star"></i> ' + CariUI.esc(String(rating)) + '</span>' : ''}
                            ${genres ? '<span class="pause-meta-item">' + CariUI.esc(typeof genres === 'string' ? genres : '') + '</span>' : ''}
                        </div>
                        ${desc ? '<p class="pause-desc">' + CariUI.esc(desc.length > 200 ? desc.substring(0, 200) + '...' : desc) + '</p>' : ''}
                        <div class="pause-status">
                            <i class="lucide-pause"></i>
                            <span>Paused</span>
                        </div>
                    </div>
                </div>
            `;
            return el;
        }

        function showPauseOverlay() {
            // Only show in fullscreen
            const isFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
            if (!isFs) return;
            if (overlay) return; // Already showing

            overlay = buildOverlay();
            if (!overlay) return;
            container.appendChild(overlay);
            // Trigger animation on next frame
            requestAnimationFrame(() => {
                if (overlay) overlay.classList.add('visible');
            });
        }

        function hidePauseOverlay() {
            if (showTimer) { clearTimeout(showTimer); showTimer = null; }
            if (!overlay) return;
            overlay.classList.remove('visible');
            const el = overlay;
            overlay = null;
            // Remove after fade-out transition
            setTimeout(() => { if (el.parentNode) el.remove(); }, 400);
        }

        video.addEventListener('pause', () => {
            // Delay slightly so quick pause/play doesn't flash the overlay
            if (showTimer) clearTimeout(showTimer);
            showTimer = setTimeout(showPauseOverlay, 600);
        });

        video.addEventListener('play', hidePauseOverlay);
        video.addEventListener('seeking', hidePauseOverlay);
    }

    /**
     * Set up all custom VOD controls: play/pause, seek bar, volume, CC, fullscreen, auto-hide.
     */
    function setupVodControls(video, container) {
        if (!video || !container) return;

        const controls = document.getElementById('vodControls');
        const playPauseBtn = document.getElementById('vodPlayPause');
        const rewindBtn = document.getElementById('vodRewind');
        const forwardBtn = document.getElementById('vodForward');
        const muteBtn = document.getElementById('vodMuteBtn');
        const volumeSlider = document.getElementById('vodVolumeSlider');
        const timeDisplay = document.getElementById('vodTimeDisplay');
        const progressWrap = document.getElementById('vodProgressWrap');
        const progressFilled = document.getElementById('vodProgressFilled');
        const progressThumb = document.getElementById('vodProgressThumb');
        const progressTooltip = document.getElementById('vodProgressTooltip');
        const bufferedBar = document.getElementById('vodBuffered');
        const vodCCBtn = document.getElementById('vodCCBtn');
        const fsBtn = document.getElementById('vodFsBtn');
        const topBar = document.getElementById('vodTopBar');

        // ---- Auto-hide timer ----
        let hideTimer = null;
        const HIDE_DELAY = 3000;

        function showControls() {
            container.classList.add('controls-visible');
            if (controls) controls.classList.add('visible');
            if (topBar) topBar.classList.add('visible');
        }

        function hideControls() {
            if (video.paused) return;
            container.classList.remove('controls-visible');
            if (controls) controls.classList.remove('visible');
            if (topBar) topBar.classList.remove('visible');
        }

        function resetHideTimer() {
            showControls();
            clearTimeout(hideTimer);
            hideTimer = setTimeout(hideControls, HIDE_DELAY);
        }

        container.addEventListener('mousemove', resetHideTimer);
        video.addEventListener('mousemove', resetHideTimer);
        container.addEventListener('touchstart', resetHideTimer, { passive: true });
        container.addEventListener('mouseleave', () => {
            clearTimeout(hideTimer);
            hideTimer = setTimeout(hideControls, 500);
        });

        // Keep controls visible while interacting with control bar
        if (controls) {
            controls.addEventListener('mouseenter', () => { clearTimeout(hideTimer); showControls(); });
            controls.addEventListener('mouseleave', resetHideTimer);
        }

        // Show controls when paused, auto-hide when playing
        video.addEventListener('pause', () => { clearTimeout(hideTimer); showControls(); });
        video.addEventListener('play', resetHideTimer);

        // ---- Play / Pause ----
        function updatePlayPauseIcon() {
            if (!playPauseBtn) return;
            const icon = playPauseBtn.querySelector('i');
            if (icon) icon.className = video.paused ? 'lucide-play' : 'lucide-pause';
            playPauseBtn.title = video.paused ? 'Play' : 'Pause';
        }

        if (playPauseBtn) {
            playPauseBtn.addEventListener('click', () => {
                if (video.paused) video.play().catch(() => {});
                else video.pause();
            });
        }

        video.addEventListener('click', (e) => {
            // Ignore double-click (handled by fullscreen)
            if (e.detail > 1) return;
            if (video.paused) video.play().catch(() => {});
            else video.pause();
        });

        video.addEventListener('play', updatePlayPauseIcon);
        video.addEventListener('pause', updatePlayPauseIcon);

        // ---- Rewind / Forward ----
        if (rewindBtn) rewindBtn.addEventListener('click', () => { video.currentTime = Math.max(0, video.currentTime - 10); });
        if (forwardBtn) forwardBtn.addEventListener('click', () => { video.currentTime = Math.min(video.duration || 0, video.currentTime + 10); });

        // ---- Volume ----
        function updateVolumeIcon() {
            if (!muteBtn) return;
            const icon = muteBtn.querySelector('i');
            if (!icon) return;
            if (video.muted || video.volume === 0) {
                icon.className = 'lucide-volume-x';
                muteBtn.title = 'Unmute';
            } else if (video.volume < 0.5) {
                icon.className = 'lucide-volume-1';
                muteBtn.title = 'Mute';
            } else {
                icon.className = 'lucide-volume-2';
                muteBtn.title = 'Mute';
            }
        }

        if (muteBtn) {
            muteBtn.addEventListener('click', () => {
                video.muted = !video.muted;
                updateVolumeIcon();
                if (volumeSlider) volumeSlider.value = video.muted ? 0 : video.volume;
            });
        }

        if (volumeSlider) {
            volumeSlider.addEventListener('input', () => {
                video.volume = parseFloat(volumeSlider.value);
                video.muted = video.volume === 0;
                updateVolumeIcon();
            });
            video.addEventListener('volumechange', () => {
                volumeSlider.value = video.muted ? 0 : video.volume;
                updateVolumeIcon();
            });
        }

        // ---- Time Display ----
        function updateTimeDisplay() {
            if (!timeDisplay) return;
            const cur = video.currentTime || 0;
            const dur = video.duration || 0;
            timeDisplay.textContent = formatPlayerTime(cur) + ' / ' + (isFinite(dur) ? formatPlayerTime(dur) : '0:00');
        }
        video.addEventListener('timeupdate', updateTimeDisplay);
        video.addEventListener('loadedmetadata', updateTimeDisplay);

        // ---- Progress / Seek Bar ----
        function updateProgress() {
            if (!progressFilled || !progressThumb) return;
            const dur = video.duration || 0;
            const pct = dur > 0 ? (video.currentTime / dur) * 100 : 0;
            progressFilled.style.width = pct + '%';
            progressThumb.style.left = pct + '%';
        }

        function updateBuffered() {
            if (!bufferedBar || !video.buffered || !video.duration) return;
            if (video.buffered.length > 0) {
                const end = video.buffered.end(video.buffered.length - 1);
                bufferedBar.style.width = (end / video.duration) * 100 + '%';
            }
        }

        video.addEventListener('timeupdate', updateProgress);
        video.addEventListener('progress', updateBuffered);

        if (progressWrap) {
            let isSeeking = false;

            function seekFromEvent(e) {
                const rect = progressWrap.getBoundingClientRect();
                const pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
                if (isFinite(video.duration) && video.duration > 0) {
                    video.currentTime = pct * video.duration;
                }
                updateProgress();
            }

            function showTooltip(e) {
                if (!progressTooltip) return;
                const rect = progressWrap.getBoundingClientRect();
                const pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
                const time = isFinite(video.duration) ? pct * video.duration : 0;
                progressTooltip.textContent = formatPlayerTime(time);
                progressTooltip.style.left = (pct * 100) + '%';
                progressTooltip.classList.add('visible');
            }

            progressWrap.addEventListener('mousedown', (e) => {
                isSeeking = true;
                seekFromEvent(e);
                e.preventDefault();
            });

            document.addEventListener('mousemove', (e) => {
                if (isSeeking) seekFromEvent(e);
            });

            document.addEventListener('mouseup', () => { isSeeking = false; });

            progressWrap.addEventListener('mousemove', showTooltip);
            progressWrap.addEventListener('mouseleave', () => {
                if (progressTooltip) progressTooltip.classList.remove('visible');
            });
        }

        // ---- CC Toggle ----
        if (vodCCBtn) vodCCBtn.addEventListener('click', toggleCC);

        // ---- Fullscreen ----
        // Override requestFullscreen on the video element to always fullscreen the container
        if (video.requestFullscreen) {
            video.requestFullscreen = function(opts) { return container.requestFullscreen(opts); };
        }
        if (video.webkitRequestFullscreen) {
            video.webkitRequestFullscreen = function(opts) {
                return (container.webkitRequestFullscreen || container.requestFullscreen).call(container, opts);
            };
        }

        if (fsBtn) {
            fsBtn.addEventListener('click', () => {
                const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
                if (fsEl) {
                    (document.exitFullscreen || document.webkitExitFullscreen).call(document);
                } else {
                    (container.requestFullscreen || container.webkitRequestFullscreen).call(container);
                }
            });
        }

        function onFullscreenChange() {
            const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
            if (fsBtn) {
                const icon = fsBtn.querySelector('i');
                if (icon) icon.className = fsEl ? 'lucide-minimize' : 'lucide-maximize';
            }
            // If video was fullscreened directly (bypass), swap to container
            if (fsEl === video) {
                const exitFn = document.exitFullscreen || document.webkitExitFullscreen;
                exitFn.call(document).then(() => {
                    (container.requestFullscreen || container.webkitRequestFullscreen).call(container);
                }).catch(() => {});
            }
        }
        document.addEventListener('fullscreenchange', onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', onFullscreenChange);

        // Double-click video → toggle fullscreen on container
        video.addEventListener('dblclick', (e) => {
            e.preventDefault();
            const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
            if (fsEl) {
                (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            } else {
                (container.requestFullscreen || container.webkitRequestFullscreen).call(container);
            }
        });

        // ---- Keyboard shortcuts ----
        function onKeyDown(e) {
            // Only handle when player is visible
            if (!document.getElementById('playerContainer')) return;
            // Don't hijack input fields
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

            switch (e.key) {
                case ' ':
                case 'k':
                    e.preventDefault();
                    if (video.paused) video.play().catch(() => {});
                    else video.pause();
                    resetHideTimer();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    video.currentTime = Math.max(0, video.currentTime - 10);
                    resetHideTimer();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    video.currentTime = Math.min(video.duration || 0, video.currentTime + 10);
                    resetHideTimer();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    video.volume = Math.min(1, video.volume + 0.1);
                    resetHideTimer();
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    video.volume = Math.max(0, video.volume - 0.1);
                    resetHideTimer();
                    break;
                case 'f':
                    e.preventDefault();
                    if (fsBtn) fsBtn.click();
                    break;
                case 'm':
                    e.preventDefault();
                    video.muted = !video.muted;
                    resetHideTimer();
                    break;
                case 'c':
                    e.preventDefault();
                    toggleCC();
                    resetHideTimer();
                    break;
                case 'n':
                case 'N':
                    e.preventDefault();
                    document.getElementById('vodNextEp')?.click();
                    break;
                case 'p':
                case 'P':
                    e.preventDefault();
                    document.getElementById('vodPrevEp')?.click();
                    break;
            }
        }
        document.addEventListener('keydown', onKeyDown);

        // Initial state
        updatePlayPauseIcon();
        updateVolumeIcon();
        resetHideTimer();
    }

    /**
     * Set up marker-based overlays: Skip Intro button, Credits countdown + auto-play next
     * Returns a cleanup function to remove listeners on navigation.
     */
    /**
     * Save fullscreen state before episode transition so we can restore it.
     */
    function _saveFullscreenState() {
        const isFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
        if (isFs) {
            sessionStorage.setItem('cari_restore_fs', '1');
        }
    }

    /**
     * Restore fullscreen state after episode transition.
     */
    function _restoreFullscreenState(container) {
        if (sessionStorage.getItem('cari_restore_fs') === '1') {
            sessionStorage.removeItem('cari_restore_fs');
            // Small delay to let DOM settle before requesting fullscreen
            setTimeout(() => {
                try {
                    const reqFs = container.requestFullscreen || container.webkitRequestFullscreen;
                    if (reqFs) reqFs.call(container);
                } catch (e) {
                    console.warn('[CariApp] Could not restore fullscreen:', e);
                }
            }, 300);
        }
    }

    function setupMarkerOverlays(video, markers, nextEpisode, type, item) {
        const container = document.getElementById('playerContainer');
        if (!container) return () => {};

        // Parse markers into a lookup
        const markerMap = {};
        markers.forEach(m => {
            markerMap[m.marker_type] = parseFloat(m.position_seconds);
        });

        const introStart = markerMap['intro_start'] ?? null;
        const introEnd = markerMap['intro_end'] ?? null;
        const creditsStart = markerMap['credits_start'] ?? null;

        let skipBtn = null;
        let countdownOverlay = null;
        let countdownTimer = null;
        let skipShown = false;
        let creditsTriggered = false;
        let autoPlaySeconds = 15;

        function onTimeUpdate() {
            const t = video.currentTime;

            // --- Skip Intro ---
            if (introStart !== null && introEnd !== null && introEnd > introStart) {
                if (t >= introStart && t < introEnd - 0.5) {
                    if (!skipBtn && !skipShown) {
                        skipBtn = document.createElement('button');
                        skipBtn.className = 'skip-intro-btn';
                        skipBtn.innerHTML = '<i class="lucide-skip-forward"></i> Skip Intro';
                        skipBtn.addEventListener('click', () => {
                            video.currentTime = introEnd;
                            removeSkipBtn();
                        });
                        container.appendChild(skipBtn);
                        // Auto-hide after the intro window
                        setTimeout(() => { if (skipBtn) skipBtn.classList.add('skip-intro-fade'); }, 200);
                    }
                } else {
                    removeSkipBtn();
                    if (t >= introEnd) skipShown = true;
                }
            }

            // --- Credits Countdown + Auto-Play Next ---
            if (creditsStart !== null && !creditsTriggered && t >= creditsStart) {
                creditsTriggered = true;

                if (nextEpisode && nextEpisode.stream_url) {
                    showAutoPlayCountdown(nextEpisode);
                }
            }
        }

        function removeSkipBtn() {
            if (skipBtn) {
                skipBtn.remove();
                skipBtn = null;
            }
        }

        function showAutoPlayCountdown(nextEp) {
            const nextTitle = nextEp.title || 'Episode ' + nextEp.episode_number;
            const nextThumb = nextEp.still_url || '';

            countdownOverlay = document.createElement('div');
            countdownOverlay.className = 'autoplay-countdown';
            countdownOverlay.innerHTML = `
                <div class="autoplay-countdown-inner">
                    <div class="autoplay-next-label">Up Next</div>
                    <div class="autoplay-next-info">
                        ${nextThumb ? '<img class="autoplay-next-thumb" src="' + CariUI.esc(nextThumb) + '" alt="">' : ''}
                        <div>
                            <div class="autoplay-next-title">${CariUI.esc(nextTitle)}</div>
                            <div class="autoplay-next-meta">
                                ${nextEp.season_number ? 'S' + nextEp.season_number + ' ' : ''}E${nextEp.episode_number || ''}
                            </div>
                        </div>
                    </div>
                    <div class="autoplay-timer">
                        <svg class="autoplay-ring" viewBox="0 0 36 36">
                            <circle class="autoplay-ring-bg" cx="18" cy="18" r="15.5" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="3"/>
                            <circle class="autoplay-ring-progress" cx="18" cy="18" r="15.5" fill="none" stroke="var(--p-primary, #6366f1)" stroke-width="3"
                                    stroke-dasharray="97.4" stroke-dashoffset="0" stroke-linecap="round"
                                    transform="rotate(-90 18 18)"/>
                        </svg>
                        <span class="autoplay-seconds">${autoPlaySeconds}</span>
                    </div>
                    <div class="autoplay-actions">
                        <button class="btn btn-play autoplay-play-btn" id="autoplayNow"><i class="lucide-play"></i> Play Now</button>
                        <button class="btn btn-secondary autoplay-cancel-btn" id="autoplayCancel"><i class="lucide-x"></i> Cancel</button>
                    </div>
                </div>
            `;

            container.appendChild(countdownOverlay);

            const ring = countdownOverlay.querySelector('.autoplay-ring-progress');
            const secondsEl = countdownOverlay.querySelector('.autoplay-seconds');
            let remaining = autoPlaySeconds;

            countdownTimer = setInterval(() => {
                remaining--;
                if (secondsEl) secondsEl.textContent = remaining;
                if (ring) {
                    const offset = 97.4 * (1 - remaining / autoPlaySeconds);
                    ring.style.strokeDashoffset = offset;
                }
                if (remaining <= 0) {
                    clearInterval(countdownTimer);
                    if (countdownOverlay) { countdownOverlay.remove(); countdownOverlay = null; }
                    _navigateToNextEpisode(nextEp.id);
                }
            }, 1000);

            // Play now button
            countdownOverlay.querySelector('#autoplayNow')?.addEventListener('click', () => {
                clearInterval(countdownTimer);
                if (countdownOverlay) { countdownOverlay.remove(); countdownOverlay = null; }
                _navigateToNextEpisode(nextEp.id);
            });

            // Cancel button
            countdownOverlay.querySelector('#autoplayCancel')?.addEventListener('click', () => {
                clearInterval(countdownTimer);
                countdownOverlay.remove();
                countdownOverlay = null;
            });
        }

        video.addEventListener('timeupdate', onTimeUpdate);

        // Also handle video ended — if no credits marker but there's a next episode
        function onEnded() {
            if (!creditsTriggered && nextEpisode && nextEpisode.stream_url) {
                creditsTriggered = true;
                showAutoPlayCountdown(nextEpisode);
            }
        }
        video.addEventListener('ended', onEnded);

        // Return cleanup function
        return function cleanup() {
            video.removeEventListener('timeupdate', onTimeUpdate);
            video.removeEventListener('ended', onEnded);
            removeSkipBtn();
            if (countdownTimer) clearInterval(countdownTimer);
            if (countdownOverlay) { countdownOverlay.remove(); countdownOverlay = null; }
        };
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

    async function initShakaPlayer(videoEl, url, drmConfig) {
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

        // Set video container so Shaka uses UITextDisplayer (DOM-based subtitle
        // rendering in .shaka-text-container) instead of NativeTextDisplayer.
        const playerContainer = videoEl.parentElement;
        if (playerContainer) {
            shakaPlayer.setVideoContainer(playerContainer);
        }

        const config = {
            streaming: {
                bufferingGoal: 15,
                rebufferingGoal: 1,
                retryParameters: { maxAttempts: 3, baseDelay: 1000 },
                alwaysStreamText: true,
                // Live stream tuning — prevent re-fetching segments
                // that are already buffered when playlist window is small
                inaccurateManifestTolerance: 2,
                segmentPrefetchLimit: 0,
            },
            preferredTextLanguage: 'en',
        };

        // Configure DRM if content is encrypted (ClearKey via license server)
        if (drmConfig && drmConfig.license_url) {
            const licenseUrl = drmConfig.license_url;
            config.drm = {
                servers: {
                    'org.w3.clearkey': licenseUrl,
                },
            };

            // Add auth token to license requests so the API middleware allows them
            shakaPlayer.getNetworkingEngine().registerRequestFilter((type, request) => {
                if (type === shaka.net.NetworkingEngine.RequestType.LICENSE) {
                    const token = localStorage.getItem('access_token');
                    if (token) {
                        request.headers['Authorization'] = 'Bearer ' + token;
                    }
                }
            });

            console.log('[CariApp] DRM enabled: ClearKey, scheme=' + (drmConfig.scheme || 'cenc'));
        }

        shakaPlayer.configure(config);

        shakaPlayer.addEventListener('error', (e) => {
            console.error('Shaka error:', e.detail);
        });

        try {
            await shakaPlayer.load(url);
            await autoplayWithFallback(videoEl);
            // Apply CC state — select first text track if CC is enabled
            applyCCState();
        } catch (err) {
            console.error('Shaka load error:', err);
            // Fallback to direct source
            try { await shakaPlayer.destroy(); } catch {}
            shakaPlayer = null;
            videoEl.src = url;
            await autoplayWithFallback(videoEl);
        }
    }

    /**
     * Load external VTT subtitle tracks into Shaka Player.
     * If a sync offset is set, fetches the VTT, adjusts timestamps, and
     * loads from a blob URL. Otherwise loads directly from the server URL.
     */
    function loadExternalSubtitles(videoEl, subtitles) {
        if (!shakaPlayer || !subtitles || subtitles.length === 0) return;
        // Store subtitle metadata on the video element for later reload (sync change)
        videoEl._cariSubtitles = subtitles;
        try {
            for (const sub of subtitles) {
                const vttUrl = sub.file_path;
                if (!vttUrl) continue;

                if (_subtitleOffset !== 0) {
                    // Fetch VTT, adjust timestamps, load from blob
                    fetch(vttUrl).then(r => r.text()).then(vttText => {
                        const adjusted = shiftVttTimestamps(vttText, _subtitleOffset);
                        const blob = new Blob([adjusted], { type: 'text/vtt' });
                        const blobUrl = URL.createObjectURL(blob);
                        shakaPlayer.addTextTrackAsync(
                            blobUrl,
                            sub.language_code || 'und',
                            'subtitle',
                            'text/vtt',
                            undefined,
                            sub.language_name || sub.language_code || 'Unknown'
                        ).catch(e => console.warn('[CariApp] Failed to add subtitle track:', sub.language_code, e));
                    }).catch(e => console.warn('[CariApp] Failed to fetch VTT for offset:', e));
                } else {
                    shakaPlayer.addTextTrackAsync(
                        vttUrl,
                        sub.language_code || 'und',
                        'subtitle',
                        '',
                        undefined,
                        sub.language_name || sub.language_code || 'Unknown'
                    ).catch(e => console.warn('[CariApp] Failed to add subtitle track:', sub.language_code, e));
                }
            }
            // Re-apply CC state after external tracks are added
            setTimeout(() => applyCCState(), 300);
        } catch (e) {
            console.warn('[CariApp] loadExternalSubtitles error:', e);
        }
    }

    /**
     * Shift all timestamps in a VTT string by offsetSeconds.
     * Positive = later, negative = earlier.
     */
    function shiftVttTimestamps(vttText, offsetSeconds) {
        // Match VTT timestamp lines: 00:01:23.456 --> 00:01:26.789
        return vttText.replace(
            /(\d{2}:\d{2}:\d{2}\.\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2}\.\d{3})/g,
            (match, start, end) => {
                const newStart = shiftTimestamp(start, offsetSeconds);
                const newEnd = shiftTimestamp(end, offsetSeconds);
                return newStart + ' --> ' + newEnd;
            }
        );
    }

    function shiftTimestamp(ts, offsetSeconds) {
        // Parse HH:MM:SS.mmm
        const parts = ts.split(':');
        const h = parseInt(parts[0]);
        const m = parseInt(parts[1]);
        const secMs = parts[2].split('.');
        const s = parseInt(secMs[0]);
        const ms = parseInt(secMs[1]);

        let totalMs = (h * 3600 + m * 60 + s) * 1000 + ms + Math.round(offsetSeconds * 1000);
        totalMs = Math.max(0, totalMs); // Don't go negative

        const nh = Math.floor(totalMs / 3600000);
        totalMs %= 3600000;
        const nm = Math.floor(totalMs / 60000);
        totalMs %= 60000;
        const ns = Math.floor(totalMs / 1000);
        const nms = totalMs % 1000;

        return String(nh).padStart(2, '0') + ':' +
               String(nm).padStart(2, '0') + ':' +
               String(ns).padStart(2, '0') + '.' +
               String(nms).padStart(3, '0');
    }

    /**
     * Apply current CC state to Shaka Player — select preferred track and show/hide.
     */
    function applyCCState() {
        if (!shakaPlayer) return;
        try {
            const tracks = shakaPlayer.getTextTracks();
            if (tracks.length > 0 && ccEnabled) {
                const savedLang = localStorage.getItem('cari_cc_lang') || 'en';
                // Try saved language, then CC1, then English, then first
                const preferred = tracks.find(t => t.language === savedLang)
                    || tracks.find(t => (t.label || '').toLowerCase().includes('cc1'))
                    || tracks.find(t => t.language === 'en')
                    || tracks[0];
                shakaPlayer.selectTextTrack(preferred);
            }
            shakaPlayer.setTextTrackVisibility(ccEnabled);
            // Offset is applied automatically at cue append time via OffsetTextDisplayer
        } catch (e) {
            console.warn('[CariApp] CC state error:', e);
        }
        updateCCButtons();
    }

    /**
     * Toggle closed captions — always show language picker if multiple tracks exist.
     */
    function toggleCC(e) {
        const tracks = shakaPlayer ? shakaPlayer.getTextTracks() : [];
        // Show menu whenever there are subtitle tracks (language + sync controls)
        if (tracks.length >= 1 && e && e.currentTarget) {
            const menu = document.getElementById('subtitleMenu');
            if (menu) {
                closeSubtitleMenu();
            } else {
                showSubtitleMenu(e.currentTarget);
            }
            return;
        }
        // No tracks at all — simple toggle
        ccEnabled = !ccEnabled;
        localStorage.setItem('cari_cc_enabled', ccEnabled ? '1' : '0');
        applyCCState();
        closeSubtitleMenu();
    }

    // Subtitle sync offset in seconds
    // Negative = subtitles appear earlier, Positive = subtitles appear later
    let _subtitleOffset = parseFloat(localStorage.getItem('cari_cc_offset') || '0');

    /**
     * Apply subtitle sync offset by reloading external VTT tracks with
     * adjusted timestamps. Modifies the VTT content in-memory and creates
     * a blob URL to replace the original track.
     */
    function applySubtitleOffset() {
        // Sync offset is applied when external subtitles are loaded via
        // loadExternalSubtitles(). Changing offset requires reloading tracks.
        if (!shakaPlayer) return;
        // Re-add external subtitles with the current offset by reloading
        const video = document.getElementById('mainVideo');
        if (!video || !video._cariSubtitles) return;
        // Remove all text tracks and re-add with offset
        const currentTracks = shakaPlayer.getTextTracks();
        const activeLang = (currentTracks.find(t => t.active) || {}).language;
        // Shaka doesn't have a removeTextTrack API, so reload the whole set
        loadExternalSubtitles(video, video._cariSubtitles);
        // Re-select the previously active language
        if (activeLang) {
            setTimeout(() => {
                const tracks = shakaPlayer.getTextTracks();
                const match = tracks.find(t => t.language === activeLang);
                if (match) {
                    shakaPlayer.selectTextTrack(match);
                    shakaPlayer.setTextTrackVisibility(ccEnabled);
                }
            }, 300);
        }
    }

    function resetSubtitleOffset() {
        // No state to reset — offset is read at load time
    }

    /**
     * Show subtitle language picker menu above the CC button.
     */
    function showSubtitleMenu(anchorBtn) {
        closeSubtitleMenu();
        if (!shakaPlayer) return;
        const tracks = shakaPlayer.getTextTracks();
        if (tracks.length === 0) return;

        const menu = document.createElement('div');
        menu.className = 'subtitle-menu';
        menu.id = 'subtitleMenu';

        const activeLang = ccEnabled
            ? (tracks.find(t => t.active) || {}).language || ''
            : '';

        // "Off" option
        let html = '<div class="subtitle-menu-item' + (!ccEnabled ? ' active' : '') + '" data-lang="off">Off</div>';
        // Language options — deduplicate by language code
        const seen = new Set();
        for (const t of tracks) {
            const lang = t.language || 'und';
            if (seen.has(lang)) continue;
            seen.add(lang);
            const label = t.label || t.language || 'Unknown';
            const isActive = ccEnabled && lang === activeLang;
            html += '<div class="subtitle-menu-item' + (isActive ? ' active' : '') + '" data-lang="' + CariUI.esc(lang) + '">' + CariUI.esc(label) + '</div>';
        }

        // Subtitle sync control
        // Negative = subs appear earlier, Positive = subs appear later
        const offsetVal = _subtitleOffset.toFixed(1);
        const offsetDisplay = (_subtitleOffset === 0) ? '0.0s'
            : (_subtitleOffset > 0 ? '+' + offsetVal + 's' : offsetVal + 's');
        html += '<div class="subtitle-menu-divider"></div>';
        html += '<div class="subtitle-sync-row">';
        html += '<span class="subtitle-sync-label">Sync</span>';
        html += '<button class="subtitle-sync-btn" data-sync="-0.5" title="Subtitles earlier">\u25C0</button>';
        html += '<span class="subtitle-sync-value" id="subtitleSyncValue">' + offsetDisplay + '</span>';
        html += '<button class="subtitle-sync-btn" data-sync="+0.5" title="Subtitles later">\u25B6</button>';
        html += '<button class="subtitle-sync-btn subtitle-sync-reset" data-sync="reset" title="Reset to 0">0</button>';
        html += '</div>';

        menu.innerHTML = html;

        // Position above the CC button
        const container = anchorBtn.closest('.vod-controls, .live-player-controls, .fs-overlay-bottom');
        if (container) {
            container.appendChild(menu);
        } else {
            anchorBtn.parentElement.appendChild(menu);
        }

        // Position near the button
        const btnRect = anchorBtn.getBoundingClientRect();
        const containerRect = (container || anchorBtn.parentElement).getBoundingClientRect();
        menu.style.position = 'absolute';
        menu.style.bottom = (containerRect.bottom - btnRect.top + 4) + 'px';
        menu.style.right = (containerRect.right - btnRect.right) + 'px';

        // Handle language clicks
        menu.addEventListener('click', (ev) => {
            const item = ev.target.closest('.subtitle-menu-item');
            if (item) {
                const lang = item.dataset.lang;
                if (lang === 'off') {
                    ccEnabled = false;
                } else {
                    ccEnabled = true;
                    localStorage.setItem('cari_cc_lang', lang);
                }
                localStorage.setItem('cari_cc_enabled', ccEnabled ? '1' : '0');
                applyCCState();
                closeSubtitleMenu();
                return;
            }

            // Handle sync buttons
            const syncBtn = ev.target.closest('.subtitle-sync-btn');
            if (syncBtn) {
                const action = syncBtn.dataset.sync;
                if (action === 'reset') {
                    _subtitleOffset = 0;
                } else {
                    _subtitleOffset = Math.round((_subtitleOffset + parseFloat(action)) * 10) / 10;
                    _subtitleOffset = Math.max(-10, Math.min(10, _subtitleOffset));
                }
                localStorage.setItem('cari_cc_offset', String(_subtitleOffset));
                applySubtitleOffset();
                const valEl = document.getElementById('subtitleSyncValue');
                if (valEl) {
                    const v = _subtitleOffset.toFixed(1);
                    valEl.textContent = (_subtitleOffset === 0) ? '0.0s'
                        : (_subtitleOffset > 0 ? '+' + v + 's' : v + 's');
                }
            }
        });

        // Close on outside click (delayed to avoid immediate close)
        setTimeout(() => {
            document.addEventListener('click', _subtitleMenuOutsideClick);
        }, 50);
    }

    function _subtitleMenuOutsideClick(e) {
        const menu = document.getElementById('subtitleMenu');
        if (menu && !menu.contains(e.target) && !e.target.closest('.cc-toggle-btn')) {
            closeSubtitleMenu();
        }
    }

    function closeSubtitleMenu() {
        const menu = document.getElementById('subtitleMenu');
        if (menu) menu.remove();
        document.removeEventListener('click', _subtitleMenuOutsideClick);
    }

    /**
     * Update all CC button icons to reflect current state.
     */
    function updateCCButtons() {
        document.querySelectorAll('.cc-toggle-btn').forEach(btn => {
            btn.classList.toggle('cc-active', ccEnabled);
            btn.title = ccEnabled ? 'Captions On' : 'Captions Off';
        });
    }

    function formatPlayerTime(seconds) {
        seconds = Math.floor(seconds);
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        return m + ':' + String(s).padStart(2, '0');
    }

    function showResumePrompt(video, progressSeconds, durationSeconds) {
        const container = document.getElementById('playerContainer');
        if (!container) return;

        const pct = durationSeconds > 0 ? Math.round((progressSeconds / durationSeconds) * 100) : 0;

        const prompt = document.createElement('div');
        prompt.className = 'resume-prompt';
        prompt.innerHTML = `
            <div class="resume-prompt-inner">
                <div class="resume-prompt-text">Resume from ${formatPlayerTime(progressSeconds)}?</div>
                ${durationSeconds > 0 ? '<div class="resume-prompt-bar"><div class="resume-prompt-bar-fill" style="width:' + pct + '%"></div></div>' : ''}
                <div class="resume-prompt-actions">
                    <button class="btn btn-play resume-btn" id="resumeYes"><i class="lucide-play"></i> Resume</button>
                    <button class="btn btn-secondary resume-btn" id="resumeNo"><i class="lucide-rotate-ccw"></i> Start Over</button>
                </div>
            </div>
        `;
        container.appendChild(prompt);

        // Pause until user decides
        video.pause();

        function dismiss() {
            prompt.classList.add('resume-prompt-hide');
            setTimeout(() => prompt.remove(), 300);
        }

        document.getElementById('resumeYes').addEventListener('click', () => {
            video.currentTime = progressSeconds;
            dismiss();
            video.play().catch(() => {});
        });

        document.getElementById('resumeNo').addEventListener('click', () => {
            dismiss();
            video.play().catch(() => {});
        });

        // Auto-dismiss after 10 seconds — resume by default
        setTimeout(() => {
            if (prompt.parentNode) {
                video.currentTime = progressSeconds;
                dismiss();
                video.play().catch(() => {});
            }
        }, 10000);
    }

    function setupProgressTracking(video, contentType, contentId) {
        let lastSaved = 0;
        let watchStartTracked = false;
        let watchCompleteTracked = false;
        const isLive = contentType === 'channel';
        const startedAt = Date.now();
        video.addEventListener('timeupdate', () => {
            const now = Math.floor(video.currentTime);
            const dur = Math.floor(video.duration || 0);
            const isFiniteDur = isFinite(dur) && dur > 0;
            // For live streams, track elapsed wall-clock time
            const elapsed = isLive ? Math.floor((Date.now() - startedAt) / 1000) : now;
            // Track watch_start once playback begins
            if (!watchStartTracked && (isLive ? elapsed > 2 : now > 2)) {
                watchStartTracked = true;
                if (typeof CariTracker !== 'undefined') CariTracker.watchStart(contentType, contentId, { duration: isFiniteDur ? dur : 0 });
            }
            // Track watch_complete once past 90% (VOD only, not live)
            if (!watchCompleteTracked && !isLive && isFiniteDur && now >= dur * 0.9) {
                watchCompleteTracked = true;
                if (typeof CariTracker !== 'undefined') CariTracker.watchComplete(contentType, contentId, { duration: dur, progress: now });
            }
            // Save progress every 10 seconds
            const saveCheck = isLive ? elapsed : now;
            if (saveCheck > 0 && saveCheck - lastSaved >= 10) {
                lastSaved = saveCheck;
                CariAPI.updateWatchProgress(contentType, contentId, elapsed, isFiniteDur ? dur : 0).catch(() => {});
            }
        });
    }

    /** Like setupProgressTracking but returns a cleanup function for in-place swaps */
    function setupProgressTrackingWithCleanup(video, contentType, contentId) {
        let lastSaved = 0;
        let watchStartTracked = false;
        let watchCompleteTracked = false;
        const handler = () => {
            const now = Math.floor(video.currentTime);
            const dur = Math.floor(video.duration || 0);
            if (!watchStartTracked && now > 2) {
                watchStartTracked = true;
                if (typeof CariTracker !== 'undefined') CariTracker.watchStart(contentType, contentId, { duration: dur });
            }
            if (!watchCompleteTracked && dur > 0 && now >= dur * 0.9) {
                watchCompleteTracked = true;
                if (typeof CariTracker !== 'undefined') CariTracker.watchComplete(contentType, contentId, { duration: dur, progress: now });
            }
            if (now > 0 && now - lastSaved >= 10) {
                lastSaved = now;
                CariAPI.updateWatchProgress(contentType, contentId, now, dur).catch(() => {});
            }
        };
        video.addEventListener('timeupdate', handler);
        return () => video.removeEventListener('timeupdate', handler);
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
