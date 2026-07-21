/**
 * Inactivity guard.
 *
 * After N minutes with no interaction we show a warning with a countdown; if
 * it runs out the user is signed out and returned to the login page. Genuine
 * activity (mouse, keys, scroll, touch) resets the clock.
 *
 * It also sends a heartbeat while the user IS active, so the server session
 * can't expire underneath a page that's been open a while — that's what used
 * to turn the next click into an error screen.
 *
 * Config comes from data-* attributes on #idleGuard.
 */
(function () {
    var el = document.getElementById('idleGuard');
    if (!el) return;

    var IDLE_MS  = (parseInt(el.dataset.idleMinutes, 10)  || 10) * 60 * 1000;
    var GRACE_MS = (parseInt(el.dataset.graceSeconds, 10) || 60) * 1000;
    var BEAT_MS  = 4 * 60 * 1000;               // heartbeat cadence while active
    var logoutUrl    = el.dataset.logoutUrl;
    var keepaliveUrl = el.dataset.keepaliveUrl;

    var modal   = document.getElementById('idleModal');
    var countEl = document.getElementById('idleCount');
    var stayBtn = document.getElementById('idleStay');
    var outBtn  = document.getElementById('idleOut');
    if (!modal || !countEl) return;

    var idleTimer = null, graceTimer = null, ticker = null, deadline = 0;
    var activeSincePing = false, lastReset = 0;

    function clearTimers() {
        clearTimeout(idleTimer);
        clearTimeout(graceTimer);
        clearInterval(ticker);
        idleTimer = graceTimer = ticker = null;
    }

    function signOut() {
        clearTimers();
        window.location.href = logoutUrl;
    }

    function paint() {
        var left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        countEl.textContent = left;
    }

    function warn() {
        deadline = Date.now() + GRACE_MS;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        paint();
        ticker = setInterval(paint, 250);
        graceTimer = setTimeout(signOut, GRACE_MS);
        if (stayBtn) { try { stayBtn.focus(); } catch (e) {} }
    }

    function arm() {
        clearTimers();
        idleTimer = setTimeout(warn, IDLE_MS);
    }

    function isWarning() {
        return modal.classList.contains('is-open');
    }

    function staySignedIn() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        // Refresh the server session immediately, then restart the clock.
        ping();
        arm();
    }

    function ping() {
        if (!keepaliveUrl) return;
        try {
            fetch(keepaliveUrl, { method: 'GET', cache: 'no-store', credentials: 'same-origin' });
        } catch (e) { /* a failed heartbeat must never break the page */ }
    }

    // While the warning is up, only a deliberate click dismisses it — drifting
    // the mouse shouldn't silently cancel a pending logout.
    function onActivity() {
        if (isWarning()) return;
        activeSincePing = true;
        var now = Date.now();
        if (now - lastReset < 1000) return;      // throttle
        lastReset = now;
        arm();
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (ev) {
        window.addEventListener(ev, onActivity, { passive: true });
    });

    if (stayBtn) stayBtn.addEventListener('click', staySignedIn);
    if (outBtn)  outBtn.addEventListener('click', signOut);

    // Keep the server session alive only while the user is genuinely active.
    setInterval(function () {
        if (activeSincePing && !isWarning()) {
            activeSincePing = false;
            ping();
        }
    }, BEAT_MS);

    arm();
})();
