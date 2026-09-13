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
