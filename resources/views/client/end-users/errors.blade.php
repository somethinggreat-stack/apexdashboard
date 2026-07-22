@extends('layouts.client')

@section('title', 'New Client Errors')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">New Client Errors <span class="err-count">{{ $endUsers->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Clients our team pulled out of your main list because something needs fixing
                (bad login, missing document, billing, etc.). We're on it — they'll move back into
                <strong>In Progress</strong> once resolved.
            </p>
        </div>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Error</th>
                <th>Email</th>
                <th>Phone</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td><strong>{{ $eu->full_name }}</strong></td>
                    <td class="err-note">{{ $eu->intake_review_note ?: '—' }}</td>
                    <td>{{ $eu->email }}</td>
                    <td>{{ $eu->phone ?: '—' }}</td>
                    <td class="no-link"><a href="{{ route('client.end-users.show', $eu) }}" class="btn btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No errors — all your clients are in good standing.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .err-count { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#fee2e2; color:#b91c1c; font-size:13px; font-weight:700; vertical-align:middle; }
    .err-note { max-width:360px; color:#b91c1c; font-weight:600; font-size:13px; white-space:pre-wrap; word-break:break-word; }
</style>
@endpush
@endsection
