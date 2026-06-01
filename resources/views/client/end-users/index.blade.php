@extends('layouts.client')

@section('title', 'My Clients')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>My Credit Repair Clients</h2>
    </div>
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
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Round</th>
                <th>Round Started</th>
                <th>Steps</th>
                <th>Days</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr class="row-link" data-href="{{ route('client.end-users.show', $eu) }}">
                    <td>
                        <a href="{{ route('client.end-users.show', $eu) }}" class="name-link">{{ $eu->full_name }}</a>
                        @if ($eu->is_incomplete)
                            <span class="pill pill-incomplete" title="{{ $eu->incomplete_reason }}">Incomplete</span>
                        @endif
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
                    <td class="no-link">{{ $eu->days_active }}</td>
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
    </table>
</div>

@push('head')
<style>
    .name-link { color:#1e40af; text-decoration:none; font-weight:600; }
    .name-link:hover { text-decoration:underline; }
    .pill-incomplete { background:#fee2e2; color:#991b1b; margin-left:6px;
        padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600;
        text-transform:uppercase; letter-spacing:.3px; }
</style>
@endpush
@endsection
