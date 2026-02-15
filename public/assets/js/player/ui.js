/**
 * CARI-IPTV UI Components
 * Renders content cards, sections, and page layouts
 */
const CariUI = (function() {
    const esc = (s) => {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    };

    const placeholderImg = 'data:image/svg+xml,' + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="450" fill="%23141422"><rect width="300" height="450"/><text x="150" y="225" text-anchor="middle" fill="%235a5f7a" font-family="sans-serif" font-size="14">No Image</text></svg>'
    );

    const placeholderBackdrop = 'data:image/svg+xml,' + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="169" fill="%23141422"><rect width="300" height="169"/></svg>'
    );

    let _castNavSource = null;

    // ---- Scroll Navigation ----

    function wrapWithScrollNav(row) {
        const wrap = document.createElement('div');
        wrap.className = 'content-row-wrap';

        const leftBtn = document.createElement('button');
        leftBtn.className = 'scroll-arrow scroll-arrow-left';
        leftBtn.type = 'button';
        leftBtn.innerHTML = '<i class="lucide-chevron-left"></i>';
        leftBtn.style.display = 'none';

        const rightBtn = document.createElement('button');
        rightBtn.className = 'scroll-arrow scroll-arrow-right';
        rightBtn.type = 'button';
        rightBtn.innerHTML = '<i class="lucide-chevron-right"></i>';

        wrap.appendChild(leftBtn);
        wrap.appendChild(row);
        wrap.appendChild(rightBtn);

        const scrollAmount = 600;

        leftBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            row.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });

        rightBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            row.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });

        function updateArrows() {
            var maxScroll = row.scrollWidth - row.clientWidth;
            leftBtn.style.display = (row.scrollLeft > 0) ? '' : 'none';
            rightBtn.style.display = (maxScroll > 0 && row.scrollLeft < maxScroll - 1) ? '' : 'none';
        }

        row.addEventListener('scroll', updateArrows);

        // Multiple retries to catch layout settling and lazy-loaded images
        function scheduleCheck() {
            setTimeout(updateArrows, 100);
            setTimeout(updateArrows, 500);
            setTimeout(updateArrows, 1500);
        }

        if (window.ResizeObserver) {
            new ResizeObserver(updateArrows).observe(row);
        }

        // Check immediately when added to DOM via MutationObserver
        if (window.MutationObserver) {
            var mo = new MutationObserver(function() {
                if (row.isConnected) {
                    mo.disconnect();
                    scheduleCheck();
                }
            });
            mo.observe(document.body, { childList: true, subtree: true });
        }

        scheduleCheck();

        return wrap;
    }

    // ---- Cards ----

    function posterCard(item, onclick, isLocked) {
        const img = item.poster || item.poster_url || item.image_url || item.logo_url || placeholderImg;
        const title = item.title || item.name || '';
        const year = item.year || item.release_year || '';
        const rating = item.rating || item.vote_average || '';
        const locked = isLocked === true;

        const el = document.createElement('div');
        el.className = 'card-poster' + (locked ? ' card-locked' : '');
        el.innerHTML = `
            <img class="card-poster-img" src="${esc(img)}" alt="${esc(title)}" loading="lazy"
                 onerror="this.src='${placeholderImg}'">
            ${locked ? '<div class="card-lock-overlay"><i class="lucide-lock"></i></div>' : ''}
            <div class="card-poster-title">${esc(title)}</div>
            <div class="card-poster-meta">${esc(year)}${rating ? ' &middot; ' + esc(String(rating)) : ''}</div>
        `;
        if (onclick) el.addEventListener('click', () => onclick(item));
        return el;
    }

    function backdropCard(item, onclick, isLocked) {
        const img = item.backdrop || item.backdrop_url || item.image_url || placeholderBackdrop;
        const title = item.title || item.name || '';
        const meta = item.year || '';
        const locked = isLocked === true;

        const el = document.createElement('div');
        el.className = 'card-backdrop' + (locked ? ' card-locked' : '');
        el.innerHTML = `
            <img class="card-backdrop-img" src="${esc(img)}" alt="${esc(title)}" loading="lazy"
                 onerror="this.src='${placeholderBackdrop}'">
            ${locked ? '<div class="card-lock-overlay"><i class="lucide-lock"></i></div>' : ''}
            <div class="card-backdrop-title">${esc(title)}</div>
            <div class="card-backdrop-meta">${esc(meta)}</div>
        `;
        if (onclick) el.addEventListener('click', () => onclick(item));
        return el;
    }

    function channelCard(channel, onclick, isLocked) {
        const logo = channel.logo_url || channel.logo || placeholderImg;
        const name = channel.name || channel.title || '';
        const locked = isLocked === true;

        const el = document.createElement('div');
        el.className = 'card-channel' + (locked ? ' card-locked' : '');
        el.innerHTML = `
            <img class="card-channel-logo" src="${esc(logo)}" alt="${esc(name)}" loading="lazy"
                 onerror="this.src='${placeholderImg}'">
            ${locked ? '<div class="card-lock-overlay"><i class="lucide-lock"></i></div>' : ''}
            <div class="card-channel-name">${esc(name)}</div>
            <div class="card-channel-now"></div>
        `;
        if (onclick) el.addEventListener('click', () => onclick(channel));
        return el;
    }

    function categoryCard(cat, onclick) {
        const icon = cat.icon || 'lucide-folder';
        const name = cat.name || '';

        const el = document.createElement('div');
        el.className = 'card-category';
        el.innerHTML = `
            <i class="${esc(icon)}"></i>
            <div class="card-category-name">${esc(name)}</div>
        `;
        if (onclick) el.addEventListener('click', () => onclick(cat));
        return el;
    }

    // ---- Sections ----

    function renderHero(items, onPlay, onInfo, isLockedFn) {
        if (!items || !items.length) return '';

        const container = document.createElement('div');
        container.className = 'hero';

        items.forEach((item, i) => {
            const bg = item.backdrop || item.backdrop_url || item.image_url || '';
            const title = item.title || item.name || '';
            const desc = item.description || item.synopsis || item.overview || '';
            const year = item.year || item.release_year || '';
            const rating = item.rating || item.vote_average || '';
            const locked = typeof isLockedFn === 'function' ? isLockedFn(item) : false;

            const slide = document.createElement('div');
            slide.className = 'hero-slide' + (i === 0 ? ' active' : '');
            slide.innerHTML = `
                <img class="hero-backdrop" src="${esc(bg)}" alt="" loading="${i === 0 ? 'eager' : 'lazy'}"
                     onerror="this.style.display='none'">
                <div class="hero-gradient"></div>
                <div class="hero-content">
                    ${item.content_type ? '<div class="hero-badge">' + esc(item.content_type) + '</div>' : ''}
                    ${locked ? '<div class="hero-lock-badge"><i class="lucide-lock"></i> Premium</div>' : ''}
                    <h2 class="hero-title">${esc(title)}</h2>
                    <div class="hero-meta">
                        ${year ? '<span>' + esc(year) + '</span>' : ''}
                        ${year && rating ? '<span class="hero-meta-dot"></span>' : ''}
                        ${rating ? '<span class="rating"><i class="lucide-star" style="font-size:.75rem"></i> ' + esc(String(rating)) + '</span>' : ''}
                    </div>
                    ${desc ? '<p class="hero-description">' + esc(desc) + '</p>' : ''}
                    <div class="hero-actions">
                        ${locked
                            ? '<button class="btn btn-subscribe" data-action="subscribe"><i class="lucide-credit-card"></i> Subscribe</button>'
                            : '<button class="btn btn-play" data-action="play"><i class="lucide-play"></i> Play</button>'}
                        <button class="btn btn-info" data-action="info"><i class="lucide-info"></i> More Info</button>
                    </div>
                </div>
            `;

            if (locked) {
                slide.querySelector('[data-action="subscribe"]').addEventListener('click', () => {
                    CariRouter.navigate('/subscribe');
                });
            } else {
                slide.querySelector('[data-action="play"]').addEventListener('click', () => onPlay && onPlay(item));
            }
            slide.querySelector('[data-action="info"]').addEventListener('click', () => onInfo && onInfo(item));

            container.appendChild(slide);
        });

        // Dots
        if (items.length > 1) {
            const dots = document.createElement('div');
            dots.className = 'hero-dots';
            items.forEach((_, i) => {
                const dot = document.createElement('div');
                dot.className = 'hero-dot' + (i === 0 ? ' active' : '');
                dot.addEventListener('click', () => setHeroSlide(container, i));
                dots.appendChild(dot);
            });
            container.appendChild(dots);

            // Auto-rotate
            let current = 0;
            setInterval(() => {
                current = (current + 1) % items.length;
                setHeroSlide(container, current);
            }, 8000);
        }

        return container;
    }

    function setHeroSlide(container, index) {
        container.querySelectorAll('.hero-slide').forEach((s, i) => {
            s.classList.toggle('active', i === index);
        });
        container.querySelectorAll('.hero-dot').forEach((d, i) => {
            d.classList.toggle('active', i === index);
        });
    }

    function renderContentRow(title, items, cardStyle, onItemClick, onSeeAll) {
        if (!items || !items.length) return null;

        const section = document.createElement('div');
        section.className = 'content-section';

        const header = document.createElement('div');
        header.className = 'section-header';
        header.innerHTML = `<h3 class="section-title">${esc(title)}</h3>`;

        if (onSeeAll) {
            const link = document.createElement('span');
            link.className = 'section-see-all';
            link.textContent = 'See All';
            link.addEventListener('click', onSeeAll);
            header.appendChild(link);
        }

        section.appendChild(header);

        const row = document.createElement('div');
        row.className = 'content-row';

        items.forEach(item => {
            let card;
            if (cardStyle === 'backdrop') {
                card = backdropCard(item, onItemClick);
            } else if (cardStyle === 'channel') {
                card = channelCard(item, onItemClick);
            } else {
                card = posterCard(item, onItemClick);
            }
            row.appendChild(card);
        });

        section.appendChild(wrapWithScrollNav(row));
        return section;
    }

    function renderBanner(settings) {
        if (!settings || !settings.image_url) return null;
        const el = document.createElement('div');
        el.className = 'content-section';
        el.innerHTML = `
            <div class="promo-banner">
                <img src="${esc(settings.image_url)}" alt="" loading="lazy">
            </div>
        `;
        if (settings.link_url) {
            el.querySelector('.promo-banner').style.cursor = 'pointer';
            el.querySelector('.promo-banner').addEventListener('click', () => {
                CariRouter.navigate(settings.link_url);
            });
        }
        return el;
    }

    function renderTextDivider(title) {
        const el = document.createElement('div');
        el.className = 'content-section text-divider';
        el.innerHTML = `<h2>${esc(title)}</h2>`;
        return el;
    }

    // ---- Detail Modal ----

    function showDetail(item, onPlay, isLocked) {
        const type = item.content_type || '';

        // Series: navigate to dedicated detail page instead of modal
        if (type === 'series') {
            CariRouter.navigate('/series/' + item.id);
            return;
        }

        const overlay = document.getElementById('detailOverlay');
        const modal = document.getElementById('detailModal');
        const locked = isLocked === true;

        const backdrop = item.backdrop || item.backdrop_url || '';
        const title = item.title || item.name || '';
        const year = item.year || item.release_year || '';
        const rating = item.rating || item.vote_average || '';
        const duration = item.duration || item.runtime || '';
        const genres = item.genres || item.genre || '';
        const desc = item.description || item.synopsis || item.overview || '';

        modal.innerHTML = `
            <button class="detail-close"><i class="lucide-x"></i></button>
            ${backdrop ? '<img class="detail-backdrop" src="' + esc(backdrop) + '" alt="" onerror="this.style.display=\'none\'">' : ''}
            <div class="detail-body">
                ${locked ? '<div class="detail-lock-badge"><i class="lucide-lock"></i> Premium Content</div>' : ''}
                <h2 class="detail-title">${esc(title)}</h2>
                <div class="detail-meta">
                    ${year ? '<span>' + esc(year) + '</span>' : ''}
                    ${duration ? '<span>' + esc(duration) + ' min</span>' : ''}
                    ${rating ? '<span class="rating"><i class="lucide-star" style="font-size:.75rem"></i> ' + esc(String(rating)) + '</span>' : ''}
                    ${genres ? '<span>' + esc(genres) + '</span>' : ''}
                </div>
                ${desc ? '<p class="detail-desc">' + esc(desc) + '</p>' : ''}
                <div class="detail-actions">
                    ${locked
                        ? '<button class="btn btn-subscribe" id="detailSubscribe"><i class="lucide-credit-card"></i> Subscribe to Watch</button>'
                        : '<button class="btn btn-play" id="detailPlay"><i class="lucide-play"></i> Play</button>'}
                    <button class="btn btn-secondary" id="detailTrailer" style="display:none"><i class="lucide-clapperboard"></i> Trailer</button>
                    <button class="btn btn-icon" id="detailWatchlist" title="Add to Watchlist"><i class="lucide-plus"></i></button>
                </div>
                <div class="trailer-embed" id="trailerEmbed" style="display:none"></div>
                <div class="detail-cast" id="detailCast"></div>
            </div>
        `;

        modal.querySelector('.detail-close').addEventListener('click', hideDetail);

        if (locked) {
            modal.querySelector('#detailSubscribe').addEventListener('click', () => {
                hideDetail();
                CariRouter.navigate('/subscribe');
            });
        } else {
            modal.querySelector('#detailPlay').addEventListener('click', () => {
                hideDetail();
                if (onPlay) onPlay(item);
            });
        }

        modal.querySelector('#detailWatchlist').addEventListener('click', async () => {
            const ctype = item.content_type || (item.stream_url ? 'channel' : 'movie');
            try {
                const res = await CariAPI.toggleWatchlist(ctype, item.id);
                const btn = modal.querySelector('#detailWatchlist i');
                btn.className = res?.data?.in_watchlist ? 'lucide-check' : 'lucide-plus';
            } catch {}
        });

        // Fetch full details for trailers + cast (async, non-blocking)
        // Only fetch for movie items — channels don't have trailers/cast
        if (item.id && type !== 'channel') {
            fetchFullDetails(item, modal);
        }

        overlay.classList.add('visible');
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) hideDetail();
        });
    }

    async function fetchFullDetails(item, modal) {
        try {
            const res = await CariAPI.getMovie(item.id);
            const full = res?.data;
            if (!full) {
                console.warn('[CariUI] getMovie returned no data for id:', item.id);
                return;
            }

            // Trailers
            if (full.trailers && full.trailers.length) {
                const trailerBtn = modal.querySelector('#detailTrailer');
                if (trailerBtn) {
                    trailerBtn.style.display = '';

                    let trailerOpen = false;
                    trailerBtn.addEventListener('click', () => {
                        const embed = modal.querySelector('#trailerEmbed');
                        if (!embed) return;
                        trailerOpen = !trailerOpen;
                        if (trailerOpen) {
                            const trailer = full.trailers[0];
                            const key = trailer.video_key || '';
                            if (key) {
                                embed.innerHTML = `<iframe src="https://www.youtube.com/embed/${esc(key)}?autoplay=1&rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
                            } else if (trailer.url) {
                                embed.innerHTML = `<iframe src="${esc(trailer.url)}" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
                            }
                            embed.style.display = '';
                            trailerBtn.innerHTML = '<i class="lucide-x"></i> Close Trailer';
                        } else {
                            embed.innerHTML = '';
                            embed.style.display = 'none';
                            trailerBtn.innerHTML = '<i class="lucide-clapperboard"></i> Trailer';
                        }
                    });
                }
            }

            // Cast
            const castContainer = modal.querySelector('#detailCast');
            if (full.cast && full.cast.length) {
                renderCastRow(castContainer, full.cast, {
                    type: 'modal',
                    item: item,
                    fromPath: (typeof CariRouter !== 'undefined') ? CariRouter.getCurrentPath() : '/'
                });
            }
        } catch (err) {
            console.error('[CariUI] Failed to fetch movie details for id:', item.id, err);
        }
    }

    function renderCastRow(container, cast, source) {
        if (!container || !cast || !cast.length) return;

        const placeholderAvatar = 'data:image/svg+xml,' + encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="180" fill="%231e293b"><rect width="120" height="180"/><text x="60" y="100" text-anchor="middle" fill="%235a5f7a" font-family="sans-serif" font-size="32">?</text></svg>'
        );

        container.innerHTML = `
            <h3 class="cast-section-title">Cast</h3>
            <div class="cast-row">
                ${cast.map(person => {
                    const img = person.profile_image || person.profile_url || '';
                    const personId = person.tmdb_person_id || '';
                    return `
                        <div class="cast-card" ${personId ? 'data-person-id="' + esc(String(personId)) + '"' : ''}>
                            <img class="cast-photo" src="${img ? esc(img) : placeholderAvatar}" alt="${esc(person.name)}" loading="lazy"
                                 onerror="this.src='${placeholderAvatar}'">
                            <div class="cast-name">${esc(person.name)}</div>
                            ${person.character_name ? '<div class="cast-character">' + esc(person.character_name) + '</div>' : ''}
                        </div>
                    `;
                }).join('')}
            </div>
        `;

        // Make cast cards clickable (navigate to person page)
        container.querySelectorAll('.cast-card[data-person-id]').forEach(card => {
            card.style.cursor = 'pointer';
            card.addEventListener('click', () => {
                const pid = card.dataset.personId;
                if (pid) {
                    _castNavSource = source || null;
                    // Close EPG modal if inside one, otherwise close detail overlay
                    const epgOverlay = card.closest('.epg-modal-overlay');
                    if (epgOverlay) epgOverlay.remove();
                    else hideDetail();
                    CariRouter.navigate('/person/' + pid);
                }
            });
        });
    }

    function hideDetail() {
        const overlay = document.getElementById('detailOverlay');
        overlay.classList.remove('visible');
        // Stop any playing trailers
        const embed = document.querySelector('#trailerEmbed');
        if (embed) { embed.innerHTML = ''; embed.style.display = 'none'; }
    }

    // ---- Loading / Empty ----

    function loading() {
        return '<div class="loading-screen"><div class="loading-spinner"></div></div>';
    }

    function emptyState(icon, title, message) {
        return `<div class="empty-state"><i class="${esc(icon)}"></i><h3>${esc(title)}</h3><p>${esc(message)}</p></div>`;
    }

    // ---- Skeleton loaders ----

    function skeletonRow(count, style) {
        const w = style === 'backdrop' ? 300 : 170;
        const h = style === 'backdrop' ? 169 : 255;
        let html = '<div class="content-section"><div class="section-header"><div class="skeleton" style="width:150px;height:20px"></div></div><div class="content-row">';
        for (let i = 0; i < count; i++) {
            html += `<div style="flex-shrink:0;width:${w}px"><div class="skeleton" style="width:${w}px;height:${h}px"></div><div class="skeleton" style="width:80%;height:14px;margin-top:8px"></div></div>`;
        }
        html += '</div></div>';
        return html;
    }

    function getCastNavSource() { return _castNavSource; }
    function clearCastNavSource() { _castNavSource = null; }

    return {
        esc, posterCard, backdropCard, channelCard, categoryCard,
        renderHero, renderContentRow, renderBanner, renderTextDivider,
        showDetail, hideDetail, renderCastRow, loading, emptyState, skeletonRow,
        wrapWithScrollNav, getCastNavSource, clearCastNavSource,
    };
})();
