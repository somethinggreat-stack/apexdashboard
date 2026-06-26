<style>
/* ============================================
   APEX LOADER v2 — 3-act sequence: arrival, flash, exit.
   3000ms total, self-removing from DOM at 3100ms.
   ============================================ */
.apex-loader {
    position: fixed;
    inset: 0;
    background:
        radial-gradient(ellipse at center, rgba(33,150,243,0.20) 0%, transparent 65%),
        linear-gradient(135deg, #0A1530 0%, #1A2D55 50%, #0A1530 100%);
    z-index: 999999;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: apexLoaderFade 3s cubic-bezier(0.65, 0, 0.35, 1) forwards;
    will-change: opacity;
    perspective: 1200px;
}
.apex-loader.skip {
    animation: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
}
@keyframes apexLoaderFade {
    0%, 92% { opacity: 1; visibility: visible; }
    100% { opacity: 0; visibility: hidden; }
}

/* Ambient pulsing orbs in the backdrop corners */
.apex-loader::before,
.apex-loader::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    filter: blur(80px);
    will-change: transform, opacity;
}
.apex-loader::before {
    top: 18%; left: 12%;
    width: 340px; height: 340px;
    background: radial-gradient(circle, rgba(96,181,255,0.55), transparent 70%);
    animation: apexLoaderOrbA 3s ease-in-out forwards;
}
.apex-loader::after {
    bottom: 12%; right: 10%;
    width: 380px; height: 380px;
    background: radial-gradient(circle, rgba(33,150,243,0.50), transparent 70%);
    animation: apexLoaderOrbB 3s ease-in-out forwards;
}
@keyframes apexLoaderOrbA {
    0%, 10% { opacity: 0; transform: scale(0.7); }
    20%, 80% { opacity: 0.55; transform: scale(1.05); }
    92%, 100% { opacity: 0; transform: scale(1.3); }
}
@keyframes apexLoaderOrbB {
    0%, 10% { opacity: 0; transform: scale(0.7); }
    25%, 75% { opacity: 0.5; transform: scale(1.05); }
    92%, 100% { opacity: 0; transform: scale(1.3); }
}

/* Stage holds all the animated elements at center */
.apex-loader-stage {
    position: relative;
    width: clamp(300px, 40vw, 520px);
    height: clamp(190px, 25vw, 320px);
    transform-style: preserve-3d;
}

/* === LIGHT STREAKS — lead the card in === */
.apex-loader-streak {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 70vw;
    height: 3px;
    transform: translate(-50%, -50%) translateX(120vw) skewX(-14deg);
    background: linear-gradient(90deg,
        transparent 0%,
        rgba(96,181,255,0.4) 25%,
        rgba(255,255,255,1) 55%,
        rgba(96,181,255,0.4) 75%,
        transparent 100%);
    box-shadow: 0 0 80px rgba(96,181,255,0.85), 0 0 160px rgba(33,150,243,0.5);
    opacity: 0;
    animation: apexLoaderStreak 3s cubic-bezier(0.45, 0, 0.55, 1) forwards;
    will-change: transform, opacity;
    border-radius: 2px;
}
@keyframes apexLoaderStreak {
    0%   { transform: translate(-50%, -50%) translateX(120vw) skewX(-14deg); opacity: 0; }
    3%   { opacity: 1; }
    18%  { transform: translate(-50%, -50%) translateX(-30vw) skewX(-14deg); opacity: 1; }
    25%  { transform: translate(-50%, -50%) translateX(-150vw) skewX(-14deg); opacity: 0; }
    100% { opacity: 0; }
}
.apex-loader-streak.thin {
    height: 1px;
    width: 50vw;
    top: calc(50% - 36px);
    animation: apexLoaderStreakThin 3s cubic-bezier(0.45, 0, 0.55, 1) forwards;
    box-shadow: 0 0 40px rgba(96,181,255,0.6);
}
.apex-loader-streak.thin-bottom {
    top: calc(50% + 36px);
    animation-delay: 60ms;
}
@keyframes apexLoaderStreakThin {
    0%   { transform: translate(-50%, -50%) translateX(120vw) skewX(-14deg); opacity: 0; }
    5%   { opacity: 0.85; }
    22%  { transform: translate(-50%, -50%) translateX(-30vw) skewX(-14deg); opacity: 0.7; }
    28%  { transform: translate(-50%, -50%) translateX(-150vw) skewX(-14deg); opacity: 0; }
    100% { opacity: 0; }
}

/* === THE CARD === */
.apex-loader-card {
    position: absolute;
    inset: 0;
    background:
        linear-gradient(135deg, #2196F3 0%, #1A6FC4 45%, #0F2043 100%);
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow:
        0 50px 120px rgba(0,0,0,0.6),
        0 0 140px rgba(33,150,243,0.55),
        inset 0 1px 0 rgba(255,255,255,0.25),
        inset 0 -1px 0 rgba(0,0,0,0.35),
        inset 0 0 60px rgba(33,150,243,0.15);
    transform: translateX(115vw) rotate(10deg) scale(0.9);
    opacity: 0;
    animation: apexLoaderCard 3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    will-change: transform, opacity;
    overflow: hidden;
}
@keyframes apexLoaderCard {
    0%   { transform: translateX(115vw) rotate(10deg) scale(0.9); opacity: 0; }
    8%   { opacity: 1; }
    28%  { transform: translateX(-2%) rotate(-1deg) scale(1.02); opacity: 1; }
    34%  { transform: translateX(0) rotate(0deg) scale(1); opacity: 1; }
    78%  { transform: translateX(0) rotate(0deg) scale(1); opacity: 1; }
    82%  { transform: translateX(-2%) rotate(0.5deg) scale(1); opacity: 1; }
    100% { transform: translateX(-140vw) rotate(-11deg) scale(0.88); opacity: 0; }
}

/* Card surface sheen — continuous slow sweep while holding */
.apex-loader-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg,
        transparent 25%,
        rgba(255,255,255,0.28) 50%,
        transparent 75%);
    transform: translateX(-100%);
    animation: apexLoaderShine 3s cubic-bezier(0.4, 0, 0.6, 1) forwards;
}
@keyframes apexLoaderShine {
    0%, 15% { transform: translateX(-100%); }
    32%   { transform: translateX(0%); }
    50%   { transform: translateX(40%); }
    70%   { transform: translateX(0%); }
    85%   { transform: translateX(120%); }
    100%  { transform: translateX(120%); }
}

/* HUD line crossing the card — draws in then retracts */
.apex-loader-card::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.55), transparent);
    transform: translate(-50%, -50%);
    animation: apexLoaderHud 3s cubic-bezier(0.4, 0, 0.6, 1) forwards;
    pointer-events: none;
}
@keyframes apexLoaderHud {
    0%, 26% { width: 0; opacity: 0; }
    32% { width: 88%; opacity: 1; }
    72% { width: 88%; opacity: 0.8; }
    82% { width: 0; opacity: 0; }
    100% { width: 0; opacity: 0; }
}

/* === CONTENT inside the card === */
.apex-loader-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    opacity: 0;
    position: relative;
    z-index: 3;
    transform: scale(0.7);
    filter: blur(12px);
    animation: apexLoaderContent 3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    will-change: opacity, transform, filter;
}
@keyframes apexLoaderContent {
    0%, 30% { opacity: 0; transform: scale(0.7) rotate(-3deg); filter: blur(12px); }
    42%, 48% { opacity: 1; transform: scale(1.05) rotate(0); filter: blur(0); }
    52% { transform: scale(1) rotate(0); }
    80% { opacity: 1; transform: scale(1) rotate(0); filter: blur(0); }
    88% { opacity: 0.7; transform: scale(1.02); }
    100% { opacity: 0; transform: scale(0.95); filter: blur(4px); }
}

.apex-loader-logo {
    height: 64px;
    width: auto;
    filter: brightness(0) invert(1) drop-shadow(0 6px 18px rgba(0,0,0,0.35));
}
.apex-loader-text {
    font-family: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
    font-size: 12px;
    letter-spacing: 0.4em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.95);
    font-weight: 600;
    text-shadow: 0 2px 12px rgba(0,0,0,0.4);
}
.apex-loader-tagline {
    font-family: 'IBM Plex Mono', 'SF Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.6);
    margin-top: 2px;
    opacity: 0;
    animation: apexLoaderTagline 3s ease forwards;
}
@keyframes apexLoaderTagline {
    0%, 48% { opacity: 0; transform: translateY(6px); }
    58%, 80% { opacity: 1; transform: translateY(0); }
    88%, 100% { opacity: 0; }
}

/* === THE FLASH BURST === */
.apex-loader-flash {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: radial-gradient(circle,
        rgba(255,255,255,1) 0%,
        rgba(255,255,255,0.95) 20%,
        rgba(96,181,255,0.5) 50%,
        transparent 80%);
    transform: translate(-50%, -50%) scale(0);
    opacity: 0;
    animation: apexLoaderFlash 3s cubic-bezier(0.12, 0.81, 0.34, 1) forwards;
    will-change: transform, opacity;
    pointer-events: none;
    z-index: 4;
}
@keyframes apexLoaderFlash {
    0%, 36% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
    40% { transform: translate(-50%, -50%) scale(0.4); opacity: 1; }
    48% { transform: translate(-50%, -50%) scale(8); opacity: 0.85; }
    58% { transform: translate(-50%, -50%) scale(18); opacity: 0; }
    100% { transform: translate(-50%, -50%) scale(18); opacity: 0; }
}

/* Secondary white-ring flash */
.apex-loader-ring {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.95);
    transform: translate(-50%, -50%) scale(0);
    opacity: 0;
    animation: apexLoaderRing 3s cubic-bezier(0.12, 0.81, 0.34, 1) forwards;
    pointer-events: none;
    z-index: 5;
    box-shadow: 0 0 40px rgba(255,255,255,0.6), inset 0 0 20px rgba(96,181,255,0.4);
}
@keyframes apexLoaderRing {
    0%, 38% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
    42% { transform: translate(-50%, -50%) scale(0.6); opacity: 1; border-width: 3px; }
    55% { transform: translate(-50%, -50%) scale(6); opacity: 0.4; border-width: 1px; }
    68% { transform: translate(-50%, -50%) scale(10); opacity: 0; border-width: 1px; }
    100% { transform: translate(-50%, -50%) scale(10); opacity: 0; }
}

/* === PARTICLES — scatter radially during the flash === */
.apex-loader-particles {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    pointer-events: none;
    z-index: 4;
}
.apex-loader-particles span {
    position: absolute;
    top: 0;
    left: 0;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 0 12px rgba(96,181,255,0.9), 0 0 24px rgba(33,150,243,0.6);
    opacity: 0;
    transform: translate(-50%, -50%);
    will-change: transform, opacity;
}
.apex-loader-particles span:nth-child(1)  { animation: apexLoaderParticle 3s ease-out forwards; --dx:  180px; --dy:  -40px; }
.apex-loader-particles span:nth-child(2)  { animation: apexLoaderParticle 3s ease-out forwards; --dx:  140px; --dy:  120px; }
.apex-loader-particles span:nth-child(3)  { animation: apexLoaderParticle 3s ease-out forwards; --dx:   30px; --dy:  170px; }
.apex-loader-particles span:nth-child(4)  { animation: apexLoaderParticle 3s ease-out forwards; --dx: -110px; --dy:  140px; }
.apex-loader-particles span:nth-child(5)  { animation: apexLoaderParticle 3s ease-out forwards; --dx: -190px; --dy:   20px; }
.apex-loader-particles span:nth-child(6)  { animation: apexLoaderParticle 3s ease-out forwards; --dx: -140px; --dy: -110px; }
.apex-loader-particles span:nth-child(7)  { animation: apexLoaderParticle 3s ease-out forwards; --dx:  -20px; --dy: -180px; }
.apex-loader-particles span:nth-child(8)  { animation: apexLoaderParticle 3s ease-out forwards; --dx:  120px; --dy: -150px; }
.apex-loader-particles span:nth-child(9)  { animation: apexLoaderParticle 3s ease-out forwards; --dx:  240px; --dy:   60px; width: 4px; height: 4px; }
.apex-loader-particles span:nth-child(10) { animation: apexLoaderParticle 3s ease-out forwards; --dx: -240px; --dy:  -60px; width: 4px; height: 4px; }
.apex-loader-particles span:nth-child(11) { animation: apexLoaderParticle 3s ease-out forwards; --dx:   90px; --dy: -220px; width: 4px; height: 4px; }
.apex-loader-particles span:nth-child(12) { animation: apexLoaderParticle 3s ease-out forwards; --dx:  -90px; --dy:  220px; width: 4px; height: 4px; }
@keyframes apexLoaderParticle {
    0%, 38% { opacity: 0; transform: translate(-50%, -50%) translate(0, 0) scale(0); }
    42% { opacity: 1; transform: translate(-50%, -50%) translate(calc(var(--dx) * 0.1), calc(var(--dy) * 0.1)) scale(1.2); }
    62% { opacity: 1; transform: translate(-50%, -50%) translate(calc(var(--dx) * 0.85), calc(var(--dy) * 0.85)) scale(1); }
    82% { opacity: 0; transform: translate(-50%, -50%) translate(var(--dx), var(--dy)) scale(0.6); }
    100% { opacity: 0; transform: translate(-50%, -50%) translate(var(--dx), var(--dy)) scale(0.4); }
}

/* === RESPONSIVE === */
@media (max-width: 720px) {
    .apex-loader-stage { width: 80vw; height: 48vw; min-height: 180px; }
    .apex-loader-logo { height: 48px; }
    .apex-loader-text { font-size: 11px; letter-spacing: 0.32em; }
    .apex-loader-tagline { font-size: 9px; }
    .apex-loader-card { border-radius: 16px; }
}
@media (max-width: 480px) {
    .apex-loader-logo { height: 40px; }
    .apex-loader-text { font-size: 10px; letter-spacing: 0.26em; }
}

/* === REDUCED MOTION — skip the showcase, just fade === */
@media (prefers-reduced-motion: reduce) {
    .apex-loader { animation: apexLoaderFade 0.5s ease forwards; }
    .apex-loader-streak, .apex-loader-card, .apex-loader-content,
    .apex-loader-flash, .apex-loader-ring, .apex-loader-particles span,
    .apex-loader-tagline { animation-duration: 0.5s !important; }
}
</style>

<div class="apex-loader" id="apexLoader" aria-hidden="true" role="presentation">
    <div class="apex-loader-streak thin" aria-hidden="true"></div>
    <div class="apex-loader-streak thin thin-bottom" aria-hidden="true"></div>
    <div class="apex-loader-streak" aria-hidden="true"></div>

    <div class="apex-loader-stage">
        <div class="apex-loader-card">
            <div class="apex-loader-content">
                <img src="/Images/logo.png" alt="" class="apex-loader-logo">
                <div class="apex-loader-text">Apex Growth Solutions</div>
                <div class="apex-loader-tagline">Backend Credit Repair Fulfillment</div>
            </div>
        </div>
    </div>

    <div class="apex-loader-flash" aria-hidden="true"></div>
    <div class="apex-loader-ring" aria-hidden="true"></div>
    <div class="apex-loader-particles" aria-hidden="true">
        <span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span>
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
        if (loader.parentNode) loader.parentNode.removeChild(loader);
        return;
    }

    try { sessionStorage.setItem('apex_loader_seen', '1'); } catch (_) {}

    // Lock body scroll during the 3s reveal so the page doesn't visibly settle
    var prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    window.setTimeout(function () {
        if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
        document.body.style.overflow = prevOverflow;
    }, 3100);
})();
</script>
