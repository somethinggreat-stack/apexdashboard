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

    var POLL_URL = @json(route('admin.team-messages.notifications'));
    var OPEN_URL = @json(route('admin.team-messages.index'));
    var onChatPage = !!document.querySelector('.tc-wrap');   // the Team Chat page renders its own surface

    // ---------- shared state ----------
    var LKEY = 'apex-team-last-msg', NKEY = 'apex-team-notifs';
    function ls(k, v){ try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
    var lastId = parseInt(ls(LKEY) || '0', 10) || 0;
    var primed = lastId > 0;               // if we have a baseline, deliver new msgs (no backlog spam)
    var seen = {};                          // id -> 1, session-level dedupe
    var activeConvs = {};                   // convId -> true while open+focused in some tab (suppress its notifications)

    // ---------- single-poller leader election (one network poller across all tabs) ----------
    var TAB = String(Date.now()) + '-' + Math.random().toString(36).slice(2, 8);
    var LEAD = 'apex-team-leader';
    function leader(){ try { return JSON.parse(ls(LEAD) || 'null'); } catch (e) { return null; } }
    function leaderFresh(){ var l = leader(); return l && (Date.now() - l.ts < 8000); }
    function amLeader(){ var l = leader(); return l && l.id === TAB; }
    function claim(){ if (!leaderFresh() || amLeader()) ls(LEAD, JSON.stringify({ id: TAB, ts: Date.now() })); }
    claim(); setInterval(claim, 3000);
    window.addEventListener('beforeunload', function () { if (amLeader()) { try { localStorage.removeItem(LEAD); } catch (e) {} } });

    // ---------- cross-tab bus ----------
    var bc = ('BroadcastChannel' in window) ? new BroadcastChannel('apex-team') : null;
    if (bc) bc.onmessage = function (e) {
        var d = e.data || {};
        if (d.kind === 'active'){ if (d.focused) activeConvs[d.conv] = true; else delete activeConvs[d.conv]; return; }
        if (d.kind === 'seen'){ hydrate(); renderPanel(); setBadge(d.unread); return; }
        if (d.kind === 'data'){ ingest(d.data, false); }   // from the leader — update in-app UI, don't re-notify/re-broadcast
    };
    function post(o){ if (bc) bc.postMessage(o); }

    // ---------- desktop notification permission ----------
    function permission(){ return ('Notification' in window) ? Notification.permission : 'denied'; }
    function askPermission(){ if (permission() === 'default') { try { Notification.requestPermission().then(renderPanel); } catch (e) {} } }

    function chime(){
        try {
            var C = window.AudioContext || window.webkitAudioContext; if (!C) return;
            var c = new C(), o = c.createOscillator(), g = c.createGain();
            o.type = 'sine'; o.frequency.value = 920; g.gain.value = 0.04; o.connect(g); g.connect(c.destination); o.start();
            g.gain.linearRampToValueAtTime(0, c.currentTime + 0.28); o.stop(c.currentTime + 0.3);
            setTimeout(function () { try { c.close(); } catch (e) {} }, 600);
        } catch (e) {}
    }
    function desktop(m){
        if (permission() !== 'granted') return;
        try {
            var n = new Notification(m.title || 'Apex Team Chat', {
                body: (m.mention ? '@ ' : '') + m.sender + ': ' + m.snippet,
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

    // ---------- nav badge (Team Chat link) + bell count ----------
    function setBadge(n){
        if (typeof n !== 'number') return;
        document.querySelectorAll('a[href*="team-messages"]').forEach(function (a) {
            var b = a.querySelector('.pro-count');
            if (n > 0){ if (!b){ b = document.createElement('span'); b.className = 'pro-count'; a.appendChild(b); } b.textContent = n > 99 ? '99+' : n; b.style.display = ''; }
            else if (b){ b.remove(); }
        });
        if (badge){ if (n > 0){ badge.textContent = n > 99 ? '99+' : n; badge.classList.add('show'); } else badge.classList.remove('show'); }
    }

    // ---------- ingest a payload (from network on the leader, or from a peer tab) ----------
    function ingest(data, fromNetwork){
        if (!data) return;
        if (typeof data.unread === 'number') setBadge(data.unread);
        var msgs = data.messages || [];
        msgs.forEach(function (m) {
            if (seen[m.id]) return; seen[m.id] = 1;
            var muted = activeConvs[m.conversation_id];   // conversation open+focused somewhere → stay quiet
            addNotif(m);
            if (m.unseen === false) {}   // reserved
            window.dispatchEvent(new CustomEvent('apex:team-message', { detail: m }));   // let an open Team Chat update live
            if (fromNetwork && amLeader() && !muted){ desktop(m); }
        });
        if (msgs.length && amLeader()) chime();
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
                if (typeof data.unread === 'number') setBadge(data.unread);
                if (!primed){ if (data.lastId){ lastId = data.lastId; ls(LKEY, String(lastId)); } primed = true; schedule(); return; }
                if (data.lastId > lastId){ lastId = data.lastId; ls(LKEY, String(lastId)); }
                if ((data.messages || []).length){ ingest(data, true); post({ kind: 'data', data: data }); }
                schedule();
            })
            .catch(function () { schedule(); });
    }

    // ---------- boot ----------
    buildUI(); renderPanel(); setBadge(0);
    schedule();
    document.addEventListener('visibilitychange', function () { if (!document.hidden){ claim(); clearTimeout(timer); loop(); } });
    // A user gesture is the only time we may prompt; make the whole page a one-shot enabler when still "default".
    if (permission() === 'default') window.addEventListener('pointerdown', function once(){ window.removeEventListener('pointerdown', once); askPermission(); }, { once: true });

    // Expose a tiny API so the Team Chat page can announce which conversation is open+focused
    // (so we never desktop-notify the chat you're actively reading) and mark notifications read.
    window.ApexRealtime = {
        setActive: function (conv, focused){ if (focused) activeConvs[conv] = true; else delete activeConvs[conv]; post({ kind: 'active', conv: conv, focused: !!focused }); },
        markConversationRead: function (conv){
            notifs.forEach(function (n) { if (String(n.conversation_id) === String(conv)) n.unseen = false; });
            saveNotifs(); renderPanel(); post({ kind: 'seen' });
        },
        pokeBadge: setBadge
    };
})();
</script>
