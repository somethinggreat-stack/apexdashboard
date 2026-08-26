@extends('layouts.admin')

@section('title', 'Clients')

@php $statusOptions = ['active','paused','graduated','cancelled']; @endphp

@section('content')
<div class="card">
    <div class="card-header">
        <h2>{{ ($bucket ?? 'in_progress') === 'clients' ? 'Clients' : 'In Progress' }} — {{ $selectedClient->business_name }}</h2>
        <a href="{{ route('admin.end-users.create') }}" class="btn btn-primary">+ Add Client</a>
    </div>
    <form method="GET" class="filter-bar">
        <select name="status">
            <option value="">All statuses</option>
            @foreach ($statusOptions as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name">
        <button class="btn btn-secondary">Filter</button>
    </form>
    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Round</th>
                <th>Round Started</th>
                <th>Next Round Date</th>
                <th>Days Left</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr class="row-link" data-href="{{ route('admin.end-users.show', $eu) }}">
                    <td>
                        <a href="{{ route('admin.end-users.show', $eu) }}" class="name-link">{{ $eu->full_name }}</a>
                        @if ($eu->is_incomplete)
                            <button type="button"
                                    class="pill pill-incomplete inline-action"
                                    title="{{ $eu->incomplete_reason }} — click to log"
                                    onclick="openQuickLog({{ $eu->id }}, '{{ addslashes($eu->full_name) }}', {{ $eu->missing_week ?? 1 }}, {{ $eu->current_round }}, {{ $eu->roundCycleDays() }})">
                                Incomplete · log
                            </button>
                        @endif
                    </td>
                    <td class="no-link">
                        <span class="inline-edit inline-edit-round"
                              data-id="{{ $eu->id }}"
                              data-current="{{ json_encode($eu->rounds ?? []) }}">
                            {{ !empty($eu->rounds) ? implode(', ', $eu->rounds) : '—' }}
                            <span class="inline-pencil" aria-hidden="true">✎</span>
                        </span>
                    </td>
                    <td class="no-link">
                        @forelse ($eu->round_timeline as $label => $date)
                            <div class="round-date">
                                <strong>{{ \Illuminate\Support\Str::before($label, ' Round') }}</strong>
                                {{ $date ? \Carbon\Carbon::parse($date)->format('M j, Y') : '—' }}
                            </div>
                        @empty
                            —
                        @endforelse
                    </td>
                    <td class="no-link">
                        {{ $eu->next_round_date ? \Carbon\Carbon::parse($eu->next_round_date)->format('M j, Y') : '—' }}
                    </td>
                    <td class="no-link">
                        @php $dl = $eu->days_left_in_round; @endphp
                        <span class="days-left {{ $dl !== null && $dl < 0 ? 'days-left-over' : ($dl !== null && $dl <= 3 ? 'days-left-soon' : '') }}"
                              title="{{ $eu->round_end_date ? 'Current round ends '.\Carbon\Carbon::parse($eu->round_end_date)->format('M j, Y') : '' }}">
                            {{ $dl === null ? '—' : $dl }}
                        </span>
                    </td>
                    <td class="no-link">
                        <span class="inline-edit inline-edit-status"
                              data-id="{{ $eu->id }}"
                              data-current="{{ $eu->status }}">
                            <span class="pill pill-{{ $eu->status }}">{{ $eu->status }}</span>
                            <span class="inline-pencil" aria-hidden="true">✎</span>
                        </span>
                    </td>
                    <td class="no-link">
                        <a href="{{ route('admin.end-users.show', $eu) }}" class="btn btn-sm">Open</a>
                        @if (($bucket ?? 'in_progress') !== 'clients')
                            <form method="POST" action="{{ route('admin.end-users.to-done', $eu->id) }}" style="display:inline"
                                  onsubmit="return confirm(@js('Are you sure you want to move ' . $eu->full_name . ' to Clients? The round clock starts today.'))">
                                @csrf
                                <button class="btn btn-sm btn-todone">Move to Clients</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.end-users.to-errors', $eu->id) }}" style="display:inline" class="send-back-form">
                            @csrf
                            <input type="hidden" name="note" value="">
                            <button type="button" class="btn btn-sm btn-sendback" onclick="moveToErrors(this, '{{ addslashes($eu->full_name) }}')">Move to Errors</button>
                        </form>
                        <form method="POST" action="{{ route('admin.end-users.to-new-clients', $eu->id) }}" style="display:inline">
                            @csrf
                            <button class="btn btn-sm btn-tonew">Move to New Clients</button>
                        </form>
                        <form method="POST" action="{{ route('admin.end-users.destroy', $eu) }}" style="display:inline"
                              data-confirm-delete
                              data-confirm-title="Delete this client?"
                              data-confirm-message="{{ $eu->full_name }} and all their documents will be moved to the Recycle Bin. This cannot be undone from here.">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No clients found.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@include('admin.end-users._list-modals')

@push('head')
<style>
    .field-error { display:block; color:#dc2626; font-size:12px; margin-top:4px; }
    .btn-sendback { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
    .btn-sendback:hover { background:#fde68a; }
    .btn-tonew { background:#e0f2fe; color:#075985; border:1px solid #bae6fd; }
    .btn-tonew:hover { background:#bae6fd; }
    .btn-todone { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .btn-todone:hover { background:#bbf7d0; }
    .days-left { font-weight:600; }
    .days-left-soon { color:#ea580c; }
    .days-left-over { color:#dc2626; font-weight:700; }
    .round-timeline { display:inline-flex; flex-direction:column; gap:1px; }
    .round-date { font-size:12px; line-height:1.5; white-space:nowrap; }
    .round-date strong { display:inline-block; min-width:34px; color:var(--text-soft); }
    .name-link { color:#1e40af; text-decoration:none; font-weight:600; }
    .name-link:hover { text-decoration:underline; }

    .inline-edit { cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
    .inline-edit .inline-pencil {
        opacity:0; transition:opacity .15s; font-size:11px; color:var(--muted);
    }
    .inline-edit:hover .inline-pencil { opacity:1; }
    .inline-edit.editing { display:inline-flex; gap:4px; }
    .inline-edit select { font-size:12px; padding:2px 6px; min-width:120px; }
    .inline-edit .inline-save  { font-size:11px; padding:2px 8px; cursor:pointer; background:#16a34a; color:white; border:0; border-radius:4px; }
    .inline-edit .inline-cancel { font-size:11px; padding:2px 8px; cursor:pointer; background:#e5e7eb; color:#374151; border:0; border-radius:4px; }

    .pill-incomplete.inline-action {
        cursor:pointer; border:none;
        background:#fee2e2; color:#991b1b;
        margin-left:6px; padding:2px 10px; border-radius:999px;
        font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.3px;
    }
    .pill-incomplete.inline-action:hover { background:#fecaca; }
</style>
@endpush

@include('admin.end-users._list-scripts')
@endsection
