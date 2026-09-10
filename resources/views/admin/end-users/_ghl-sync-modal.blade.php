{{--
    Sync now, without the page-reload jolt. The form still posts normally if
    JavaScript is unavailable; this just intercepts it, shows what is happening,
    and reports what came back. Pulling documents for several clients takes a
    few seconds, and a frozen button with no feedback reads as a broken button.
--}}
<div class="ghl-modal" id="ghlModal" hidden>
    <div class="ghl-modal-card" role="dialog" aria-modal="true" aria-labelledby="ghlModalTitle">

        <div class="ghl-state" data-state="working">
            <div class="ghl-orbit"><span></span><span></span><span></span></div>
            <h3 id="ghlModalTitle">Syncing with GoHighLevel</h3>
            <p class="ghl-step" id="ghlStep">Connecting…</p>
            <div class="ghl-bar"><i></i></div>
        </div>

        <div class="ghl-state" data-state="done" hidden>
            <div class="ghl-mark ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3>Sync complete</h3>
            <div class="ghl-figures">
                <div><strong id="ghlImported">0</strong><span>New</span></div>
                <div><strong id="ghlLinked">0</strong><span>Matched</span></div>
                <div><strong id="ghlSkipped">0</strong><span>Already had</span></div>
            </div>
            <p class="ghl-note" id="ghlDoneNote"></p>
            <button type="button" class="ghl-btn" data-ghl-close>Done</button>
        </div>

        <div class="ghl-state" data-state="failed" hidden>
            <div class="ghl-mark bad">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
            <h3>Sync could not finish</h3>
            <p class="ghl-note" id="ghlError"></p>
            <button type="button" class="ghl-btn" data-ghl-close>Close</button>
        </div>

    </div>
</div>

@push('head')
<style>
    .ghl-modal {
        position:fixed; inset:0; z-index:9999;
        display:flex; align-items:center; justify-content:center; padding:20px;
        background:rgba(15,17,26,.55); backdrop-filter:blur(3px);
    }
    .ghl-modal[hidden] { display:none !important; }
    .ghl-modal-card {
        width:100%; max-width:400px; border-radius:16px; padding:32px 28px;
        background:#fff; color:#111827; text-align:center;
        box-shadow:0 24px 60px rgba(0,0,0,.28);
        animation:ghlIn .18s ease-out;
    }
    @keyframes ghlIn { from { opacity:0; transform:translateY(8px) scale(.98); } to { opacity:1; transform:none; } }
    .ghl-modal-card h3 { margin:0 0 6px; font-size:17px; font-weight:700; }
    .ghl-step, .ghl-note { margin:0; font-size:13px; color:#6b7280; line-height:1.5; }
    .ghl-note { margin-top:8px; }

    /* three dots orbiting a ring while we wait */
    .ghl-orbit { width:56px; height:56px; margin:0 auto 18px; position:relative; }
    .ghl-orbit span {
        position:absolute; top:50%; left:50%; width:9px; height:9px; margin:-4.5px;
        border-radius:50%; background:#6366f1;
        animation:ghlOrbit 1.1s linear infinite;
    }
    .ghl-orbit span:nth-child(2) { animation-delay:-.36s; background:#8b5cf6; }
    .ghl-orbit span:nth-child(3) { animation-delay:-.72s; background:#a78bfa; }
    @keyframes ghlOrbit {
        from { transform:rotate(0deg) translateX(20px); }
        to   { transform:rotate(360deg) translateX(20px); }
    }

    .ghl-bar { margin-top:18px; height:3px; border-radius:3px; background:#eef0f5; overflow:hidden; }
    .ghl-bar i { display:block; height:100%; width:35%; border-radius:3px; background:#6366f1; animation:ghlSlide 1.3s ease-in-out infinite; }
    @keyframes ghlSlide { 0% { margin-left:-35%; } 100% { margin-left:100%; } }

    .ghl-mark { width:52px; height:52px; margin:0 auto 16px; border-radius:50%; display:grid; place-items:center; }
    .ghl-mark svg { width:26px; height:26px; }
    .ghl-mark.ok  { background:#d1fae5; color:#047857; }
    .ghl-mark.bad { background:#fee2e2; color:#b91c1c; }

    .ghl-figures { display:flex; gap:10px; margin:18px 0 4px; }
    .ghl-figures div { flex:1; padding:12px 6px; border-radius:10px; background:#f5f6fa; }
    .ghl-figures strong { display:block; font-size:22px; font-weight:700; line-height:1.1; }
    .ghl-figures span { font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; }

    .ghl-btn {
        margin-top:18px; width:100%; padding:10px 16px; border:0; border-radius:9px;
        background:#4f46e5; color:#fff; font-size:14px; font-weight:600; cursor:pointer;
    }
    .ghl-btn:hover { background:#4338ca; }

    @media (prefers-color-scheme: dark) {
        .ghl-modal-card { background:#1c1f2b; color:#f3f4f6; }
        .ghl-figures div { background:#252938; }
        .ghl-bar { background:#252938; }
        .ghl-step, .ghl-note, .ghl-figures span { color:#9aa1b1; }
    }
    body.dark .ghl-modal-card, html.dark .ghl-modal-card { background:#1c1f2b; color:#f3f4f6; }
    body.dark .ghl-figures div, html.dark .ghl-figures div { background:#252938; }
    body.dark .ghl-bar, html.dark .ghl-bar { background:#252938; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var form = document.querySelector('[data-ghl-sync]');
    var modal = document.getElementById('ghlModal');
    if (!form || !modal || !window.fetch) return;   // no JS support: plain form post still works

    var stepEl = document.getElementById('ghlStep');
    var timers = [];

    function show(state) {
        modal.hidden = false;
        modal.querySelectorAll('.ghl-state').forEach(function (el) {
            el.hidden = el.getAttribute('data-state') !== state;
        });
    }

    function clearTimers() {
        timers.forEach(clearTimeout);
        timers = [];
    }

    function runSteps() {
        var steps = [
            'Connecting to GoHighLevel…',
            'Reading onboarding submissions…',
            'Checking who we already have…',
            'Downloading documents…',
            'Almost there…'
        ];
        steps.forEach(function (text, i) {
            timers.push(setTimeout(function () { stepEl.textContent = text; }, i * 1600));
        });
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

        stepEl.textContent = 'Connecting…';
        show('working');
        runSteps();

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
            clearTimers();
            var d = result.data || {};

            if (!result.ok || d.ok === false) {
                document.getElementById('ghlError').textContent =
                    d.message || 'Something went wrong talking to GoHighLevel. Please try again.';
                show('failed');
                return;
            }

            document.getElementById('ghlImported').textContent = d.imported || 0;
            document.getElementById('ghlLinked').textContent   = d.linked   || 0;
            document.getElementById('ghlSkipped').textContent  = d.skipped  || 0;

            var note = '';
            if ((d.imported || 0) === 0 && (d.linked || 0) === 0) {
                note = 'No new onboarding submissions since the last sync.';
            } else if ((d.linked || 0) > 0) {
                note = 'Matched clients were already in the system — their missing details were filled in rather than duplicated.';
            }
            if (d.failed) {
                note = d.failed + ' submission(s) could not be pulled. They will be retried on the next sync.';
            }
            document.getElementById('ghlDoneNote').textContent = note;

            show('done');
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
})();
</script>
@endpush
