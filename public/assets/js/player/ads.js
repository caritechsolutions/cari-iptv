/**
 * CARI-IPTV Ad Manager
 * Handles ad serving, rendering, cue point integration, pods, and tracking
 * Supports: pre-roll, mid-roll, post-roll video ads, banners, text scrollers
 */
const CariAdManager = (function() {
    'use strict';

    const AD_API_BASE = '/api/v1/ads';
    let _context = {};
    let _adBreaks = [];
    let _playedBreaks = {};
    let _activeOverlays = [];
    let _videoElement = null;
    let _playerContainer = null;
    let _onAdStart = null;
    let _onAdEnd = null;
    let _adPlaying = false;
    let _companionBanners = [];

    // =========================================
    // Init & Config
    // =========================================

    function init(options) {
        _videoElement = options.video || null;
        _playerContainer = options.container || document.getElementById('playerContainer');
        _onAdStart = options.onAdStart || function() {};
        _onAdEnd = options.onAdEnd || function() {};
        _context = {
            platform: options.platform || 'web',
            package_id: options.packageId || null,
            user_id: options.userId || null,
            content_type: options.contentType || null,
            content_id: options.contentId || null,
            channel_id: options.channelId || null,
            category_id: options.categoryId || null,
        };
        _adBreaks = [];
        _playedBreaks = {};
        _adPlaying = false;
        console.log('[CariAds] Initialized with context:', JSON.stringify(_context), 'container:', _playerContainer ? _playerContainer.id || 'found' : 'MISSING');
    }

    // =========================================
    // Ad Fetching
    // =========================================

    function fetchAds(zoneType, extra) {
        var params = new URLSearchParams();
        params.set('zone_type', zoneType);
        if (_context.platform) params.set('platform', _context.platform);
        if (_context.package_id) params.set('package_id', _context.package_id);
        if (_context.user_id) params.set('user_id', _context.user_id);
        if (_context.content_type) params.set('content_type', _context.content_type);
        if (_context.content_id) params.set('content_id', _context.content_id);
        if (_context.channel_id) params.set('channel_id', _context.channel_id);
        if (_context.category_id) params.set('category_id', _context.category_id);
        if (extra) {
            Object.keys(extra).forEach(function(k) { params.set(k, extra[k]); });
        }
        var url = AD_API_BASE + '/serve?' + params.toString();
        console.log('[CariAds] Fetching ads:', url);
        return fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                console.log('[CariAds] Response for ' + zoneType + ':', d.success ? (d.ads || []).length + ' ads' : 'failed', d);
                return d.success ? d : { ads: [], source: 'none' };
            })
            .catch(function(err) {
                console.error('[CariAds] Fetch error for ' + zoneType + ':', err);
                return { ads: [], source: 'none' };
            });
    }

    function fetchAdBreakSchedule(contentType, contentId, duration) {
        var params = new URLSearchParams({
            content_type: contentType,
            content_id: contentId,
            duration: duration
        });
        return fetch(AD_API_BASE + '/breaks?' + params.toString())
            .then(function(r) { return r.json(); })
            .then(function(d) { return d.success ? d.breaks : []; })
            .catch(function() { return []; });
    }

    // =========================================
    // Pre-Roll
    // =========================================

    function playPreRoll(callback) {
        fetchAds('pre_roll').then(function(result) {
            var ads = result.ads || [];

            // If we got a VAST redirect
            if (result.vast_url) {
                playVastAd(result.vast_url, callback);
                return;
            }

            if (ads.length === 0) {
                callback();
                return;
            }

            playAdPod(ads, 'pre_roll', callback);
        });
    }

    // =========================================
    // Mid-Roll (cue point integration)
    // =========================================

    function setupMidRolls(video, contentType, contentId, duration) {
        _videoElement = video;

        fetchAdBreakSchedule(contentType, contentId, duration).then(function(breaks) {
            _adBreaks = breaks.filter(function(b) { return b.type === 'mid_roll'; });

            if (_adBreaks.length > 0) {
                // Add cue point markers to progress bar
                renderCuePointMarkers(duration);

                // Listen for timeupdate to trigger breaks
                video.addEventListener('timeupdate', onMidRollTimeUpdate);
            }
        });
    }

    function onMidRollTimeUpdate() {
        if (_adPlaying) return;
        var t = _videoElement.currentTime;

        for (var i = 0; i < _adBreaks.length; i++) {
            var brk = _adBreaks[i];
            var pos = brk.position;
            var key = 'midroll_' + pos;

            // Trigger within 1.5 second window
            if (!_playedBreaks[key] && t >= pos - 0.5 && t <= pos + 1.5) {
                _playedBreaks[key] = true;
                triggerMidRollBreak(brk);
                break;
            }
        }
    }

    function triggerMidRollBreak(brk) {
        _videoElement.pause();

        fetchAds('mid_roll', { content_id: _context.content_id }).then(function(result) {
            var ads = result.ads || [];

            if (result.vast_url) {
                playVastAd(result.vast_url, function() { _videoElement.play(); });
                return;
            }

            if (ads.length === 0) {
                _videoElement.play();
                return;
            }

            playAdPod(ads, 'mid_roll', function() {
                _videoElement.play();
            });
        });
    }

    // =========================================
    // Ad Pod Player (sequential ad playback)
    // =========================================

    function playAdPod(ads, podType, onComplete) {
        if (ads.length === 0) {
            onComplete();
            return;
        }

        _adPlaying = true;
        _onAdStart(podType);

        var container = _playerContainer;
        var adIndex = 0;

        // Create ad overlay
        var overlay = document.createElement('div');
        overlay.className = 'cari-ad-overlay';
        overlay.innerHTML = '<div class="cari-ad-container">' +
            '<div class="cari-ad-label"><span>Ad</span> <span class="cari-ad-counter">' + (adIndex + 1) + ' of ' + ads.length + '</span></div>' +
            '<video class="cari-ad-video" playsinline></video>' +
            '<div class="cari-ad-skip" style="display:none"><i class="lucide-skip-forward"></i> Skip Ad</div>' +
            '<div class="cari-ad-click-layer"></div>' +
            '<div class="cari-ad-companion"></div>' +
            '</div>';
        container.appendChild(overlay);

        var adVideo = overlay.querySelector('.cari-ad-video');
        var skipBtn = overlay.querySelector('.cari-ad-skip');
        var counter = overlay.querySelector('.cari-ad-counter');
        var clickLayer = overlay.querySelector('.cari-ad-click-layer');
        var companionDiv = overlay.querySelector('.cari-ad-companion');

        function playNextAd() {
            if (adIndex >= ads.length) {
                // Pod complete
                overlay.remove();
                removeCompanionBanners();
                _adPlaying = false;
                _onAdEnd(podType);
                onComplete();
                return;
            }

            var ad = ads[adIndex];
            counter.textContent = (adIndex + 1) + ' of ' + ads.length;

            // Track impression
            trackImpression(ad);

            if (ad.type === 'pre_roll' || ad.type === 'mid_roll') {
                playVideoAd(ad, adVideo, skipBtn, clickLayer, companionDiv, function() {
                    trackEvent(ad, 'complete');
                    adIndex++;
                    playNextAd();
                });
            } else if (ad.type === 'banner') {
                showBannerAd(ad, overlay, function() {
                    adIndex++;
                    playNextAd();
                });
            } else {
                adIndex++;
                playNextAd();
            }
        }

        playNextAd();
    }

    function playVideoAd(ad, videoEl, skipBtn, clickLayer, companionDiv, onDone) {
        var videoUrl = ad.video_url || ad.vast_tag_url;

        if (!videoUrl && ad.vast_tag_url) {
            // Fetch VAST and extract media URL
            playVastAd(ad.vast_tag_url, onDone);
            return;
        }

        if (!videoUrl) {
            onDone();
            return;
        }

        videoEl.src = videoUrl;
        videoEl.style.display = 'block';
        videoEl.play().catch(function() { onDone(); });

        // Skip button
        var skipAfter = parseInt(ad.skip_after) || 0;
        if (skipAfter > 0) {
            skipBtn.style.display = 'none';
            var skipTimer = setInterval(function() {
                var elapsed = Math.floor(videoEl.currentTime);
                if (elapsed >= skipAfter) {
                    skipBtn.style.display = 'flex';
                    clearInterval(skipTimer);
                }
            }, 500);

            skipBtn.onclick = function() {
                trackEvent(ad, 'skip');
                videoEl.pause();
                videoEl.src = '';
                onDone();
            };
        }

        // Click-through
        if (ad.click_url) {
            clickLayer.style.display = 'block';
            clickLayer.onclick = function(e) {
                e.preventDefault();
                trackEvent(ad, 'click');
                window.open(ad.click_url, ad.click_target || '_blank');
            };
        }

        // Companion banner
        if (ad.companions && ad.companions.length > 0) {
            var comp = ad.companions[0];
            showCompanionBanner(comp);
        }

        // Quartile tracking
        var quartiles = { 25: false, 50: false, 75: false };
        videoEl.addEventListener('timeupdate', function onQ() {
            if (videoEl.duration <= 0) return;
            var pct = (videoEl.currentTime / videoEl.duration) * 100;
            if (pct >= 25 && !quartiles[25]) { quartiles[25] = true; trackEvent(ad, 'quartile_25'); }
            if (pct >= 50 && !quartiles[50]) { quartiles[50] = true; trackEvent(ad, 'quartile_50'); }
            if (pct >= 75 && !quartiles[75]) { quartiles[75] = true; trackEvent(ad, 'quartile_75'); }
        });

        videoEl.onended = function() {
            videoEl.src = '';
            clickLayer.style.display = 'none';
            skipBtn.style.display = 'none';
            onDone();
        };

        videoEl.onerror = function() {
            trackEvent(ad, 'error');
            onDone();
        };
    }

    function playVastAd(vastUrl, onComplete) {
        // Simple VAST 4.2 parser
        fetch(vastUrl)
            .then(function(r) { return r.text(); })
            .then(function(xml) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(xml, 'application/xml');
                var mediaFile = doc.querySelector('MediaFile');
                if (mediaFile) {
                    var url = mediaFile.textContent.trim();
                    var skipEl = doc.querySelector('Linear');
                    var skipOffset = skipEl ? skipEl.getAttribute('skipoffset') : null;
                    var skipSec = 0;
                    if (skipOffset) {
                        var parts = skipOffset.split(':');
                        skipSec = parseInt(parts[parts.length - 1]) || 5;
                    }

                    var fakeAd = {
                        type: 'pre_roll',
                        video_url: url,
                        skip_after: skipSec,
                        campaign_id: 0,
                        id: 0,
                    };

                    var overlay = _playerContainer.querySelector('.cari-ad-overlay');
                    if (!overlay) {
                        onComplete();
                        return;
                    }

                    var adVideo = overlay.querySelector('.cari-ad-video');
                    var skipBtn = overlay.querySelector('.cari-ad-skip');
                    var clickLayer = overlay.querySelector('.cari-ad-click-layer');
                    var companionDiv = overlay.querySelector('.cari-ad-companion');

                    playVideoAd(fakeAd, adVideo, skipBtn, clickLayer, companionDiv, onComplete);
                } else {
                    onComplete();
                }
            })
            .catch(function() { onComplete(); });
    }

    // =========================================
    // Banner Ads
    // =========================================

    function showBannerAd(ad, parentOverlay, onDone) {
        var banner = document.createElement('div');
        banner.className = 'cari-ad-banner cari-ad-banner-' + (ad.banner_position || 'bottom');
        banner.innerHTML = '<img src="' + ad.image_url + '" alt="' + (ad.alt_text || 'Ad') + '">';

        if (ad.click_url) {
            banner.style.cursor = 'pointer';
            banner.onclick = function() {
                trackEvent(ad, 'click');
                window.open(ad.click_url, ad.click_target || '_blank');
            };
        }

        // Show for 5 seconds in a pod, or persistent for overlay
        if (parentOverlay) {
            parentOverlay.querySelector('.cari-ad-container').appendChild(banner);
            setTimeout(function() {
                banner.remove();
                onDone();
            }, 5000);
        }
    }

    function showOverlayBanner(ad) {
        if (!_playerContainer) return;

        var banner = document.createElement('div');
        banner.className = 'cari-ad-banner-overlay cari-ad-banner-' + (ad.banner_position || 'overlay_bottom');
        banner.innerHTML = '<img src="' + ad.image_url + '" alt="' + (ad.alt_text || 'Ad') + '">' +
            '<button class="cari-ad-close" onclick="this.parentNode.remove()">&times;</button>';

        if (ad.click_url) {
            banner.querySelector('img').style.cursor = 'pointer';
            banner.querySelector('img').onclick = function() {
                trackEvent(ad, 'click');
                window.open(ad.click_url, ad.click_target || '_blank');
            };
        }

        trackImpression(ad);
        _playerContainer.appendChild(banner);
        _activeOverlays.push(banner);

        // Auto-dismiss after 15 seconds
        setTimeout(function() {
            if (banner.parentNode) banner.remove();
        }, 15000);
    }

    // =========================================
    // Text Scroller
    // =========================================

    function showTextScroller(ad) {
        if (!_playerContainer) return;

        var scroller = document.createElement('div');
        scroller.className = 'cari-ad-scroller';
        scroller.style.backgroundColor = ad.bg_color || '#000';
        scroller.style.opacity = ad.bg_opacity || 0.8;
        scroller.style.color = ad.text_color || '#fff';

        var speed = ad.scroll_speed === 'slow' ? 25 : (ad.scroll_speed === 'fast' ? 12 : 18);
        var text = ad.scroll_text || '';
        scroller.innerHTML = '<div class="cari-scroller-text" style="animation: scrollText ' + speed + 's linear infinite">' + escapeHtml(text) + '</div>';

        trackImpression(ad);
        _playerContainer.appendChild(scroller);
        _activeOverlays.push(scroller);

        // Auto-dismiss after 2 full scrolls
        setTimeout(function() {
            if (scroller.parentNode) {
                scroller.classList.add('cari-scroller-fade');
                setTimeout(function() { if (scroller.parentNode) scroller.remove(); }, 500);
            }
        }, speed * 2 * 1000);
    }

    // =========================================
    // Companion Banners (show alongside video ads)
    // =========================================

    function showCompanionBanner(companion) {
        if (!_playerContainer) return;

        var banner = document.createElement('div');
        banner.className = 'cari-ad-companion-banner';
        banner.innerHTML = '<img src="' + companion.image_url + '" alt="Ad">';

        if (companion.click_url) {
            banner.style.cursor = 'pointer';
            banner.onclick = function() {
                window.open(companion.click_url, companion.click_target || '_blank');
            };
        }

        _playerContainer.appendChild(banner);
        _companionBanners.push(banner);
    }

    function removeCompanionBanners() {
        _companionBanners.forEach(function(b) { if (b.parentNode) b.remove(); });
        _companionBanners = [];
    }

    // =========================================
    // Overlay Ads (banners + text scrollers during playback)
    // =========================================

    function loadOverlayAds() {
        console.log('[CariAds] loadOverlayAds called, container:', _playerContainer ? 'found' : 'MISSING');

        // Fetch banner ads
        fetchAds('banner').then(function(result) {
            var ads = result.ads || [];
            console.log('[CariAds] Banner ads received:', ads.length);
            if (ads.length > 0) {
                setTimeout(function() {
                    showOverlayBanner(ads[0]);
                }, 30000);
            }
        });

        // Fetch text scroller ads
        fetchAds('text_scroller').then(function(result) {
            var ads = result.ads || [];
            console.log('[CariAds] Text scroller ads received:', ads.length);
            if (ads.length > 0) {
                setTimeout(function() {
                    console.log('[CariAds] Showing text scroller now');
                    showTextScroller(ads[0]);
                }, 15000);
            }
        });
    }

    // =========================================
    // Cue Point Markers on Progress Bar
    // =========================================

    function renderCuePointMarkers(duration) {
        var progressBar = document.querySelector('.player-progress-bar, .progress-bar-container, [class*="progress"]');
        if (!progressBar || !duration) return;

        _adBreaks.forEach(function(brk) {
            var pct = (brk.position / duration) * 100;
            var marker = document.createElement('div');
            marker.className = 'cari-ad-cue-marker';
            marker.style.left = pct + '%';
            marker.title = brk.label || 'Ad Break';
            progressBar.appendChild(marker);
        });
    }

    // =========================================
    // Tracking
    // =========================================

    function trackImpression(ad) {
        if (!ad.campaign_id || !ad.id) return;

        var data = new FormData();
        data.append('campaign_id', ad.campaign_id);
        data.append('creative_id', ad.id);
        data.append('placement_id', ad.placement_id || '');
        data.append('platform', _context.platform || 'web');
        data.append('content_type', _context.content_type || '');
        data.append('content_id', _context.content_id || '');
        data.append('channel_id', _context.channel_id || '');
        data.append('user_id', _context.user_id || '');

        fetch(AD_API_BASE + '/impression', { method: 'POST', body: data }).catch(function() {});
    }

    function trackEvent(ad, eventType) {
        if (!ad.campaign_id || !ad.id) return;

        var data = new FormData();
        data.append('campaign_id', ad.campaign_id);
        data.append('creative_id', ad.id);
        data.append('event_type', eventType);
        data.append('user_id', _context.user_id || '');

        fetch(AD_API_BASE + '/event', { method: 'POST', body: data }).catch(function() {});
    }

    function trackConversion(pixelCode, value) {
        var data = JSON.stringify({
            pixel_code: pixelCode,
            user_id: _context.user_id,
            session_id: sessionStorage.getItem('cari_session_id') || '',
            value: value || null,
        });

        fetch(AD_API_BASE + '/conversion', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: data,
        }).catch(function() {});
    }

    // =========================================
    // Cleanup
    // =========================================

    function clearOverlays() {
        _activeOverlays.forEach(function(el) { if (el.parentNode) el.remove(); });
        _activeOverlays = [];
        removeCompanionBanners();

        // Remove cue point markers
        document.querySelectorAll('.cari-ad-cue-marker').forEach(function(m) { m.remove(); });

        // Remove ad overlay if exists
        var overlay = _playerContainer ? _playerContainer.querySelector('.cari-ad-overlay') : null;
        if (overlay) overlay.remove();

        _adPlaying = false;
    }

    function destroy() {
        clearOverlays();
        if (_videoElement) {
            _videoElement.removeEventListener('timeupdate', onMidRollTimeUpdate);
        }
        _adBreaks = [];
        _playedBreaks = {};
    }

    // =========================================
    // Helpers
    // =========================================

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function isAdPlaying() {
        return _adPlaying;
    }

    // =========================================
    // Public API
    // =========================================

    return {
        init: init,
        playPreRoll: playPreRoll,
        setupMidRolls: setupMidRolls,
        loadOverlayAds: loadOverlayAds,
        showOverlayBanner: showOverlayBanner,
        showTextScroller: showTextScroller,
        trackConversion: trackConversion,
        clearOverlays: clearOverlays,
        destroy: destroy,
        isAdPlaying: isAdPlaying,
        fetchAds: fetchAds,
    };
})();
