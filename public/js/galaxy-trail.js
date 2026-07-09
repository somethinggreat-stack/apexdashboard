(function () {
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var canvas = document.createElement('canvas');
    canvas.id = 'gxCanvas';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.appendChild(canvas);
    var ctx = canvas.getContext('2d');

    /* deep-space palette: violet â†’ indigo â†’ blue â†’ cyan, with magenta accents */
    var COLORS = [
        [139,  92, 246], [124,  58, 237], [ 99, 102, 241],
        [ 79,  70, 229], [ 59, 130, 246], [ 34, 211, 238],
        [232,  62, 189], [192, 132, 252]
    ];
    var MAX = 1000;
    var parts = [];
    var lx = 0, ly = 0, hx = 0, hy = 0, headA = 0, primed = false;
    var running = false, idle = 0;
    var W = 0, H = 0, ox = 0, oy = 0;   // canvas size + viewport offset, in clientX space

    function rnd(a, b) { return a + Math.random() * (b - a); }

    /* Pre-rendered sprites: drawing a gradient per particle per frame is far too
       slow, so each colour gets one glow sprite and one star sprite, blitted. */
    function sprite(draw) {
        var c = document.createElement('canvas');
        c.width = c.height = 64;
        draw(c.getContext('2d'));
        return c;
    }
    function rgba(c, a) { return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a + ')'; }

    var GLOW = COLORS.map(function (c) {
        return sprite(function (g) {
            var grd = g.createRadialGradient(32, 32, 0, 32, 32, 32);
            grd.addColorStop(0,    rgba(c, 1));
            grd.addColorStop(0.18, rgba(c, 0.72));
            grd.addColorStop(0.45, rgba(c, 0.22));
            grd.addColorStop(1,    rgba(c, 0));
            g.fillStyle = grd;
            g.fillRect(0, 0, 64, 64);
        });
    });
    var STAR = COLORS.map(function (c) {
        return sprite(function (g) {
            var grd = g.createRadialGradient(32, 32, 0, 32, 32, 12);
            grd.addColorStop(0, rgba(c, 1));
            grd.addColorStop(1, rgba(c, 0));
            g.fillStyle = grd;
            g.fillRect(0, 0, 64, 64);
            g.fillStyle = rgba(c, 0.85);          // 4-point flare
            g.fillRect(31.2, 4, 1.6, 56);
            g.fillRect(4, 31.2, 56, 1.6);
        });
    });

    /* Ask the browser where the canvas actually is instead of trying to undo
       html{zoom} by hand â€” getBoundingClientRect() reports in the same space as
       event.clientX, so the trail lands exactly under the cursor at any zoom. */
    function resize() {
        var r = canvas.getBoundingClientRect();
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        W = r.width; H = r.height; ox = r.left; oy = r.top;
        canvas.width  = Math.max(1, Math.round(W * dpr));
        canvas.height = Math.max(1, Math.round(H * dpr));
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    resize();
    window.addEventListener('resize', resize, { passive: true });

    function push(x, y, vx, vy, kind) {
        if (parts.length >= MAX) parts.shift();
        var i = (Math.random() * COLORS.length) | 0;
        var life = kind === 'dust' ? rnd(55, 105) : (kind === 'star' ? rnd(20, 34) : rnd(30, 60));
        parts.push({
            x: x, y: y, px: x, py: y, vx: vx, vy: vy,
            life: life, max: life,
            size: kind === 'dust' ? rnd(0.8, 2.4) : (kind === 'star' ? rnd(2.2, 4) : rnd(3, 8)),
            w: rnd(-0.055, 0.055),                // per-frame curl â†’ spiral arms
            img: kind === 'star' ? STAR[i] : GLOW[i],
            rgb: COLORS[i],
            streak: kind === 'star'
        });
    }

    /* Emit along the segment the cursor travelled, so fast moves stay continuous
       instead of leaving gaps between frames. */
    function emit(x0, y0, x1, y1) {
        var dx = x1 - x0, dy = y1 - y0;
        var dist = Math.sqrt(dx * dx + dy * dy) || 1;
        var ux = dx / dist, uy = dy / dist;      // direction of travel
        var nx = -uy, ny = ux;                   // perpendicular
        var steps = Math.min(6, Math.ceil(dist / 8));
        var speed = Math.min(dist, 40);

        /* t runs 1..steps so the last particle is born exactly under the cursor
           tip â€” the tail is drawn behind it by drift, not by spawning behind. */
        for (var i = 1; i <= steps; i++) {
            var t = i / steps;
            var x = x0 + dx * t, y = y0 + dy * t;
            var back = 0.4 + speed * 0.05;       // trail streams away from the cursor

            push(x + rnd(-1, 1), y + rnd(-1, 1),
                 -ux * rnd(0.2, 1) * back + nx * rnd(-0.8, 0.8),
                 -uy * rnd(0.2, 1) * back + ny * rnd(-0.8, 0.8), 'core');

            var dustN = Math.random() < 0.5 ? 2 : 1;
            for (var d = 0; d < dustN; d++) {
                push(x + rnd(-3, 3), y + rnd(-3, 3),
                     -ux * rnd(0, 0.5) * back * 0.7 + nx * rnd(-1.5, 1.5),
                     -uy * rnd(0, 0.5) * back * 0.7 + ny * rnd(-1.5, 1.5), 'dust');
            }
            if (Math.random() < 0.09) {
                push(x, y, -ux * rnd(1.2, 2.6), -uy * rnd(1.2, 2.6), 'star');
            }
        }
    }

    function frame() {
        ctx.clearRect(0, 0, W, H);

        for (var i = parts.length - 1; i >= 0; i--) {
            var p = parts[i];
            p.life--;
            if (p.life <= 0) { parts.splice(i, 1); continue; }

            p.px = p.x; p.py = p.y;
            var cs = Math.cos(p.w), sn = Math.sin(p.w);   // rotate velocity â†’ curl
            var vx = p.vx * cs - p.vy * sn;
            p.vy = p.vx * sn + p.vy * cs;
            p.vx = vx;
            p.vx *= 0.968; p.vy *= 0.968;
            p.vy -= 0.014;                                 // slow drift upward
            p.x += p.vx; p.y += p.vy;

            var t = p.life / p.max;                        // 1 â†’ 0
            var a = Math.min(1, (1 - t) * 7) * t;          // quick fade in, long fade out
            var s = p.size * (0.55 + 0.45 * t);

            if (p.streak) {
                ctx.strokeStyle = rgba(p.rgb, a * 0.5);
                ctx.lineWidth = s * 0.4;
                ctx.beginPath();
                ctx.moveTo(p.px, p.py);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
            }
            ctx.globalAlpha = a;
            ctx.drawImage(p.img, p.x - s * 2, p.y - s * 2, s * 4, s * 4);
        }

        /* comet head: a hot core riding the cursor, fading out when it stops */
        if (headA > 0.01) {
            ctx.globalAlpha = headA;
            ctx.drawImage(GLOW[0], hx - 26, hy - 26, 52, 52);
            ctx.globalAlpha = headA * 0.9;
            ctx.drawImage(STAR[5], hx - 13, hy - 13, 26, 26);
            headA *= 0.9;
        }
        ctx.globalAlpha = 1;

        if (parts.length === 0 && ++idle > 60) { running = false; return; }
        requestAnimationFrame(frame);
    }

    document.addEventListener('mousemove', function (e) {
        var x = e.clientX - ox, y = e.clientY - oy;

        hx = x; hy = y; headA = 1; idle = 0;

        if (!primed) { primed = true; lx = x; ly = y; }
        var dx = x - lx, dy = y - ly;
        if (dx * dx + dy * dy >= 4) { emit(lx, ly, x, y); lx = x; ly = y; }

        if (!running) { running = true; requestAnimationFrame(frame); }
    }, { passive: true });
})();
