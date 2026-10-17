/*
 * Session keep-alive — stops "419 Page Expired" everywhere (admin/VA dashboard,
 * owner portal, Team Chat, login pages).
 *
 * A 419 means the page's hidden CSRF token no longer matches the session: the
 * page sat open for hours, or the phone (WhatsApp / Safari) showed an old copy
 * from memory without reloading it. This script:
 *   1. pings /session/token every 20 min while the page is visible, and whenever
 *      the tab or phone comes back into focus — keeping the session alive and
 *      the page's token current (meta tag + every hidden _token field);
 *   2. refreshes straight away when the browser restores a page from memory;
 *   3. for forms marked data-fresh-token (the login forms) fetches a fresh token
 *      right before submitting, so a stale login page can never 419.
 *
 * Included with: <script src="/js/session-keepalive.js" data-token-url="..."></script>
 */
(function () {
    var script = document.currentScript;
    var URL_ = (script && script.getAttribute('data-token-url')) || '/session/token';
    var EVERY_MS = 20 * 60 * 1000;     // background refresh while visible
    var STALE_MS = 5 * 60 * 1000;      // on refocus, refresh if older than this
    var last = Date.now();
    var inflight = null;

    function apply(token) {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
        var inputs = document.querySelectorAll('input[name="_token"]');
        for (var i = 0; i < inputs.length; i++) inputs[i].value = token;
        // Scripts that cached the token in a variable can listen for this.
        try { window.dispatchEvent(new CustomEvent('apex:csrf', { detail: token })); } catch (e) {}
    }

    function refresh() {
        if (inflight) return inflight;
        inflight = fetch(URL_ + (URL_.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (j && j.token) { apply(j.token); last = Date.now(); return j.token; }
                return null;
            })
            .catch(function () { return null; })
            .then(function (t) { inflight = null; return t; });
        return inflight;
    }
    window.apexRefreshCsrf = refresh;

    setInterval(function () { if (!document.hidden) refresh(); }, EVERY_MS);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && Date.now() - last > STALE_MS) refresh();
    });
    window.addEventListener('focus', function () {
        if (Date.now() - last > STALE_MS) refresh();
    });
    // The phone/browser restored this page from memory (back button, reopened tab).
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) refresh();
    });

    // Login forms: always submit with a token fetched just now.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.hasAttribute || !form.hasAttribute('data-fresh-token')) return;
        if (form.getAttribute('data-token-ready') === '1') return;   // second pass — let it go
        e.preventDefault();
        var btn = form.querySelector('[type="submit"]');
        if (btn) btn.disabled = true;
        var done = false;
        var go = function () {
            if (done) return;   // the timeout and the refresh can both fire — submit once
            done = true;
            form.setAttribute('data-token-ready', '1');
            if (btn) btn.disabled = false;
            HTMLFormElement.prototype.submit.call(form);
        };
        // Never block sign-in on the refresh: if it's slow or offline, submit anyway.
        var timer = setTimeout(go, 4000);
        refresh().then(function () { clearTimeout(timer); go(); });
    }, true);
})();
