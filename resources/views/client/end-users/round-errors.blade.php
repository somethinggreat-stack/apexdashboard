@extends('layouts.client')

@section('title', 'Round Errors')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">Round Errors <span class="re-count">{{ $endUsers->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Clients past their 1st round that hit a problem (e.g. an import error when a later round started).
                Our team is fixing these — they'll move back into <strong>Done Clients</strong> once resolved.
            </p>
        </div>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Error Type</th>
                <th>Reason</th>
                <th>Email</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($endUsers as $eu)
                <tr>
                    <td><strong>{{ $eu->full_name }}</strong></td>
                    <td><span class="re-type">{{ $eu->error_type ?: '—' }}</span></td>
                    <td class="re-reason">{{ $eu->intake_review_note ?: '—' }}</td>
                    <td>{{ $eu->email }}</td>
                    <td class="no-link"><a href="{{ route('client.end-users.show', $eu) }}" class="btn btn-sm">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No round errors — all your clients are on track.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .re-count { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#ffedd5; color:#c2410c; font-size:13px; font-weight:700; vertical-align:middle; }
    .re-type { display:inline-block; padding:2px 9px; border-radius:999px; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; font-size:12.5px; font-weight:600; }
    .re-reason { max-width:360px; color:#b45309; font-weight:600; font-size:13px; white-space:pre-wrap; word-break:break-word; }
</style>
@endpush
@endsection
