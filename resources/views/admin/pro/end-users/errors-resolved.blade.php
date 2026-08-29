@extends('layouts.admin-pro')

@section('title', 'Errors Resolved by BO for Next Round')
@section('subtitle', 'Round errors the business owner fixed — updated login is ready, process and send back to Clients.')

@section('content')
<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#34d399,#059669);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </span>
            <h2>Errors Resolved by BO for Next Round</h2>
            <span class="pro-panel-count" style="background:#dcfce7; color:#166534;">{{ $endUsers->count() }}</span>
        </div>
    </div>

    <p style="margin:0; padding:0 22px 4px; font-size:13px; color:var(--pro-muted);">
        The business owner updated the credit-monitoring login for these clients. Use the credentials below to finish
        the round, then <strong>Resolve → Clients</strong> to send them back into the Clients list.
    </p>

    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Round</th>
                    <th>Error Type</th>
                    <th>Resolved On</th>
                    <th>Updated Login (from Business Owner)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($endUsers as $eu)
                    <tr>
                        <td>
                            <div class="pro-name">
                                <span class="pro-avatar" style="background:#dcfce7; color:#166534;">
                                    {{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}
                                </span>
                                <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                            </div>
                        </td>
                        <td>{{ $eu->started_rounds_short ?: 'Not started' }}</td>
                        <td><span class="re-type">{{ $eu->error_type ?: '—' }}</span></td>
                        <td>
                            <span class="re-resolved-at">
                                {{ $eu->error_resolved_by_client_at ? $eu->error_resolved_by_client_at->format('M j, Y g:ia') : '—' }}
                            </span>
                        </td>
                        <td>
                            <div class="re-creds">
                                <div><span class="re-k">Service</span><span class="re-v">{{ $eu->credit_monitoring_name ?: '—' }}</span></div>
                                <div><span class="re-k">Username</span><span class="re-v">{{ $eu->credit_monitoring_username ?: '—' }}</span></div>
                                <div>
                                    <span class="re-k">Password</span>
                                    <span class="re-v re-secret" data-secret="{{ $eu->credit_monitoring_password }}">
                                        {{ $eu->credit_monitoring_password ? '••••••••' : '—' }}
                                    </span>
                                    @if ($eu->credit_monitoring_password)<button type="button" class="re-eye" title="Show / hide">show</button>@endif
                                </div>
                                <div><span class="re-k">Security Q</span><span class="re-v">{{ $eu->credit_monitoring_security_question ?: '—' }}</span></div>
                                <div>
                                    <span class="re-k">Security A</span>
                                    <span class="re-v re-secret" data-secret="{{ $eu->credit_monitoring_security_answer }}">
                                        {{ $eu->credit_monitoring_security_answer ? '••••••••' : '—' }}
                                    </span>
                                    @if ($eu->credit_monitoring_security_answer)<button type="button" class="re-eye" title="Show / hide">show</button>@endif
                                </div>
                                <div>
                                    <span class="re-k">PIN</span>
                                    <span class="re-v re-secret" data-secret="{{ $eu->credit_monitoring_pin }}">
                                        {{ $eu->credit_monitoring_pin ? '••••' : '—' }}
                                    </span>
                                    @if ($eu->credit_monitoring_pin)<button type="button" class="re-eye" title="Show / hide">show</button>@endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="pro-actions">
                                <a href="{{ route('admin.end-users.show', $eu) }}" class="pro-act view">Open</a>

                                <form method="POST" action="{{ route('admin.end-users.resolve-round-error', $eu->id) }}"
                                      data-confirm-action data-confirm-message="Mark {{ $eu->full_name }} processed and move them back to the Clients list?">
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
                    <tr><td colspan="6" class="empty">Nothing here yet. When a business owner resolves a round error, it lands here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('head')
<style>
    .re-type { display:inline-block; padding:3px 10px; border-radius:999px; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; font-size:12.5px; font-weight:600; }
    .re-resolved-at { display:inline-block; padding:3px 10px; border-radius:999px; background:#dcfce7; color:#166534; border:1px solid #bbf7d0; font-size:12.5px; font-weight:600; white-space:nowrap; }
    .re-creds { display:grid; gap:3px; min-width:240px; }
    .re-creds > div { display:flex; align-items:center; gap:8px; font-size:12.5px; }
    .re-k { flex:0 0 74px; color:var(--pro-muted); font-weight:600; }
    .re-v { color:var(--pro-text); word-break:break-word; }
    .re-eye { font-size:10.5px; padding:1px 7px; border:1px solid var(--pro-line); background:transparent; color:var(--pro-muted); border-radius:5px; cursor:pointer; }
    .re-eye:hover { color:var(--pro-text); }
</style>
@endpush
@push('scripts')
<script>
document.querySelectorAll('.re-eye').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var span = btn.parentElement.querySelector('.re-secret');
        if (!span) return;
        var secret = span.getAttribute('data-secret') || '';
        var shown = btn.textContent === 'hide';
        span.textContent = shown ? (secret ? '••••••••' : '—') : secret;
        btn.textContent = shown ? 'show' : 'hide';
    });
});
</script>
@endpush
@endsection
