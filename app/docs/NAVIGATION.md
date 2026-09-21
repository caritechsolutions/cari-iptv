# Navigation audit

Every screen in the app, how you reach it, how you leave it, and whether the bottom tab bar is visible. The **Before** column records what the audit found in the code before the fix; **Now** is the behaviour after it (verified by `test/navigation/navigation_walk_test.dart`, which walks every route and presses the system back button on each).

## Rules

1. Every screen that is not a bottom-tab root shows an in-app back arrow, and the system back button (or gesture) does exactly what the arrow does.
2. The bottom tab bar stays visible on all top-level pages, Search and Settings included.
3. Leaving the player by any route stops playback and releases the native player; no audio may continue once the player is off screen. Watch progress is saved on the way out.
4. When the app goes to the background (home button, screen off, incoming call) playback pauses. It does not auto-resume; the user presses play.
5. System back on a bottom-tab root goes to Home first; only Home exits the app.
6. In the player, the first back leaves full screen; the second closes the player.

## Structure

```
Root navigator
├─ /splash, /login, /register, /forgot-password, /verify-pending     (auth, no tabs)
├─ Shell (bottom tab bar)                                              ← PopScope: back on a tab root → Home; Home → exit
│  ├─ tab roots: whichever of the pages below the backend navigation lists (max 5)
│  └─ pages inside the shell (tab bar visible): /home /movies /series /live /categories
│     /my-list /subscribe /profile /page/:slug /search /settings
│     – reached with `go` when it is a tab (no back arrow), with `push` otherwise (back arrow)
└─ full-screen pages pushed over the shell (no tab bar): /movie/:id /series/:id
   /episode/:id /category/:id /person/:id /guide /guide/:id /player
```

Which pages are tabs comes from the backend (`GET /app/navigation/mobile`, page types `home movies series live_tv categories search watchlist settings profile subscription custom`). When the backend has no mobile navigation the app uses Home, Movies, TV Shows, Live TV, My List. `openTopLevel()` (see `app_shell.dart`) decides at runtime: `go` to a tab, `push` to anything else, so a page is never left without a way back.

## Screens

| Screen | Route | How you get there | How you leave (arrow / system back) | Tab bar | Before the fix | Now |
|---|---|---|---|---|---|---|
| Splash | `/splash` | app start | automatic redirect | no | – | – |
| Sign In | `/login` | signed-out redirect, sign-out, session expiry | root: system back exits the app | no | ok | ok |
| Register | `/register` | Sign In → Register (push) | arrow / back → Sign In | no | ok | ok |
| Forgot password | `/forgot-password` | Sign In → Forgot password? (push) | arrow / back → Sign In | no | ok | ok |
| Verify e-mail | `/verify-pending` | after registering (`go`) | arrow / back → Sign In; "Go to Sign In" button | no | **Dead end**: no arrow (nothing to pop); system back exited the app | arrow + back go to Sign In |
| Home | `/home` | Home tab, fallback of every "leave" | tab root: back exits the app | yes | ok | ok |
| Movies | `/movies` | tab, or "See all" from Home | tab root: back → Home. Pushed (not a tab): arrow / back → previous | yes | **Inconsistent**: "See all" used `go` even when Movies is not a tab → no arrow, back exited the app | `openTopLevel` |
| TV Shows | `/series` | tab, or "See all" | as Movies | yes | same as Movies | `openTopLevel` |
| Live TV | `/live` | tab, or "See all" (channel grid, live now, TV guide, Live TV rail) | as Movies | yes | same as Movies | `openTopLevel` |
| Categories | `/categories` | tab, or "See all" from the category grid | as Movies | yes | same as Movies | `openTopLevel` |
| My List | `/my-list` | tab | tab root: back → Home | yes | back exited the app | back → Home |
| Packages | `/subscribe` | tab, or Profile → View all packages, or Packages section "See all" | as Movies | yes | `go` from Profile/layout → no arrow when not a tab; back exited the app | `openTopLevel` |
| Profile | `/profile` | tab, or Settings → account row | as Movies | yes | `go` from Settings → no arrow when not a tab | `openTopLevel` |
| Custom page | `/page/:slug` | tab, or a custom link on a card | tab root: back → Home; pushed: arrow / back | yes | ok | ok |
| Search | `/search` | Search icon in every tab app bar (push), or Search tab | pushed: arrow / back → previous page. Tab: back → Home | **yes** | **Dead end (reported)**: route lived outside the shell, so no tab bar; when the backend makes Search a tab it was opened with `go` → nothing to pop → no arrow, back exited the app | inside the shell |
| Settings | `/settings` | Account icon in every tab app bar (push), Profile → Settings, or Settings tab | as Search | **yes** | same structure as Search (dead end when it is a tab) | inside the shell |
| Movie detail | `/movie/:id` | any movie card | arrow / back → previous | no | **No arrow while loading or on error**: the back arrow was inside the loaded-content view only | arrow in every state |
| Series detail | `/series/:id` | any series card | arrow / back → previous | no | same as Movie detail | arrow in every state |
| Episode launcher | `/episode/:id` | Continue-watching episode cards, deep links | replaced by the player, or leaves by itself | no | **Broken**: popped itself, then tried to open the player with the popped context → the player never opened (silent no-op); on a gated or external title the spinner stayed forever | player replaces the launcher; if nothing opens it leaves |
| Category | `/category/:id` | category chips and "Browse by category" tiles | arrow / back → previous | no | ok | ok |
| Person | `/person/:id` | cast member on a detail page | arrow / back → previous | no | ok | ok |
| TV Guide | `/guide` | Live TV app bar action | arrow / back → previous | no | ok | ok |
| Channel guide | `/guide/:id` | long-press a channel | arrow / back → previous | no | ok | ok |
| Player | `/player` | Play buttons, channel cards, episode launcher | arrow / back → previous (first back leaves full screen). Playback released, progress saved | no | **Audio continued after back (reported)**: `dispose()` called `ref.read(...)`, which throws once the widget is unmounted, before the controller was released; the throw also skipped `super.dispose()` and the wakelock release. Live channels stopped (the release ran before the throw) but leaked the wakelock and orientation cleanup | `_releaseController()` first and unconditionally: pause → save progress → dispose; repositories captured in `initState`; background pause via `WidgetsBindingObserver` |
| Nothing to play | `/player` without a request | programming error only | arrow / back | no | ok | ok |

## Problems the audit found

Reported by the user:

- **A. Video kept playing after back.** `PlayerScreen.dispose()` (old `player_screen.dart:352-374`) read repositories through `ref` to post `watch_abandon` *before* calling `controller.dispose()`. Riverpod 3 throws `StateError('Using "ref" when a widget is about to or has been unmounted is unsafe')` because `Element.unmount()` clears the widget before `State.dispose()` runs. The route was disposed on back (the portrait restore in the child `PlayerShell` proved that), but the controller was never paused or released, so ExoPlayer kept playing. It only happened for VOD with position > 0 (the branch that used `ref`); live channels released the player but then threw on the analytics flush after it.
- **B. Search had no way back.** `/search` was a route outside the tab shell, so the tab bar was never shown there, and when the backend navigation lists Search as a tab it was opened with `go`, leaving nothing to pop and therefore no back arrow.

Found by the audit, not reported:

1. Settings had the same structure as Search (no tab bar; a dead end when the backend makes it a tab).
2. "See all" links on Home and in layout sections used `go` to `/movies`, `/series`, `/live`, `/categories`, `/subscribe`; when the target is not one of the bottom tabs that replaced the stack with a page that has no back arrow, and system back then exited the app.
3. Profile → "View all packages" and Settings → account row did the same with `go`.
4. System back on any tab root other than Home exited the app immediately.
5. Movie and series detail pages had no back arrow while loading or when loading failed (the arrow lived inside the loaded view), so a title that fails to load could only be left with the system back.
6. The episode launcher (`/episode/:id`, used by Continue Watching episode cards) popped itself and then tried to navigate with the popped context, so those cards never opened the player; when the title was gated or external it stayed on a spinner forever.
7. Verify-email screen after registering had no back arrow and system back exited the app.
8. Progress for the *next* episode (auto-play / "Next episode") was reported against the first episode's id because the reporter kept the initial request.
9. Live channels: the same `ref` use in `dispose()` threw after the controller was released, skipping the wakelock release, the full-screen cleanup and `super.dispose()`.
10. `_initController` disposed the previous controller with an `await` and, if the screen was left during that await, created a new controller on a dead screen (a leak, silent because it never played). All continuation points now check that the screen is mounted and the controller is still current.
11. No pause when the app went to the background beyond video_player's own `paused` handling, which auto-resumed on return; the app now pauses on `inactive`/`hidden`/`paused` (covers screen off and incoming calls) and leaves resuming to the user.

## Tests

- `test/features/player/player_release_test.dart`: system back and the in-app arrow close the player, `pause` precedes `dispose` on the platform, nothing talks to the player afterwards, progress is saved at the last position, `watch_abandon` is tracked; backgrounding pauses and saves without releasing and does not auto-resume; leaving before the stream is ready still releases.
- `test/navigation/navigation_walk_test.dart`: with the real router, every pushed route shows a back arrow and system back returns to where the user was; tab-bar visibility per route; tab roots go to Home on back and Home exits; Search and Settings as backend tabs; Search from the app bar keeps the tab bar and has an arrow; auth screens.
- `test/features/player/player_fullscreen_test.dart`: full-screen enter/exit and the two-step back.
