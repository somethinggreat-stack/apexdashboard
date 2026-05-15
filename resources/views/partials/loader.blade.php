<style>
/* ============================================
   APEX LOADER — 1s right-to-left card sweep.
   Self-contained; runs once per session via sessionStorage.
   ============================================ */
.apex-loader {
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at center, rgba(33,150,243,0.18) 0%, transparent 60%),
        linear-gradient(135deg, #0F2043 0%, #1A2D55 50%, #0F2043 100%);
    z-index: 999999;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: apexLoaderFade 1s cubic-bezier(0.65, 0, 0.35, 1) forwards;
    will-change: opacity;
}
.apex-loader.skip {
    animation: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
}

@keyframes apexLoaderFade {
    0%, 82% { opacity: 1; visibility: visible; }
    100% { opacity: 0; visibility: hidden; }
}

/* Glowing light streak that precedes the card */
.apex-loader-streak {
    position: absolute;
    top: 50%;
    left: 0;
    width: 60vw;
    height: 3px;
    transform: translateY(-50%) translateX(110vw) skewX(-12deg);
    background: linear-gradient(90deg,
        transparent 0%,
        rgba(96,181,255,0.4) 30%,
        rgba(255,255,255,1) 60%,
        rgba(96,181,255,0.4) 80%,
        transparent 100%);
    box-shadow: 0 0 60px rgba(96,181,255,0.7), 0 0 120px rgba(33,150,243,0.4);
    opacity: 0;
    animation: apexLoaderStreak 1s cubic-bezier(0.4, 0, 0.6, 1) forwards;
    will-change: transform, opacity;
}

@keyframes apexLoaderStreak {
    0%   { transform: translateY(-50%) translateX(110vw) skewX(-12deg); opacity: 0; }
    10%  { opacity: 1; }
    50%  { transform: translateY(-50%) translateX(-50vw) skewX(-12deg); opacity: 1; }
    100% { transform: translateY(-50%) translateX(-180vw) skewX(-12deg); opacity: 0; }
}

/* Secondary thinner streak for depth */
.apex-loader-streak.thin {
    height: 1px;
    top: calc(50% - 24px);
    width: 40vw;
    opacity: 0;
    animation: apexLoaderStreakThin 1s cubic-bezier(0.4, 0, 0.6, 1) 0.08s forwards;
    box-shadow: 0 0 30px rgba(96,181,255,0.5);
}
@keyframes apexLoaderStreakThin {
    0%   { transform: translateY(-50%) translateX(110vw) skewX(-12deg); opacity: 0; }
    20%  { opacity: 0.7; }
    100% { transform: translateY(-50%) translateX(-180vw) skewX(-12deg); opacity: 0; }
}

/* The card that flies through */
.apex-loader-card {
    position: relative;
    width: clamp(280px, 38vw, 480px);
    height: clamp(170px, 23vw, 280px);
    background: linear-gradient(135deg, #2196F3 0%, #1A6FC4 50%, #0F2043 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow:
        0 40px 100px rgba(0,0,0,0.55),
        0 0 120px rgba(33,150,243,0.5),
        inset 0 1px 0 rgba(255,255,255,0.22),
        inset 0 -1px 0 rgba(0,0,0,0.3);
    transform: translateX(110vw) rotate(6deg);
    opacity: 0;
    animation: apexLoaderCard 1s cubic-bezier(0.65, 0, 0.35, 1) forwards;
    will-change: transform, opacity;
    overflow: hidden;
}
.apex-loader-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg,
        transparent 30%,
        rgba(255,255,255,0.22) 50%,
        transparent 70%);
    transform: translateX(-100%);
    animation: apexLoaderShine 1s cubic-bezier(0.4, 0, 0.6, 1) 0.35s forwards;
}
@keyframes apexLoaderShine {
    0% { transform: translateX(-100%); }
    50%, 100% { transform: translateX(120%); }
}

@keyframes apexLoaderCard {
    0%   { transform: translateX(115vw) rotate(8deg) scale(0.94); opacity: 0; }
    18%  { opacity: 1; }
    35%  { transform: translateX(0) rotate(0deg) scale(1); opacity: 1; }
    60%  { transform: translateX(0) rotate(0deg) scale(1); opacity: 1; }
    100% { transform: translateX(-135vw) rotate(-9deg) scale(0.92); opacity: 0; }
}

.apex-loader-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    opacity: 0;
    position: relative;
    z-index: 2;
    animation: apexLoaderContent 1s ease forwards;
    will-change: opacity, transform, filter;
}
@keyframes apexLoaderContent {
    0%, 28% { opacity: 0; transform: scale(0.7); filter: blur(10px); }
    40%, 58% { opacity: 1; transform: scale(1); filter: blur(0); }
    72%, 100% { opacity: 0; transform: scale(1); filter: blur(0); }
}

.apex-loader-logo {
    height: 56px;
    width: auto;
    filter: brightness(0) invert(1) drop-shadow(0 4px 12px rgba(0,0,0,0.3));
}
.apex-loader-text {
    font-family: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
    font-size: 11px;
    letter-spacing: 0.32em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.92);
    font-weight: 500;
    text-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

/* Subtle particles in the backdrop for depth */
.apex-loader::before,
.apex-loader::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    filter: blur(60px);
    opacity: 0.5;
}
.apex-loader::before {
    top: 20%; left: 15%;
    width: 280px; height: 280px;
    background: radial-gradient(circle, rgba(96,181,255,0.5), transparent 70%);
    animation: apexLoaderOrbA 1s ease-in-out forwards;
}
.apex-loader::after {
    bottom: 15%; right: 12%;
    width: 320px; height: 320px;
    background: radial-gradient(circle, rgba(33,150,243,0.45), transparent 70%);
    animation: apexLoaderOrbB 1s ease-in-out forwards;
}
@keyframes apexLoaderOrbA {
    0% { opacity: 0; transform: scale(0.6); }
    50% { opacity: 0.5; transform: scale(1); }
    100% { opacity: 0; transform: scale(1.2); }
}
@keyframes apexLoaderOrbB {
    0% { opacity: 0; transform: scale(0.6); }
    50% { opacity: 0.45; transform: scale(1); }
    100% { opacity: 0; transform: scale(1.2); }
}

@media (max-width: 600px) {
    .apex-loader-card { width: 78vw; height: 44vw; min-height: 150px; border-radius: 16px; }
    .apex-loader-logo { height: 40px; }
    .apex-loader-text { font-size: 10px; letter-spacing: 0.26em; }
}

@media (prefers-reduced-motion: reduce) {
    .apex-loader, .apex-loader-streak, .apex-loader-card, .apex-loader-content, .apex-loader-card::before {
        animation-duration: 0.4s !important;
    }
    .apex-loader { animation: apexLoaderFade 0.4s forwards; }
}
</style>

<div class="apex-loader" id="apexLoader" aria-hidden="true" role="presentation">
    <div class="apex-loader-streak thin" aria-hidden="true"></div>
    <div class="apex-loader-streak" aria-hidden="true"></div>
    <div class="apex-loader-card">
        <div class="apex-loader-content">
            <img src="/Images/logo.png" alt="" class="apex-loader-logo">
            <div class="apex-loader-text">Apex Growth Systems</div>
        </div>
    </div>
</div>

<script>
(function () {
    var loader = document.getElementById('apexLoader');
    if (!loader) return;

    var seen = false;
    try { seen = sessionStorage.getItem('apex_loader_seen') === '1'; } catch (_) {}

    if (seen) {
        loader.classList.add('skip');
        // Remove from DOM immediately so it can't capture clicks
        if (loader.parentNode) loader.parentNode.removeChild(loader);
        return;
    }

    try { sessionStorage.setItem('apex_loader_seen', '1'); } catch (_) {}

    // Lock body scroll during the 1s reveal so the page doesn't jump
    var prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    window.setTimeout(function () {
        if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
        document.body.style.overflow = prevOverflow;
    }, 1080);
})();
</script>
