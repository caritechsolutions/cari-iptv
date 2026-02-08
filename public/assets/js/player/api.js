/**
 * CARI-IPTV API Client
 * Handles all API communication with JWT authentication
 */
const CariAPI = (function() {
    const BASE = '/api/v1';
    let _cacheBust = '';

    function getAccessToken() {
        return localStorage.getItem('access_token');
    }

    function getRefreshToken() {
        return localStorage.getItem('refresh_token');
    }

    function isTokenExpired() {
        const expires = parseInt(localStorage.getItem('token_expires') || '0', 10);
        return Date.now() >= expires - 30000; // refresh 30s before expiry
    }

    function getUser() {
        try {
            return JSON.parse(localStorage.getItem('user') || 'null');
        } catch { return null; }
    }

    function clearAuth() {
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
        localStorage.removeItem('token_expires');
        localStorage.removeItem('user');
    }

    function isAuthenticated() {
        return !!getAccessToken();
    }

    async function refreshToken() {
        const token = getRefreshToken();
        if (!token) return false;

        try {
            const res = await fetch(BASE + '/auth/refresh', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ refresh_token: token }),
            });

            if (!res.ok) {
                clearAuth();
                return false;
            }

            const data = await res.json();
            const d = data.data;
            localStorage.setItem('access_token', d.access_token);
            localStorage.setItem('token_expires', Date.now() + (d.expires_in * 1000));
            return true;
        } catch {
            return false;
        }
    }

    async function request(method, path, body) {
        // Auto-refresh token if expired
        if (isTokenExpired() && getRefreshToken()) {
            const ok = await refreshToken();
            if (!ok) {
                window.location.href = '/login';
                return null;
            }
        }

        const headers = {
            'Content-Type': 'application/json',
        };

        const token = getAccessToken();
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }

        const opts = { method, headers };
        if (body && method !== 'GET') {
            opts.body = JSON.stringify(body);
        }

        const res = await fetch(BASE + path, opts);

        // Handle 401 — try refresh once
        if (res.status === 401 && getRefreshToken()) {
            const ok = await refreshToken();
            if (ok) {
                headers['Authorization'] = 'Bearer ' + getAccessToken();
                const retry = await fetch(BASE + path, { method, headers, body: opts.body });
                if (retry.ok) return retry.json();
            }
            clearAuth();
            window.location.href = '/login';
            return null;
        }

        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.error?.message || 'Request failed');
        }

        return res.json();
    }

    function get(path) {
        if (_cacheBust) {
            path += (path.includes('?') ? '&' : '?') + '_v=' + _cacheBust;
        }
        return request('GET', path);
    }
    function post(path, body) { return request('POST', path, body); }

    function bustAllCaches() {
        _cacheBust = String(Date.now());
    }
    function clearCacheBust() {
        _cacheBust = '';
    }

    async function logout() {
        const token = getRefreshToken();
        if (token) {
            try { await post('/auth/logout', { refresh_token: token }); } catch {}
        }
        clearAuth();
        window.location.href = '/login';
    }

    // ---- Content API helpers ----

    function getAppConfig() { return get('/app/config/web'); }
    function getLayout(layoutId) {
        const q = layoutId ? '?id=' + layoutId : '';
        return get('/app/layout/web' + q);
    }
    function getNavigation() { return get('/app/navigation/web'); }
    function getPages() { return get('/app/pages/web'); }
    function getManifest() {
        // Always bypass browser cache for manifest checks
        return request('GET', '/manifest?_t=' + Date.now());
    }

    function getChannels(params) {
        const q = params ? '?' + new URLSearchParams(params) : '';
        return get('/channels' + q);
    }

    function getChannel(id) { return get('/channels/' + id); }

    function getMovies(params) {
        const q = params ? '?' + new URLSearchParams(params) : '';
        return get('/movies' + q);
    }

    function getMovie(id) { return get('/movies/' + id); }

    function getSeries(params) {
        const q = params ? '?' + new URLSearchParams(params) : '';
        return get('/series' + q);
    }

    function getSeriesDetail(id) { return get('/series/' + id); }
    function getEpisode(id) { return get('/episodes/' + id); }

    function getCategories(params) {
        const q = params ? '?' + new URLSearchParams(params) : '';
        return get('/categories' + q);
    }

    function search(query, type) {
        return get('/search?q=' + encodeURIComponent(query) + (type ? '&type=' + type : ''));
    }

    function getEpg(channelId) {
        return get(channelId ? '/epg/' + channelId : '/epg');
    }

    // User actions
    function updateWatchProgress(contentType, contentId, progress, duration) {
        return post('/auth/watch-progress', { content_type: contentType, content_id: contentId, progress, duration });
    }

    function getContinueWatching() { return get('/auth/continue-watching'); }
    function toggleWatchlist(contentType, contentId) { return post('/auth/watchlist/toggle', { content_type: contentType, content_id: contentId }); }
    function getWatchlist() { return get('/auth/watchlist'); }
    function getEntitlements() { return get('/auth/entitlements'); }
    function subscribeTo(packageId) { return post('/auth/subscribe', { package_id: packageId }); }
    function unsubscribeFrom(packageId) { return post('/auth/unsubscribe', { package_id: packageId }); }
    function updateProfile(data) { return post('/auth/update-profile', data); }

    return {
        isAuthenticated, getUser, clearAuth, logout, refreshToken,
        getAppConfig, getLayout, getNavigation, getPages, getManifest,
        getChannels, getChannel, getMovies, getMovie, getSeries, getSeriesDetail, getEpisode,
        getCategories, search, getEpg,
        updateWatchProgress, getContinueWatching, toggleWatchlist, getWatchlist, getEntitlements,
        subscribeTo, unsubscribeFrom, updateProfile,
        bustAllCaches, clearCacheBust,
    };
})();
