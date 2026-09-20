#!/usr/bin/env python3
"""Live API verification for the mobile app.

Reads the test account from the environment ONLY:
    CARI_API_BASE  (default https://player.caritech.net)
    CARI_TEST_USER
    CARI_TEST_PASS

Logs in as a mobile device, GETs every endpoint the app uses, prints a compact
shape summary (keys + value types, tokens redacted), tallies stream URL
schemes/formats/hosts, checks one HLS playlist per host for #EXT-X-KEY, and
logs out again. It never POSTs anything that changes the account (no
watch-progress, watchlist, rating, subscribe, profile or delete calls).

Usage:  CARI_TEST_USER=... CARI_TEST_PASS=... python3 tool/verify_api.py
"""
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from collections import Counter, defaultdict

BASE = os.environ.get("CARI_API_BASE", "https://player.caritech.net").rstrip("/")
USER = os.environ.get("CARI_TEST_USER")
PASS = os.environ.get("CARI_TEST_PASS")
if not USER or not PASS:
    print("Set CARI_TEST_USER and CARI_TEST_PASS in the environment.", file=sys.stderr)
    sys.exit(2)

REDACT = {"access_token", "refresh_token", "password", "parental_pin", "email", "phone"}
TOKEN = None


def call(method, path, body=None, auth=True, raw=False, base=None):
    url = (base or (BASE + "/api/v1")) + path
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Accept", "application/json")
    if data is not None:
        req.add_header("Content-Type", "application/json")
    if auth and TOKEN:
        req.add_header("Authorization", "Bearer " + TOKEN)
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            payload = r.read()
            return r.status, (payload if raw else _json(payload)), dict(r.headers)
    except urllib.error.HTTPError as e:
        payload = e.read()
        return e.code, (payload if raw else _json(payload)), dict(e.headers)
    except Exception as e:  # network
        return None, {"_error": str(e)}, {}


def _json(payload):
    try:
        return json.loads(payload.decode("utf-8", "replace"))
    except Exception:
        return {"_raw": payload[:200].decode("utf-8", "replace")}


def shape(v, depth=0, maxdepth=3):
    """Describe a JSON value's structure, redacting sensitive keys."""
    if isinstance(v, dict):
        if depth >= maxdepth:
            return "{...%d keys}" % len(v)
        parts = []
        for k, val in v.items():
            if k in REDACT:
                parts.append("%s:<redacted>" % k)
            else:
                parts.append("%s:%s" % (k, shape(val, depth + 1, maxdepth)))
        return "{" + ", ".join(parts) + "}"
    if isinstance(v, list):
        if not v:
            return "[]"
        return "[%d× %s]" % (len(v), shape(v[0], depth + 1, maxdepth))
    if isinstance(v, bool):
        return "bool"
    if isinstance(v, int):
        return "int"
    if isinstance(v, float):
        return "float"
    if v is None:
        return "null"
    if isinstance(v, str):
        if len(v) > 40:
            return 'str"%s…"' % v[:37].replace("\n", " ")
        return 'str"%s"' % v.replace("\n", " ")
    return type(v).__name__


def show(label, status, body, headers=None):
    extra = ""
    if headers:
        cc = headers.get("Cache-Control") or headers.get("cache-control")
        et = headers.get("ETag") or headers.get("etag")
        extra = "  [Cache-Control: %s%s]" % (cc, "; ETag" if et else "")
    print("\n== %s → %s%s" % (label, status, extra))
    print("   " + shape(body)[:1600])


# ---------------------------------------------------------------- public / auth
print("BASE", BASE)
s, b, h = call("GET", "/manifest", auth=False)
show("GET /manifest (no auth)", s, b)

s, b, h = call("POST", "/auth/login", {"identity": USER, "password": PASS, "device_type": "mobile", "device_name": "verify_api.py"}, auth=False)
show("POST /auth/login", s, b, h)
if s != 200 or "data" not in b:
    print("LOGIN FAILED; stopping.")
    sys.exit(1)
TOKEN = b["data"]["access_token"]
REFRESH = b["data"].get("refresh_token")
user = b["data"].get("user", {})
print("   user keys:", sorted(user.keys()))
print("   max_connections:", user.get("max_connections"), " adult_enabled:", user.get("adult_enabled"), " pin present:", bool(user.get("parental_pin")))

s, b, h = call("POST", "/auth/refresh", {"refresh_token": REFRESH}, auth=False)
show("POST /auth/refresh", s, b)
if s == 200:
    TOKEN = b["data"]["access_token"]

for path in ["/auth/me", "/auth/entitlements", "/manifest?platform=mobile", "/app/config/mobile", "/app/layout/mobile",
             "/app/navigation/mobile?position=main", "/app/pages/mobile", "/categories", "/auth/continue-watching",
             "/auth/watchlist", "/recommendations", "/search?q=the&type=all&limit=5", "/epg?limit=50"]:
    s, b, h = call("GET", path)
    show("GET " + path, s, b, h)

# ---------------------------------------------------------------- content + streams
streams = []  # (kind, id, url)


def collect(kind, items):
    for it in items:
        u = it.get("stream_url")
        if u:
            streams.append((kind, it.get("id"), u))


s, b, h = call("GET", "/channels?limit=1000")
show("GET /channels", s, b, h)
channels = b.get("data", []) if isinstance(b, dict) else []
collect("channel", channels)
if channels:
    cid = channels[0]["id"]
    s, b, h = call("GET", "/channels/%s" % cid)
    show("GET /channels/%s" % cid, s, b)
    s, b, h = call("GET", "/epg/%s" % cid)
    show("GET /epg/%s" % cid, s, b)
    ch = b if isinstance(b, dict) else {}

s, b, h = call("GET", "/movies?limit=200")
show("GET /movies", s, b, h)
movies = b.get("data", []) if isinstance(b, dict) else []
collect("movie", movies)
s, b, h = call("GET", "/movies/featured")
show("GET /movies/featured", s, b)
if movies:
    mid = next((m["id"] for m in movies if m.get("stream_url")), movies[0]["id"])
    s, b, h = call("GET", "/movies/%s" % mid)
    show("GET /movies/%s" % mid, s, b)
    d = b.get("data", {}) if isinstance(b, dict) else {}
    print("   detail extras: drm=%s trailers=%d artwork=%d cast=%d markers=%d subtitles=%d" % (
        d.get("drm") is not None, len(d.get("trailers") or []), len(d.get("artwork") or []), len(d.get("cast") or []),
        len(d.get("markers") or []), len(d.get("subtitles") or [])))
    if d.get("cast"):
        print("   cast[0] keys:", sorted(d["cast"][0].keys()))
    if d.get("subtitles"):
        print("   subtitles[0]:", shape(d["subtitles"][0]))
    if d.get("drm"):
        print("   drm:", shape(d["drm"]))
    s, b, h = call("GET", "/auth/watch-progress?content_type=movie&content_id=%s" % mid)
    show("GET /auth/watch-progress (movie %s)" % mid, s, b)

s, b, h = call("GET", "/series?limit=200")
show("GET /series", s, b, h)
series = b.get("data", []) if isinstance(b, dict) else []
ep_ids = []
if series:
    sid = series[0]["id"]
    s, b, h = call("GET", "/series/%s" % sid)
    show("GET /series/%s" % sid, s, b)
    d = b.get("data", {}) if isinstance(b, dict) else {}
    for season in d.get("seasons") or []:
        for ep in season.get("episodes") or []:
            ep_ids.append(ep["id"])
            if ep.get("stream_url"):
                streams.append(("episode", ep["id"], ep["stream_url"]))
    if ep_ids:
        s, b, h = call("GET", "/episodes/%s" % ep_ids[0])
        show("GET /episodes/%s" % ep_ids[0], s, b)
        s, b, h = call("GET", "/auth/watch-progress/batch?content_type=episode&ids=%s" % ",".join(str(i) for i in ep_ids[:5]))
        show("GET /auth/watch-progress/batch", s, b)

# ads (public)
for path in ["/ads/overlay-settings", "/ads/serve?zone_type=pre_roll&platform=mobile-dev&limit=1",
             "/ads/serve?zone_type=banner&platform=mobile-dev&limit=1", "/ads/breaks?content_type=movie&content_id=1&duration=3600"]:
    s, b, h = call("GET", path, auth=False)
    show("GET " + path + " (no auth)", s, b)

# ---------------------------------------------------------------- stream analysis
print("\n\n==================== STREAM URL ANALYSIS ====================")
print("total stream URLs:", len(streams))
by_kind = Counter(k for k, _, _ in streams)
print("by kind:", dict(by_kind))
scheme = Counter()
fmt = Counter()
hosts = defaultdict(Counter)
example = {}
for kind, _id, u in streams:
    p = urllib.parse.urlparse(u)
    scheme[(kind, p.scheme)] += 1
    path = p.path.lower()
    f = "m3u8" if ".m3u8" in path else "mpd" if ".mpd" in path else "mp4" if ".mp4" in path else "ts" if path.endswith(".ts") else "other(%s)" % (path.rsplit(".", 1)[-1][:6] if "." in path else "no-ext")
    fmt[(kind, f)] += 1
    hosts[kind][(p.scheme, p.netloc)] += 1
    example.setdefault((kind, p.scheme, p.netloc), u)
print("schemes:", dict(scheme))
print("formats:", dict(fmt))
for kind, c in hosts.items():
    print("hosts for %s:" % kind)
    for (sch, host), n in c.most_common():
        print("   %s://%s  ×%d   e.g. %s" % (sch, host, n, example[(kind, sch, host)][:120]))

print("\n-- playlist probes (one per host, GET, read-only) --")
for (kind, sch, host), u in example.items():
    if ".m3u8" not in u.lower():
        continue
    try:
        req = urllib.request.Request(u, headers={"User-Agent": "cari-tv-verify/1.0"})
        with urllib.request.urlopen(req, timeout=15) as r:
            text = r.read(200000).decode("utf-8", "replace")
            first = [l for l in text.splitlines() if l.strip()][:8]
            print("   %s %s://%s → %s %s" % (kind, sch, host, r.status, r.headers.get("Content-Type")))
            print("      master? %s  variants=%d  EXT-X-KEY=%s  media-seq=%s" % (
                "#EXT-X-STREAM-INF" in text, text.count("#EXT-X-STREAM-INF"), "#EXT-X-KEY" in text, "#EXT-X-MEDIA-SEQUENCE" in text))
            keylines = [l for l in text.splitlines() if l.startswith("#EXT-X-KEY")]
            for kl in keylines[:2]:
                print("      ", kl[:160])
            print("      head:", " | ".join(l[:60] for l in first[:4]))
    except urllib.error.HTTPError as e:
        print("   %s %s://%s → HTTP %s" % (kind, sch, host, e.code))
    except Exception as e:
        print("   %s %s://%s → ERROR %s" % (kind, sch, host, str(e)[:100]))

# ---------------------------------------------------------------- logout
s, b, h = call("POST", "/auth/logout", {"refresh_token": REFRESH}, auth=False)
show("POST /auth/logout", s, b)
print("\nDone.")
