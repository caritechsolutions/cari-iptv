import 'dart:async';

import '../../../core/network/api_client.dart';

/// Best-effort analytics: events are queued and flushed in batches of ≤50 to
/// `/analytics/batch`; QoE events go to `/analytics/qoe`. Failures are dropped.
/// `platform` carries the flavour tag (`mobile` / `mobile-dev`).
class AnalyticsRepository {
  AnalyticsRepository(this._api) : sessionId = _newSessionId();
  final ApiClient _api;
  final String sessionId;

  final List<Map<String, dynamic>> _events = [];
  final List<Map<String, dynamic>> _qoe = [];
  Timer? _timer;

  static String _newSessionId() => 'm-${DateTime.now().millisecondsSinceEpoch.toRadixString(36)}';

  String get _platform => _api.config.platformTag;

  /// Valid `eventType` values are listed in docs/API_DISCOVERY.md §8.
  void track(String eventType, {String? contentType, int? contentId, String? page, Map<String, dynamic>? metadata}) {
    _events.add({
      'event_type': eventType,
      'content_type': ?contentType,
      'content_id': ?contentId,
      'page': ?page,
      'metadata': ?metadata,
    });
    _schedule();
  }

  /// `eventType` ∈ startup | buffer_start | buffer_end | playback_error | quality_switch | quality_report
  void qoe(String eventType, {String? contentType, int? contentId, Map<String, dynamic>? metadata}) {
    _qoe.add({
      'event_type': eventType,
      'content_type': ?contentType,
      'content_id': ?contentId,
      'metadata': ?metadata,
    });
    _schedule();
  }

  void _schedule() {
    if (_events.length >= 50 || _qoe.length >= 50) {
      unawaited(flush());
      return;
    }
    _timer ??= Timer(const Duration(seconds: 15), () => unawaited(flush()));
  }

  Future<void> flush() async {
    _timer?.cancel();
    _timer = null;
    if (_events.isNotEmpty) {
      final batch = _events.take(50).toList();
      _events.removeRange(0, batch.length);
      try {
        await _api.post('/analytics/batch', body: {'events': batch, 'session_id': sessionId, 'platform': _platform});
      } catch (_) {}
    }
    if (_qoe.isNotEmpty) {
      final batch = _qoe.take(50).toList();
      _qoe.removeRange(0, batch.length);
      try {
        await _api.post('/analytics/qoe', body: {'events': batch, 'session_id': sessionId, 'platform': _platform});
      } catch (_) {}
    }
    if (_events.isNotEmpty || _qoe.isNotEmpty) _schedule();
  }

  void dispose() {
    _timer?.cancel();
  }
}
