/* One-shot "fly across the screen" animation, played on the first page a VA or
   the super admin sees after logging in (see AuthController's walker_once flag).
   The businessman-rocket launches from the bottom-left corner and flies up to
   the top-right corner, then removes itself.

   Purely decorative:
     - the host element is position:fixed + pointer-events:none, so it can never
       intercept a click;
     - it removes itself from the DOM when the flight ends;
     - it is skipped entirely under prefers-reduced-motion;
     - if lottie or the JSON fails to load, nothing happens and nothing throws.

   Deliberately does NOT use requestAnimationFrame to kick off the transition:
   rAF does not fire in every context (background tabs, some headless/virtualised
   renderers), and a missed frame there meant the flight never started and the
   character sat off-screen forever. setTimeout always fires. */
(function () {
    var host = document.getElementById('fatmanWalker');
    if (!host) return;

    var FLY_MS = 6000;            // bottom-left → top-right, a brisk launch
    var START_DELAY = 80;         // let the browser paint the start position
    var SAFETY_MS = 2000;         // fly even if lottie never reports DOMLoaded

    var started = false, done = false, anim = null;

    function cleanup() {
        if (done) return;
        done = true;
        if (anim) { try { anim.destroy(); } catch (e) {} }
        if (host && host.parentNode) host.remove();
    }

    function fly() {
        if (started || done) return;
        started = true;

        // CSS parks him at translate(-100%, 100%), just off the bottom-left
        // corner. Fly him a full viewport across and up so he exits completely
        // off the top-right. clientWidth/Height, not innerWidth/Height:
        // html{zoom} would skew the latter.
        var dx = document.documentElement.clientWidth;
        var dy = document.documentElement.clientHeight;

        host.style.transition = 'transform ' + FLY_MS + 'ms cubic-bezier(.37,.01,.4,1)';
        setTimeout(function () {
            host.style.transform = 'translate(' + dx + 'px, ' + (-dy) + 'px)';
        }, START_DELAY);

        setTimeout(cleanup, FLY_MS + START_DELAY + 400);
    }

    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return cleanup();
    if (typeof lottie === 'undefined' || !lottie.loadAnimation) return cleanup();

    try {
        anim = lottie.loadAnimation({
            container: host.querySelector('.fw-art'),
            renderer: 'svg',
            loop: true,               // the rocket loop plays while he crosses
            autoplay: true,
            path: host.dataset.src,
        });
    } catch (e) {
        return cleanup();
    }

    anim.addEventListener('data_failed', cleanup);
    anim.addEventListener('DOMLoaded', fly);

    // If DOMLoaded never arrives (slow JSON, odd renderer), fly anyway.
    setTimeout(fly, SAFETY_MS);
})();
