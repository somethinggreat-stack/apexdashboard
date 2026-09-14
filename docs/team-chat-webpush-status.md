# Team Chat — Status & Handoff (as of last session)

On `main`. All backend tests green.

## ✅ Web Push is LIVE
On the server, `/admin/push/setup` reported **"Web Push is active"** — the dependency-free
crypto generated keys and they're in `.env`. The setup page has now been **removed** (route +
controller methods deleted) since it's no longer needed. Remaining next step: each teammate
clicks **Allow** once, then confirm the live closed-browser test (message a teammate who has
allowed + closed their browser → OS toast in ~1–2s).

## What's done

### 1. MS-Teams-style UI (standalone chat, white theme)
- Teams-style **emoji picker** (search, Recent, category tabs, pops up from the composer).
- **Pencil** to rename a group inline in the header.
- **Chat / Files / Photos** header tabs. Files = sortable table (checkbox, type icon,
  Name / Shared on / Sent by, bulk download, Upload). Photos = date-grouped grid → lightbox.
- Unread badge is **green** and shows only on the chat that actually got a message.

### 2. Real-time (polling — there is NO websocket infra; cPanel can't run one)
- One global listener (`partials/team-realtime.blade.php`, loaded on every admin page).
- Single-poller **leader election** across tabs + `BroadcastChannel` sync (no dupes).
- Authoritative unread from the server (`notifications` returns `perConv` + total).
- Fixed earlier bugs: unread-on-all-rows, auto-read without opening (SPA listener leak,
  now tracked via `TCW`), repeated chimes (persistent `apex-team-last-notified`),
  suppressed notifications after navigating (active state keyed per tab).
- In-app notification center bell on non-chat pages; "Turn on notifications" pill on the chat page.

### 3. Web Push (background / closed-browser notifications) — **DEPENDENCY-FREE**
- `app/Services/WebPush/WebPushCrypto.php`: pure-PHP VAPID (RFC 8292) + aes128gcm
  (RFC 8291) using only **ext-openssl** + `hash_hkdf`. **No composer package, no extra
  PHP extensions.** (We removed `minishlink/web-push` because its `web-token/jwt-library`
  dependency fails to install on shared cPanel → that was the "Class ... VAPID not found".)
- Verified: encrypt→decrypt round-trips; my code decrypts the old library's real output
  (proves RFC-compliance); a full send to a mock endpoint decrypts back to the exact payload.
- `WebPushSender` sends via Laravel Http (curl), prunes dead (404/410) subs, never throws.
- `push_subscriptions` table + `PushSubscription` model; `POST admin/push/{subscribe,unsubscribe}`.
- Service worker `public/sw.js` is **push-only** (the installable PWA was removed): shows the
  OS toast (unless a focused tab is on that exact conversation) and postMessages open tabs to
  poll instantly.
- Frontend subscribes once notifications are allowed; suppresses in-page notifications when a
  push subscription exists (SW handles the toast → no duplicates).

### 4. Setup without a terminal (cPanel has no Terminal)
- Auto-provision on deploy: `.cpanel.yml` runs `php artisan webpush:install` before
  `config:cache` (writes VAPID keys to `.env` if missing).
- Backup page **`/admin/push/setup`** (super-admin only, all GET, no CSRF): Generate → shows
  the 3 keys to copy; if it can't write `.env`, paste via File Manager then click "activate"
  (`?refresh=1` runs `config:clear` so they take effect without a redeploy).

## What the USER must do (next session / after deploy)
1. **Deploy** `main` (Deploy HEAD). This ships the dependency-free crypto — the composer
   install should now succeed (no heavy deps), so "class not found" is gone.
2. Open **`apexgrowthsolution.com/admin/push/setup`** as super admin → click **Generate**.
   - If it shows the keys but says "couldn't write": copy them, paste into `.env` via
     **cPanel File Manager** (Show Hidden Files), Save, then click **"activate"**.
   - Page should then say **"✅ Web Push is active."**
3. Each teammate clicks **Allow** once (or the 🔔 pill).
4. Acceptance test: one VA allows + closes the browser; another account messages them →
   OS notification should appear within ~1–2s.

## ✅ FIXED — sidebar not live-updating with no conversation open
Was: a new message updated the notification bell but NOT the Team Chat sidebar when no
conversation was selected. Cause: the sidebar's live-update wiring lived inside the thread
IIFE, which bails at `if (!box) return` when `#tcMessages` isn't rendered (no chat open).
Fix: extracted the sidebar wiring (`announceActive`, `bumpSidebar`, `applyRowUnread`, the
`apex:team-unread` / `apex:team-message` listeners) into its own ALWAYS-ON block that runs on
every chat-page load; when a message lands in the currently-open chat it dispatches
`apex:thread-poll`, which the thread IIFE listens for to fetch the full message. Verified with
two accounts: receiver on Team Chat with nothing selected → sidebar preview + unread + reorder
update live; open-conversation append + no-notify-for-open-chat still pass.

## ✅ Team Chat is an installable app (chat-scoped PWA)
The standalone Team Chat can now be **installed as its own app** (dedicated window, taskbar/
Start icon, more reliable closed-app notifications on Windows). It is **scoped to the chat
only** so the dashboard never shows an install prompt:
- `public/team-chat.webmanifest` (scope `/admin/team-messages`, start_url `?standalone=1`,
  standalone display, existing `/Images/pwa` icons).
- Linked ONLY in `layouts/chat.blade.php` (+ apple-mobile-web-app metas). The dashboard
  layouts have no manifest.
- A tidy **"Install app"** button appears bottom-left in Team Chat **only when installable**
  (captures `beforeinstallprompt`, hides after install) — no auto browser nag.
- `public/sw.js` gained a **presence-only** `fetch` handler (required by some browsers for
  installability). It caches NOTHING and never calls `respondWith` — no offline app, no stored
  client data. Push + notificationclick unchanged.
Verified: manifest serves 200 with name "Apex Team Chat" / scope `/admin/team-messages`; the
chat page links it and shows the install button; the dashboard does NOT link it. (The install
prompt/click itself needs real Chrome — headless can't fire `beforeinstallprompt`.)

## Still to verify / open items
- **End-to-end browser delivery** (push service → SW → OS toast) is the ONE leg not
  automatable locally (headless can't create push subscriptions). Needs the live test above.
- **openssl EC keygen on the server**: pure-PHP crypto calls `openssl_pkey_new(EC)`. Works on
  standard cPanel/Linux; if the server's `openssl.cnf` is odd, the setup page shows a clear
  error. (Local Windows needs `OPENSSL_CONF`; the server does not.)
- If, after activating, notifications still don't arrive: check `storage/logs/laravel.log`
  for `WebPush send failed: ...` lines (the sender logs failures there).

## Nice-to-have / not started
- Move push send to a queue if the team grows a lot (currently sent inline after the response
  via `app()->terminating()` — fine for a small team).
- The `/admin/push/setup` page has been removed (keys are set). If keys ever need rotating,
  run `php artisan webpush:install --force` on deploy, or re-add the setup route temporarily.

## Local test harness (dev notes)
- Throwaway sqlite + `php artisan serve` with `DB_CONNECTION=sqlite DB_DATABASE=<path>`.
- Windows openssl EC keygen needs `OPENSSL_CONF=<a minimal .cnf>`; the Linux server does not.
- Crypto verified against the known-good library by decrypting its wire output.
- Playwright (via `playwright-core`) drives the local server for UI checks (login as
  `super@test.com` / `password123`, `?c=<id>&standalone=1`).

## Full end-to-end audit (four fix phases — all shipped)
A complete audit of Team Chat (backend + frontend + a live Playwright battery) was run, then
fixed in four phases. **235 tests green; 3 skipped** (Windows-only openssl EC keygen — passes on
the Linux server).

- **Phase 1 — security & data integrity:** reject markup in reaction emoji (422); can't react to
  a deleted/system message (404); removed group members can't edit old messages; attachment
  serving hardened (`X-Content-Type-Options: nosniff`, non-images forced to
  `application/octet-stream` download, images served with an explicit image type); attachment
  filename capped at 200 chars.
- **Phase 2 — real-time correctness:** notification rows carry `sender_id`; sidebar adopts
  peer-only rows on first message (`apex:conv-adopted`); thread poll guards on auth/redirect
  with a one-time "session expired" toast; SW `focusedHere` matches the exact conversation.
- **Phase 3 — robustness & concurrency:** reaction read-modify-write wrapped in a row-locked
  transaction; unread counts exclude "delete-for-me" + tombstoned messages; `touchConversation`
  is a guarded conditional update; thread state sync is "changed-since" (`statesSince`/token);
  DM creation is race-safe via a canonical `dm_key` + transaction/retry.
- **Phase 4 — scale & polish:** **message pagination** (newest 50 on open, "Load earlier"
  pill + auto-fetch near the top, `older` endpoint, prepend with day separators, boundary-dedupe,
  `offsetTop`-anchored scroll preservation — drift 0, live-verified); poll endpoints throttled
  (`throttle:240,1`); filter-aware sidebar live updates (`apex:sidebar-changed`); lazy-image
  re-scroll; catch-up desktop-toast cap (`TOAST_CAP=4` + summary); dead-code cleanup
  (removed `TeamMessage::recipient()/scopeBetween()`, `Conversation::hasMember()`, unused EMOJI
  const). Pagination: `TeamMessageController::older()` + `GET admin/team-messages/older`;
  `index()` loads only `PAGE=50` newest + `hasMoreOlder`; `present()` returns `day`/`dayKey`.
