@extends('layouts.admin')

@section('title', $client->business_name)

@section('content')
<div class="card">
    <div class="card-header">
        <h2>{{ $client->business_name }}</h2>
        <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">← Back</a>
    </div>
    <div class="info-grid">
        <div><label>Email</label><div>{{ $client->email }}</div></div>
        <div><label>Phone</label><div>{{ $client->phone ?? '—' }}</div></div>
        <div><label>Monthly Fee</label><div>${{ number_format($client->monthly_fee, 2) }}</div></div>
        <div><label>Clients</label><div>{{ $client->endUsers->count() }}</div></div>
        <div><label>Monthly Revenue</label><div>${{ number_format($client->monthly_revenue, 2) }}</div></div>
        <div><label>Status</label><div><span class="pill pill-{{ $client->status }}">{{ $client->status }}</span></div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Clients ({{ $client->endUsers->count() }})</h2>
        <a href="{{ route('admin.end-users.index', ['client_id' => $client->id]) }}" class="btn btn-primary">+ Add Client</a>
    </div>
    <table class="data-table">
        <thead>
            <tr><th>Name</th><th>Email</th><th>Score</th><th>Status</th><th>Started</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($client->endUsers as $eu)
                <tr>
                    <td>{{ $eu->full_name }}</td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->current_score ?? '—' }}</td>
                    <td><span class="pill pill-{{ $eu->status }}">{{ $eu->status }}</span></td>
                    <td>{{ $eu->start_date?->format('M d, Y') }}</td>
                    <td><a href="{{ route('admin.end-users.show', $eu) }}" class="btn btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No clients yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
