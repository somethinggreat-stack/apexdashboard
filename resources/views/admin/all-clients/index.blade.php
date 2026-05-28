@extends('layouts.admin')

@section('title', 'All Clients (All BOs)')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>All Clients — across every Business Owner</h2>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('admin.today-queue') }}" class="btn btn-secondary">Today's Queue</a>
            <a href="{{ route('admin.client-selector.index') }}" class="btn btn-primary">Pick a BO</a>
        </div>
    </div>
    <form method="GET" class="filter-bar">
        <select name="bo">
            <option value="">All BOs</option>
            @foreach ($businessOwners as $bo)
                <option value="{{ $bo->id }}" @selected((int) request('bo') === $bo->id)>{{ $bo->business_name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">All statuses</option>
            @foreach (['active','paused','graduated','cancelled'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or email">
        <button class="btn btn-secondary">Filter</button>
    </form>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Business Owner</th>
                <th>Email</th>
                <th>Round</th>
                <th>Steps</th>
                <th>Days</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td>
                        <a href="{{ route('admin.client-selector.select', $eu->client_id) }}"
                           onclick="event.preventDefault(); document.getElementById('sel-{{ $eu->id }}').submit();">
                            {{ $eu->full_name }}
                        </a>
                        <form id="sel-{{ $eu->id }}" method="POST" action="{{ route('admin.client-selector.select', $eu->client_id) }}" style="display:none;">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ route('admin.end-users.show', $eu->id) }}">
                        </form>
                        @if ($eu->is_incomplete)
                            <span class="pill pill-incomplete" title="{{ $eu->incomplete_reason }}">Incomplete</span>
                        @endif
                    </td>
                    <td><span class="muted">{{ $eu->client?->business_name ?? '—' }}</span></td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ !empty($eu->rounds) ? implode(', ', $eu->rounds) : '—' }}</td>
                    <td>{{ $eu->process_steps_count }}</td>
                    <td>{{ $eu->days_active }}</td>
                    <td><span class="pill pill-{{ $eu->status }}">{{ $eu->status }}</span></td>
                    <td>
                        <a href="{{ route('admin.end-users.status-report', $eu) }}" target="_blank" class="btn btn-sm">Report</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No clients found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
