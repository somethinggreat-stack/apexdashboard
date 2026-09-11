{{--
    Sync now, without the page-reload jolt. The form still posts normally if
    JavaScript is unavailable; this just intercepts it, shows what is happening,
    and reports what came back. Pulling documents for several clients takes a
    few seconds, and a frozen button with no feedback reads as a broken button.

    Light to match the page, with a soft scrim behind it so the card still reads
    as a moment rather than another panel.
--}}
<div class="ghl-modal" id="ghlModal" hidden>
    <div class="ghl-modal-card" role="dialog" aria-modal="true" aria-labelledby="ghlModalTitle">
        <div class="ghl-card-inner">

            <div class="ghl-state" data-state="working">
                <div class="ghl-visual">
                    <span class="ghl-ring"></span>
                    <span class="ghl-ring ghl-ring-2"></span>
                    <span class="ghl-sweep"></span>
                    <span class="ghl-core">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/>
                            <path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>
                        </svg>
                    </span>
                </div>

                <h3 id="ghlModalTitle">Syncing with GoHighLevel</h3>
                <p class="ghl-step" id="ghlStep">Connecting…</p>

                <div class="ghl-dots" id="ghlDots">
                    <i></i><i></i><i></i><i></i><i></i>
                </div>
            </div>

            <div class="ghl-state" data-state="done" hidden>
                <div class="ghl-mark ok">
                    <svg viewBox="0 0 52 52">
                        <circle class="ghl-mark-ring" cx="26" cy="26" r="23"/>
                        <path class="ghl-mark-tick" d="M15 27 L23 34 L38 18"/>
                    </svg>
                </div>

                <h3>Sync complete</h3>

                <div class="ghl-figures">
                    <div><strong id="ghlImported" data-count="0">0</strong><span>New</span></div>
                    <div><strong id="ghlLinked" data-count="0">0</strong><span>Matched</span></div>
                    <div><strong id="ghlSkipped" data-count="0">0</strong><span>Already had</span></div>
                </div>

                <p class="ghl-note" id="ghlDoneNote"></p>
                <button type="button" class="ghl-btn" data-ghl-close>Done</button>
            </div>

            <div class="ghl-state" data-state="failed" hidden>
                <div class="ghl-mark bad">
                    <svg viewBox="0 0 52 52">
                        <circle class="ghl-mark-ring" cx="26" cy="26" r="23"/>
                        <path class="ghl-mark-tick" d="M18 18 L34 34 M34 18 L18 34"/>
                    </svg>
                </div>
                <h3>Sync could not finish</h3>
                <p class="ghl-note" id="ghlError"></p>
                <button type="button" class="ghl-btn ghl-btn-quiet" data-ghl-close>Close</button>
            </div>

        </div>
    </div>
</div>

@push('head')
<style>
    .ghl-modal {
        --ghl-a:#6366f1; --ghl-b:#a855f7;
        position:fixed; inset:0; z-index:9999;
        display:flex; align-items:center; justify-content:center; padding:20px;
        background:radial-gradient(120% 100% at 50% 0%, rgba(79,70,229,.16), rgba(17,24,39,.38));
        backdrop-filter:blur(5px) saturate(115%);
        animation:ghlFade .22s ease-out;
    }
    .ghl-modal[hidden] { display:none !important; }
    @keyframes ghlFade { from { opacity:0; } to { opacity:1; } }

    /* gradient hairline border, drawn as a padded wrapper */
    .ghl-modal-card {
        width:100%; max-width:420px; padding:1px; border-radius:22px;
        background:linear-gradient(150deg, rgba(99,102,241,.55), rgba(168,85,247,.3) 45%, rgba(226,228,240,.6) 75%);
        box-shadow:0 28px 64px -22px rgba(49,46,129,.42), 0 0 50px -20px rgba(129,140,248,.4);
        animation:ghlPop .32s cubic-bezier(.2,.9,.3,1.2);
    }
    @keyframes ghlPop { from { opacity:0; transform:translateY(14px) scale(.965); } to { opacity:1; transform:none; } }

    .ghl-card-inner {
        border-radius:21px; padding:34px 30px 28px; text-align:center;
        background:linear-gradient(180deg,#ffffff 0%, #fcfbff 100%);
        color:#111827;
    }
    .ghl-card-inner h3 { margin:0 0 7px; font-size:18px; font-weight:650; letter-spacing:-.01em; }
    .ghl-step, .ghl-note { margin:0; font-size:13px; color:#6b7280; line-height:1.55; }
    .ghl-note { margin-top:10px; }
    .ghl-step { transition:opacity .25s ease; }
    .ghl-step.is-swapping { opacity:0; }

    /* ---------- working: concentric rings, a sweeping arc, a breathing core ---------- */
    .ghl-visual { position:relative; width:96px; height:96px; margin:0 auto 22px; }
    .ghl-visual > * { position:absolute; inset:0; border-radius:50%; }

    .ghl-ring { border:1.5px solid rgba(99,102,241,.3); animation:ghlPulse 2.6s ease-out infinite; }
    .ghl-ring-2 { animation-delay:1.3s; }
    @keyframes ghlPulse {
        0%   { transform:scale(.62); opacity:0; }
        35%  { opacity:.9; }
        100% { transform:scale(1.12); opacity:0; }
    }

    .ghl-sweep {
        background:conic-gradient(from 0deg, transparent 0deg, transparent 250deg, var(--ghl-a) 320deg, var(--ghl-b) 360deg);
        -webkit-mask:radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 3px));
                mask:radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 3px));
        animation:ghlSpin 1.15s linear infinite;
    }
    @keyframes ghlSpin { to { transform:rotate(360deg); } }

    .ghl-core {
        inset:26px; display:grid; place-items:center; color:#fff;
        background:linear-gradient(140deg, var(--ghl-a), var(--ghl-b));
        box-shadow:0 8px 26px -6px rgba(129,140,248,.8);
        animation:ghlBreathe 2.1s ease-in-out infinite;
    }
    .ghl-core svg { width:21px; height:21px; }
    @keyframes ghlBreathe { 0%,100% { transform:scale(1); } 50% { transform:scale(1.07); } }

    /* five step dots that fill as the sync progresses */
    .ghl-dots { display:flex; gap:7px; justify-content:center; margin-top:20px; }
    .ghl-dots i {
        width:6px; height:6px; border-radius:50%; background:#e4e6f0;
        transition:background .35s ease, transform .35s ease, box-shadow .35s ease;
    }
    .ghl-dots i.on {
        background:linear-gradient(140deg, var(--ghl-a), var(--ghl-b));
        transform:scale(1.35); box-shadow:0 0 10px rgba(99,102,241,.55);
    }

    /* ---------- result marks ---------- */
    .ghl-mark { width:62px; height:62px; margin:0 auto 18px; }
    .ghl-mark svg { width:100%; height:100%; overflow:visible; }
    .ghl-mark-ring, .ghl-mark-tick { fill:none; stroke-linecap:round; stroke-linejoin:round; }
    .ghl-mark-ring { stroke-width:2; opacity:.35; stroke-dasharray:145; stroke-dashoffset:145; animation:ghlDraw .5s ease-out forwards; }
    .ghl-mark-tick { stroke-width:3.4; stroke-dasharray:60; stroke-dashoffset:60; animation:ghlDraw .42s .34s ease-out forwards; }
    @keyframes ghlDraw { to { stroke-dashoffset:0; } }
    .ghl-mark.ok  { color:#10b981; filter:drop-shadow(0 8px 20px rgba(16,185,129,.3)); }
    .ghl-mark.bad { color:#ef4444; filter:drop-shadow(0 8px 20px rgba(239,68,68,.28)); }
    .ghl-mark.ok .ghl-mark-ring, .ghl-mark.ok .ghl-mark-tick,
    .ghl-mark.bad .ghl-mark-ring, .ghl-mark.bad .ghl-mark-tick { stroke:currentColor; }

    /* ---------- figures ---------- */
    .ghl-figures { display:flex; gap:9px; margin:20px 0 2px; }
    .ghl-figures div {
        flex:1; padding:13px 6px 11px; border-radius:13px;
        background:linear-gradient(180deg,#f7f7fd,#fbfaff);
        border:1px solid #ecebf7;
        opacity:0; transform:translateY(8px);
        animation:ghlRise .4s ease-out forwards;
    }
    .ghl-figures div:nth-child(1) { animation-delay:.42s; }
    .ghl-figures div:nth-child(2) { animation-delay:.5s; }
    .ghl-figures div:nth-child(3) { animation-delay:.58s; }
    @keyframes ghlRise { to { opacity:1; transform:none; } }
    .ghl-figures strong {
        display:block; font-size:25px; font-weight:700; line-height:1.15;
        font-variant-numeric:tabular-nums;
        background:linear-gradient(140deg,#4f46e5,#a855f7); -webkit-background-clip:text; background-clip:text; color:transparent;
    }
    .ghl-figures span { font-size:10.5px; color:#8b90a3; text-transform:uppercase; letter-spacing:.07em; font-weight:600; }

    /* ---------- button ---------- */
    .ghl-btn {
        margin-top:20px; width:100%; padding:12px 16px; border:0; border-radius:12px;
        background:linear-gradient(140deg, var(--ghl-a), #6366f1 55%, var(--ghl-b));
        color:#fff; font-size:14px; font-weight:650; cursor:pointer;
        box-shadow:0 10px 24px -10px rgba(129,140,248,.9);
        transition:transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }
    .ghl-btn:hover { transform:translateY(-1px); filter:brightness(1.07); box-shadow:0 14px 30px -10px rgba(129,140,248,1); }
    .ghl-btn:active { transform:translateY(0); }
    .ghl-btn-quiet {
        background:#f4f5f9; color:#4b5563; box-shadow:none; border:1px solid #e6e8f0;
    }
    .ghl-btn-quiet:hover { background:#eceef5; box-shadow:none; filter:none; }

    @media (max-width:480px) {
        .ghl-card-inner { padding:28px 20px 22px; }
        .ghl-figures { gap:7px; }
        .ghl-figures strong { font-size:21px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .ghl-modal, .ghl-modal-card, .ghl-ring, .ghl-sweep, .ghl-core,
        .ghl-mark-ring, .ghl-mark-tick, .ghl-figures div { animation:none !important; }
        .ghl-mark-ring, .ghl-mark-tick { stroke-dashoffset:0; }
        .ghl-figures div { opacity:1; transform:none; }
        .ghl-sweep { border:2px solid var(--ghl-a); background:none; -webkit-mask:none; mask:none; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var form  = document.querySelector('[data-ghl-sync]');
    var modal = document.getElementById('ghlModal');
    if (!form || !modal || !window.fetch) return;   // no JS support: plain form post still works

    var stepEl = document.getElementById('ghlStep');
    var dots   = Array.prototype.slice.call(document.querySelectorAll('#ghlDots i'));
    var timers = [];

    var STEPS = [
        'Connecting to GoHighLevel…',
        'Reading onboarding submissions…',
        'Checking who we already have…',
        'Downloading documents…',
        'Finishing up…'
    ];

    function show(state) {
        modal.hidden = false;
        modal.querySelectorAll('.ghl-state').forEach(function (el) {
            el.hidden = el.getAttribute('data-state') !== state;
        });
    }

    function clearTimers() { timers.forEach(clearTimeout); timers = []; }

    function setStep(index) {
        stepEl.classList.add('is-swapping');
        setTimeout(function () {
            stepEl.textContent = STEPS[index];
            stepEl.classList.remove('is-swapping');
        }, 220);
        dots.forEach(function (d, i) { d.classList.toggle('on', i <= index); });
    }

    function runSteps() {
        dots.forEach(function (d) { d.classList.remove('on'); });
        setStep(0);
        STEPS.forEach(function (_, i) {
            if (i === 0) return;
            timers.push(setTimeout(function () { setStep(i); }, i * 1500));
        });
    }

    // numbers that count up rather than snapping into place
    function countTo(el, target) {
        target = Number(target) || 0;
        if (target === 0) { el.textContent = '0'; return; }
        var start = null, duration = 650;
        function frame(now) {
            if (start === null) start = now;
            var p = Math.min((now - start) / duration, 1);
            el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    modal.querySelectorAll('[data-ghl-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            modal.hidden = true;
            window.location.reload();       // pick up whatever just landed
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var button = form.querySelector('button');
        if (button) button.disabled = true;

        show('working');
        runSteps();

        var started = Date.now();

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new FormData(form),
            credentials: 'same-origin'
        })
        .then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        })
        .then(function (result) {
            // A sync that returns in 300ms looks like nothing happened, so let the
            // animation land before showing the result.
            var wait = Math.max(0, 1100 - (Date.now() - started));
            setTimeout(function () { render(result); }, wait);
        })
        .catch(function () {
            clearTimers();
            document.getElementById('ghlError').textContent =
                'The sync did not respond. It may still be running — close this and refresh in a moment.';
            show('failed');
        })
        .finally(function () {
            if (button) button.disabled = false;
        });
    });

    function render(result) {
        clearTimers();
        var d = result.data || {};

        if (!result.ok || d.ok === false) {
            document.getElementById('ghlError').textContent =
                d.message || 'Something went wrong talking to GoHighLevel. Please try again.';
            show('failed');
            return;
        }

        show('done');
        countTo(document.getElementById('ghlImported'), d.imported);
        countTo(document.getElementById('ghlLinked'),   d.linked);
        countTo(document.getElementById('ghlSkipped'),  d.skipped);

        var note = '';
        if (!d.imported && !d.linked) {
            note = 'No new onboarding submissions since the last sync.';
        } else if (d.linked > 0) {
            note = 'Matched clients were already in the system — their missing details were filled in rather than duplicated.';
        } else {
            note = d.imported + (d.imported === 1 ? ' client is' : ' clients are') + ' ready for review below.';
        }
        // Deleted clients are not counted above, so say why the totals are short.
        if (d.removed) {
            note += ' ' + d.removed + (d.removed === 1 ? ' deleted client was' : ' deleted clients were')
                + ' left alone rather than brought back.';
        }
        if (d.failed) {
            note = d.failed + ' submission(s) could not be pulled. They will be retried on the next sync.';
        }
        document.getElementById('ghlDoneNote').textContent = note;
    }
})();
</script>
@endpush
