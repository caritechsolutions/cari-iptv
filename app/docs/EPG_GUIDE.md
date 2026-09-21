# TV guide (EPG) in the app

Mirrors the web player's live page (`public/assets/js/player/app.js`: `generatePlaceholderEpg`, `buildEpgMap`, `renderEpgGrid`, `scrollEpg`).

## Data model

| Piece | Where | Behaviour |
|---|---|---|
| `EpgProgramme` | `lib/models/epg.dart` | Real guide row **or** placeholder. Always has `start`/`end` (UTC) when shown; `isPlaceholder`, `key` (`channelId_startIso`, same as the web's programme key), `duration`, `isAiringAt/isPastAt/isFutureAt`. Rows without valid times are dropped. |
| `placeholderSchedule(channel, now)` | `lib/features/live/epg/guide_model.dart` | 27 one-hour blocks from three hours ago (local, on the hour): title `<Channel> Content`, description `Regular programming on <Channel>`, category = channel category or `General`. Exact, contiguous times. |
| `buildGuideMap(channels, guide, now)` | same | Programmes per channel: real rows when a channel has any, placeholders otherwise (as the web). |
| `nowAndNext(programmes, now)` | same | Now = airing block; next = first block starting after now. Used by the Live TV tiles and the channel guide. |
| `GuideWindow.around(now)` | same | Grid range: two hours before now floored to :00/:30 local, 8 hours wide, 30-minute columns, 7 px per minute (web constants). `xFor`, `blockFor` (clipped to the window). |

## Grid (`lib/features/live/ui/epg_grid.dart`)

- Sticky channel column (logo + name) on the left, sticky time header on top, horizontal scroll for time, vertical scroll for channels (two linked lists with equal row extents).
- Now marker line, moved every 30 s by `GuideScreen`; airing block gets a highlight and progress bar, past blocks are dimmed.
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

Tests: `test/features/live/guide_model_test.dart` (placeholders, map, now/next, window maths) and `test/features/live/epg_grid_test.dart` (now in view, sticky column, paging, vertical sync, taps on real and placeholder blocks, channel tap).
