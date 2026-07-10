/* One-shot "walk across the screen" animation, played on the first page a VA or
   the super admin sees after logging in (see AuthController's walker_once flag).

   Purely decorative:
     - the host element is position:fixed + pointer-events:none, so it can never
       intercept a click;
     - it removes itself from the DOM when the walk ends;
     - it is skipped entirely under prefers-reduced-motion;
     - if lottie or the JSON fails to load, nothing happens and nothing throws. */
(function () {
    var host = document.getElementById('fatmanWalker');
    if (!host) return;

    function bail() { if (host && host.parentNode) host.remove(); }

    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return bail();
    if (typeof lottie === 'undefined' || !lottie.loadAnimation) return bail();

    var WALK_MS = 22000;          // right edge → left edge, slow amble
    var anim;

    try {
        anim = lottie.loadAnimation({
            container: host.querySelector('.fw-art'),
            renderer: 'svg',
            loop: true,               // the 1.4s walk cycle loops while he crosses
            autoplay: true,
            path: host.dataset.src,
        });
    } catch (e) {
        return bail();
    }

    // If the JSON 404s or is malformed, don't leave a ghost element behind.
    anim.addEventListener('data_failed', bail);

    anim.addEventListener('DOMLoaded', function () {
        // Distance is measured in the element's own coordinate space
        // (clientWidth, not innerWidth) so html{zoom} can't stretch the walk.
        var distance = document.documentElement.clientWidth + host.offsetWidth;

        host.style.transition = 'transform ' + WALK_MS + 'ms linear';

        // rAF so the browser commits the start position before the transition.
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                host.style.transform = 'translateX(-' + distance + 'px)';
            });
        });

        setTimeout(function () {
            try { anim.destroy(); } catch (e) {}
            bail();
        }, WALK_MS + 400);
    });
})();
