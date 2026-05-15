<!-- ============ FLOATING BACKGROUND ANIMATION ============ -->
<div class="bg-floaters" id="bgFloaters" aria-hidden="true"></div>

<style>
.bg-floaters {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  overflow: hidden;
}

.bg-floater {
  position: absolute;
  opacity: 0;
  will-change: transform, opacity;
  animation: floaterDrift linear infinite;
  pointer-events: none;
}

/* Card shapes */
.bg-floater--card {
  width: 64px;
  height: 42px;
  border-radius: 8px;
  border: 1.5px solid rgba(26, 111, 196, 0.25);
  background: linear-gradient(135deg, rgba(26, 111, 196, 0.08) 0%, rgba(33, 150, 243, 0.05) 100%);
  box-shadow: 0 4px 12px rgba(15, 32, 67, 0.08);
}
.bg-floater--card::before {
  content: '';
  position: absolute;
  top: 9px; left: 9px;
  width: 16px; height: 12px;
  border-radius: 3px;
  background: rgba(26, 111, 196, 0.2);
}
.bg-floater--card::after {
  content: '';
  position: absolute;
  bottom: 8px; left: 9px; right: 9px;
  height: 4px;
  border-radius: 2px;
  background: rgba(26, 111, 196, 0.12);
}

/* Dollar sign circles */
.bg-floater--coin {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 1.5px solid rgba(26, 111, 196, 0.25);
  background: rgba(219, 234, 254, 0.15);
  display: grid;
  place-items: center;
  font-family: 'Geist', sans-serif;
  font-size: 15px;
  font-weight: 700;
  color: rgba(26, 111, 196, 0.35);
}

/* Arrow shapes */
.bg-floater--arrow {
  width: 22px;
  height: 22px;
  border-right: 2.5px solid rgba(26, 111, 196, 0.2);
  border-top: 2.5px solid rgba(26, 111, 196, 0.2);
  transform: rotate(-45deg);
}

/* Small dots */
.bg-floater--dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(26, 111, 196, 0.18);
}

/* Bar chart mini icon */
.bg-floater--chart {
  width: 36px;
  height: 28px;
  display: flex;
  align-items: flex-end;
  gap: 3px;
}
.bg-floater--chart span {
  flex: 1;
  background: rgba(26, 111, 196, 0.18);
  border-radius: 2px 2px 0 0;
}

/* Drifting upward */
@keyframes floaterDrift {
  0% {
    opacity: 0;
    transform: translateY(0) rotate(0deg) scale(var(--fl-scale, 1));
  }
  4% {
    opacity: var(--fl-opacity, 1);
  }
  85% {
    opacity: var(--fl-opacity, 1);
  }
  100% {
    opacity: 0;
    transform: translateY(calc(-100vh - 80px)) rotate(var(--rotate-end, 20deg)) scale(var(--fl-scale, 1));
  }
}

/* Pre-placed elements that are already visible on load */
@keyframes floaterPreDrift {
  0% {
    opacity: var(--fl-opacity, 1);
    transform: translateY(0) rotate(0deg) scale(var(--fl-scale, 1));
  }
  85% {
    opacity: var(--fl-opacity, 1);
  }
  100% {
    opacity: 0;
    transform: translateY(calc(-100vh - 80px)) rotate(var(--rotate-end, 20deg)) scale(var(--fl-scale, 1));
  }
}

/* Gentle side-to-side sway */
@keyframes floaterSway {
  0%, 100% { margin-left: 0; }
  50% { margin-left: var(--sway, 20px); }
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
  .bg-floaters { display: none; }
}
</style>

<script>
(function(){
  var container = document.getElementById('bgFloaters');
  if (!container || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var types = ['card', 'coin', 'arrow', 'dot', 'chart'];

  function makeElement(type) {
    var el = document.createElement('div');
    el.className = 'bg-floater bg-floater--' + type;
    if (type === 'coin') el.textContent = '$';
    if (type === 'chart') {
      [40, 65, 85, 100].forEach(function(h) {
        var bar = document.createElement('span');
        bar.style.height = h + '%';
        el.appendChild(bar);
      });
    }
    return el;
  }

  function spawnFloater(opts) {
    var type = types[Math.floor(Math.random() * types.length)];
    var el = makeElement(type);

    var left = 3 + Math.random() * 94;
    var duration = 16 + Math.random() * 14;
    var scale = 0.65 + Math.random() * 0.7;
    var rotateEnd = -20 + Math.random() * 40;
    var sway = -25 + Math.random() * 50;
    var swayDuration = 3 + Math.random() * 4;

    el.style.left = left + '%';
    el.style.setProperty('--fl-scale', scale);
    el.style.setProperty('--fl-opacity', '1');
    el.style.setProperty('--rotate-end', rotateEnd + 'deg');
    el.style.setProperty('--sway', sway + 'px');

    if (opts && opts.prePlace) {
      // Already on screen at a random Y position
      var startY = Math.random() * 100;
      el.style.top = startY + '%';
      // Remaining time proportional to position
      var remainDuration = duration * (1 - startY / 100);
      el.style.animation =
        'floaterPreDrift ' + remainDuration + 's linear forwards, ' +
        'floaterSway ' + swayDuration + 's ease-in-out infinite';
      el.style.animationDelay = '0s, 0s';
    } else {
      el.style.bottom = '-60px';
      var delay = (opts && opts.delay) || 0;
      el.style.animation =
        'floaterDrift ' + duration + 's linear ' + delay + 's forwards, ' +
        'floaterSway ' + swayDuration + 's ease-in-out ' + delay + 's infinite';
    }

    container.appendChild(el);

    // Clean up after animation ends, then respawn
    var totalTime = ((opts && opts.prePlace) ?
      (duration * (1 - (parseFloat(el.style.top) || 0) / 100)) :
      (duration + ((opts && opts.delay) || 0))) * 1000;

    setTimeout(function() {
      if (el.parentNode) el.parentNode.removeChild(el);
      // Respawn a new one
      spawnFloater({ delay: 0 });
    }, totalTime);
  }

  // Wait for page content to render and hero animations to play first
  setTimeout(function() {
    // PHASE 1: Fade in pre-placed elements with stagger
    for (var i = 0; i < 8; i++) {
      (function(idx) {
        setTimeout(function() {
          spawnFloater({ prePlace: true });
        }, idx * 200);
      })(i);
    }

    // PHASE 2: Start streaming from bottom after pre-placed are in
    setTimeout(function() {
      var STREAM_COUNT = 6;
      for (var j = 0; j < STREAM_COUNT; j++) {
        spawnFloater({ delay: j * 2 });
      }
    }, 2500);
  }, 2000);
})();
</script>
