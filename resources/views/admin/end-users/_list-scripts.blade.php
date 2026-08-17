{{--
    Inline round/status editing, quick-log, quick-note and Move-to-Errors prompt.
    Shared by the original list view and the super-admin pro console so the
    behaviour can never drift between them.
--}}
@php
    $statusOptions = ['active','paused','graduated','cancelled'];
    $roundOptions  = App\Models\EndUser::ROUND_OPTIONS;
    // Canonical first step per week, keyed by round cycle (30-day = 4 weeks,
    // 20-day = 3 weeks). Used to pre-fill the quick-log step type.
    $weekStepCanonical = [
        30 => [
            1 => 'ex_tu_eq_letters_generated',
            2 => 'tu_ex_call_followups',
            3 => 'aggressive_bureau_followup',
            4 => 'pull_latest_report',
        ],
        20 => [
            1 => 'ex_tu_eq_letters_generated',
            2 => 'cfpb_3b_and_innovis',
            3 => 'aggressive_bureau_followup',
        ],
    ];
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

    /* --------- inline status edit --------- */
    document.querySelectorAll('.inline-edit-status').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (el.classList.contains('editing')) return;
            inlineStop(e);
            var current = el.dataset.current;
            el.classList.add('editing');
            el.innerHTML =
                '<select>' + STATUSES.map(function (s) {
                    return '<option value="'+s+'"'+(s===current?' selected':'')+'>'+s+'</option>';
                }).join('') + '</select>' +
                '<button class="inline-save" type="button">Save</button>' +
                '<button class="inline-cancel" type="button">×</button>';

            var sel = el.querySelector('select');
            sel.addEventListener('click', inlineStop);
            sel.focus();

            el.querySelector('.inline-cancel').addEventListener('click', function (e2) {
                inlineStop(e2); window.location.reload();
            });
            el.querySelector('.inline-save').addEventListener('click', function (e2) {
                inlineStop(e2);
                var newStatus = sel.value;
                var fd = new FormData();
                fd.append('_method', 'PUT');
                fd.append('_token', csrf);
                fd.append('status', newStatus);
                fetch(updateUrlTpl.replace('__ID__', el.dataset.id), {
                    method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function (r) {
                    if (r.ok) window.location.reload();
                    else alert('Could not save status.');
                });
            });
        });
    });

    /* --------- inline round edit --------- */
    document.querySelectorAll('.inline-edit-round').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (el.classList.contains('editing')) return;
            inlineStop(e);
            var current = [];
            try { current = JSON.parse(el.dataset.current || '[]'); } catch (_) {}
            el.classList.add('editing');
            el.innerHTML =
                '<select multiple size="3" style="min-width:140px;">' +
                ROUNDS.map(function (r) {
                    var sel = current.indexOf(r) !== -1 ? ' selected' : '';
                    return '<option value="'+r+'"'+sel+'>'+r+'</option>';
                }).join('') + '</select>' +
                '<button class="inline-save" type="button">Save</button>' +
                '<button class="inline-cancel" type="button">×</button>';

            var sel = el.querySelector('select');
            sel.addEventListener('click', inlineStop);
            sel.focus();

            el.querySelector('.inline-cancel').addEventListener('click', function (e2) {
                inlineStop(e2); window.location.reload();
            });
            el.querySelector('.inline-save').addEventListener('click', function (e2) {
                inlineStop(e2);
                var picked = Array.from(sel.selectedOptions).map(function (o) { return o.value; });
                var fd = new FormData();
                fd.append('_method', 'PUT');
                fd.append('_token', csrf);
                fd.append('rounds_present', '1');
                picked.forEach(function (r) { fd.append('rounds[]', r); });
                fetch(updateUrlTpl.replace('__ID__', el.dataset.id), {
                    method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function (r) {
                    if (r.ok) window.location.reload();
                    else alert('Could not save rounds.');
                });
            });
        });
    });

    /* --------- quick-log modal --------- */
    window.openQuickLog = function (euId, name, missingWeek, currentRound, cycleDays) {
        cycleDays = (parseInt(cycleDays, 10) === 20) ? 20 : 30;
        var weekCount = (cycleDays === 20) ? 3 : 4;               // 20-day → 3 weeks
        var stepsMap  = WEEK_STEPS[cycleDays] || WEEK_STEPS[30];

        document.getElementById('quickLogEndUserId').value = euId;
        document.getElementById('quickLogName').textContent = name;
        var weekSel = document.getElementById('quickLogWeek');
        var roundSel = document.getElementById('quickLogRound');
        var typeIn  = document.getElementById('quickLogStepType');

        // Rebuild the Week options to match this client's cycle (3 or 4 weeks).
        weekSel.innerHTML = '';
        for (var w = 1; w <= weekCount; w++) {
            var o = document.createElement('option');
            o.value = w; o.textContent = 'Week ' + w;
            weekSel.appendChild(o);
        }

        missingWeek = Math.min(parseInt(missingWeek, 10) || 1, weekCount);
        weekSel.value = missingWeek;
        roundSel.value = Math.max(1, currentRound || 1);
        typeIn.value = stepsMap[missingWeek] || 'ex_tu_eq_letters_generated';
        weekSel.onchange = function () { typeIn.value = stepsMap[weekSel.value] || ''; };
        openModal('quickLogModal');
    };

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
    function inlineDateEdit(selector, field) {
        document.querySelectorAll(selector).forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (el.classList.contains('editing')) return;
                inlineStop(e);
                var current = el.dataset.current || '';
                el.classList.add('editing');
                el.innerHTML =
                    '<input type="date" value="' + current + '">' +
                    '<button class="inline-save" type="button">Save</button>' +
                    '<button class="inline-cancel" type="button">×</button>';
                var input = el.querySelector('input');
                input.addEventListener('click', inlineStop);
                input.focus();
                el.querySelector('.inline-cancel').addEventListener('click', function (e2) {
                    inlineStop(e2); window.location.reload();
                });
                el.querySelector('.inline-save').addEventListener('click', function (e2) {
                    inlineStop(e2);
                    var fd = new FormData();
                    fd.append('_method', 'PUT');
                    fd.append('_token', csrf);
                    fd.append(field, input.value);   // blank clears / reverts to auto
                    fetch(updateUrlTpl.replace('__ID__', el.dataset.id), {
                        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    }).then(function (r) {
                        if (r.ok) window.location.reload();
                        else alert('Could not save the date.');
                    });
                });
            });
        });
    }
    inlineDateEdit('.inline-edit-round-started', 'round_started');
    inlineDateEdit('.inline-edit-next', 'next_round_override');

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
