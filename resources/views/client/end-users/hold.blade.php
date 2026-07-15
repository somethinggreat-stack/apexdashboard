@extends('layouts.client')

@section('title', 'Hold / Pause')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Hold/Pause <span class="hold-count">{{ $endUsers->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Clients our team has temporarily paused. They're set aside from your
                <strong>In Progress</strong> and <strong>Done Clients</strong> lists until work resumes.
            </p>
        </div>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>On Hold Since</th>
                <th>Email</th>
                <th>Phone</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td><strong>{{ $eu->full_name }}</strong></td>
                    <td>{{ $eu->held_at?->format('M j, Y') ?: '—' }}</td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->phone ?: '—' }}</td>
                    <td class="no-link"><a href="{{ route('client.end-users.show', $eu) }}" class="btn btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No clients on hold.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .hold-count { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#e2e8f0; color:#475569; font-size:13px; font-weight:700; vertical-align:middle; }
</style>
@endpush
@endsection
