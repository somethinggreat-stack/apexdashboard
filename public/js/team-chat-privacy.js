/* Session lifetime for Team Chat. No message content belongs in the session marker. */
(function () {
    'use strict';
    if (window.ApexChatPrivacy) return;
    var current = null, stopped = false, requests = new Set(), uploads = new Set();
    var prefix = 'apex-team-session:u';
    var legacy = ['tc-draft', 'tc-drafts', 'tc-recent-emoji', 'apex-team-last-msg',
        'apex-team-notifs', 'apex-team-last-notified', 'apex-team-leader'];
    function read(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function removeLegacy() {
        [localStorage, sessionStorage].forEach(function (s) {
            legacy.forEach(function (k) { try { s.removeItem(k); } catch (e) {} });
        });
    }
    function live() {
        if (!current || stopped) return false;
        try { return localStorage.getItem(prefix + current.uid) === current.stamp; }
        catch (e) { return !stopped; }
    }
    // The server renewed this person's session (e.g. "keep me signed in" after an idle spell).
    // Take the new stamp and carry on — signing them out here would interrupt live work.
    function renew(stamp) {
        if (!stamp || !current || stopped) return false;
        current.stamp = stamp;
        try { localStorage.setItem(prefix + current.uid, stamp); } catch (e) {}
        return true;
    }
    function clear(scope) {
        if (!scope || !scope.uid) return Promise.resolve();
        // A delayed cleanup from a former login must not wipe a newer login of this user.
        var marker = read(prefix + scope.uid);
        if (marker && scope.stamp && marker !== scope.stamp) return Promise.resolve();
        removeLegacy();
        [localStorage, sessionStorage].forEach(function (s) {
            try { Object.keys(s).forEach(function (k) {
                if (/^(apex-team-|tc-drafts?:u|tc-recent-emoji:u)/.test(k) && k.endsWith(':u' + scope.uid)) s.removeItem(k);
            }); } catch (e) {}
        });
        return new Promise(function (resolve) {
            try {
                var r = indexedDB.open('apexChat', 1);
                r.onupgradeneeded = function () { r.result.createObjectStore('threads'); };
                r.onerror = function () { resolve(); };
                r.onsuccess = function () {
                    var db = r.result;
                    var t = db.transaction('threads', 'readwrite'), c = t.objectStore('threads').openCursor();
                    c.onsuccess = function () {
                        var row = c.result; if (!row) return;
                        var v = row.value;
                        if (String(row.key).startsWith('u' + scope.uid + '|') &&
                            (!v.uid || String(v.uid) === String(scope.uid))) row.delete();
                        // Unstamped legacy snapshots cannot safely be assigned to any VA.
                        else if (!v.uid && !/^u\d+\|/.test(String(row.key))) row.delete();
                        row.continue();
                    };
                    t.oncomplete = t.onabort = t.onerror = function () { db.close(); resolve(); };
                };
            } catch (e) { resolve(); }
        });
    }
    function stop(navigate) {
        if (stopped) return;
        stopped = true;
        window.__tcPrivacyStopped = true;
        window.__tcAllowLeave = true;
        window.__tcRun = (window.__tcRun || 0) + 1;
        window.__tcNavSeq = (window.__tcNavSeq || 0) + 1;
        window.__tcDrafts = {};
        requests.forEach(function (c) { c.abort(); });
        uploads.forEach(function (x) { x.abort(); });
        if (window.__tcReg) window.__tcReg.cleanup();
        window.dispatchEvent(new Event('apex:chat-session-ended'));
        // Scrub the old document immediately, before any network navigation or pending reply.
        document.querySelectorAll('.tc-wrap, .apex-nc-panel, .apex-nc-bell').forEach(function (e) { e.remove(); });
        try { window.__TAURI__.core.invoke('set_unread', { count: 0 }); } catch (e) {}
        clear(current);
        if (navigate) location.replace(current.login);
    }
    function check() { if (!live()) stop(true); return live(); }
    function chatUrl(url) {
        try { var u = new URL(url, location.href); return u.origin === location.origin && /^\/admin\/(team-messages(?:\/|$)|logout$)/.test(u.pathname); }
        catch (e) { return false; }
    }
    function abandoned() { return new Promise(function () {}); }
    function start(scope) {
        if (current) return;
        current = scope;
        removeLegacy();
        var previous = read(prefix + scope.uid);
        if (previous && previous !== scope.stamp) clear({ uid: scope.uid, stamp: previous });
        // Cookies are shared across tabs of this origin. A newly authenticated account
        // invalidates old tabs; separate browser profiles have separate storage.
        try {
            Object.keys(localStorage).forEach(function (k) {
                if (k.startsWith(prefix) && k !== prefix + scope.uid) {
                    clear({ uid: k.slice(prefix.length), stamp: localStorage.getItem(k) });
                }
            });
            localStorage.setItem(prefix + scope.uid, scope.stamp);
        } catch (e) {}
        window.addEventListener('storage', function (e) { if (e.key === prefix + scope.uid || e.key === null) check(); });
        window.addEventListener('focus', check);
        window.addEventListener('visibilitychange', check);
        // A history/BFCache restoration must not paint a former user's document.
        window.addEventListener('pagehide', function () { document.documentElement.style.visibility = 'hidden'; });
        window.addEventListener('pageshow', function () { if (check()) document.documentElement.style.visibility = ''; });
        var fetch0 = window.fetch;
        window.fetch = function (url, opts) {
            if (!chatUrl(url instanceof Request ? url.url : url)) return fetch0.apply(this, arguments);
            if (!check()) return abandoned();
            opts = Object.assign({}, opts || {});
            opts.headers = new Headers(opts.headers || (url instanceof Request ? url.headers : undefined));
            opts.headers.set('X-Apex-Chat-Session', current.stamp);
            // Whether the VA is actually at this window. Background polls keep the chat live
            // but must not keep marking them "Online" while the app sits in the tray.
            opts.headers.set('X-Apex-Active', (!document.hidden && document.hasFocus()) ? '1' : '0');
            var ctl = new AbortController(), signal = opts.signal || (url instanceof Request ? url.signal : null);
            if (signal) { if (signal.aborted) ctl.abort(); else signal.addEventListener('abort', function () { ctl.abort(); }, { once: true }); }
            opts.signal = ctl.signal; requests.add(ctl);
            var self = this;
            return fetch0.call(this, url, opts).then(function (r) {
                requests.delete(ctl);
                if (!live()) return abandoned();
                if (r.status === 401 || r.redirected) { stop(true); return abandoned(); }
                // Same person, renewed session: take the new stamp and repeat the request once,
                // so a session that rolls over never interrupts what the VA is doing.
                if (r.status === 409 && !opts.__apexRetried) {
                    return r.clone().json().catch(function () { return null; }).then(function (j) {
                        if (j && j.reason === 'session-renewed' && renew(j.stamp)) {
                            var again = Object.assign({}, opts, { __apexRetried: true });
                            return window.fetch.call(self, url, again);
                        }
                        return r;
                    });
                }
                return r;
            }, function (e) { requests.delete(ctl); if (!live()) return abandoned(); throw e; });
        };
        var open0 = XMLHttpRequest.prototype.open, send0 = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method, url) { this.__apexChat = chatUrl(url); return open0.apply(this, arguments); };
        XMLHttpRequest.prototype.send = function () {
            if (this.__apexChat) {
                if (!check()) { this.abort(); return; }
                this.setRequestHeader('X-Apex-Chat-Session', current.stamp);
                this.setRequestHeader('X-Apex-Active', '1');   // sending a message IS activity
                var x = this; uploads.add(x);
                x.addEventListener('loadend', function () {
                    uploads.delete(x);
                    if (x.status === 401) { stop(true); return; }
                    // Renewed session: adopt the new stamp so the resend (same client id,
                    // so it can't duplicate) goes through.
                    if (x.status === 409) { try { renew(JSON.parse(x.responseText).stamp); } catch (e) {} }
                });
            }
            return send0.apply(this, arguments);
        };
    }
    window.ApexChatPrivacy = { start: start, clear: clear, live: live,
        token: function () { return current && current.stamp; },
        renew: renew,
        logout: function () { stop(false); return clear(current); } };
})();
