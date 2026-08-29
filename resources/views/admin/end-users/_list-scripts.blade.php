{{--
    Inline round/status editing, quick-log, quick-note and Move-to-Errors prompt.
    Shared by the original list view and the super-admin pro console so the
    behaviour can never drift between them.
--}}
@php
    $statusOptions = ['active','paused','graduated','cancelled'];
    $roundOptions  = App\Models\EndUser::ROUND_OPTIONS;
    // Canonical (first) step per week for the quick-log modal — derived straight
    // from the backend's step-by-week map so it can never drift from what the
    // store() validator accepts. 20-day week 2 = tu_ex_call_followups, NOT the
    // 30-day value; hardcoding it wrong is what produced "step_types.0 invalid".
    $weekStepCanonical = [];
    foreach ([30, 20] as $cycleDays) {
        foreach (App\Models\ProcessStep::stepTypesByWeek($cycleDays) as $w => $steps) {
            $weekStepCanonical[$cycleDays][$w] = array_key_first($steps);
        }
    }
@endphp

@push('scripts')
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var STATUSES = @json($statusOptions);
    var ROUNDS   = @json($roundOptions);
    var WEEK_STEPS = @json($weekStepCanonical);
    var updateUrlTpl = "{{ url('admin/end-users') }}/__ID__";

    function inlineStop(e) { e.preventDefault(); e.stopPropagation(); }

    /* --------- generic field-edit popup (no more inline editing) --------- */
    var feModal = document.getElementById('fieldEditModal');
    var feId = null, feField = null, feExtra = {};
    window.openFieldEdit = function (opts) {
        if (!feModal) return;
        feId = opts.id; feField = opts.field; feExtra = opts.extra || {};
        document.getElementById('feTitle').textContent = opts.title || 'Edit';
        document.getElementById('feWho').textContent = opts.name || '';
        document.getElementById('feLabel').textContent = opts.label || 'Value';
        var dateEl = document.getElementById('feDate');
        var selEl  = document.getElementById('feSelect');
        var hintEl = document.getElementById('feHint');
        dateEl.style.display = 'none'; selEl.style.display = 'none'; hintEl.style.display = 'none';
        if (opts.kind === 'select') {
            selEl.innerHTML = (opts.options || []).map(function (o) {
                return '<option value="'+o.value+'"'+(o.value===opts.value?' selected':'')+'>'+o.label+'</option>';
            }).join('');
            selEl.style.display = '';
        } else {
            dateEl.value = opts.value || '';
            dateEl.style.display = '';
            if (opts.hint) { hintEl.textContent = opts.hint; hintEl.style.display = ''; }
        }
        openModal('fieldEditModal');
    };
    if (feModal) {
        document.getElementById('feSave').addEventListener('click', function () {
            if (!feId || !feField) return;
            var selEl = document.getElementById('feSelect');
            var val = selEl.style.display !== 'none' ? selEl.value : document.getElementById('feDate').value;
            var fd = new FormData();
            fd.append('_method', 'PUT');
            fd.append('_token', csrf);
            fd.append(feField, val);
            Object.keys(feExtra).forEach(function (k) { fd.append(k, feExtra[k]); });
            fetch(updateUrlTpl.replace('__ID__', feId), {
                method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function (r) { if (r.ok) window.location.reload(); else alert('Could not save.'); });
        });
    }

    /* --------- status → popup --------- */
    document.querySelectorAll('.inline-edit-status').forEach(function (el) {
        el.addEventListener('click', function (e) {
            inlineStop(e);
            openFieldEdit({
                id: el.dataset.id, name: el.dataset.name || '',
                title: 'Change Status', label: 'Status', kind: 'select',
                field: 'status', value: el.dataset.current,
                options: STATUSES.map(function (s) { return { value: s, label: s.charAt(0).toUpperCase() + s.slice(1) }; })
            });
        });
    });

    /* --------- round edit — proper popup (shared with the client page data) --------- */
    var rpModal = document.getElementById('roundPickerModal');
    var rpEditingId = null;
    document.querySelectorAll('.inline-edit-round').forEach(function (el) {
        el.addEventListener('click', function (e) {
            inlineStop(e);
            if (!rpModal) return;
            var current = [];
            try { current = JSON.parse(el.dataset.current || '[]'); } catch (_) {}
            rpEditingId = el.dataset.id;
            document.getElementById('rpName').textContent = el.dataset.name || 'client';
            rpModal.querySelectorAll('.rp-pill').forEach(function (p) {
                p.classList.toggle('on', current.indexOf(p.dataset.round) !== -1);
            });
            openModal('roundPickerModal');
        });
    });
    if (rpModal) {
        rpModal.querySelectorAll('.rp-pill').forEach(function (p) {
            p.addEventListener('click', function () { p.classList.toggle('on'); });
        });
        document.getElementById('rpSave').addEventListener('click', function () {
            if (!rpEditingId) return;
            var picked = Array.from(rpModal.querySelectorAll('.rp-pill.on')).map(function (p) { return p.dataset.round; });
            var fd = new FormData();
            fd.append('_method', 'PUT');
            fd.append('_token', csrf);
            fd.append('rounds_present', '1');
            picked.forEach(function (r) { fd.append('rounds[]', r); });
            fetch(updateUrlTpl.replace('__ID__', rpEditingId), {
                method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function (r) {
                if (r.ok) window.location.reload();
                else alert('Could not save rounds.');
            });
        });
    }

    /* --------- quick-log modal --------- */
    // Labels for the closeout steps (past-due-only) so the hint reads well.
    var QL_LABELS = { pull_latest_report: 'Pull Latest Report', record_deletions: 'Record Deletions / Update Deletions' };
    var qlPresetWeek = null, qlPresetSteps = [], qlStepsMap = {};

    function qlHint(week) {
        var hint = document.getElementById('quickLogTypeHint');
        if (!hint) return;
        if (String(week) === String(qlPresetWeek) && qlPresetSteps.length) {
            hint.textContent = 'Will log: ' + qlPresetSteps.map(function (s) { return QL_LABELS[s] || s; }).join(', ') + '.';
        } else {
            hint.textContent = 'A canonical step will be created for the chosen week. Open the client to add additional step types.';
        }
    }

    // The step types the modal will submit for the chosen week: the preset
    // (closeout) steps for the targeted week, otherwise that week's canonical step.
    function qlStepsFor(week) {
        if (String(week) === String(qlPresetWeek) && qlPresetSteps.length) {
            return qlPresetSteps.slice();
        }
        var c = qlStepsMap[week];
        return c ? [c] : [];
    }

    window.openQuickLog = function (euId, name, targetWeek, currentRound, cycleDays, presetSteps) {
        cycleDays = (parseInt(cycleDays, 10) === 20) ? 20 : 30;
        var weekCount = (cycleDays === 20) ? 3 : 4;               // 20-day → 3 weeks
        qlStepsMap = WEEK_STEPS[cycleDays] || WEEK_STEPS[30];

        document.getElementById('quickLogEndUserId').value = euId;
        document.getElementById('quickLogName').textContent = name;
        var weekSel = document.getElementById('quickLogWeek');
        var roundSel = document.getElementById('quickLogRound');

        weekSel.innerHTML = '';
        for (var w = 1; w <= weekCount; w++) {
            var o = document.createElement('option');
            o.value = w; o.textContent = 'Week ' + w;
            weekSel.appendChild(o);
        }

        targetWeek = Math.min(parseInt(targetWeek, 10) || 1, weekCount);
        weekSel.value = targetWeek;
        roundSel.value = Math.max(1, currentRound || 1);

        qlPresetWeek  = targetWeek;
        qlPresetSteps = Array.isArray(presetSteps) ? presetSteps : [];
        qlHint(weekSel.value);
        weekSel.onchange = function () { qlHint(weekSel.value); };
        openModal('quickLogModal');
    };

    // Build the step_types[] the store expects from whatever the modal resolves to.
    (function () {
        var qlForm = document.querySelector('#quickLogModal form');
        if (!qlForm) return;
        qlForm.addEventListener('submit', function () {
            qlForm.querySelectorAll('input[data-ql-step]').forEach(function (n) { n.remove(); });
            var single = document.getElementById('quickLogStepType');
            if (single) single.value = '';   // use step_types[] instead
            var steps = qlStepsFor(document.getElementById('quickLogWeek').value);
            if (!steps.length) { steps = ['ex_tu_eq_letters_generated']; }
            steps.forEach(function (s) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = 'step_types[]'; i.value = s; i.setAttribute('data-ql-step', '1');
                qlForm.appendChild(i);
            });
        });
    })();

    /* --------- move to errors (prompts for the error) --------- */
    window.moveToErrors = function (btn, name) {
        var note = prompt('Move ' + name + ' to Errors.\n\nWhat is the error? (shown on their line so it can be fixed):', '');
        if (note === null) return; // cancelled
        var form = btn.closest('form');
        form.querySelector('input[name="note"]').value = note;
        form.submit();
    };

    window.openQuickNote = function (euId, name) {
        document.getElementById('quickNoteEndUserId').value = euId;
        document.getElementById('quickNoteName').textContent = name;
        openModal('quickNoteModal');
    };

    /* --------- inline date edits: Round Started + Next Round Date --------- */
    /* --------- date edits → popup --------- */
    document.querySelectorAll('.inline-edit-round-started').forEach(function (el) {
        el.addEventListener('click', function (e) {
            inlineStop(e);
            openFieldEdit({
                id: el.dataset.id, name: el.dataset.name || '',
                title: el.dataset.title || 'Edit round start date',
                label: 'Round start date', kind: 'date',
                field: 'round_started', value: el.dataset.current
            });
        });
    });
    document.querySelectorAll('.inline-edit-next').forEach(function (el) {
        el.addEventListener('click', function (e) {
            inlineStop(e);
            openFieldEdit({
                id: el.dataset.id, name: el.dataset.name || '',
                title: 'Edit next round date',
                label: 'Next round date', kind: 'date',
                field: 'next_round_override', value: el.dataset.current,
                hint: 'Leave blank to auto-calculate (one cycle after the current round start).'
            });
        });
    });

    /* --------- Hold/Pause or Move to New Clients, with a reason --------- */
    window.openMoveReason = function (euId, name, kind) {
        var form = document.getElementById('moveReasonForm');
        if (!form) return;
        form.reset();
        var isHold = kind === 'hold';
        form.action = updateUrlTpl.replace('__ID__', euId) + (isHold ? '/hold' : '/to-new-clients');
        document.getElementById('moveReasonTitle').textContent = isHold ? 'Hold / Pause' : 'Move to New Clients';
        document.getElementById('moveReasonWho').textContent =
            (isHold ? 'Pausing ' : 'Moving ') + name + (isHold ? ' — why?' : ' back to New Clients — why?');
        document.getElementById('moveReasonSubmit').textContent = isHold ? 'Hold / Pause' : 'Move to New Clients';
        openModal('moveReasonModal');
    };

    /* --------- move a Clients-list client to Round Errors (type + reason) --------- */
    window.openRoundError = function (euId, name) {
        var form = document.getElementById('roundErrorForm');
        if (!form) return;
        form.reset();
        form.action = updateUrlTpl.replace('__ID__', euId) + '/to-round-error';
        document.getElementById('roundErrorWho').textContent = 'Moving ' + name + ' to Round Errors.';
        openModal('roundErrorModal');
    };
})();
</script>
@endpush
