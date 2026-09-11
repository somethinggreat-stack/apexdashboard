{{--
    The DisputeFox push, in a dialog rather than a browser alert.

    Creating a client file in someone else's live system deserves a confirmation
    that names who is about to be sent, and the send itself takes a while — each
    client carries three base64 documents — so it shows progress rather than a
    frozen button. Reuses the sync dialog's styling; only the pieces unique to
    this flow are defined here.
--}}
<div class="ghl-modal" id="dfModal" hidden>
    <div class="ghl-modal-card" role="dialog" aria-modal="true" aria-labelledby="dfModalTitle">
        <div class="ghl-card-inner">

            {{-- 1. confirm --}}
            <div class="ghl-state" data-state="confirm">
                <div class="ghl-mark ask">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                    </svg>
                </div>
                <h3 id="dfModalTitle">Send to DisputeFox?</h3>
                <p class="ghl-note" id="dfConfirmLead"></p>

                <ul class="df-names" id="dfNames"></ul>

                <p class="ghl-note df-caution">
                    This creates their client file in DisputeFox with their details and documents.
                    It cannot be undone from here — a file sent by mistake has to be deleted in
                    DisputeFox.
                </p>

                <div class="df-btn-row">
                    <button type="button" class="ghl-btn ghl-btn-quiet" data-df-cancel>Cancel</button>
                    <button type="button" class="ghl-btn" data-df-go>Yes, send</button>
                </div>
            </div>

            {{-- 2. working --}}
            <div class="ghl-state" data-state="working" hidden>
                <div class="ghl-visual">
                    <span class="ghl-ring"></span>
                    <span class="ghl-ring ghl-ring-2"></span>
                    <span class="ghl-sweep"></span>
                    <span class="ghl-core">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                        </svg>
                    </span>
                </div>
                <h3>Sending to DisputeFox</h3>
                <p class="ghl-step" id="dfStep">Preparing the file…</p>
                <div class="ghl-dots" id="dfDots"><i></i><i></i><i></i><i></i></div>
            </div>

            {{-- 3. done --}}
            <div class="ghl-state" data-state="done" hidden>
                <div class="ghl-mark ok">
                    <svg viewBox="0 0 52 52">
                        <circle class="ghl-mark-ring" cx="26" cy="26" r="23"/>
                        <path class="ghl-mark-tick" d="M15 27 L23 34 L38 18"/>
                    </svg>
                </div>
                <h3 id="dfDoneTitle">Sent to DisputeFox</h3>

                <div class="ghl-figures">
                    <div><strong id="dfSent">0</strong><span>Sent</span></div>
                    <div><strong id="dfFailed">0</strong><span>Failed</span></div>
                </div>

                <ul class="df-lines" id="dfLines"></ul>
                <button type="button" class="ghl-btn" data-df-close>Done</button>
            </div>

            {{-- 4. failed --}}
            <div class="ghl-state" data-state="failed" hidden>
                <div class="ghl-mark bad">
                    <svg viewBox="0 0 52 52">
                        <circle class="ghl-mark-ring" cx="26" cy="26" r="23"/>
                        <path class="ghl-mark-tick" d="M18 18 L34 34 M34 18 L18 34"/>
                    </svg>
                </div>
                <h3>Nothing was sent</h3>
                <p class="ghl-note" id="dfError"></p>
                <ul class="df-lines" id="dfErrorLines"></ul>
                <button type="button" class="ghl-btn ghl-btn-quiet" data-df-close>Close</button>
            </div>

        </div>
    </div>
</div>

@push('head')
<style>
    .ghl-mark.ask {
        display:grid; place-items:center; color:#fff;
        background:linear-gradient(140deg,#6366f1,#a855f7);
        border-radius:50%; filter:drop-shadow(0 8px 20px rgba(99,102,241,.35));
    }
    .ghl-mark.ask svg { width:24px; height:24px; }

    .df-names {
        list-style:none; margin:14px 0 0; padding:12px 14px; text-align:left;
        max-height:190px; overflow-y:auto;
        background:#f7f7fd; border:1px solid #ecebf7; border-radius:12px;
    }
    .df-names li {
        font-size:13px; font-weight:600; color:#111827; padding:4px 0;
        display:flex; align-items:center; gap:8px;
    }
    .df-names li::before {
        content:''; width:6px; height:6px; border-radius:50%; flex:none;
        background:linear-gradient(140deg,#6366f1,#a855f7);
    }

    .df-caution { margin-top:12px; font-size:12.5px; }

    .df-btn-row { display:flex; gap:10px; margin-top:18px; }
    .df-btn-row .ghl-btn { margin-top:0; }

    .df-lines {
        list-style:none; margin:14px 0 0; padding:0; text-align:left;
        max-height:170px; overflow-y:auto;
    }
    .df-lines li {
        font-size:12px; line-height:1.5; color:#4b5563; padding:6px 10px;
        border-radius:8px; background:#f7f7fd; margin-bottom:5px;
    }
    .df-lines li.bad { background:#fef2f2; color:#b91c1c; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var form  = document.querySelector('[data-df-push]');
    var modal = document.getElementById('dfModal');
    if (!form || !modal || !window.fetch) return;   // without JS the plain post still works

    var stepEl = document.getElementById('dfStep');
    var dots   = Array.prototype.slice.call(modal.querySelectorAll('#dfDots i'));
    var timers = [];

    var STEPS = [
        'Preparing the file…',
        'Packing up their documents…',
        'Creating the client in DisputeFox…',
        'Waiting for DisputeFox to confirm…'
    ];

    function show(state) {
        modal.hidden = false;
        modal.querySelectorAll('.ghl-state').forEach(function (el) {
            el.hidden = el.getAttribute('data-state') !== state;
        });
    }

    function clearTimers() { timers.forEach(clearTimeout); timers = []; }

    function runSteps() {
        dots.forEach(function (d) { d.classList.remove('on'); });
        stepEl.textContent = STEPS[0];
        dots[0] && dots[0].classList.add('on');
        STEPS.forEach(function (text, i) {
            if (i === 0) return;
            timers.push(setTimeout(function () {
                stepEl.textContent = text;
                dots.forEach(function (d, j) { d.classList.toggle('on', j <= i); });
            }, i * 1800));
        });
    }

    function chosen() {
        return Array.prototype.slice.call(form.querySelectorAll('.df-tick:checked'));
    }

    function nameOf(tick) {
        var row = tick.closest('tr');
        var link = row ? row.querySelector('.ghl-person-name') : null;
        return link ? link.textContent.trim() : 'this client';
    }

    // ---- step 1: confirm, by name ----
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var picked = chosen();
        if (!picked.length) return;

        var names = picked.map(nameOf);
        document.getElementById('dfConfirmLead').textContent =
            names.length === 1
                ? 'One client is about to be created in DisputeFox:'
                : names.length + ' clients are about to be created in DisputeFox:';

        var list = document.getElementById('dfNames');
        list.innerHTML = '';
        names.forEach(function (n) {
            var li = document.createElement('li');
            li.textContent = n;
            list.appendChild(li);
        });

        show('confirm');
    });

    modal.querySelectorAll('[data-df-cancel]').forEach(function (b) {
        b.addEventListener('click', function () { modal.hidden = true; });
    });

    modal.querySelectorAll('[data-df-close]').forEach(function (b) {
        b.addEventListener('click', function () {
            modal.hidden = true;
            window.location.reload();
        });
    });

    // ---- step 2: send ----
    modal.querySelector('[data-df-go]').addEventListener('click', function () {
        var picked = chosen();
        if (!picked.length) { modal.hidden = true; return; }

        show('working');
        runSteps();

        var body = new FormData();
        body.append('_token', form.querySelector('input[name="_token"]').value);
        picked.forEach(function (t) { body.append('end_user_ids[]', t.value); });

        var started = Date.now();

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: body,
            credentials: 'same-origin'
        })
        .then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        })
        .then(function (result) {
            // Let the animation land rather than flashing past on a quick send.
            var wait = Math.max(0, 1200 - (Date.now() - started));
            setTimeout(function () { render(result); }, wait);
        })
        .catch(function () {
            clearTimers();
            document.getElementById('dfError').textContent =
                'The push did not respond. Check DisputeFox before trying again — the client may still have been created.';
            document.getElementById('dfErrorLines').innerHTML = '';
            show('failed');
        });
    });

    function fillLines(el, lines) {
        el.innerHTML = '';
        (lines || []).forEach(function (line) {
            var li = document.createElement('li');
            li.textContent = line;
            if (/fail|reject|could not|not sent|unexpected/i.test(line)) li.className = 'bad';
            el.appendChild(li);
        });
    }

    function render(result) {
        clearTimers();
        var d = result.data || {};
        var sent = d.sent || 0;

        if (!sent) {
            document.getElementById('dfError').textContent = d.message || 'DisputeFox did not accept the submission.';
            fillLines(document.getElementById('dfErrorLines'), d.lines);
            show('failed');
            return;
        }

        document.getElementById('dfDoneTitle').textContent =
            sent === 1 ? 'Client sent to DisputeFox' : sent + ' clients sent to DisputeFox';
        document.getElementById('dfSent').textContent = sent;
        document.getElementById('dfFailed').textContent = d.failed || 0;
        fillLines(document.getElementById('dfLines'), d.lines);
        show('done');
    }
})();
</script>
@endpush
