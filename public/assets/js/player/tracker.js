/**
 * CARI-IPTV Behavioral Event Tracker (v2)
 * Collects user behavior events and sends them in batches to the analytics API.
 *
 * Tracks:
 *  - Watch events (start, complete, abandon, pause, resume, seek)
 *  - Navigation (page views, dwell time)
 *  - Interactions (detail views, card hovers, search, watchlist, ratings)
 *  - Preferences (skip intro/credits, CC, fullscreen, language)
 *  - QoE (buffering, startup latency, errors, quality switches)
 *  - Content impressions (what was seen vs clicked)
 *  - Session context (device, browser, OS, screen, connection)
 *  - Binge watching detection (consecutive episode tracking)
 *  - Sharing events
 */
const CariTracker = (function() {
    // --- Configuration ---
    var BATCH_INTERVAL = 15000;     // Send batch every 15 seconds
    var MAX_QUEUE_SIZE = 50;        // Force-send at 50 events
    var SESSION_KEY = 'cari_session_id';
    var HOVER_DELAY = 1500;         // Min hover time (ms) to count as interest
    var QOE_BATCH_INTERVAL = 30000; // Send QoE every 30 seconds (separate queue)
    var IMPRESSION_DELAY = 1000;    // Content must be visible 1s to count
    var BINGE_GAP_THRESHOLD = 300;  // Max 5 min gap between episodes to be a binge

    // --- State ---
    var _queue = [];
    var _qoeQueue = [];
    var _impressionQueue = [];
    var _sessionId = null;
    var _platform = 'web';
    var _batchTimer = null;
    var _qoeBatchTimer = null;
    var _currentPage = null;
    var _pageEnteredAt = null;
    var _initialized = false;
    var _deviceInfo = null;
    var _sessionStartSent = false;

    // Binge tracking state
    var _bingeState = null; // { seriesId, seasonNumber, episodes: [], startedAt, lastEpisodeEndedAt }

    // Impression observer
    var _intersectionObserver = null;
    var _observedElements = new Map(); // element -> { contentType, contentId, sectionType, sectionId, position, visibleSince }

    // =========================================================================
    // INITIALIZATION
    // =========================================================================

    function init() {
        if (_initialized) return;
        _initialized = true;

        _sessionId = _getOrCreateSession();
        _platform = _detectPlatform();
        _deviceInfo = _collectDeviceInfo();

        // Start batch senders
        _batchTimer = setInterval(_flushQueue, BATCH_INTERVAL);
        _qoeBatchTimer = setInterval(_flushQoeQueue, QOE_BATCH_INTERVAL);

        // Flush on page unload
        window.addEventListener('beforeunload', _onBeforeUnload);
        document.addEventListener('visibilitychange', _onVisibilityChange);

        // Track page views via router integration
        _hookIntoRouter();

        // Set up IntersectionObserver for content impressions
        _setupImpressionObserver();

        // Send session start
        _sendSessionStart();
    }

    function destroy() {
        _flushQueue();
        _flushQoeQueue();
        _flushImpressions();
        _sendSessionEnd();
        clearInterval(_batchTimer);
        clearInterval(_qoeBatchTimer);
        window.removeEventListener('beforeunload', _onBeforeUnload);
        document.removeEventListener('visibilitychange', _onVisibilityChange);
        if (_intersectionObserver) _intersectionObserver.disconnect();
        _initialized = false;
    }

    // =========================================================================
    // PUBLIC TRACKING METHODS — WATCH EVENTS
    // =========================================================================

    function track(eventType, data) {
        if (!CariAPI.isAuthenticated()) return;

        _queue.push({
            event_type: eventType,
            content_type: data && data.content_type || null,
            content_id: data && data.content_id || null,
            page: data && data.page || _currentPage,
            metadata: data && data.metadata || null,
            timestamp: Date.now(),
        });

        if (_queue.length >= MAX_QUEUE_SIZE) {
            _flushQueue();
        }
    }

    function watchStart(contentType, contentId, metadata) {
        track('watch_start', {
            content_type: contentType,
            content_id: contentId,
            metadata: Object.assign({ start_time: Date.now() }, metadata || {}),
        });
    }

    function watchComplete(contentType, contentId, metadata) {
        track('watch_complete', {
            content_type: contentType,
            content_id: contentId,
            metadata: metadata || {},
        });

        // Check for binge session if this is an episode
        if (contentType === 'episode' && metadata) {
            _checkBingeSession(contentId, metadata.series_id, metadata.season_number);
        }
    }

    function watchAbandon(contentType, contentId, progressPercent) {
        track('watch_abandon', {
            content_type: contentType,
            content_id: contentId,
            metadata: { progress_percent: progressPercent },
        });
    }

    function detailView(contentType, contentId) {
        track('detail_view', { content_type: contentType, content_id: contentId });
    }

    function detailDismiss(contentType, contentId) {
        track('detail_dismiss', { content_type: contentType, content_id: contentId });
    }

    function searchQuery(query, resultCount) {
        var eventType = resultCount > 0 ? 'search' : 'search_no_results';
        track(eventType, { metadata: { query: query, result_count: resultCount } });
    }

    function cardHoverStart(contentType, contentId) {
        return { contentType: contentType, contentId: contentId, startTime: Date.now() };
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

    function skipIntro(contentType, contentId) {
        track('skip_intro', { content_type: contentType, content_id: contentId });
    }

    function skipCredits(contentType, contentId) {
        track('skip_credits', { content_type: contentType, content_id: contentId });
    }

    function trailerView(contentType, contentId) {
        track('trailer_view', { content_type: contentType, content_id: contentId });
    }

    function fullscreenEnter() {
        track('fullscreen_enter', {});
    }

    function ccToggle(enabled) {
        track('cc_toggle', { metadata: { enabled: enabled } });
    }

    // =========================================================================
    // QoE (Quality of Experience) TRACKING
    // =========================================================================

    function _qoeEvent(eventType, contentType, contentId, metadata) {
        if (!CariAPI.isAuthenticated()) return;
        _qoeQueue.push({
            event_type: eventType,
            content_type: contentType || null,
            content_id: contentId || null,
            metadata: metadata || null,
            timestamp: Date.now(),
        });
        // Errors are high-priority — flush immediately
        if (eventType === 'playback_error') {
            _flushQoeQueue();
        }
    }

    /**
     * Track video startup time (time from play click to first frame rendered)
     */
    function startupTime(contentType, contentId, startupMs) {
        _qoeEvent('startup', contentType, contentId, {
            startup_ms: startupMs,
        });
    }

    /**
     * Track buffering start
     * Returns a state object — pass to bufferEnd() when buffering stops.
     */
    function bufferStart(contentType, contentId, currentTimeSeconds) {
        var state = {
            contentType: contentType,
            contentId: contentId,
            startTime: Date.now(),
            position: currentTimeSeconds,
        };
        _qoeEvent('buffer_start', contentType, contentId, {
            position_seconds: currentTimeSeconds,
        });
        return state;
    }

    /**
     * Track buffering end
     */
    function bufferEnd(bufferState) {
        if (!bufferState) return;
        var durationMs = Date.now() - bufferState.startTime;
        _qoeEvent('buffer_end', bufferState.contentType, bufferState.contentId, {
            buffer_duration_ms: durationMs,
            position_seconds: bufferState.position,
        });
    }

    /**
     * Track a playback error
     */
    function playbackError(contentType, contentId, errorCode, errorMessage) {
        _qoeEvent('playback_error', contentType, contentId, {
            error_code: errorCode || 'unknown',
            error_message: (errorMessage || '').substring(0, 200),
        });
    }

    /**
     * Track quality/rendition switch (adaptive bitrate)
     */
    function qualitySwitch(contentType, contentId, fromQuality, toQuality, reason) {
        _qoeEvent('quality_switch', contentType, contentId, {
            from_quality: fromQuality,
            to_quality: toQuality,
            reason: reason || 'auto', // auto, manual
        });
    }

    /**
     * Periodic quality report (call every 30-60 seconds during playback)
     * Captures current bitrate, resolution, dropped frames, etc.
     */
    function qualityReport(contentType, contentId, stats) {
        _qoeEvent('quality_report', contentType, contentId, {
            bitrate_kbps: stats.bitrate || null,
            resolution: stats.resolution || null,
            framerate: stats.framerate || null,
            dropped_frames: stats.droppedFrames || 0,
            buffer_length_ms: stats.bufferLength || null,
        });
    }

    // =========================================================================
    // CONTENT IMPRESSIONS — IntersectionObserver-based
    // =========================================================================

    function _setupImpressionObserver() {
        if (typeof IntersectionObserver === 'undefined') return;

        _intersectionObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                var el = entry.target;
                var data = _observedElements.get(el);
                if (!data) return;

                if (entry.isIntersecting) {
                    // Started being visible
                    data.visibleSince = Date.now();
                } else if (data.visibleSince) {
                    // Left viewport — record impression if visible long enough
                    var dwellMs = Date.now() - data.visibleSince;
                    if (dwellMs >= IMPRESSION_DELAY) {
                        _impressionQueue.push({
                            content_type: data.contentType,
                            content_id: data.contentId,
                            section_type: data.sectionType || null,
                            section_id: data.sectionId || null,
                            position: data.position != null ? data.position : null,
                            was_clicked: data.wasClicked ? 1 : 0,
                            dwell_ms: dwellMs,
                            timestamp: Date.now(),
                        });
                    }
                    data.visibleSince = null;
                }
            });
        }, { threshold: 0.5 }); // 50% visible counts
    }

    /**
     * Observe a content card for impression tracking.
     * Call this when a content card is rendered into the DOM.
     *
     * @param {HTMLElement} element - The card DOM element
     * @param {string} contentType - movie, series, channel
     * @param {number} contentId - Content ID
     * @param {object} options - { sectionType, sectionId, position }
     */
    function observeImpression(element, contentType, contentId, options) {
        if (!_intersectionObserver || !element) return;
        var opts = options || {};
        _observedElements.set(element, {
            contentType: contentType,
            contentId: contentId,
            sectionType: opts.sectionType || null,
            sectionId: opts.sectionId || null,
            position: opts.position != null ? opts.position : null,
            visibleSince: null,
            wasClicked: false,
        });
        _intersectionObserver.observe(element);
    }

    /**
     * Mark an observed element as clicked (updates was_clicked flag)
     */
    function impressionClicked(element) {
        var data = _observedElements.get(element);
        if (data) data.wasClicked = true;
    }

    /**
     * Stop observing an element (call on unmount/re-render)
     */
    function unobserveImpression(element) {
        if (!_intersectionObserver || !element) return;
        _intersectionObserver.unobserve(element);
        _observedElements.delete(element);
    }

    // =========================================================================
    // BINGE WATCHING DETECTION
    // =========================================================================

    function _checkBingeSession(episodeId, seriesId, seasonNumber) {
        if (!seriesId) return;
        var now = Date.now();

        if (_bingeState && _bingeState.seriesId === seriesId) {
            // Same series — check if gap is within threshold
            var gapSeconds = (now - _bingeState.lastEpisodeEndedAt) / 1000;
            if (gapSeconds <= BINGE_GAP_THRESHOLD) {
                // Continue binge
                _bingeState.episodes.push(episodeId);
                _bingeState.lastEpisodeEndedAt = now;
                _bingeState.seasonNumber = seasonNumber || _bingeState.seasonNumber;
            } else {
                // Gap too long — finalize previous binge and start new
                _finalizeBinge();
                _startBinge(seriesId, seasonNumber, episodeId, now);
            }
        } else {
            // Different series or first episode
            if (_bingeState) _finalizeBinge();
            _startBinge(seriesId, seasonNumber, episodeId, now);
        }
    }

    function _startBinge(seriesId, seasonNumber, episodeId, now) {
        _bingeState = {
            seriesId: seriesId,
            seasonNumber: seasonNumber || null,
            episodes: [episodeId],
            startedAt: now,
            lastEpisodeEndedAt: now,
        };
    }

    function _finalizeBinge() {
        if (!_bingeState || _bingeState.episodes.length < 2) {
            _bingeState = null;
            return;
        }
        // Record binge session event
        track('binge_session', {
            content_type: 'series',
            content_id: _bingeState.seriesId,
            metadata: {
                series_id: _bingeState.seriesId,
                season_number: _bingeState.seasonNumber,
                episodes_watched: _bingeState.episodes.length,
                episode_ids: _bingeState.episodes,
                duration_minutes: Math.round((Date.now() - _bingeState.startedAt) / 60000),
            },
        });
        _bingeState = null;
    }

    /**
     * Track auto-play vs manual next episode selection
     */
    function episodeNavigation(seriesId, episodeId, method) {
        track('episode_nav', {
            content_type: 'episode',
            content_id: episodeId,
            metadata: {
                series_id: seriesId,
                method: method, // 'auto_play', 'manual_next', 'episode_select'
            },
        });
    }

    // =========================================================================
    // SHARING EVENTS
    // =========================================================================

    /**
     * Track content sharing
     * @param {string} contentType - movie, series, channel
     * @param {number} contentId
     * @param {string} method - copy_link, whatsapp, facebook, twitter, email, native_share
     */
    function share(contentType, contentId, method) {
        track('share', {
            content_type: contentType,
            content_id: contentId,
            metadata: { share_method: method || 'unknown' },
        });
    }

    // =========================================================================
    // SESSION & DEVICE CONTEXT
    // =========================================================================

    function _collectDeviceInfo() {
        var info = {
            device_type: _detectDeviceType(),
            browser: _detectBrowser(),
            os: _detectOS(),
            screen_resolution: screen.width + 'x' + screen.height,
            connection_type: _detectConnection(),
            timezone: _detectTimezone(),
            referrer: document.referrer || null,
        };
        return info;
    }

    function _detectDeviceType() {
        var ua = navigator.userAgent || '';
        if (/SmartTV|SMART-TV|TV|NetCast|BRAVIA|Tizen|WebOS/i.test(ua)) return 'tv';
        if (/iPad|Android(?!.*Mobile)/i.test(ua)) return 'tablet';
        if (/Android|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua)) return 'mobile';
        return 'desktop';
    }

    function _detectBrowser() {
        var ua = navigator.userAgent || '';
        if (ua.indexOf('Firefox') > -1) return 'Firefox';
        if (ua.indexOf('SamsungBrowser') > -1) return 'Samsung Internet';
        if (ua.indexOf('Opera') > -1 || ua.indexOf('OPR') > -1) return 'Opera';
        if (ua.indexOf('Trident') > -1) return 'IE';
        if (ua.indexOf('Edg') > -1) return 'Edge';
        if (ua.indexOf('Chrome') > -1) return 'Chrome';
        if (ua.indexOf('Safari') > -1) return 'Safari';
        return 'Other';
    }

    function _detectOS() {
        var ua = navigator.userAgent || '';
        if (ua.indexOf('Windows') > -1) return 'Windows';
        if (ua.indexOf('Mac OS') > -1) return 'macOS';
        if (ua.indexOf('Linux') > -1 && ua.indexOf('Android') === -1) return 'Linux';
        if (ua.indexOf('Android') > -1) return 'Android';
        if (/iPhone|iPad|iPod/.test(ua)) return 'iOS';
        if (ua.indexOf('CrOS') > -1) return 'ChromeOS';
        if (/Tizen|WebOS/.test(ua)) return 'SmartTV';
        return 'Other';
    }

    function _detectConnection() {
        var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (!conn) return null;
        return conn.effectiveType || conn.type || null;
    }

    function _detectTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch (e) {
            return null;
        }
    }

    function _sendSessionStart() {
        if (_sessionStartSent || !CariAPI.isAuthenticated()) return;
        _sessionStartSent = true;

        track('session_start', {
            metadata: {
                device_type: _deviceInfo.device_type,
                browser: _deviceInfo.browser,
                os: _deviceInfo.os,
                screen_resolution: _deviceInfo.screen_resolution,
                connection_type: _deviceInfo.connection_type,
                timezone: _deviceInfo.timezone,
                referrer: _deviceInfo.referrer,
            },
        });
    }

    function _sendSessionEnd() {
        if (!_sessionStartSent) return;
        var sessionDuration = _pageEnteredAt ? Date.now() - _pageEnteredAt : 0;
        track('session_end', {
            metadata: {
                session_duration_ms: sessionDuration,
            },
        });
    }

    /**
     * Get current device info (for external use)
     */
    function getDeviceInfo() {
        return _deviceInfo ? Object.assign({}, _deviceInfo) : null;
    }

    /**
     * Get current session ID (for external use)
     */
    function getSessionId() {
        return _sessionId;
    }

    // =========================================================================
    // PAGE VIEW TRACKING
    // =========================================================================

    function _trackPageView(path) {
        if (_currentPage && _pageEnteredAt) {
            var dwellMs = Date.now() - _pageEnteredAt;
            if (dwellMs > 500) {
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
        if (typeof CariRouter !== 'undefined' && CariRouter.onNavigate) {
            CariRouter.onNavigate(_trackPageView);
        } else {
            window.addEventListener('popstate', function() {
                _trackPageView(window.location.pathname);
            });
            _trackPageView(window.location.pathname);
        }
    }

    // =========================================================================
    // BATCH SENDING
    // =========================================================================

    function _flushQueue() {
        if (_queue.length === 0 || !CariAPI.isAuthenticated()) return;

        var batch = _queue.splice(0, MAX_QUEUE_SIZE);

        CariAPI.trackEventBatch(batch, _sessionId, _platform).catch(function(err) {
            _queue = batch.concat(_queue);
            if (_queue.length > MAX_QUEUE_SIZE * 2) {
                _queue = _queue.slice(-MAX_QUEUE_SIZE);
            }
        });
    }

    function _flushQoeQueue() {
        if (_qoeQueue.length === 0 || !CariAPI.isAuthenticated()) return;

        var batch = _qoeQueue.splice(0, MAX_QUEUE_SIZE);

        CariAPI.post('/analytics/qoe', {
            events: batch,
            session_id: _sessionId,
            platform: _platform,
        }).catch(function(err) {
            _qoeQueue = batch.concat(_qoeQueue);
            if (_qoeQueue.length > MAX_QUEUE_SIZE * 2) {
                _qoeQueue = _qoeQueue.slice(-MAX_QUEUE_SIZE);
            }
        });
    }

    function _flushImpressions() {
        // Collect remaining visible items
        _observedElements.forEach(function(data, el) {
            if (data.visibleSince) {
                var dwellMs = Date.now() - data.visibleSince;
                if (dwellMs >= IMPRESSION_DELAY) {
                    _impressionQueue.push({
                        content_type: data.contentType,
                        content_id: data.contentId,
                        section_type: data.sectionType,
                        section_id: data.sectionId,
                        position: data.position,
                        was_clicked: data.wasClicked ? 1 : 0,
                        dwell_ms: dwellMs,
                        timestamp: Date.now(),
                    });
                }
                data.visibleSince = null;
            }
        });

        if (_impressionQueue.length === 0 || !CariAPI.isAuthenticated()) return;

        var batch = _impressionQueue.splice(0, 100);

        CariAPI.post('/analytics/impressions', {
            impressions: batch,
            session_id: _sessionId,
            platform: _platform,
        }).catch(function() {
            // Drop on failure — impressions are not critical
        });
    }

    function _onBeforeUnload() {
        // Finalize any binge session
        _finalizeBinge();

        // Record page exit dwell time
        if (_currentPage && _pageEnteredAt) {
            track('page_exit', {
                page: _currentPage,
                metadata: { dwell_ms: Date.now() - _pageEnteredAt },
            });
        }

        // Flush impressions before unload
        _flushImpressions();

        // Combine all queues for sendBeacon
        var allEvents = _queue.concat(_qoeQueue.map(function(e) {
            e.event_type = 'qoe_' + e.event_type;
            return e;
        }));

        if (allEvents.length > 0 && navigator.sendBeacon && CariAPI.isAuthenticated()) {
            var payload = JSON.stringify({
                events: allEvents,
                session_id: _sessionId,
                platform: _platform,
            });
            navigator.sendBeacon(
                '/api/v1/analytics/batch',
                new Blob([payload], { type: 'application/json' })
            );
            _queue = [];
            _qoeQueue = [];
        }
    }

    function _onVisibilityChange() {
        if (document.hidden) {
            _flushQueue();
            _flushQoeQueue();
            _flushImpressions();
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

        // Watch events
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

        // QoE
        startupTime: startupTime,
        bufferStart: bufferStart,
        bufferEnd: bufferEnd,
        playbackError: playbackError,
        qualitySwitch: qualitySwitch,
        qualityReport: qualityReport,

        // Impressions
        observeImpression: observeImpression,
        impressionClicked: impressionClicked,
        unobserveImpression: unobserveImpression,

        // Binge / episode navigation
        episodeNavigation: episodeNavigation,

        // Sharing
        share: share,

        // Session / device info
        getDeviceInfo: getDeviceInfo,
        getSessionId: getSessionId,
    };
})();
