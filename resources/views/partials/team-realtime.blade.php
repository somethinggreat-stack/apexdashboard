{{-- Team Chat global realtime layer.
     Runs on EVERY authenticated admin page (super/va). One elected "leader" tab polls
     the network; all tabs stay in sync via BroadcastChannel. Delivers: live nav badge,
     an in-app notification center, and OS desktop notifications — with no page refresh
     and without opening Team Chat. Closed-browser push is out of scope for polling and
     needs Web Push (see the notification center's hint + docs). --}}
<style>
  .apex-nc-bell { position:fixed; right:20px; bottom:20px; z-index:5900; width:52px; height:52px; border:0; border-radius:50%; cursor:pointer;
    background:linear-gradient(135deg,#6366f1,#7c3aed); color:#fff; box-shadow:0 12px 30px -8px rgba(99,102,241,.6); display:flex; align-items:center; justify-content:center; transition:transform .12s; }
  .apex-nc-bell:hover { transform:translateY(-2px); }
  .apex-nc-bell svg { width:23px; height:23px; }
  .apex-nc-bell.pulse { animation:apexBellPulse 1.6s ease-in-out infinite; }
  @keyframes apexBellPulse { 0%,100%{ box-shadow:0 12px 30px -8px rgba(99,102,241,.6);} 50%{ box-shadow:0 12px 34px -4px rgba(124,58,237,.85);} }
  .apex-nc-count { position:absolute; top:-3px; right:-3px; min-width:20px; height:20px; padding:0 5px; border-radius:11px; background:#ef4444; color:#fff;
    font:700 11px system-ui,sans-serif; display:none; align-items:center; justify-content:center; box-shadow:0 0 0 2px #fff; }
  .apex-nc-count.show { display:flex; }
  .apex-nc-panel { position:fixed; right:20px; bottom:82px; z-index:5901; width:360px; max-width:calc(100vw - 40px); max-height:70vh; display:none; flex-direction:column;
    background:#fff; border:1px solid #e6ebf2; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(15,23,42,.28); }
  .apex-nc-panel.open { display:flex; }
  .apex-nc-head { display:flex; align-items:center; justify-content:space-between; padding:13px 16px; border-bottom:1px solid #eef2f7; }
  .apex-nc-head b { font:700 15px system-ui,sans-serif; color:#1e293b; }
  .apex-nc-clear { border:0; background:transparent; color:#6366f1; font:600 12.5px system-ui,sans-serif; cursor:pointer; }
  .apex-nc-enable { display:block; width:calc(100% - 24px); margin:10px 12px 2px; border:0; cursor:pointer; padding:10px; border-radius:10px;
    background:linear-gradient(135deg,#6366f1,#7c3aed); color:#fff; font:600 13px system-ui,sans-serif; }
  .apex-nc-blocked { margin:10px 14px 2px; padding:9px 11px; border-radius:9px; background:#fef2f2; color:#b91c1c; font:500 12px system-ui,sans-serif; line-height:1.4; }
  .apex-nc-list { flex:1; overflow-y:auto; padding:6px; }
  .apex-nc-empty { text-align:center; color:#94a3b8; font-size:13px; padding:34px 10px; }
  .apex-nc-item { display:flex; gap:10px; align-items:flex-start; padding:10px 11px; border-radius:11px; cursor:pointer; text-decoration:none; }
  .apex-nc-item:hover { background:#f5f7fb; }
  .apex-nc-item.unseen { background:rgba(99,102,241,.06); }
  .apex-nc-ic { flex:none; width:34px; height:34px; border-radius:9px; background:linear-gradient(135deg,#6366f1,#7c3aed); color:#fff; display:flex; align-items:center; justify-content:center; font:700 13px system-ui,sans-serif; }
  .apex-nc-tx { min-width:0; flex:1; }
  .apex-nc-tx b { display:block; font:700 13.5px system-ui,sans-serif; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .apex-nc-tx span { display:block; font:500 12.5px system-ui,sans-serif; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .apex-nc-tx i { font:500 11px system-ui,sans-serif; color:#94a3b8; font-style:normal; }
  :root[data-theme="dark"] .apex-nc-panel { background:#0f1629; border-color:#233150; }
  :root[data-theme="dark"] .apex-nc-head { border-bottom-color:#233150; }
  :root[data-theme="dark"] .apex-nc-head b, :root[data-theme="dark"] .apex-nc-tx b { color:#e2e8f0; }
  :root[data-theme="dark"] .apex-nc-item:hover { background:#182444; }
</style>
<script>
(function () {
    'use strict';
    if (!window.fetch) return;
    if (window.__apexRealtimeBooted) return;   // never run two pollers in one page context
    window.__apexRealtimeBooted = true;

    var POLL_URL = @json(route('admin.team-messages.notifications'));
    var OPEN_URL = @json(route('admin.team-messages.index'));
    var SUB_URL  = @json(route('admin.push.subscribe'));
    var VAPID    = @json(config('webpush.public_key'));   // null if Web Push isn't configured
    var CSRF     = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var onChatPage = !!document.querySelector('.tc-wrap');   // the Team Chat page renders its own surface

    // Native desktop app (Tauri): we fire OS-native, Apex-branded notifications instead of
    // browser ones, and skip Web Push entirely (the app polls while it lives in the tray).
    var IS_TAURI = !!window.__TAURI__;
    var lastNotifiedConv = null;   // the conversation of the most recent OS notification
    if (IS_TAURI) {
        try {
            var _n = window.__TAURI__.notification;
            if (_n && _n.isPermissionGranted) {
                _n.isPermissionGranted().then(function (g) { if (!g && _n.requestPermission) return _n.requestPermission(); }).catch(function () {});
            }
            // Click-to-jump: clicking the toast brings the app forward and opens that chat.
            if (_n && _n.onAction) {
                _n.onAction(function () {
                    if (!lastNotifiedConv) return;
                    try { var w = window.__TAURI__.window.getCurrentWindow(); w.show(); w.unminimize(); w.setFocus(); } catch (e) {}
                    location.href = OPEN_URL + '?c=' + lastNotifiedConv + '&standalone=1';
                });
            }
        } catch (e) {}
    }
    function nativeNotify(title, body){
        if (!IS_TAURI) return false;
        var T = window.__TAURI__, opts = { title: title || 'Apex Team Chat', body: body };
        try {
            if (T.notification && T.notification.sendNotification) { T.notification.sendNotification(opts); }
            else if (T.core && T.core.invoke) { T.core.invoke('plugin:notification|notify', { options: opts }); }
        } catch (e) {}
        return true;   // in the app we never fall back to a browser toast
    }
    // A small unread dot on the app's taskbar icon (cleared when caught up).
    function nativeBadge(n){
        if (!IS_TAURI) return;
        var T = window.__TAURI__;
        try { if (T.core && T.core.invoke) T.core.invoke('set_unread', { count: (n > 0 ? n : 0) }); } catch (e) {}
    }
    // In the app, a link to another site opens in the real browser instead of navigating
    // away from the chat window.
    if (IS_TAURI) {
        document.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;
            var href = a.getAttribute('href') || '';
            if (!/^https?:\/\//i.test(href)) return;                 // in-app / relative links stay in-app
            try { if (new URL(href, location.href).host === location.host) return; } catch (err) { return; }
            e.preventDefault();
            var T = window.__TAURI__;
            try {
                if (T.opener && T.opener.openUrl) T.opener.openUrl(href);
                else if (T.core && T.core.invoke) T.core.invoke('plugin:opener|open_url', { url: href });
            } catch (er) {}
        }, true);
    }

    // ---------- shared state ----------
    var LKEY = 'apex-team-last-msg', NKEY = 'apex-team-notifs', NLKEY = 'apex-team-last-notified';
    function ls(k, v){ try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
    var lastId = parseInt(ls(LKEY) || '0', 10) || 0;
    var primed = lastId > 0;               // if we have a baseline, deliver new msgs (no backlog spam)
    var seen = {};                          // id -> 1, per-tab UI dedupe
    function lastNotified(){ return parseInt(ls(NLKEY) || '0', 10) || 0; }   // cross-tab: chime/notify each msg only ONCE
    // Which conversation each tab is actively viewing (open + focused). Keyed by TAB so a tab
    // that SPA-navigates simply REPLACES its own entry (no stale "active" convs pile up and
    // wrongly silence a chat you left). A conv is "active" if ANY tab is looking at it.
    var activeByTab = {};
    function isActive(conv){ conv = String(conv); for (var t in activeByTab){ if (activeByTab[t] === conv) return true; } return false; }

    // ---------- single-poller leader election (one network poller across all tabs) ----------
    // A VISIBLE tab is preferred as leader so a backgrounded tab (throttled to 15s) never starves
    // the tab you're actually looking at (M4). A visible tab preempts a hidden leader.
    var TAB = String(Date.now()) + '-' + Math.random().toString(36).slice(2, 8);
    var LEAD = 'apex-team-leader';
    function leader(){ try { return JSON.parse(ls(LEAD) || 'null'); } catch (e) { return null; } }
    function leaderFresh(){ var l = leader(); return l && (Date.now() - l.ts < 8000); }
    function amLeader(){ var l = leader(); return l && l.id === TAB; }
    function claim(){
        var l = leader(), fresh = l && (Date.now() - l.ts < 8000), vis = !document.hidden;
        // Claim if: no fresh leader, I already am, or I'm visible and the current leader is hidden.
        if (!fresh || (l && l.id === TAB) || (vis && l && !l.vis)) {
            ls(LEAD, JSON.stringify({ id: TAB, ts: Date.now(), vis: vis }));
        }
    }
    claim(); setInterval(claim, 3000);
    window.addEventListener('beforeunload', function () { if (amLeader()) { try { localStorage.removeItem(LEAD); } catch (e) {} } });

    // ---------- cross-tab bus ----------
    var bc = ('BroadcastChannel' in window) ? new BroadcastChannel('apex-team') : null;
    if (bc) bc.onmessage = function (e) {
        var d = e.data || {};
        if (d.kind === 'active'){ if (d.conv) activeByTab[d.tab] = String(d.conv); else delete activeByTab[d.tab]; return; }
        if (d.kind === 'seen'){ hydrate(); renderPanel(); return; }
        if (d.kind === 'unread'){ applyUnread(d.unread, d.perConv); return; }   // authoritative counts from the leader
        if (d.kind === 'data'){ ingest(d.data, false); }   // from the leader — update in-app UI, don't re-notify/re-broadcast
    };
    function post(o){ if (bc) bc.postMessage(o); }

    // ---------- desktop notification permission ----------
    function permission(){ return ('Notification' in window) ? Notification.permission : 'denied'; }
    function askPermission(){ if (permission() === 'default') { try { Notification.requestPermission().then(function () { renderPanel(); refreshEnablePill(); ensurePush(); }); } catch (e) {} } }

    // ---------- Web Push: subscribe this browser so notifications arrive when the tab is
    // backgrounded/throttled or the browser is closed (needs VAPID keys on the server). ----------
    function urlB64ToUint8Array(b64){
        var pad = '='.repeat((4 - b64.length % 4) % 4);
        var base = (b64 + pad).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(base), arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }
    var pushTried = false, pushActive = false;   // pushActive => the SW delivers OS toasts; don't also fire in-page ones
    function ensurePush(){
        if (pushTried) return;
        if (IS_TAURI) return;   // the native app uses OS notifications, not Web Push
        if (!VAPID || permission() !== 'granted') return;
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        pushTried = true;
        navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription().then(function (sub) {
                if (sub) return sub;
                return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToUint8Array(VAPID) });
            });
        }).then(function (sub) {
            if (!sub) return;
            var j = sub.toJSON() || {};
            var enc = (window.PushManager && PushManager.supportedContentEncodings) ? PushManager.supportedContentEncodings[0] : 'aesgcm';
            // Only treat push as active once the server has STORED the endpoint — otherwise the SW
            // can't deliver and suppressing the in-page fallback would mean zero notifications (M6).
            fetch(SUB_URL, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ endpoint: sub.endpoint, keys: j.keys || {}, contentEncoding: enc })
            }).then(function (r) { if (r && r.ok) pushActive = true; else pushTried = false; })
              .catch(function () { pushTried = false; });   // keep the in-page fallback on failure
        }).catch(function () { pushTried = false; });
    }

    // Any tab where a push subscription already exists suppresses its own in-page notifications
    // (the SW shows the OS toast). getSubscription() returns the browser-wide sub in EVERY tab,
    // so this keeps a non-subscribing leader tab from double-notifying (M7).
    function detectExistingPush(){
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        navigator.serviceWorker.ready
            .then(function (reg) { return reg.pushManager.getSubscription(); })
            .then(function (sub) { if (sub) pushActive = true; })
            .catch(function () {});
    }

    var chimeAudio = null;
    function chime(){
        try {
            if (!chimeAudio) { chimeAudio = new Audio(@json(asset('sounds/notify.mp3'))); chimeAudio.volume = 0.6; }
            chimeAudio.currentTime = 0;
            var p = chimeAudio.play(); if (p && p.catch) p.catch(function () {});
        } catch (e) {}
    }
    function desktop(m){
        var title = m.title || 'Apex Team Chat';
        var body  = (m.body != null && m.body !== '') ? m.body : ((m.mention ? '@ ' : '') + m.sender + ': ' + m.snippet);
        if (IS_TAURI && m.conversation_id && m.conversation_id !== 'summary') lastNotifiedConv = m.conversation_id;
        if (nativeNotify(title, body)) return;   // native app → OS toast, Apex-branded, no browser
        if (permission() !== 'granted') return;
        try {
            var n = new Notification(title, {
                body: body,
                icon: '/Images/pwa/icon-192.png', badge: '/Images/pwa/icon-192.png',
                tag: 'apex-team-' + m.conversation_id, renotify: true
            });
            n.onclick = function () { window.focus(); location.href = OPEN_URL + '?c=' + m.conversation_id + '&standalone=1'; n.close(); };
        } catch (e) {}
    }

    // ---------- notification center ----------
    var notifs = [];
    function hydrate(){ try { notifs = JSON.parse(ls(NKEY) || '[]') || []; } catch (e) { notifs = []; } }
    hydrate();
    function saveNotifs(){ ls(NKEY, JSON.stringify(notifs.slice(0, 40))); }

    var bell, badge, panel, list;
    function timeAgo(ts){
        var s = Math.floor((Date.now() - ts) / 1000);
        if (s < 45) return 'Just now'; if (s < 3600) return Math.floor(s / 60) + 'm ago';
        if (s < 86400) return Math.floor(s / 3600) + 'h ago'; return Math.floor(s / 86400) + 'd ago';
    }
    function initials(s){ var p = String(s || '?').trim().split(/\s+/); return ((p[0]||'?')[0] + (p[1] ? p[1][0] : '')).toUpperCase(); }
    function buildUI(){
        if (onChatPage) return;   // the chat page's own sidebar is the notification surface
        bell = document.createElement('button'); bell.type = 'button'; bell.className = 'apex-nc-bell'; bell.setAttribute('aria-label', 'Chat notifications');
        bell.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span class="apex-nc-count"></span>';
        badge = bell.querySelector('.apex-nc-count');
        panel = document.createElement('div'); panel.className = 'apex-nc-panel';
        panel.innerHTML = '<div class="apex-nc-head"><b>Notifications</b><button type="button" class="apex-nc-clear">Mark all read</button></div><div class="apex-nc-list"></div>';
        list = panel.querySelector('.apex-nc-list');
        document.body.appendChild(bell); document.body.appendChild(panel);
        bell.addEventListener('click', function (e) { e.stopPropagation(); panel.classList.toggle('open'); bell.classList.remove('pulse'); if (panel.classList.contains('open')) renderPanel(); });
        panel.querySelector('.apex-nc-clear').addEventListener('click', markAllRead);
        document.addEventListener('click', function (e) { if (panel.classList.contains('open') && !panel.contains(e.target) && e.target !== bell) panel.classList.remove('open'); });
        if (permission() === 'default') bell.classList.add('pulse');
    }
    function renderPanel(){
        if (!panel) return;
        var head = '';
        if (permission() === 'default') head = '<button type="button" class="apex-nc-enable">🔔 Enable desktop notifications</button>';
        else if (permission() === 'denied') head = '<div class="apex-nc-blocked">Desktop notifications are blocked. Enable them for this site in your browser’s address-bar site settings, then reload.</div>';
        var body = notifs.length ? notifs.map(function (n) {
            return '<a class="apex-nc-item ' + (n.unseen ? 'unseen' : '') + '" href="' + OPEN_URL + '?c=' + n.conversation_id + '&standalone=1">'
                + '<span class="apex-nc-ic">' + esc(initials(n.sender)) + '</span>'
                + '<span class="apex-nc-tx"><b>' + esc(n.title || n.sender) + '</b><span>' + (n.mention ? '@ ' : '') + esc(n.sender) + ': ' + esc(n.snippet) + '</span><i>' + timeAgo(n.ts) + '</i></span></a>';
        }).join('') : '<div class="apex-nc-empty">No new messages.</div>';
        list.innerHTML = head + body;
        var en = list.querySelector('.apex-nc-enable'); if (en) en.addEventListener('click', askPermission);
    }
    function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' })[c]; }); }
    function addNotif(m){
        notifs = notifs.filter(function (n) { return n.id !== m.id; });
        notifs.unshift({ id: m.id, conversation_id: m.conversation_id, title: m.title, sender: m.sender, snippet: m.snippet, mention: m.mention, ts: Date.now(), unseen: true });
        notifs = notifs.slice(0, 40); saveNotifs();
        if (bell){ bell.classList.add('pulse'); renderPanel(); }
    }
    function markAllRead(){
        notifs.forEach(function (n) { n.unseen = false; }); saveNotifs(); renderPanel();
        if (bell) bell.classList.remove('pulse');
    }

    // ---------- nav badge (the ONE Team Chat nav link) + floating bell count ----------
    // IMPORTANT: sidebar conversation rows are ALSO <a href=...team-messages...>. We must
    // NEVER stamp the global unread total onto them — each row owns its own per-chat badge.
    function setBadge(n){
        if (typeof n !== 'number') return;
        if (IS_TAURI) nativeBadge(n);   // taskbar unread dot in the desktop app
        document.querySelectorAll('a[href*="team-messages"]').forEach(function (a) {
            if (a.classList.contains('tc-contact') || a.closest('.tc-list') || a.closest('.tc-wrap')) return;   // skip Team Chat sidebar rows
            var b = a.querySelector('.pro-count');
            if (n > 0){ if (!b){ b = document.createElement('span'); b.className = 'pro-count'; a.appendChild(b); } b.textContent = n > 99 ? '99+' : n; b.style.display = ''; }
            else if (b){ b.remove(); }
        });
        if (badge){ if (n > 0){ badge.textContent = n > 99 ? '99+' : n; badge.classList.add('show'); } else badge.classList.remove('show'); }
    }
    // Push the authoritative unread total to badges and hand the per-conversation map to
    // an open Team Chat so each sidebar row shows exactly its own count (never all rows).
    function applyUnread(total, perConv){
        if (typeof total === 'number') setBadge(total);
        if (perConv) window.dispatchEvent(new CustomEvent('apex:team-unread', { detail: perConv }));
    }

    // ---------- ingest a payload (from network on the leader, or from a peer tab) ----------
    function ingest(data, fromNetwork){
        if (!data) return;
        applyUnread(data.unread, data.perConv);
        var msgs = data.messages || [];
        if (!msgs.length) return;
        var floor = lastNotified();   // messages at/below this were already chimed/notified elsewhere
        var maxId = floor, didNotify = false, shown = 0, TOAST_CAP = 4;
        msgs.forEach(function (m) {
            // UI update (sidebar/thread) happens once per TAB — so every tab reflects the message.
            if (!seen[m.id]){ seen[m.id] = 1; window.dispatchEvent(new CustomEvent('apex:team-message', { detail: m })); }
            if (m.id > maxId) maxId = m.id;
            // Chime / desktop / notification-center happen once GLOBALLY — never re-fire an old id.
            if (m.id <= floor) return;
            var muted = isActive(m.conversation_id);   // conversation open+focused somewhere → stay quiet
            addNotif(m);
            if (!muted){
                didNotify = true;
                // Cap the OS-toast burst after a long absence: show the first few, then a summary
                // (the notification center still has them all). Web Push suppresses in-page toasts.
                if (amLeader() && !pushActive){
                    if (shown < TOAST_CAP){ desktop(m); shown++; }
                    else shown++;
                }
            }
        });
        if (amLeader() && !pushActive && shown > TOAST_CAP){
            desktop({ title: 'Apex Team Chat', sender: '', mention: false,
                snippet: '', conversation_id: 'summary',
                body: shown + ' new messages' });
        }
        if (maxId > floor) ls(NLKEY, String(maxId));   // advance the global notify watermark
        if (didNotify && amLeader()) chime();
    }

    // ---------- the poll loop (leader hits the network; others ride broadcasts) ----------
    var timer = null;
    function schedule(){ clearTimeout(timer); timer = setTimeout(loop, document.hidden ? 15000 : 4000); }
    function loop(){
        claim();
        if (!amLeader()){ schedule(); return; }   // a peer is the poller — we update via BroadcastChannel
        fetch(POLL_URL + '?after=' + lastId, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data){ schedule(); return; }
                // Authoritative unread every poll — so counts also DROP when read elsewhere.
                applyUnread(data.unread, data.perConv);
                post({ kind: 'unread', unread: data.unread, perConv: data.perConv });
                if (!primed){ if (data.lastId){ lastId = data.lastId; ls(LKEY, String(lastId)); } primed = true; schedule(); return; }
                if (data.lastId > lastId){ lastId = data.lastId; ls(LKEY, String(lastId)); }
                if ((data.messages || []).length){ ingest(data, true); post({ kind: 'data', data: data }); }
                schedule();
            })
            .catch(function () { schedule(); });
    }

    // A push arriving at the service worker nudges every tab to poll NOW → instant in-app sync
    // (no waiting for the timer), on top of the OS notification the SW shows.
    if ('serviceWorker' in navigator && navigator.serviceWorker) {
        navigator.serviceWorker.addEventListener('message', function (e) {
            if (e.data && e.data.type === 'apex-push') { claim(); clearTimeout(timer); loop(); }
        });
    }

    // A clear one-tap enabler shown on EVERY page (incl. the chat page, which has no bell)
    // whenever notifications are still off — so a teammate can turn them on without hunting.
    var enablePill = null;
    function refreshEnablePill(){
        // On the chat page there's no floating bell, so surface a clear one-tap enabler there.
        var need = onChatPage && (permission() === 'default');
        if (need && !enablePill){
            enablePill = document.createElement('button');
            enablePill.type = 'button';
            enablePill.textContent = '🔔 Turn on chat notifications';
            enablePill.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:92px;z-index:6000;border:0;cursor:pointer;padding:11px 18px;border-radius:999px;font:600 13.5px system-ui,sans-serif;color:#fff;background:linear-gradient(135deg,#6366f1,#7c3aed);box-shadow:0 14px 32px -8px rgba(99,102,241,.6);';
            enablePill.onclick = function(){ askPermission(); };
            document.body.appendChild(enablePill);
        } else if (!need && enablePill){ enablePill.remove(); enablePill = null; }
    }

    // ---------- boot ----------
    buildUI(); renderPanel(); setBadge(0);
    detectExistingPush();                            // if a push sub already exists, suppress in-page dupes (M7)
    if (permission() === 'granted') ensurePush();   // already allowed → make sure a push subscription exists
    refreshEnablePill();
    schedule();
    document.addEventListener('visibilitychange', function () { if (!document.hidden){ claim(); clearTimeout(timer); loop(); } });
    // A user gesture is the only time we may prompt; make the whole page a one-shot enabler when still "default".
    if (permission() === 'default') window.addEventListener('pointerdown', function once(){ window.removeEventListener('pointerdown', once); askPermission(); }, { once: true });

    // Expose a tiny API so the Team Chat page can announce which conversation is open+focused
    // (so we never desktop-notify the chat you're actively reading) and mark notifications read.
    window.addEventListener('beforeunload', function () { activeByTab[TAB] = undefined; delete activeByTab[TAB]; post({ kind: 'active', tab: TAB, conv: null }); });
    window.ApexRealtime = {
        setActive: function (conv, focused){
            var v = focused ? String(conv) : null;
            if (v) activeByTab[TAB] = v; else delete activeByTab[TAB];
            post({ kind: 'active', tab: TAB, conv: v });
        },
        markConversationRead: function (conv){
            notifs.forEach(function (n) { if (String(n.conversation_id) === String(conv)) n.unseen = false; });
            saveNotifs(); renderPanel(); post({ kind: 'seen' });
        },
        pokeBadge: setBadge
    };
})();
</script>
