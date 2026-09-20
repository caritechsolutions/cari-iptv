import 'package:intl/intl.dart';

import '../../../core/network/api_client.dart';
import '../../../models/epg.dart';

/// `/epg` endpoints. Times are treated as UTC (docs/API_DISCOVERY.md §6).
class EpgRepository {
  EpgRepository(this._api);
  final ApiClient _api;

  static String formatDate(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

  /// Grouped guide for all channels. Without [date]: −3 h … +24 h.
  Future<List<EpgChannelSchedule>> guide({DateTime? date, int limit = 2000, bool preferCache = false}) async {
    final res = await _api.get('/epg', cacheScope: 'epg', preferCache: preferCache, query: {
      if (date != null) 'date': formatDate(date),
      'limit': limit,
    });
    return res.envelope.dataAsList.map(EpgChannelSchedule.fromJson).toList(growable: false);
  }

  /// Flat programme list for one channel (limit fixed at 200 server-side).
  Future<List<EpgProgramme>> channel(int channelId, {DateTime? date}) async {
    final res = await _api.get('/epg/$channelId', cacheScope: 'epg', query: {
      if (date != null) 'date': formatDate(date),
    });
    return res.envelope.dataAsList.map((p) => EpgProgramme.fromJson(p, channelId: channelId)).toList(growable: false);
  }
}
