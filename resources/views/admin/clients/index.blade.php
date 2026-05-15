@extends('layouts.admin')

@section('title', 'Business Clients')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Business Owner Clients</h2>
        <button class="btn btn-primary" onclick="openModal('createClientModal')">+ Add Business Client</button>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Business Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Clients</th>
                <th>Monthly Fee</th>
                <th>Monthly Revenue</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clients as $client)
                <tr>
                    <td>{{ $client->business_name }}</td>
                    <td>{{ $client->email }}</td>
                    <td>{{ $client->phone ?? '—' }}</td>
                    <td>{{ $client->end_users_count }}</td>
                    <td>${{ number_format($client->monthly_fee, 2) }}</td>
                    <td>${{ number_format($client->monthly_revenue, 2) }}</td>
                    <td><span class="pill pill-{{ $client->status }}">{{ $client->status }}</span></td>
                    <td>
                        <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-sm">View</a>
                        <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" style="display:inline" onsubmit="return confirm('Delete {{ $client->business_name }} and all their clients?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No business clients yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="createClientModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Business Client</h3>
            <button class="modal-close" onclick="closeModal('createClientModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.clients.store') }}">
            @csrf
            <div class="form-group">
                <label>Business Name</label>
                <input type="text" name="business_name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone">
            </div>
            <div class="form-group">
                <label>Monthly Fee ($)</label>
                <input type="number" step="0.01" name="monthly_fee" value="149.00">
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createClientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>
@endsection
