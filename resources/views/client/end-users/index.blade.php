@extends('layouts.client')

@php $isDone = ($bucket ?? 'in_progress') === 'clients'; @endphp

@section('title', $isDone ? 'Done Clients' : 'In Progress')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>{{ $isDone ? 'Done Clients' : 'In Progress' }}</h2>
        <a href="{{ route('client.end-users.create') }}" class="btn btn-primary">+ Add New Client</a>
    </div>
    <p class="muted" style="margin:-4px 0 14px; font-size:13px;">
        @if ($isDone)
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
                    <td class="no-link">{{ !empty($eu->rounds) ? implode(', ', $eu->rounds) : '—' }}</td>
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
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No clients yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .name-link { color:#1e40af; text-decoration:none; font-weight:600; }
    .name-link:hover { text-decoration:underline; }
</style>
@endpush
@endsection
