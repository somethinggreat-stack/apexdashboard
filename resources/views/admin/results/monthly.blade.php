@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Monthly Results')
@section('subtitle', "Monthly client results for {$ownerName} — pick a month and copy.")

@php
    $join = fn ($arr) => count($arr) ? implode('; ', $arr) : '—';

    // Copy-paste monthly report in the SOP format.
    $sep = str_repeat('—', 26);
    $L = [];
    $L[] = strtoupper($ownerName) . " — Monthly Results ({$monthLabel})";
    $L[] = "";
    foreach ($rows as $r) {
        $L[] = $r['name'];
        $L[] = "Came into month with (" . count($r['cameIn']) . "): " . $join($r['cameIn']);
        $L[] = "Deleted this month (" . count($r['deleted']) . "): " . $join($r['deleted']);
        $L[] = "Updated to positive this month (" . count($r['updated']) . "): " . $join($r['updated']);
        $L[] = "Remaining (" . count($r['remaining']) . "): " . $join($r['remaining']);
        $L[] = "Round: {$r['round']} | Status: {$r['status']}";
        $L[] = $sep;
    }
    if (empty($rows)) { $L[] = "No client results for this month."; }
    $monthlyText = implode("\n", $L);
@endphp

@section('content')
@if (!$enabled)
    <div class="pro-panel" style="padding:40px 22px; text-align:center; color:var(--pro-muted);">
        Results tracking isn't enabled for any business owner yet.
    </div>
@else
<div class="pro-panel" style="margin-bottom:16px;">
    <div class="pro-panel-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#818cf8,#4338ca);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </span>
            <h2>Monthly Results — {{ $ownerName }}</h2>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <form method="GET" style="margin:0;">
                <select name="month" onchange="this.form.submit()" class="mr-month">
                    @foreach ($months as $m)
                        <option value="{{ $m }}" @selected($m === $month)>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->format('F Y') }}</option>
                    @endforeach
                </select>
            </form>
            <button type="button" class="dt-btn dt-btn-primary" data-copy-el="monthlyText">📋 Copy Report</button>
        </div>
    </div>
    <p style="margin:0; padding:0 22px 6px; font-size:13px; color:var(--pro-muted);">
        Showing <strong>{{ $monthLabel }}</strong> · {{ count($rows) }} clients with activity · generated {{ $generatedAt->timezone('America/New_York')->format('M j, Y g:i A') }} ET.
    </p>
</div>

<div class="pro-panel" style="margin-bottom:16px; padding:16px 18px;">
    <textarea id="monthlyText" class="dt-wa-text" style="min-height:180px;" readonly onclick="this.select()">{{ $monthlyText }}</textarea>
</div>

<div class="pro-panel" style="padding:0; overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="mr-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Came into {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F') }} with</th>
                    <th>Deleted this month</th>
                    <th>Updated to positive</th>
                    <th>Remaining</th>
                    <th>Round</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td><strong>{{ $r['name'] }}</strong></td>
                        <td><span class="mr-count">{{ count($r['cameIn']) }}</span><span class="mr-names">{{ $join($r['cameIn']) }}</span></td>
                        <td><span class="mr-count mr-del">{{ count($r['deleted']) }}</span><span class="mr-names">{{ $join($r['deleted']) }}</span></td>
                        <td><span class="mr-count mr-upd">{{ count($r['updated']) }}</span><span class="mr-names">{{ $join($r['updated']) }}</span></td>
                        <td><span class="mr-count">{{ count($r['remaining']) }}</span><span class="mr-names">{{ $join($r['remaining']) }}</span></td>
                        <td>{{ $r['round'] }}</td>
                        <td>{{ $r['status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--pro-muted);">No client results for {{ $monthLabel }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@push('head')
    @include('admin.partials.daily-report-styles')
    <style>
        .mr-month { padding:8px 12px; border:1px solid #d7dee8; border-radius:9px; font-size:13px; background:#fff; color:#0f172a; font-weight:600; }
        :root[data-theme="dark"] .mr-month { background:#10152a; border-color:var(--pro-line); color:var(--pro-text); }
        .mr-table { width:100%; border-collapse:collapse; font-size:13px; min-width:900px; }
        .mr-table th { text-align:left; padding:12px 14px; background:rgba(148,163,184,.08); font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--pro-muted,#64748b); white-space:nowrap; }
        .mr-table td { padding:12px 14px; border-top:1px solid var(--pro-line,#eef2f7); vertical-align:top; }
        .mr-count { display:inline-block; min-width:22px; height:22px; line-height:22px; text-align:center; border-radius:6px; background:#e0f2fe; color:#075985; font-weight:800; font-size:12px; margin-right:8px; }
        .mr-del { background:#dcfce7; color:#166534; }
        .mr-upd { background:#ede9fe; color:#5b21b6; }
        .mr-names { color:var(--pro-text,#334155); }
    </style>
@endpush
@push('scripts')
    @include('admin.partials.daily-report-scripts')
@endpush
@endsection
