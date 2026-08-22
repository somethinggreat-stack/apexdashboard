@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Download Letters')
@section('subtitle', "Letters for {$bo->business_name}'s active clients — {$perBatch} clients per download.")

@section('content')
<div class="pro-panel" style="margin-bottom:16px; padding:18px 20px;">
    <h2 style="margin:0 0 6px;">Download Letters — {{ $bo->business_name }}</h2>
    <p style="margin:0; color:var(--pro-muted,#64748b); font-size:13.5px;">
        {{ $total }} active {{ $total === 1 ? 'client' : 'clients' }}, split into {{ $batches->count() }}
        {{ $batches->count() === 1 ? 'download' : 'downloads' }} of up to {{ $perBatch }} clients each.
        Each ZIP is foldered <strong>Client Name → Nth Round Letters</strong>. Click each part below.
    </p>
</div>

@forelse ($batches as $i => $batch)
    <div class="pro-panel lt-batch">
        <div class="lt-batch-info">
            <div class="lt-batch-title">Part {{ $i + 1 }}</div>
            <div class="lt-batch-names">
                @foreach ($batch as $eu){{ $eu->full_name }}@if (!$loop->last) · @endif @endforeach
            </div>
        </div>
        <a href="{{ route('admin.client-list.letters-export', ['batch' => $i]) }}" class="btn btn-primary lt-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download Part {{ $i + 1 }}
        </a>
    </div>
@empty
    <div class="pro-panel" style="padding:34px; text-align:center; color:var(--pro-muted);">
        No active clients for {{ $bo->business_name }}.
    </div>
@endforelse

@push('head')
<style>
    .lt-batch { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 18px; margin-bottom:10px; flex-wrap:wrap; }
    .lt-batch-title { font-weight:700; font-size:14px; margin-bottom:3px; }
    .lt-batch-names { color:var(--pro-muted,#64748b); font-size:13px; }
    .lt-btn { display:inline-flex; align-items:center; gap:7px; white-space:nowrap; }
</style>
@endpush
@endsection
