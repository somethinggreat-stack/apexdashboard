# Apex Team Chat — Master Context (single source of truth)

> Read this first before touching Team Chat. It captures the architecture, deploy model,
> features, key code decisions, gotchas, versions, and rules. Keep it updated when you change things.
> Last major update: 2026-09-15.

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
- **`.cpanel.yml`** on deploy: copies `app bootstrap config database public resources routes tests`, `.htaccess`, `artisan`, composer/package files; `mkdir -p` the `storage/` tree (incl. `storage/app/private`, `storage/app/public`) + `bootstrap/cache`; `chmod -R 775`; `composer install --no-dev --optimize-autoloader` (with `COMPOSER_MEMORY_LIMIT=-1`); `config:clear`/`route:clear`/`view:clear`; **`php artisan migrate --force`**; `db:seed --class=TeamSeeder --force`; `storage:link`; `images:optimize`.
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
- `src-tauri/tauri.conf.json` — window url, `userAgent` (must contain `ApexDesktop/1.0`), `additionalBrowserArgs`, bundle targets `["nsis"]`, `createUpdaterArtifacts: true`, updater `endpoints`/`pubkey`, nsis `installerIcon`, `version`.
- `src-tauri/src/lib.rs` — tray (Open/Reload/Sign out/Quit), close-to-tray, `set_unread` command (taskbar overlay badge), global shortcut Ctrl+Shift+A, autostart, updater check on startup, opener plugin (external links → real browser).
- `src-tauri/Cargo.toml` — `image-png` feature needed for the badge image.
- Plugins: `tauri-plugin-notification`, `-autostart`, `-global-shortcut`, `-updater`, `-opener`.

---

## 4. Database — chat tables

- **`conversations`**: `type` (`dm`|`group`), `name`, `icon`, `data_owner_id`, `dm_key`, `last_message_id`, `last_message_at`, `created_by`.
  - `dm_key`: `self:<adminId>` for the "message yourself" notes thread; `dm:<minId>:<maxId>` for a DM.
- **`conversation_participants`**: `conversation_id`, `admin_id`, `role` (`member`|`admin`), `muted`, `favorite`, `last_read_message_id`, `typing_at`, `notify_level` (`all`|`mentions`|`none`), `joined_at`.
- **`team_messages`**: `conversation_id`, `type` (`text`|`system`), `sender_id`, `reply_to_id`, `body`, `forwarded`, `pinned_at`, `edited_at`, `deleted_at`, `deleted_by`, `reactions` (JSON map uid→emoji), `mentions_all`, mentioned admins via pivot.
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
- **Build:** `cd Z:\Projects\apex-desktop; npm run tauri build` (~2–3 min incremental). Outputs:
  - Installer: `src-tauri\target\release\bundle\nsis\Apex Team Chat_<version>_x64-setup.exe`
  - Signature: `...exe.sig`
- **App version** comes from `tauri.conf.json` `version` (currently **0.1.1**). `Cargo.toml`/npm package still say 0.1.0 (cosmetic; the bundle/installer/updater use tauri.conf.json).
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

---

## 12. Current production state (as of 2026-09-15)

- **Web:** all of the above **deployed** to the chat host (user confirmed). One chat-host deploy covers every web feature.
- **Desktop:** version **0.1.1** built, signed, and published. `https://chat.apexgrowthsolution.com/download/latest.json` **verified live at 0.1.1** pointing at `apex-team-chat-setup.exe`. Installed 0.1.0 apps auto-update on next launch; new VAs install 0.1.1 from the download page.
- Git: `main` on `somethinggreat-stack/apexdashboard`, all work committed + pushed. Commit attribution line used: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

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

---

## 15. Open / watch items

- Confirm in the field that the **0.1.1 no-throttle flags** actually keep background polling + OS notifications alive (couldn't fully test the built app here).
- `apex-desktop` is **not under version control** — consider `git init` so config/version changes are tracked.
- `Cargo.toml`/npm version (0.1.0) lags `tauri.conf.json` (0.1.1) — cosmetic; align on the next bump.
- The 2.65MB installer binary is committed under `public/download/` — acceptable but bloats history over time.
- Related memory notes: `team-chat-roadmap.md`, `chat-open-speed-request.md` in the project memory dir.
