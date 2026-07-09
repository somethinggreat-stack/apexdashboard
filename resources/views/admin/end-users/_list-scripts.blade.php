{{--
    Inline round/status editing, quick-log, quick-note and Move-to-Errors prompt.
    Shared by the original list view and the super-admin pro console so the
    behaviour can never drift between them.
--}}
@php
    $statusOptions = ['active','paused','graduated','cancelled'];
    $roundOptions  = App\Models\EndUser::ROUND_OPTIONS;
    $weekStepCanonical = [
        1 => 'ex_tu_eq_letters_generated',
        2 => 'tu_ex_call_followups',
        3 => 'aggressive_bureau_followup',
        4 => 'pull_latest_report',
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
    window.openQuickLog = function (euId, name, missingWeek, currentRound) {
        document.getElementById('quickLogEndUserId').value = euId;
        document.getElementById('quickLogName').textContent = name;
        var weekSel = document.getElementById('quickLogWeek');
        var roundSel = document.getElementById('quickLogRound');
        var typeIn  = document.getElementById('quickLogStepType');
        weekSel.value = missingWeek;
        roundSel.value = Math.max(1, currentRound || 1);
        typeIn.value = WEEK_STEPS[missingWeek] || 'ex_tu_eq_letters_generated';
        weekSel.onchange = function () { typeIn.value = WEEK_STEPS[weekSel.value] || ''; };
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
})();
</script>
@endpush
