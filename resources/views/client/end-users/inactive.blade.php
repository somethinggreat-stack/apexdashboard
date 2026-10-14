@extends('layouts.client')

@section('title', 'Inactive')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Inactive <span class="inactive-count">{{ $endUsers->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Clients who are no longer active (not paying, or stopped service). They're kept out of
                every other list and out of the daily EOD. Let our team know if one should be reactivated.
            </p>
        </div>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Inactive Since</th>
                <th>Reason</th>
                <th>Email</th>
                <th>Phone</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td><strong>{{ $eu->full_name }}</strong></td>
                    <td>{{ $eu->inactive_at?->format('M j, Y') ?: '—' }}</td>
                    <td>{{ $eu->move_reason ?: '—' }}</td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->phone ?: '—' }}</td>
                    <td class="no-link"><a href="{{ route('client.end-users.show', $eu) }}" class="btn btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No inactive clients.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .inactive-count { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#e7e5e4; color:#57534e; font-size:13px; font-weight:700; vertical-align:middle; }
</style>
@endpush
@endsection
