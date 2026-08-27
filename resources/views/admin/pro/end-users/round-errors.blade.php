@extends('layouts.admin-pro')

@section('title', 'Round Errors')
@section('subtitle', 'Clients past round 1, pulled out with an import / later-round problem.')

@section('content')
<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#fb923c,#ea580c);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12a9.5 9.5 0 1 0 2.8-6.7"/><polyline points="2.5 4 2.5 8 6.5 8"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="15.5" x2="12" y2="15.5"/></svg>
            </span>
            <h2>Round Errors</h2>
            <span class="pro-panel-count danger">{{ $endUsers->count() }}</span>
        </div>
    </div>

    <p style="margin:0; padding:0 22px 4px; font-size:13px; color:var(--pro-muted);">
        These clients hit a problem after their 1st round (e.g. an import error when starting a later round).
        Fix the issue, then <strong>Resolve</strong> to send them back into the Clients list.
    </p>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Round</th>
                    <th>Round Started</th>
                    <th>Next Round Date</th>
                    <th>Days Left</th>
                    <th>Error Type</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($endUsers as $eu)
                    @php $dl = $eu->days_left_in_round; @endphp
                    <tr>
                        <td>
                            <div class="pro-name">
                                <span class="pro-avatar" style="background:#ffedd5; color:#c2410c;">
                                    {{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}
                                </span>
                                <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                            </div>
                        </td>
                        <td>{{ !empty($eu->rounds) ? implode(', ', $eu->rounds) : '—' }}</td>
                        <td>
                            <div class="pro-round-dates">
                                @forelse ($eu->round_timeline as $label => $date)
                                    <div>
                                        <b>{{ \Illuminate\Support\Str::before($label, ' Round') }}</b>
                                        <span>{{ $date ? \Carbon\Carbon::parse($date)->format('M j, Y') : '—' }}</span>
                                    </div>
                                @empty
                                    —
                                @endforelse
                            </div>
                        </td>
                        <td>
                            <span class="pro-next {{ $dl !== null && $dl < 0 ? 'over' : '' }}">
                                {{ $eu->next_round_date ? \Carbon\Carbon::parse($eu->next_round_date)->format('M j, Y') : '—' }}
                            </span>
                        </td>
                        <td>
                            <span class="pro-days {{ $dl !== null && $dl < 0 ? 'over' : ($dl !== null && $dl <= 3 ? 'soon' : '') }}"
                                  title="{{ $eu->round_end_date ? 'Current round ends '.\Carbon\Carbon::parse($eu->round_end_date)->format('M j, Y') : '' }}">
                                {{ $dl === null ? '—' : $dl }}
                            </span>
                        </td>
                        <td>
                            <span class="re-edit" data-id="{{ $eu->id }}" data-field="error_type" data-current="{{ $eu->error_type }}" title="Click to edit">
                                <span class="re-type">{{ $eu->error_type ?: '—' }}</span>
                                <span class="re-pencil" aria-hidden="true">✎</span>
                            </span>
                        </td>
                        <td>
                            <span class="re-edit re-edit-multi" data-id="{{ $eu->id }}" data-field="reason" data-current="{{ $eu->intake_review_note }}" title="Click to edit">
                                <span class="re-reason">{{ $eu->intake_review_note ?: '—' }}</span>
                                <span class="re-pencil" aria-hidden="true">✎</span>
                            </span>
                        </td>
                        <td>
                            <div class="pro-actions">
                                <a href="{{ route('admin.end-users.show', $eu) }}" class="pro-act view">Open</a>

                                <form method="POST" action="{{ route('admin.end-users.resolve-round-error', $eu->id) }}"
                                      data-confirm-action data-confirm-message="Resolve {{ $eu->full_name }} and move them back to the Clients list?">
                                    @csrf
                                    <button class="pro-act done">Resolve → Clients</button>
                                </form>

                                <form method="POST" action="{{ route('admin.end-users.destroy', $eu) }}"
                                      data-confirm-delete data-confirm-message="Delete client {{ $eu->full_name }} and all their documents? This cannot be undone.">
                                    @csrf @method('DELETE')
                                    <button class="pro-act del">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No round errors — all clients are on track.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('head')
<style>
    .re-type { display:inline-block; padding:3px 10px; border-radius:999px; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; font-size:12.5px; font-weight:600; }
    .re-reason { display:inline-block; max-width:360px; color:#b45309; font-size:13px; white-space:pre-wrap; word-break:break-word; }
    /* click-to-edit error type + reason */
    .re-edit { cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
    .re-edit-multi { max-width:380px; }
    .re-pencil { opacity:0; transition:opacity .15s; font-size:11px; color:var(--pro-muted); }
    .re-edit:hover .re-pencil { opacity:1; }
    .re-edit.editing { display:inline-flex; flex-wrap:wrap; gap:6px; align-items:flex-start; }
    .re-edit input[type="text"], .re-edit textarea {
        font-size:12.5px; padding:5px 8px; border-radius:6px; border:1px solid var(--pro-line);
        background:var(--surface); color:var(--pro-text); min-width:180px; font-family:inherit;
    }
    .re-edit textarea { min-width:260px; resize:vertical; }
    .re-save   { font-size:11px; padding:4px 10px; cursor:pointer; background:#16a34a; color:var(--on-accent, #fff); border:0; border-radius:5px; }
    .re-cancel { font-size:11px; padding:4px 10px; cursor:pointer; background:var(--pro-line); color:var(--pro-text-soft); border:0; border-radius:5px; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) return;
    var csrf = meta.getAttribute('content');
    var tpl = "{{ url('admin/end-users') }}/__ID__";
    function stop(e) { e.preventDefault(); e.stopPropagation(); }

    document.querySelectorAll('.re-edit').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (el.classList.contains('editing')) return;
            stop(e);
            var multi = el.classList.contains('re-edit-multi');
            var input = document.createElement(multi ? 'textarea' : 'input');
            if (!multi) input.type = 'text';
            if (multi) input.rows = 2;
            input.value = el.dataset.current || '';           // set as value — no escaping needed
            var save = document.createElement('button'); save.type = 'button'; save.className = 're-save'; save.textContent = 'Save';
            var cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 're-cancel'; cancel.textContent = '×';

            el.classList.add('editing');
            el.innerHTML = '';
            el.appendChild(input); el.appendChild(save); el.appendChild(cancel);
            input.addEventListener('click', stop);
            input.focus();

            cancel.addEventListener('click', function (e2) { stop(e2); window.location.reload(); });
            save.addEventListener('click', function (e2) {
                stop(e2);
                var fd = new FormData();
                fd.append('_method', 'PUT');
                fd.append('_token', csrf);
                fd.append(el.dataset.field, input.value);      // blank clears
                fetch(tpl.replace('__ID__', el.dataset.id), {
                    method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function (r) {
                    if (r.ok) window.location.reload();
                    else alert('Could not save.');
                });
            });
        });
    });
})();
</script>
@endpush
@endsection
