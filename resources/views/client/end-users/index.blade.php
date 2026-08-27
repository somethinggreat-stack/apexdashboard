@extends('layouts.client')

@php
    $bucket   = $bucket ?? 'in_progress';
    $isCustom = $bucket === 'custom';
    $isDone   = $bucket === 'clients';
    $bo       = Auth::guard('client')->user();
    $title    = $isCustom ? $listLabel : ($isDone ? 'Done Clients' : 'In Progress');
@endphp

@section('title', $title)

@unless ($isCustom)
@section('topbar-action')
    <a href="{{ route('client.end-users.create') }}" class="btn btn-primary">+ Add New Client</a>
@endsection
@endunless

@section('content')
<div class="card">
    <div class="card-header">
        <h2>{{ $title }}</h2>
    </div>
    <p class="muted" style="margin:-4px 0 14px; font-size:13px;">
        @if ($isCustom)
            Your <strong>{{ $listLabel }}</strong> list. Use the buttons on each row to move a client in or out of a list — this only changes grouping; rounds and work status stay exactly as they are.
        @elseif ($isDone)
            Clients whose first round is complete. Every round after that is worked here — rounds, dates and days left are tracked below.
        @else
            Clients our team has verified and is actively working through the first round. They move into <strong>Done Clients</strong> once round&nbsp;1 is complete.
        @endif
    </p>
    <form method="GET" class="filter-bar">
        <select name="status">
            <option value="">All statuses</option>
            @foreach (['active','paused','graduated','cancelled'] as $s)
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
                <th>Email</th>
                <th>Round</th>
                <th>Round Started</th>
                <th>Steps</th>
                <th>Days Left</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr class="row-link" data-href="{{ route('client.end-users.show', $eu) }}">
                    <td>
                        <a href="{{ route('client.end-users.show', $eu) }}" class="name-link">{{ $eu->full_name }}</a>
                    </td>
                    <td class="no-link">{{ $eu->email }}</td>
                    <td class="no-link">{{ !empty($eu->round_timeline) ? implode(', ', array_keys($eu->round_timeline)) : '—' }}</td>
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
                    <td class="no-link">{{ $eu->process_steps_count }}</td>
                    <td class="no-link">
                        @php $dl = $eu->days_left_in_round; @endphp
                        <span style="font-weight:600; {{ $dl !== null && $dl < 0 ? 'color:#dc2626;font-weight:700;' : ($dl !== null && $dl <= 3 ? 'color:#ea580c;' : '') }}"
                              title="{{ $eu->round_end_date ? 'Current round ends '.\Carbon\Carbon::parse($eu->round_end_date)->format('M j, Y') : '' }}">
                            {{ $dl === null ? '—' : $dl }}
                        </span>
                    </td>
                    <td class="no-link"><span class="pill pill-{{ $eu->status }}">{{ $eu->status }}</span></td>
                    <td class="no-link">
                        <a href="{{ route('client.end-users.show', $eu) }}" class="btn btn-sm">Open</a>
                        <a href="{{ route('client.end-users.status-report', $eu) }}" target="_blank" class="btn btn-sm">Report</a>
                        @if ($bo?->custom_lists_enabled)
                            <div class="list-moves">
                                @foreach (\App\Models\EndUser::CUSTOM_LISTS as $lk => $ll)
                                    <form method="POST" action="{{ route('client.end-users.list', $eu) }}" class="lm-form">
                                        @csrf
                                        <input type="hidden" name="list" value="{{ $eu->custom_list === $lk ? 'none' : $lk }}">
                                        <button type="submit" class="lm-btn {{ $eu->custom_list === $lk ? 'on' : '' }}"
                                                title="{{ $eu->custom_list === $lk ? 'Remove from '.$ll : 'Move to '.$ll }}">{{ $ll }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">{{ ($isCustom ?? false) ? 'No clients in this list yet.' : 'No clients yet.' }}</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .name-link { color:#1e40af; text-decoration:none; font-weight:600; }
    .name-link:hover { text-decoration:underline; }
    /* Custom-list move buttons (Tycon Stan) */
    .list-moves { display:flex; flex-wrap:wrap; gap:5px; margin-top:7px; }
    .list-moves .lm-form { margin:0; display:inline; }
    .lm-btn {
        cursor:pointer; font:inherit; font-size:11px; font-weight:700; line-height:1.2;
        padding:5px 10px; border-radius:999px; white-space:nowrap;
        background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;
        transition:background .12s, border-color .12s, color .12s;
    }
    .lm-btn:hover { background:#eef2ff; border-color:#c7d2fe; color:#4338ca; }
    .lm-btn.on { background:#4f46e5; border-color:#4f46e5; color:#fff; }
    .lm-btn.on:hover { background:#4338ca; }
</style>
@endpush
@endsection
