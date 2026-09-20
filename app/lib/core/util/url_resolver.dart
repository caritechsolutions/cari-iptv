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
