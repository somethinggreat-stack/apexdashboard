/* One-shot "walk across the screen" animation, played on the first page a VA or
   the super admin sees after logging in (see AuthController's walker_once flag).

   Purely decorative:
     - the host element is position:fixed + pointer-events:none, so it can never
       intercept a click;
     - it removes itself from the DOM when the walk ends;
     - it is skipped entirely under prefers-reduced-motion;
     - if lottie or the JSON fails to load, nothing happens and nothing throws.

   Deliberately does NOT use requestAnimationFrame to kick off the transition:
   rAF does not fire in every context (background tabs, some headless/virtualised
   renderers), and a missed frame there meant the walk never started and the
   character stood off-screen forever. setTimeout always fires. */
(function () {
    var host = document.getElementById('fatmanWalker');
    if (!host) return;

    var WALK_MS = 11000;          // right edge → left edge, brisk walk
    var START_DELAY = 80;         // let the browser paint the start position
    var SAFETY_MS = 2000;         // walk even if lottie never reports DOMLoaded

    var started = false, done = false, anim = null;

    function cleanup() {
        if (done) return;
        done = true;
        if (anim) { try { anim.destroy(); } catch (e) {} }
        if (host && host.parentNode) host.remove();
    }

    function walk() {
        if (started || done) return;
        started = true;

        // Distance is measured in the element's own coordinate space
        // (clientWidth, not innerWidth) so html{zoom} can't stretch the walk.
        var distance = document.documentElement.clientWidth + (host.offsetWidth || 460);

        host.style.transition = 'transform ' + WALK_MS + 'ms linear';
        setTimeout(function () {
            host.style.transform = 'translateX(-' + distance + 'px)';
        }, START_DELAY);

        setTimeout(cleanup, WALK_MS + START_DELAY + 400);
    }

    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return cleanup();
    if (typeof lottie === 'undefined' || !lottie.loadAnimation) return cleanup();

    try {
        anim = lottie.loadAnimation({
            container: host.querySelector('.fw-art'),
            renderer: 'svg',
            loop: true,               // the 1.4s walk cycle loops while he crosses
            autoplay: true,
            path: host.dataset.src,
        });
    } catch (e) {
        return cleanup();
    }

    anim.addEventListener('data_failed', cleanup);
    anim.addEventListener('DOMLoaded', walk);

    // If DOMLoaded never arrives (slow JSON, odd renderer), walk anyway.
    setTimeout(walk, SAFETY_MS);
})();
