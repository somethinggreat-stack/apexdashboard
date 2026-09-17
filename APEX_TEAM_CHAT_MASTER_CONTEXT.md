# Apex Team Chat — Master Context (single source of truth)

> Read this first before touching Team Chat. It captures the architecture, deploy model,
> features, key code decisions, gotchas, versions, and rules. Keep it updated when you change things.
> Last major update: 2026-09-18.

---

## 1. What this is

An internal team chat for the **Apex Credit Dashboard** (Laravel 11). Two products in one codebase:

1. **Web chat** — Blade + vanilla JS, under `Route::prefix('admin')`, guard `auth:admin`. For **super admins and VAs only** (role `leads` is excluded from chat).
2. **Native desktop app** (`apex-desktop`, Tauri v2 + WebView2) — a thin client that loads the web chat at `https://chat.apexgrowthsolution.com/admin/team-messages?standalone=1`. This is how VAs actually use the chat.

Web Push is **disabled**; the desktop app fires **native OS notifications** while it polls in the tray.

---

## 2. Deploy architecture (CRITICAL)

- **One git repo → two cPanel Git checkouts**, differentiated by a `.deploy-target` marker file (gitignored) read by `.cpanel.yml` to set `DEPLOYPATH`:
  - **apexdashboard** → `/home2/apexgrow/public_html/` (main dashboard host)
  - **apexchat** → `/home2/apexgrow/chat.apexgrowthsolution.com/` (chat subdomain host)
- **Shared MySQL database** — both checkouts use the same DB. A DB migration run on one is visible to both; run migrations once.
- **`.cpanel.yml`** on deploy: copies `app bootstrap config database public resources routes tests`, `.htaccess`, `artisan`, composer/package files; `mkdir -p` the `storage/` tree (incl. `storage/app/private`, `storage/app/public`) + `bootstrap/cache`; `chmod -R 775`; `composer install --no-dev --optimize-autoloader` (with `COMPOSER_MEMORY_LIMIT=-1`); `config:clear`/`route:clear`/`view:clear`; **`php artisan migrate --force`**; `db:seed --class=TeamSeeder --force`; `storage:link`; `images:optimize`; caches. Critical steps end in `|| fail "..."`, which **logs** the failed step to `storage/logs/deploy-failed.txt` and carries on; the run always writes `storage/logs/deployed-at.txt`. **`fail()` must never `exit`** — see the trap in §11.
- **Do NOT add `sessions:flush` to deploy** — it wiped sessions and caused "CSRF token mismatch" for logged-in users. (Removed; the app also has a graceful 419 `reconnect(draft)` path that stashes the unsent draft and reloads.)
- Deploying the chat host = "update from remote" (cPanel pulls the branch). `public/` is copied, so anything in `public/download/` ships to the chat host.

### Guards / middleware
- **`ChatAppOnly`** (`app/Http/Middleware/ChatAppOnly.php`): blocks any non-`ApexDesktop` User-Agent from `admin/chat-login` + `admin/team-messages`, redirecting browsers to `https://<chat_host>/download`. **The chat is desktop-app-only in the browser.**
- **`ChatHostGuard`**: the chat subdomain serves chat only.
- **`.env` `WEBPUSH_ENABLED`** defaults false; `config('webpush.enabled')` gates `WebPushSender::enabled()`.

---

## 3. Repositories & key files

### Main repo: `Z:\Projects\Apex Credit Dashboard` (this repo; git remote `somethinggreat-stack/apexdashboard`, branch `main`)
- `app/Http/Controllers/Admin/TeamMessageController.php` — the central chat controller (index, open, thread, older, store, search, react, pin, forward, group CRUD, attachment, avatar, notifications, presence, typing, purge trigger).
- `resources/views/admin/team-messages/index.blade.php` — the giant main view (~2900 lines). Contains the HTML, the `<script data-tc>` chat app JS, and (previously) the CSS.
- `public/css/team-chat.css` — the chat CSS, **externalized** from index.blade.php (static, ~67KB, cacheable). Referenced via `<link ... ?v={filemtime}>` inside `@push('head')`.
- `resources/views/layouts/chat.blade.php` — the **standalone** chat layout (`?standalone=1`). Forces light mode (`<html data-theme="light">`, white body, no OS-dark switch). Stacks: `@stack('head')`, `@stack('scripts')` — NOTE: index pushes CSS to `head`, not a `styles` stack.
- `resources/views/partials/team-message.blade.php` — one message bubble (mirrors client `bubbleInner`/`buildRow`).
- `resources/views/partials/team-chat-row.blade.php` — one sidebar contact/group row.
- `resources/views/partials/team-realtime.blade.php` — the **global realtime/notification poller** (runs on every admin page). Leader election + BroadcastChannel + OS notifications + nav badge.
- `app/Console/Commands/PurgeOldTeamChat.php` — `team-chat:purge` (7-day retention + `--all` wipe).
- `app/Models/Admin.php` — `avatarUrl()`, `dataOwnerId()`, `avatar` column. `role`/`parent_admin_id` are intentionally NOT mass-assignable (security invariant).
- `app/Models/Conversation.php`, `TeamMessage.php`, `ConversationParticipant.php`, `MessageAttachment.php`.
- `public/download/` — `apex-team-chat-setup.exe` (installer, stable name), `index.html` (static download page), `latest.json` (updater manifest). The `/download` route was a 403 (shadowed by the physical dir) → serve a static `index.html`.
- `public/sw.js` — service worker: **pass-through, no asset caching** (only needed for PWA installability); clears all caches on activate.
- Routes: `routes/web.php`, inside `Route::prefix('admin')` group. Names: `admin.team-messages.{index,open,thread,older,presence,notifications,store,search,typing,notify,attachment,avatar,avatar.update,avatar.remove,react,favorite,mute,pin,forward,group.store,group.members.add,group.members.remove,group.leave,group.rename,gallery,update,destroy}`. **Route order matters**: literal routes (`open`, `search`, `avatar`) are registered before the `team-messages/{message}` wildcard.

### Desktop repo: `Z:\Projects\apex-desktop` — **NOT a git repo** (changes live only in files)
- `src-tauri/tauri.conf.json` — window url (**`index.html`**, the bundled waiting room — see below), `userAgent` (must contain `ApexDesktop/1.0`), `additionalBrowserArgs`, `dragDropEnabled: false`, bundle targets `["nsis"]`, `createUpdaterArtifacts: true`, updater `endpoints`/`pubkey`, nsis `installerIcon`, `version`.
- `src/index.html` — **the waiting room**. The window no longer opens straight onto the live chat URL: starting with no internet left WebView2's own grey "can't reach this page" error on screen with no retry. This page pings `/up`, goes through the moment it answers, and otherwise shows "No connection" and retries every 4s (plus a plain `<a>` in the HTML as a last resort if its script ever fails). Verified by `bootstrap.js` in the session scratchpad.
- `src-tauri/src/lib.rs` — tray (Open/Reload/Install update/Sign out/Quit), close-to-tray, `set_unread` command (taskbar overlay badge), single-instance focus, summon hotkey (`SUMMON_KEYS`, first that registers), autostart-once + `--autostarted` → start in tray, updater check at startup and every 6h, opener plugin (external links → real browser).
- `src-tauri/Cargo.toml` — `image-png` feature needed for the badge image.
- Plugins: `tauri-plugin-notification`, `-autostart`, `-global-shortcut`, `-updater`, `-opener`, `-single-instance` (**must be registered first**).

---

## 4. Database — chat tables

- **`conversations`**: `type` (`dm`|`group`), `name`, `icon`, `data_owner_id`, `dm_key`, `last_message_id`, `last_message_at`, `created_by`.
  - `dm_key`: `self:<adminId>` for the "message yourself" notes thread; `dm:<minId>:<maxId>` for a DM.
- **`conversation_participants`**: `conversation_id`, `admin_id`, `role` (`member`|`admin`), `muted`, `favorite`, `last_read_message_id`, `typing_at`, `notify_level` (`all`|`mentions`|`none`), `joined_at`.
- **`team_messages`**: `conversation_id`, `type` (`text`|`system`), `sender_id`, `reply_to_id`, `body`, `forwarded`, `pinned_at`, `edited_at`, `deleted_at`, `deleted_by`, `reactions` (JSON map uid→emoji), `mentions_all`, `mentions_all_at` (when it first said @everyone), `client_uuid`, mentioned admins via pivot.
- **`message_mentions`**: `team_message_id`, `admin_id`, `created_at` (when the mention appeared — what makes a mention added by editing notify).
- **`message_attachments`**: `team_message_id`, `disk_path`, `original_name`, `mime`, `size`, `width`, `height`. Files live on the **private disk** at `storage/app/private/team-chat/<ownerId>/<uuid>.<ext>`.
- **`admins.avatar`** (nullable string, added 2026-09-15): `team-avatars/<id>.jpg` (uploaded, on private disk, served via guarded route), a bundled filename, or `'-'` (removed → monogram). `avatarUrl()` streams uploaded ones via `admin.team-messages.avatar` and **falls back to a monogram if the file isn't present on this host** (chat & dashboard hosts share only the DB, not the private disk).

### DM contacts vs groups (important behavioral fact)
- **DM contacts are virtual** — every teammate (super + their VAs, excluding leads) shows as a DM row even with **no** `conversations` row.
- **Groups only appear if their `conversations` row exists.** Deleting `conversations` rows makes groups vanish (DMs remain). This is why a full `DELETE FROM conversations` wipe removes groups. The 7-day auto-purge NEVER deletes conversations — only messages.

---

## 5. Retention (7-day auto-clear) — `team-chat:purge`

- Command `PurgeOldTeamChat` (`app/Console/Commands/PurgeOldTeamChat.php`), scheduled `->hourly()` in `routes/console.php`.
- Options: `--days=7` (default), `--all` (wipe everything, ignores days), `--dry-run`.
- **`--days` below 1 is clamped back to 7** (safety, so a stray `--days=0` can't nuke). Use **`--all`** for a true wipe.
- Deletes messages + attachments + their private-disk files older than the window; keeps conversations (re-points `last_message_id`); sweeps orphan files under `team-chat/`.
- **Cron-less trigger** (the chat host has no cron): `TeamMessageController::maybePurgeRetention()` runs at the start of `notifications()` (the poll endpoint). Guarded by a per-docroot marker `storage_path('framework/team-chat-purge.at')` — at most once/hour — and runs the purge via `app()->terminating()` (after the response). Skipped under `app()->runningUnitTests()`.

### Manual clear SQL (run in phpMyAdmin on the shared DB)
Clear history, **keep groups/contacts**:
```sql
DELETE FROM message_attachments;
DELETE FROM team_messages;
UPDATE conversations SET last_message_id = NULL, last_message_at = NULL;
```
Full nuke (also removes groups): add `DELETE FROM conversation_participants; DELETE FROM conversations;`
Files: `php artisan team-chat:purge` (orphan sweep) or `rm -rf storage/app/private/team-chat/*` on the chat host.
CLI equivalent (keeps groups): `php artisan team-chat:purge --all` (dry run: `--all --dry-run`).

---

## 6. Chat-open speed — the "light-speed" architecture

The perceived ~1s open was NOT a full browser reload — see the pre-existing SPA layer below. Opens are now instant via a light in-place swap.

### The pre-existing `tcNav` SPA layer (index.blade.php ~line ~3384) — KNOW THIS
- `window.tcNav(url)` opens chats reload-free by fetching the **full 283KB index HTML** (`X-Requested-With: fetch`), DOMParsing it, replacing `.tc-wrap`, and **re-running the entire `script[data-tc]`**. That full re-run is the real cost.
- A **cleanup registry** `window.__tcReg` makes re-running safe: `window.TCI` (intervals), `TCB` (append-to-body nodes), `TCD` (document listeners), `TCW` (window listeners) are all tracked; `reg.cleanup()` tears them down before a re-run. Use TCI/TCB/TCD/TCW for anything that must survive/clean up across a tcNav swap.
- tcNav intercepts `a.tc-contact, a.tc-sr-item` clicks. It is now the **fallback only** (errors + header-search-result links).

### Tier 1 — light in-place swap (JSON, no script re-run)
- **`/open` endpoint** (`TeamMessageController::open`) returns the newest page + header + composer state + (for groups) the roster, as JSON. ~6.4KB vs 283KB (44× smaller). Accepts `?c=<id>` / `?with=<peer>` / `?self=1` / `&prefetch=1`.
- Client `applyOpen(data, url, push)` (inside the thread IIFE in index.blade.php) updates the thread panel in place: header identity block rebuilt for DM/self **or group**, composer target/placeholder, messages (`renderThreadMessages`), pins, read receipts, active sidebar row, `history.pushState`. `IS_GROUP`, `GID`, `CONV`, `PEER_ID` are **mutable** vars updated here.
- Sidebar-click interceptor (a separate IIFE) calls `/open` and `applyOpen`, and **`stopPropagation()` so tcNav does NOT also run**. `swapEligible()` now returns true for all conversation types. On fetch error → falls back to `tcNav` (still reload-free), never a hard reload.
- Message actions (3-dots menu, reply, react, load-earlier) survive swaps because they're **delegated on the stable `box` (`#tcMessages`)** and the body-level menu.

### Tier 2 — prefetch
- Hover / idle-warm the top ~5 visible rows: fetch `/open?...&prefetch=1` into an in-memory cache. **`prefetch=1` skips `markRead`** so hovering doesn't mark chats read. On a real open the read-marking happens via a **forced thread poll** (`apex:thread-poll`).

### Tier 3 — persistent IndexedDB cache
- IndexedDB DB **`apexChat`**, object store **`threads`** — snapshot each opened/prefetched thread. On click with no in-memory copy: paint from disk instantly, then a background `/open` refresh re-applies only if a cheap signature (conv id, msg count, last id, readUpTo, pins, title) differs.

### Groups made light too
- `/open` returns `group` = `{id, name, icon, isAdmin, members[], addable[]}`. The **members modal is client-rendered** from this into a persistent `#tcMembers` shell; `GID` is mutable and re-pointed on every group switch; header controls (`#tcMembersBtn`, `#tcGroupNameEdit`) are delegated on `.tc-thread`. Add/remove/rename/leave are **still authorised server-side** and target the swapped group; they `location.reload()` on success.

### Request freshness guards (Phase 1 integrity fixes, 2026-09-17)
Every async request in the thread script checks, when it returns, that the chat it was started for is still on screen:
- `window.__tcRun` — bumped on each `script[data-tc]` run; a callback from an older run (`runAlive()`) does nothing. `reg.cleanup()` also deletes `window.tcApplyOpen/tcSwapEligible/tcOpenUrl/tcGalleryDirty/tcDownload`, so a page with no thread never routes clicks into dead code.
- `VIEW_GEN` — bumped on every `applyOpen`; `poll`, `loadOlder`, `doEdit`, `loadGallery` drop answers where `viewAlive(gen)` is false.
- `chatKey()` — chat identity (`self` / `p<peerId>` for 1:1 / `c<convId>` for groups). The send path uses `chatAlive(key)`. `#tcMessages` now always carries `data-peer` for 1:1 chats (same as `/open`), so the key is identical after a full load or an in-place open.
- `window.__tcNavSeq` — shared "latest navigation wins" ticket for `openInPlace` and `tcNav`; the previous `/open` fetch is aborted.
- `applyOpen` re-applying the SAME chat (cached paint → network) keeps the composer and doesn't push a second history entry. It always fires `apex:conv-adopted` (conv `''` when there's no conversation yet), so the sidebar/notifier stop treating the previous chat as open.

### CSS externalization
- The 67KB static CSS moved from an inline `<style>` to `public/css/team-chat.css`, linked with a `?v=<filemtime>` cache-buster. Shrinks the page (283KB → ~213KB) and caches across launches. The 147KB inline JS stays inline (externalizing it would break tcNav's `script[data-tc].textContent` re-run — not worth it).

---

## 7. Real-time delivery (messages/notifications appear without interaction)

### The poller (`partials/team-realtime.blade.php`)
- One elected **leader** tab polls the network; others sync via **BroadcastChannel** `apex-team`. Leader election via localStorage `apex-team-leader` (visible tab preempts hidden).
- Poll cadence: **`document.hidden ? 8000 : 4000`** ms (hidden was 15000, tightened to 8000). Fetches `admin.team-messages.notifications?after=<lastId>`, `ingest()`s new messages → dispatches `apex:team-message` → sidebar (`bumpSidebar`) + open thread (`apex:thread-poll` → `poll(true)`).
- The open thread also has its own 3s poll (`TCI(poll, 3000)`).

### The bug + fix
- **Bug:** in WebView2, when the app window is **occluded/backgrounded**, Chromium **freezes the renderer + its timers**, so the poll (and the OS notifications it fires) stops until a user gesture (a click). `document.hidden` can stay false (no `visibilitychange`), so focus alone didn't resume it.
- **Fix A (web, ships with chat deploy):** poll immediately on `window` **focus**, and on any pointer/keydown (debounced 1.5s); refresh the OPEN thread on focus/visibility. So returning to the app shows new messages with no click. (Playwright-verified: focus delivers a pending message in ~660ms.)
- **Fix B (desktop, the real background fix — needs the 0.1.1 build):** `additionalBrowserArgs` in `tauri.conf.json`:
  `--disable-features=msWebOOUI,msPdfOOUI,msSmartScreenProtection,CalculateNativeWinOcclusion --disable-background-timer-throttling --disable-backgrounding-occluded-windows --disable-renderer-backgrounding`
  — keeps the renderer + timers alive in the background so polling + OS notifications keep running while the VA is in another app.

---

## 8. Desktop app — build, sign, publish, auto-update

- Toolchain present on the dev machine: Rust/cargo at `C:\Users\DELL\.cargo\bin\cargo`, Tauri CLI at `apex-desktop/node_modules/.bin/tauri` (`@tauri-apps/cli@^2`).
- **Signing key:** `C:\Users\DELL\.tauri\apex-updater.key` — **empty password**. Env for build/sign:
  - `TAURI_SIGNING_PRIVATE_KEY` = the **file CONTENTS** (base64 string), NOT the path. (`$env:TAURI_SIGNING_PRIVATE_KEY = Get-Content <key> -Raw` in PowerShell, or `"$(cat <key>)"` in bash.) Passing the path fails with "Invalid symbol 58" (the `:` in `C:`).
  - `TAURI_SIGNING_PRIVATE_KEY_PASSWORD` = `""` (empty).
- **Build:** from `Z:\Projects\apex-desktop`, in bash:
  `TAURI_SIGNING_PRIVATE_KEY="$(cat ~/.tauri/apex-updater.key)" TAURI_SIGNING_PRIVATE_KEY_PASSWORD="" npx tauri build`
  (~4 min from cold, less incrementally; `npm run tauri build` is the same thing). Outputs:
  - Installer: `src-tauri\target\release\bundle\nsis\Apex Team Chat_<version>_x64-setup.exe`
  - Signature: `...exe.sig`
- **App version** comes from `tauri.conf.json` `version` (currently **0.1.5**; `Cargo.toml` + `package.json` kept in step).
- **Publish for auto-update:** copy the installer to `public/download/apex-team-chat-setup.exe` (stable name — renaming does NOT break the signature, which is over the file bytes), and update `public/download/latest.json`:
  ```json
  { "version": "<new>", "notes": "...", "pub_date": "<ISO8601 Z>",
    "platforms": { "windows-x86_64": { "signature": "<contents of the .sig>",
      "url": "https://chat.apexgrowthsolution.com/download/apex-team-chat-setup.exe" } } }
  ```
  Commit + push, then deploy the chat host. Installed older-version apps auto-update on next launch (updater checks `latest.json` on startup; same key pair, so the baked-in pubkey verifies the new signature).
- **First install** for new VAs = download `apex-team-chat-setup.exe` from the download page. No auto-update needed for a first install.

---

## 9. Local test harness & commands

- **PHP tests:** `DB_CONNECTION=sqlite DB_DATABASE=":memory:" C:/php83/php.exe artisan test` (filter with `--filter=...`). Baseline ~257 passed, 3 skipped. Chat feature tests set `$this->withHeader("User-Agent", "ApexDesktop/1.0")` in `setUp` (else `ChatAppOnly` redirects them).
- **Lint PHP:** `C:/php83/php.exe -l <file>`.
- **Local browser harness** (for Playwright / manual):
  - DB: a sqlite file in the session scratchpad; `DB_CONNECTION=sqlite DB_DATABASE=<scratch>/local.sqlite SESSION_DRIVER=file php artisan migrate:fresh --force` then seed.
  - Serve: `... php artisan serve --port=8931 --host=127.0.0.1`. **`artisan serve` is single-threaded** — concurrent requests queue, which causes flaky "no response" in tests (wait longer / poll for the condition).
  - Note: a migration inserts `admin@umair.com` as admin id 1, so the seeded super is id **2** — look ids up, don't hard-code them. Phase 1 browser scripts (`phase1.js`, `phase1b.js`) + `reset.sh` + `seed.php` (adds `va5@apex.test` "Sanwal Khan", a VA with no conversations) lived in the 2026-09-17 session scratchpad.
  - Seed users (password `password123`): `super@apex.test` = **Umair Arshad** (super); `va0..va4@apex.test` = Abid Hussain, Mujeeb Rahman, Raja Khuram, Ubaid Dogar, Umair Sajid (VAs). Groups: "CFPB Screenshots", "Credit Repair CFPB".
  - **Playwright:** `playwright-core` installed in the scratchpad; Chromium at `~/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe`. Launch with `userAgent: 'ApexDesktop/1.0'` (to pass `ChatAppOnly`). Two different users need two **contexts** (cookies are shared within a context). Reliable reload/swap detection: watch `document`-type requests or an `addInitScript` counter — `framenavigated` fires for pushState too, and `page.on('load')` is unreliable here.

---

## 10. Features shipped (2026-09-15 session)

- **Image viewer** prev/next (arrows + keyboard) — all grid images reachable.
- **Header search bar** (Teams-style) — searches message **bodies + attachment file names** across my conversations; results dropdown. (Dropdown was clipped behind the messages area due to `.tc-thread` `overflow:hidden` + shared `z-index:1` stacking — **fixed** by moving `#tcHeaderSearchResults` to `<body>`, `position:fixed`, `z-index:1005`, positioned under the box via JS.)
- **Message yourself** — private notes thread (`?self=1`, `dm_key='self:<id>'`), 1 participant, title "Notes (You)".
- **Task 3 — 7-day retention** (see §5).
- **Task 4 — any file type** on drag-drop/attach (removed the extension allowlist client+server). Safe because non-images are force-downloaded as `application/octet-stream` with `nosniff` from the private disk; disk extension sanitized to a safe token (`/^[a-z0-9]{1,10}$/` else `bin`). Drag-drop + attach-then-type (files stage, nothing auto-sends).
- **Task 5 — download progress dock** — bottom-right cards with live progress bar + "Saved to your Downloads" / retry; streaming `fetch` with `Content-Disposition` filename. Covers thread cards, Files tab, gallery.
- **Task 6 — forced light mode** (white app; was auto-switching to OS dark) + **Teams-style file cards**: received = neutral slate `#eef2f8` card / dark text; sent = frosted-white card on the purple bubble (`.tc-msg.mine .tc-bubble` gradient) with a white icon chip; sent media bubble = purple gradient frame.
- **Task 7 — profile photo** change/remove with a drag-and-zoom face-crop frame (self + every VA manages their own). Upload → GD normalises to a centred square JPEG (max 512px, quality 88) on the private disk `team-avatars/<id>.jpg`; served via guarded org-scoped route; "Remove" sets the `'-'` sentinel → monogram. Also **removed the redundant "Active" text** under the name (kept the green presence dot).
- **Light-speed opens** — Tiers 1/2/3 + groups + CSS externalization (see §6).
- **Real-time focus/interaction catch-up** + desktop no-throttle flags (see §7).
- **Refresh button** — in the sidebar header, order **Refresh · New group · Logout**. Clears **all** local state (every IndexedDB DB, Cache Storage, localStorage, sessionStorage, service workers) then reloads, **keeping the auth cookie so you stay logged in**. Spins; 1.5s fail-safe reload. Header buttons shrunk (`.tc-newgroup` 38→30px, avatar 42→38px, gap 11→8px) so the full name fits.

---

### Send integrity (Phase 1, 2026-09-17)
- **Client id / idempotency:** `team_messages.client_uuid` (nullable, unique per `(sender_id, client_uuid)`, migration `2026_09_23_000001`). The composer sends one id per compose attempt and re-uses it on retry of the same content; `store()` returns the already-saved message (`duplicate: true`) instead of inserting again.
- **One send per chat:** the composer is read-only (`.tc-composer.tc-sending`) while its send is in flight; Enter/click are ignored. Other chats stay usable. A send that fails after the VA switched away is kept in memory and put back when that chat is reopened; a 419 reconnect stashes `{key, body, id}` and restores it only into the same chat (legacy plain-string drafts still restore).
- **All-or-nothing save:** files are written to disk first (`storeFiles`), then message + attachments + mentions + conversation pointer commit in ONE transaction (`serializedSend`); on failure the files are deleted (`discardFiles`).
- **Ordered commits:** `serializedSend` locks the org owner's `admins` row (`lockForUpdate`) before inserting, so an org's message ids become visible in id order — the `after=<id>` polls can't skip a lower id that commits late. Used by `store`, `forward`, `system()` and `findOrCreateDm` (which also makes duplicate DM creation impossible). Only quick DB writes run under the lock. (Not exercised against MySQL locally — sqlite ignores row locks.)
- **Own send doesn't move the poll cursor:** `append(m, true)` shows my message but leaves `lastId`; a forced `poll(true)` then fetches anything a teammate sent just before, and `append` inserts rows in id order.
- **Groups in notifications:** `notifications()` returns `is_group` + `icon`; the sidebar adopts a "tap to message" DM row only for `is_group === false`, and adds a new row for a group it doesn't know.
- **Purge:** the orphan sweep skips files modified in the last 10 minutes (an in-progress send), except with `--all`.

### Uploads & attachments (Phase 2, 2026-09-17)
- **One set of limits, measured, not guessed.** `TeamMessageController::uploadLimits()` = the smallest of our caps (50 MB/file, 10 files), PHP's `upload_max_filesize` / `post_max_size` / `max_file_uploads`, and the edge cap `config('team.chat.max_request_mb')` (default **95 MiB**). **Cloudflare's real limit was measured on 2026-09-17: 104,857,600 bytes (100 MiB) accepted, 106,000,000 rejected with Cloudflare's own 413.** The page gets the same numbers (`$uploadLimits`), refuses files before uploading, and names the file + limit. Server-side `checkAttachments()` repeats every check. Super-admin diagnostic: **`/admin/system/upload-limits`** (dashboard host, browser) prints the live PHP values.
- **No fixed upload timeout.** A stall watchdog replaces it: 45s without upload progress, 180s after the last byte (server processing), 20s for a text-only send. Progress shows "Uploading… 42% (8 MB of 19 MB)" then "Processing…" (`#tcProgressLabel`).
- **Clear failures, no surprise reloads.** 413 → "Too large for the server — one message can carry up to X"; 422 → the server's own message; 404 → "you may have been removed"; 403 → security-filter wording; 429/5xx/network → "press Enter to try again". Only a real sign-out reloads. A **419 refreshes the token** (`admin/team-messages/csrf`) and resends once — same `client_uuid`, files kept.
- **Per-chat drafts.** Text + staged files + the quoted message are kept per `chatKey()` in `window.__tcDrafts` (text also in sessionStorage `tc-drafts`, so it survives a reload) and restored on return; a send that fails after switching away becomes that chat's draft. Re-applying the same chat (cached paint → network) no longer clears the composer, and `sig()` ignores presence text so it rarely re-renders at all.
- **No page reloads that kill uploads.** Pin/unpin (`refreshPins`), add/remove/rename members (`refreshGroup`), profile photo (`applyMyAvatar`) all update in place. Leaving a group goes through `tcNav` and keeps `standalone=1`. A `beforeunload` guard plus `window.tcConfirmIfSending()` (used by Refresh and Sign out) warns while a send is in flight.
- **Forward carries the files.** `copyAttachments()` gives the forwarded message its OWN copies on the private disk (so purging the original can't break it); a file-only message whose file is gone is refused, text + missing file reports `missing_files`.
- **CSP fix (was breaking the UI):** `img-src` lacked `blob:`, so staged image thumbnails were broken and "Change photo" failed with "That image could not be opened". Now `img-src 'self' data: blob:`.
- **Desktop 0.1.2:** `dragDropEnabled: false` — required for HTML5 drag-and-drop on Windows (Tauri's own schema says so); without it the app swallowed dropped files. Installer URL is now versioned (`?v=0.1.2`) so Cloudflare can't serve a stale exe against a new signature.
- Fixed while in there: `res.messages.forEach(append)` passed the array index as append's `own` flag, so only one new message per poll advanced the cursor (batches re-fetched until they trickled through). `append` now takes `own === true` only.

### Unread / read / seen (Phase 3, 2026-09-17)
- **Priming, not replaying.** A client with no stored position polls `notifications?after=0&prime=1`; the server answers with **no messages** and `lastId = MAX(id)` (plus the unread counts). Before this it returned the OLDEST 30 and the client walked 7 days of history, firing notifications for days-old messages — every first launch and after every Refresh.
- **Catch-up bursts.** `notifications()` returns `more: true` while further batches follow; the client holds its toasts and chime and shows a single "N new messages" once caught up (`catchUpHeld` in `team-realtime`).
- **Read only when actually looking.** `isViewing()` = `!document.hidden && document.hasFocus()`. The thread poll sends `&read=0` when the window isn't in front and `thread()` then skips `markRead` (no flag at all = old client = previous behaviour). So no "Seen"/blue ticks for messages nobody looked at while the app sat in the tray or behind another window. Opening a chat still marks it read; a hover prefetch still doesn't.
- **Badges match.** The sidebar treats the open chat as read **only while `isViewing()`**, so messages that arrive while the VA is away keep their badge (and `bumpSidebar` still updates that row). Returning to the window polls with `read=1`, which clears both. Unread counts come from one server helper, `unreadForBadge()`.
- **Taskbar dot (desktop 0.1.3).** `set_unread` was rejected for the remote page: since Tauri 2.11 an app command called from a remote origin needs the ACL to resolve it. `build.rs` now declares the command (`AppManifest::new().commands(&["set_unread"])`) and `capabilities/remote.json` grants `allow-set-unread` — only to `*.apexgrowthsolution.com`. Verified in `gen/schemas/capabilities.json`. The dot shows the unread total across all chats and clears when caught up.

### Groups, mentions, pins & membership (Phase 5, 2026-09-17)
- **New-group field renamed** to `#tcNewGroupName` and read as `modal.querySelector(...)`. It used to share the id `tcGroupName` with the OPEN group's header name, so with a group open `getElementById` found the header span and "Create group" threw and did nothing. The button is also single-shot and clears the form.
- **Mentions survive an edit.** `update()` re-resolves them from the NEW text (`mentionsInBody()`: any participant written as "@Their Name", plus `@everyone` in a group), unioned with what the client sent; the client also pre-fills the edit's mentions from the bubble. Editing out a name drops that mention; editing one in adds it.
- **Pins never outlive their message.** `destroy()` clears `pinned_at`/`pinned_by`; both pinned queries add `whereNull('deleted_at')`; `pin()` allows UNpinning a deleted message (only pinning is refused). The open page refreshes the pinned bar when a pinned message is deleted (by me, or via the states poll).
- **Removed members are told.** `findConversation()` now answers 403 "You're no longer a member of this group." (or 404 "This chat is no longer available.") instead of Laravel's raw model-not-found. `okJson`/the send path call `chatGone()`: toast, notice in the thread, sidebar row removed, cached snapshot dropped (`window.tcDropCache`), composer disabled, polling stopped. **Note:** an in-org non-participant now gets 403, not 404 — four tests were updated to match.
- **A group can't be left without an admin.** `Conversation::handOverGroupAdminRoles()` runs from `Admin::deleting`, so deleting the only admin's account promotes the longest-standing remaining member (the same rule as leaving a group).
- Leave Group keeping `standalone=1` was fixed in Phase 2.

### Shared-PC privacy & local state (2026-09-18)
- **Everything stored locally is per signed-in user.** IndexedDB records are keyed `u<adminId>|<query>` and stamped `{uid, ts}`; a snapshot is only painted when `uid` matches the current user AND it is under **24h** old (`SNAP_TTL`), so nothing cached can outlive the 7-day retention. A sweep on every page load deletes foreign, expired and unstamped (pre-update) records. `localStorage` notification keys (`apex-team-last-msg`, `-notifs`, `-last-notified`, `-leader`) and the BroadcastChannel name all carry `:u<id>`; drafts use `tc-drafts:u<id>` and `tc-draft:u<id>`.
- **Sign-out wipes this browser's chat data.** The logout response sends `Clear-Site-Data: "storage"` (covers the desktop app's tray sign-out, HTTPS only), and the chat page's Sign out also clears localStorage, sessionStorage, Cache Storage and every IndexedDB database itself before navigating (works on http too, and asks first if a send is in flight).
- Per-chat drafts (the other half of that list) shipped in Phase 2 — see the Uploads section.
- **Deploy note:** the reconnect-draft key changed, so a draft stashed by the previous version at the exact moment of this deploy is not restored. One-off, affects only a send that hit a 419 mid-deploy.

### Routine UI actions (2026-09-18)
- **Downloads:** one at a time (a queue), so "Download selected" no longer holds every zip in memory at once. An expired session is detected (401/redirect/HTML) and reported instead of saving the login page under the zip's name. The card says "Downloaded — check your Downloads folder" only once the bytes are in.
- **Paste:** copying cells from Excel/Word puts a picture AND text on the clipboard — the text wins now. Screenshots (no text) and files copied from Explorer (real name) still attach.
- **Search jump:** `search` already returned `message_id`; result links carry `&m=<id>`, and `index()`/`open()` load the page AROUND that message (`messagePage()`, `focusId`) so the app scrolls to it and flashes it. Header-search results open in place.
- **Image viewer:** one `openLightbox(container, link)` builds the list from wherever it was opened (thread, Files, Photos, gallery). The Photos grid had NO click handler at all — clicking a photo used to navigate the app to the raw image.
- Pinned bar double-toggle was fixed in Phase 2; re-verified here.

### Shared-PC session lifetime (Codex work, reviewed and repaired 2026-09-18)
- `TeamChatSession` middleware + `public/js/team-chat-privacy.js` + `partials/team-chat-session|logout`: chat requests carry `X-Apex-Chat-Session: <uid>.<token>`; a tab belonging to a DIFFERENT account gets 401 and clears itself, and logout hands the login page a scoped cleanup instead of an origin-wide `Clear-Site-Data` (unrelated dashboard data survives).
- **Repairs made to that work:** (1) a renewed session used to 401 and bounce the VA to the login page — the token is now a per-sign-in scope kept IN the session, and a mismatch for the SAME person returns **409 + a fresh stamp** which the page adopts and retries, so a session rolling over never interrupts anything; (2) `Cache-Control: private, no-store` was being forced onto EVERY admin response (killing back/forward cache and the image caching below) — now only chat paths, and never over a deliberate `max-age`; (3) the chat page called `window.ApexChatPrivacy.*` directly and would break if that file failed to load — all calls go through `tcPriv()` with a safe fallback.
- Drafts moved from sessionStorage to localStorage (per user, cleared on sign-out) so a desktop update restart can't lose typed text.

### Server load & waste (2026-09-18)
- The **new-clients poller** no longer runs on the chat surface (it was redirected by `ChatHostGuard`, making the server render the whole chat page every 45s per VA, for an alert that could never fire there).
- `/favicon.ico` didn't exist, so the chat host rendered the whole chat page for it — the chat layout now points at `favicon.svg`.
- The **realtime poller only loads where the chat can be used** (ApexDesktop UA or chat host). In a plain dashboard browser every one of those polls was refused by `ChatAppOnly`, 15×/minute, all day.
- **Images and avatars are cacheable now:** chat images `private, max-age=31536000, immutable`; avatars `private, max-age=86400` — both set AFTER building the response, because a file response marks itself `public` and these are private files behind Cloudflare. Avatar URLs are versioned by the PHOTO's mtime, not `admins.updated_at`, which presence writes bumped every ~20s (so every avatar re-downloaded constantly). `TrackPresence` now uses a plain UPDATE that doesn't touch `updated_at`.
- **Idle windows cost far less:** notifier 4s while focused / 10s otherwise; the open thread's 3s poll drops to one in five when not being looked at; presence dots pause unfocused; the 5-thread idle warm runs once per page, not on every chat switch. Focus, click, key press and visibility changes still poll immediately.
- Added indexes `(conversation_id, updated_at)` and `(created_at)` on `team_messages` (migration `2026_09_24_000001`) for the states poll and the purge — both were full scans on the shared database.

### Sessions & desktop updates (2026-09-18)
- **Session lifetime:** chat requests keep their own session alive for `team.chat.session_days` (default 30, `TEAM_CHAT_SESSION_DAYS`), never shorter than `SESSION_LIFETIME`. A 2-hour idle no longer makes the next send fail with "Reconnecting…", whatever production's `.env` says. `/admin/system/upload-limits` now also reports the live session settings.
- **Desktop 0.1.4:** updates are checked at startup AND every 6 hours (an app that lives in the tray for weeks used to sit on an old version until a reboot). Only the first check of a fresh launch installs immediately; later ones **download** and wait — a tray item "Install update & restart" plus a notification, and it installs automatically on Quit. No more silent mid-session exit that threw away typed text.

### Attachment shown without its file (2026-09-18)
Reported from production: a message arrived as text only, with the sender seeing the file. The cause was the pre-Phase-1 half-saved send, but a client that had already drawn it kept it text-only until a reload. `thread()`'s states now carry `atts` (attachment count) and the page redraws just that message when its bubble has no files. Covered by `phase8.js` A1.

### Files/Photos paging, mention-by-edit, desktop 0.1.5 (2026-09-18)
- **Files & Photos stopped dead at 200.** `gallery()` returned only the newest 200 attachments with no hint that anything older existed. It now pages (`?before=<attachment id>`, `hasMore`, `oldest`) and both tabs show a **"Load older files"** button. Paging state (`galleryMore`/`galleryOldest`) resets on every chat switch, or the new chat would ask for files older than the previous chat's oldest and show none.
- **A mention added by EDITING an older message never notified.** The notification poll only walks forward by message id and an edit keeps its id, so the mention showed a silent badge. There is now a **second watermark, by time**: `message_mentions.created_at` (and `team_messages.mentions_all_at` for `@everyone`) record when each mention appeared, the client sends `?mAfter=<mLast>` alongside `?after=<id>`, and the server also returns mentions recorded since then, flagged `editedMention: true` so the client's "never re-fire an old id" rule doesn't swallow them. `syncMentions` now uses `sync()` instead of detach-then-attach, so a typo fix keeps the existing rows (and their timestamps) and does **not** re-ping people who were already mentioned. A client with no `mAfter` yet (or `after=0`) gets nothing — old mentions are never replayed.
- **Desktop 0.1.5:**
  - **Starts offline.** The window opens on the bundled waiting room (`src/index.html`) instead of the live URL, so no internet at launch means "No connection" + automatic retry, not WebView2's dead error page.
  - **One copy only** (`tauri-plugin-single-instance`, registered first). A second launch — Start menu, the installer's "run now", clicking a notification — focuses the running window instead of starting a rival tray + poller that notified twice.
  - **Summon hotkey** moved off `Ctrl+Shift+A` (taken by Photoshop/VS Code/Slack, so registration silently failed) to the first of `Ctrl+Alt+A`, `Ctrl+Alt+K`, `Ctrl+Shift+A` that Windows grants.
  - **Autostart is enabled once**, recorded by a marker file in the app config dir, instead of being forced on at every launch behind a VA who turned it off. The registered command carries `--autostarted`, and a launch with that flag **starts in the tray** rather than throwing a window over their work.

---

## 11. Bugs & fixes worth remembering

- `ChatAppOnly` broke ~55 tests (no ApexDesktop UA) → add the UA header in chat test `setUp`.
- CSRF mismatch after deploy → removed `sessions:flush`; added graceful 419 `reconnect(draft)`.
- Chat subdomain 500s → missing `storage/` tree (mkdir in `.cpanel.yml`) + missing `vendor/` (composer install failed on memory → `COMPOSER_MEMORY_LIMIT=-1`).
- `/download` 403 → physical dir shadowed the route → static `index.html`.
- Emoji picker closed instantly → outside-click handler needed a `[data-qr-more]` exclusion.
- Tauri `Image::from_bytes` needed the `image-png` Cargo feature.
- Installer showed the NSIS globe icon → set `nsis.installerIcon`.
- `--days=0` clamps to 7 (safety) → added `--all` for a real wipe.
- Groups "disappeared" → the `conversations` rows were hard-deleted (full-nuke SQL). Not a bug; recreate with New group. Auto-purge keeps conversations.
- Search results dropdown mispositioned/hidden → moved to `<body>` as a fixed popup (see §10).
- Desktop real-time froze in background → WebView2 no-throttle flags (0.1.1) + web focus-poll (see §7).
- **A deploy stuck on "in progress" for ever (2026-09-18).** `.cpanel.yml`'s `fail()` ended in `exit 1`. cPanel drives all of these tasks in ONE shell, so that exit killed cPanel's own deploy shell mid-script and it never wrote the finished state — the Git Version Control page just spun. **Never `exit` from a `.cpanel.yml` task**: log the failure and return. Also dropped `migrate --isolated` (its cache lock is one more thing that can fail during a deploy, for a two-host collision that doesn't happen — they are deployed one at a time) and the `php artisan about` boot check.

---

## 12. Current production state (as of 2026-09-18)

- **Web:** everything through the 2026-09-18 phases is committed on `main` and **needs a deploy on BOTH hosts** (dashboard `public_html` + the chat subdomain). One migration ships with it (`2026_09_25_000001_add_created_at_to_message_mentions`) — additive and nullable.
- **Desktop:** version **0.1.5** built, signed, and published to `public/download/` (`apex-team-chat-setup.exe` + `latest.json`). It goes live with the chat-host deploy; installed 0.1.4 apps pick it up on their next update check (startup, then every 6h) and install when the VA quits or chooses "Install update & restart". **No VA has to uninstall or reinstall anything.**
- Nothing here logs anyone out: sessions survive deploys (`.cpanel.yml` deliberately does not flush them), and chat sessions last `TEAM_CHAT_SESSION_DAYS` (30).
- Git: `main` on `somethinggreat-stack/apexdashboard`, all work committed + pushed.

---

## 13. Security invariants (never violate)

- **Never** place `id_rsa`/client credential files in the project or git.
- SSN-bearing zips stay on the **private** disk behind a guarded route.
- The Tauri **signing key** (`~/.tauri/apex-updater.key`) is secret — never commit it.
- Chat is **app-only** (ApexDesktop UA) — keep `ChatAppOnly` in place.
- **Web Push disabled** — OS notifications only.
- Attachments: **only whitelisted image types render inline**; everything else is force-downloaded octet-stream + `nosniff` from the private disk. Files are UUID-named on a non-web-executable disk.
- `admins.role` / `admins.parent_admin_id` are **not mass-assignable** — set only via seeder/UserController.

---

## 14. Rules for future changes

1. **Test at every aspect.** The user demands it. Run PHP tests (`--filter`), and for UI/real-time changes drive the app with the Playwright harness under the `ApexDesktop/1.0` UA. Don't claim a fix without verifying.
2. **Respect the two layers.** New per-thread behavior belongs in the light `applyOpen` swap; register timers/listeners/body-nodes via TCI/TCB/TCD/TCW so they clean up on a tcNav re-run. Keep tcNav working as the fallback.
3. **CSS** goes in `public/css/team-chat.css` (bump happens automatically via `?v=filemtime`). **JS** stays inline in `<script data-tc>` (do not externalize — it breaks tcNav's re-run).
4. **Migrations** run once on the shared DB via deploy. Additive/nullable columns; guard with `Schema::hasColumn`.
5. **Retention keeps conversations.** Never delete `conversations` rows in automated cleanup.
6. **Desktop config changes** (`apex-desktop`, not a git repo) require a rebuild + re-sign + republish `latest.json` + a chat-host deploy. Bump `tauri.conf.json` `version` for auto-update to fire.
7. **When "search / file / message" behavior changes,** keep body-search AND attachment-name search in sync (`TeamMessageController::search`).
8. **Don't build manual chat-clearing UX** — the 7-day auto-purge is the intended mechanism (user was explicit).
9. **Every async request in the chat script must check freshness on return** (`runAlive` / `viewAlive(gen)` / `chatAlive(key)` / nav ticket — see §6). Every message insert goes through `serializedSend` (see §10 Send integrity).
10. **Never `location.reload()` from a chat action** — an upload may be in flight. Update in place, or go through `window.tcConfirmIfSending()` first.
11. **Upload limits come from `uploadLimits()`**, never hard-coded in the page; keep the client and server checks in step.
12. **Durable browser suites live in `tests/Browser/TeamChat/`** — `node tests/Browser/TeamChat/run.cjs` gives each suite its own throwaway SQLite database and PHP process (needs `PHP_BINARY`/`PLAYWRIGHT_MODULE`/`CHROMIUM_PATH`). Suites are `phase1, 1b, 2, 3, 5, 6, 7, 8, 9`; phase9 also loads `seed-files.php`. Keep them in step with the session scratchpad copies. **Reset the scratchpad database before EVERY suite** (`reset.sh`) — running them back to back against a drifted database produces a wall of timeouts that look like regressions and are not.
13. **Never call an app command from the chat page without granting it in `capabilities/remote.json` AND declaring it in `build.rs`** — a remote origin is refused otherwise (that is what broke the taskbar dot).
14. **The desktop window must never point straight at a remote URL.** It opens on the bundled waiting room, which is the only thing standing between a VA with slow wifi and WebView2's dead error page. Keep a working plain `<a>` in that page's HTML so a scripting failure can never strand someone on a blank app.
15. **Anything that can change who a message notifies AFTER it was sent needs a time watermark, not an id one.** The notification poll is id-ordered and only moves forward; `mAfter`/`mLast` is that second watermark (see §10). Never make it replay old state when the client sends no watermark.
16. **Any list the chat renders from one request needs a page size AND a way to see past it.** The Files/Photos tabs silently hid everything past the newest 200 for months.

---

## 15. Open / watch items

- **0.1.5 smoke-tested by running the built binary on the dev machine (2026-09-18)** — all confirmed in real WebView2: the waiting room hands over to the live chat and the signed-in page renders; a second launch does not start a second process; autostart is rewritten once to `<exe> --autostarted` and the `autostart-configured` marker is written; a launch with `--autostarted` leaves the "Apex Team Chat" window `visible=False` (tray only). The registry entry and marker were restored afterwards. **Useful trick:** capture the window with `Graphics.CopyFromScreen` over the `GetWindowRect` of the process's `MainWindowHandle` and look at the PNG — that is how "does it actually reach the chat" was answered without guessing. Note `MainWindowTitle` reports the single-instance plugin's 14×14 `…-siw` helper window, so enumerate windows instead of trusting it.
- **Still field-check after this deploy** (needs a VA's real machine): drag-and-drop onto the window, the taskbar unread dot, `Ctrl+Alt+A` actually summoning the window (registration is best-effort across `SUMMON_KEYS`), a PC started with no internet, and `/admin/system/upload-limits` output.
- Confirm in the field that the **no-throttle flags** actually keep background polling + OS notifications alive.
- `apex-desktop` is **not under version control** — consider `git init` so config/version changes are tracked.
- The 2.65MB installer binary is committed under `public/download/` — acceptable but bloats history over time.
- Related memory notes: `team-chat-roadmap.md`, `chat-open-speed-request.md` in the project memory dir.
