/**
 * CARI-IPTV Behavioral Event Tracker
 * Collects user behavior events and sends them in batches to the analytics API.
 * Tracks: watch events, navigation, interactions, search, preferences.
 */
const CariTracker = (function() {
    // --- Configuration ---
    const BATCH_INTERVAL = 15000;   // Send batch every 15 seconds
    const MAX_QUEUE_SIZE = 50;      // Force-send at 50 events
    const SESSION_KEY = 'cari_session_id';
    const HOVER_DELAY = 1500;       // Min hover time (ms) to count as interest

    // --- State ---
    let _queue = [];
    let _sessionId = null;
    let _platform = 'web';
    let _batchTimer = null;
    let _currentPage = null;
    let _pageEnteredAt = null;
    let _initialized = false;

    // =========================================================================
    // INITIALIZATION
    // =========================================================================

    function init() {
        if (_initialized) return;
        _initialized = true;

        _sessionId = _getOrCreateSession();
        _platform = _detectPlatform();

        // Start batch sender
        _batchTimer = setInterval(_flushQueue, BATCH_INTERVAL);

        // Flush on page unload
        window.addEventListener('beforeunload', _onBeforeUnload);
        document.addEventListener('visibilitychange', _onVisibilityChange);

        // Track page views via router integration
        _hookIntoRouter();
    }

    function destroy() {
        _flushQueue();
        clearInterval(_batchTimer);
        window.removeEventListener('beforeunload', _onBeforeUnload);
        document.removeEventListener('visibilitychange', _onVisibilityChange);
        _initialized = false;
    }

    // =========================================================================
    // PUBLIC TRACKING METHODS
    // =========================================================================

    /**
     * Track a generic event
     */
    function track(eventType, data) {
        if (!CariAPI.isAuthenticated()) return;

        _queue.push({
            event_type: eventType,
            content_type: data?.content_type || null,
            content_id: data?.content_id || null,
            page: data?.page || _currentPage,
            metadata: data?.metadata || null,
            timestamp: Date.now(),
        });

        if (_queue.length >= MAX_QUEUE_SIZE) {
            _flushQueue();
        }
    }

    /**
     * Track watch start (call when playback begins)
     */
    function watchStart(contentType, contentId, metadata) {
        track('watch_start', {
            content_type: contentType,
            content_id: contentId,
            metadata: Object.assign({ start_time: Date.now() }, metadata || {}),
        });
    }

    /**
     * Track watch complete (call when >90% watched)
     */
    function watchComplete(contentType, contentId, metadata) {
        track('watch_complete', {
            content_type: contentType,
            content_id: contentId,
            metadata: metadata || {},
        });
    }

    /**
     * Track watch abandon (call when stopped before completion)
     */
    function watchAbandon(contentType, contentId, progressPercent) {
        track('watch_abandon', {
            content_type: contentType,
            content_id: contentId,
            metadata: { progress_percent: progressPercent },
        });
    }

    /**
     * Track detail view (opened detail modal or page)
     */
    function detailView(contentType, contentId) {
        track('detail_view', {
            content_type: contentType,
            content_id: contentId,
        });
    }

    /**
     * Track detail dismiss (closed detail without playing)
     */
    function detailDismiss(contentType, contentId) {
        track('detail_dismiss', {
            content_type: contentType,
            content_id: contentId,
        });
    }

    /**
     * Track search query
     */
    function searchQuery(query, resultCount) {
        var eventType = resultCount > 0 ? 'search' : 'search_no_results';
        track(eventType, {
            metadata: { query: query, result_count: resultCount },
        });
    }

    /**
     * Track card hover (with minimum dwell time threshold)
     */
    function cardHoverStart(contentType, contentId) {
        return {
            contentType: contentType,
            contentId: contentId,
            startTime: Date.now(),
        };
    }

    function cardHoverEnd(hoverState) {
        if (!hoverState) return;
        var dwellTime = Date.now() - hoverState.startTime;
        if (dwellTime >= HOVER_DELAY) {
            track('card_hover', {
                content_type: hoverState.contentType,
                content_id: hoverState.contentId,
                metadata: { dwell_ms: dwellTime },
            });
        }
    }

    /**
     * Track skip intro / skip credits
     */
    function skipIntro(contentType, contentId) {
        track('skip_intro', { content_type: contentType, content_id: contentId });
    }

    function skipCredits(contentType, contentId) {
        track('skip_credits', { content_type: contentType, content_id: contentId });
    }

    /**
     * Track trailer view
     */
    function trailerView(contentType, contentId) {
        track('trailer_view', { content_type: contentType, content_id: contentId });
    }

    /**
     * Track fullscreen enter
     */
    function fullscreenEnter() {
        track('fullscreen_enter', {});
    }

    /**
     * Track CC toggle
     */
    function ccToggle(enabled) {
        track('cc_toggle', { metadata: { enabled: enabled } });
    }

    // =========================================================================
    // PAGE VIEW TRACKING
    // =========================================================================

    function _trackPageView(path) {
        // Record exit from previous page with dwell time
        if (_currentPage && _pageEnteredAt) {
            var dwellMs = Date.now() - _pageEnteredAt;
            if (dwellMs > 500) { // Skip very quick navigations
                track('page_exit', {
                    page: _currentPage,
                    metadata: { dwell_ms: dwellMs },
                });
            }
        }

        _currentPage = path;
        _pageEnteredAt = Date.now();

        track('page_view', { page: path });
    }

    function _hookIntoRouter() {
        // Integrate with CariRouter if available
        if (typeof CariRouter !== 'undefined' && CariRouter.onNavigate) {
            CariRouter.onNavigate(_trackPageView);
        } else {
            // Fallback: check for route changes on popstate
            window.addEventListener('popstate', function() {
                _trackPageView(window.location.pathname);
            });
            // Track initial page
            _trackPageView(window.location.pathname);
        }
    }

    // =========================================================================
    // BATCH SENDING
    // =========================================================================

    function _flushQueue() {
        if (_queue.length === 0 || !CariAPI.isAuthenticated()) return;

        var batch = _queue.splice(0, MAX_QUEUE_SIZE);

        // Fire and forget — don't block the UI
        CariAPI.trackEventBatch(batch, _sessionId, _platform).catch(function(err) {
            // On failure, put events back at the front of the queue
            _queue = batch.concat(_queue);
            if (_queue.length > MAX_QUEUE_SIZE * 2) {
                // If queue is getting too large, drop oldest events
                _queue = _queue.slice(-MAX_QUEUE_SIZE);
            }
        });
    }

    function _onBeforeUnload() {
        // Record page exit dwell time
        if (_currentPage && _pageEnteredAt) {
            track('page_exit', {
                page: _currentPage,
                metadata: { dwell_ms: Date.now() - _pageEnteredAt },
            });
        }

        // Use sendBeacon for reliable delivery on page unload
        if (_queue.length > 0 && navigator.sendBeacon && CariAPI.isAuthenticated()) {
            var token = localStorage.getItem('access_token');
            var payload = JSON.stringify({
                events: _queue,
                session_id: _sessionId,
                platform: _platform,
            });
            navigator.sendBeacon(
                '/api/v1/analytics/batch',
                new Blob([payload], { type: 'application/json' })
            );
            _queue = [];
        }
    }

    function _onVisibilityChange() {
        if (document.hidden) {
            _flushQueue();
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    function _getOrCreateSession() {
        var sessionId = sessionStorage.getItem(SESSION_KEY);
        if (!sessionId) {
            sessionId = 'sess_' + Date.now().toString(36) + '_' + Math.random().toString(36).substr(2, 8);
            sessionStorage.setItem(SESSION_KEY, sessionId);
        }
        return sessionId;
    }

    function _detectPlatform() {
        var ua = navigator.userAgent || '';
        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua)) return 'mobile';
        if (/SmartTV|SMART-TV|TV|NetCast|BRAVIA|Tizen|WebOS/i.test(ua)) return 'tv';
        return 'web';
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    return {
        init: init,
        destroy: destroy,
        track: track,
        watchStart: watchStart,
        watchComplete: watchComplete,
        watchAbandon: watchAbandon,
        detailView: detailView,
        detailDismiss: detailDismiss,
        searchQuery: searchQuery,
        cardHoverStart: cardHoverStart,
        cardHoverEnd: cardHoverEnd,
        skipIntro: skipIntro,
        skipCredits: skipCredits,
        trailerView: trailerView,
        fullscreenEnter: fullscreenEnter,
        ccToggle: ccToggle,
    };
})();
