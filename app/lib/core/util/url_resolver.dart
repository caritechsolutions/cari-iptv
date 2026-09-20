/// Turns the API's mixed image/subtitle paths into absolute URLs.
///
/// The backend returns locally processed artwork as root-relative paths
/// (`/uploads/vod/12/poster_poster.webp`) and TMDB artwork as absolute URLs.
class UrlResolver {
  const UrlResolver(this.siteOrigin);

  /// e.g. `https://player.caritech.net` (no trailing slash).
  final String siteOrigin;

  /// Returns an absolute URL, or `null` for empty input.
  String? resolve(String? path) {
    if (path == null) return null;
    final p = path.trim();
    if (p.isEmpty) return null;
    if (p.startsWith('http://') || p.startsWith('https://')) return p;
    if (p.startsWith('//')) return 'https:$p';
    if (p.startsWith('/')) return '$siteOrigin$p';
    return '$siteOrigin/$p';
  }
}

/// True when a `stream_url` is a web page (YouTube, Vimeo, …) rather than a
/// media stream. Such items open in the browser instead of the player.
bool isExternalWatchUrl(String? url) {
  if (url == null || url.isEmpty) return false;
  final u = Uri.tryParse(url);
  if (u == null) return false;
  final host = u.host.toLowerCase();
  if (host.contains('youtube.com') || host.contains('youtu.be') || host.contains('vimeo.com') || host.contains('dailymotion.com')) return true;
  final path = u.path.toLowerCase();
  const media = ['.m3u8', '.mpd', '.mp4', '.ts', '.webm', '.mkv', '.mov', '.m4v', '.mp3', '.aac'];
  if (media.any(path.endsWith)) return false;
  // Unknown extension: assume a stream (HLS servers often use extensionless paths).
  return false;
}
