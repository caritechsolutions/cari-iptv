import '../core/util/json.dart';
import '../core/util/time.dart';
import 'media_card.dart';

/// `now_playing` / `next_up` on `/channels/{id}` (raw MySQL datetimes).
class ChannelProgramme {
  const ChannelProgramme({required this.title, required this.description, required this.start, required this.end});
  final String title;
  final String description;
  final DateTime? start;
  final DateTime? end;

  static ChannelProgramme? fromJsonOrNull(Object? v) {
    if (v is! Map) return null;
    final j = asJson(v);
    return ChannelProgramme(
      title: asString(j['title']),
      description: asString(j['description']),
      start: parseApiDateTime(j['start_time']),
      end: parseApiDateTime(j['end_time']),
    );
  }
}

class Channel {
  const Channel({
    required this.id,
    required this.name,
    required this.slug,
    required this.logoUrl,
    required this.streamUrl,
    required this.epgChannelId,
    required this.categoryId,
    required this.categoryName,
    required this.country,
    required this.isHd,
    required this.isAdult,
    required this.isRestricted,
    required this.channelNumber,
    required this.description,
    required this.nowPlaying,
    required this.nextUp,
  });

  final int id;
  final String name;
  final String slug;
  final String? logoUrl;
  final String streamUrl;
  final String? epgChannelId;
  final int? categoryId;
  final String? categoryName;
  final String? country;
  final bool isHd;
  final bool isAdult;
  final bool isRestricted;
  final int? channelNumber;
  final String? description;
  final ChannelProgramme? nowPlaying;
  final ChannelProgramme? nextUp;

  factory Channel.fromJson(Json j) => Channel(
        id: asInt(j['id']),
        name: asString(j['name'], asString(j['title'])),
        slug: asString(j['slug']),
        logoUrl: asStringOrNull(j['logo_url']),
        streamUrl: asString(j['stream_url']),
        epgChannelId: asStringOrNull(j['epg_channel_id']),
        categoryId: asIntOrNull(j['category_id']),
        categoryName: asStringOrNull(j['category_name']),
        country: asStringOrNull(j['country']),
        isHd: asBool(j['is_hd']),
        isAdult: asBool(j['is_adult']),
        isRestricted: asBool(j['is_restricted']),
        channelNumber: asIntOrNull(j['channel_number']),
        description: asStringOrNull(j['description']),
        nowPlaying: ChannelProgramme.fromJsonOrNull(j['now_playing']),
        nextUp: ChannelProgramme.fromJsonOrNull(j['next_up']),
      );

  MediaCard toCard() => MediaCard(
        id: id,
        type: 'channel',
        title: name,
        logoUrl: logoUrl,
        subtitle: channelNumber != null ? 'Ch $channelNumber' : categoryName,
        isRestricted: isRestricted,
        isAdult: isAdult,
        categoryId: categoryId,
        streamUrl: streamUrl,
      );
}
