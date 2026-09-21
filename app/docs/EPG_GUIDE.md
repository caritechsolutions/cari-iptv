# TV guide (EPG) in the app

Mirrors the web player's live page (`public/assets/js/player/app.js`: `generatePlaceholderEpg`, `buildEpgMap`, `renderEpgGrid`, `scrollEpg`).

## Data model

| Piece | Where | Behaviour |
|---|---|---|
| `EpgProgramme` | `lib/models/epg.dart` | Real guide row **or** placeholder. Always has `start`/`end` (UTC) when shown; `isPlaceholder`, `key` (`channelId_startIso`, same as the web's programme key), `duration`, `isAiringAt/isPastAt/isFutureAt`. Rows without valid times are dropped. |
| `placeholderRange(now)` / `placeholderBlocks(channel, from, to)` | `lib/features/live/epg/guide_model.dart` | Hour-aligned placeholder blocks over a range, clipped at both ends: title `<Channel> Content`, description `Regular programming on <Channel>`, category = channel category or `General`. The range is three hours ago (local, on the hour) to 24 hours ahead, the web's 27 hourly blocks. |
| `placeholderSchedule(channel, now)` | same | The full 27-block schedule for a channel with no guide data at all. |
| `fillGaps(channel, real, now)` | same | Real programmes plus clipped placeholder blocks in every gap (before the first, between, after the last), so the channel has continuous, non-overlapping coverage of the range. Overlapping real rows are kept as they are. |
| `buildGuideMap(channels, guide, now)` | same | Programmes per channel, sorted: `fillGaps` for channels with data, `placeholderSchedule` for the rest. This goes one step past the web, which fills only channels without any data. |
| `nowAndNext(programmes, now)` | same | Now = airing block; next = first block starting after now. Used by the Live TV tiles and the channel guide. |
| `GuideWindow.around(now)` | same | Grid range: two hours before now floored to :00/:30 local, 8 hours wide, 30-minute columns, 7 px per minute (web constants). `xFor`, `blockFor` (clipped to the window). |

## Grid (`lib/features/live/ui/epg_grid.dart`)

- Sticky channel column (logo + name) on the left, sticky time header on top, horizontal scroll for time, vertical scroll for channels (two linked lists with equal row extents).
- Now marker line, moved every 30 s by `GuideScreen`; airing block gets a highlight and progress bar, past blocks are dimmed.
- Block titles stay readable: when a block's start is off the left edge, its title and time shift right by the hidden amount (keeping at least 48 px of the block for the text), so the airing programme's name is visible on a phone. The web clips instead.
- Opens with the current time a third of the way into the timeline. `EpgGridController.scrollHours(±1)` and `jumpToNow()` back the prev / **Now** / next buttons in the app bar.
- Category filter chips (`/categories?type=live`) filter the rows, like the web.
- **Every block is tappable**, placeholders included: `onProgrammeTap(channel, programme)` opens `showProgrammeSheet` (title, channel, exact start–end, duration, category, description, "Watch now" while airing).

## Time-shift readiness

Nothing time-shifts yet. When it is added:

1. The tap target already exists (`showProgrammeSheet`) and receives the exact `programme.start` / `programme.end` of the block, real or placeholder.
2. Add a "Watch from HH:mm" action there that builds a `PlaybackRequest` with a start offset (or a catch-up URL) from `programme.start`.
3. The grid, model and placeholder generation need no change; `EpgProgramme.key` identifies a block across reloads.

## Screens

- `/guide` — `GuideScreen`: the grid with chips and paging. Optional `highlightChannelId` marks a row.
- `/guide/:id` — `ChannelGuideScreen`: one channel's day list (7-day picker); today falls back to placeholders when the channel has no data.
- `/live` — channel list whose now/next come from the same `buildGuideMap` (placeholders included).

Tests: `test/features/live/guide_model_test.dart` (placeholders, gap filling, map, now/next, window maths) and `test/features/live/epg_grid_test.dart` (now in view, sticky column, paging, vertical sync, pinned titles, taps on real and placeholder blocks, channel tap).
