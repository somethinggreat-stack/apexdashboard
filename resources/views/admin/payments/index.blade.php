@extends($adminLayout ?? 'layouts.admin')

@section('title', 'Payments')
@section('subtitle', $client->business_name)

@section('content')

@include('admin.payments._settings', ['client' => $client])

@if ($model === 'per_round')
    @include('admin.payments._per_round', ['data' => $data, 'client' => $client])
@elseif ($model === 'hourly')
    @include('admin.payments._hourly', ['data' => $data, 'client' => $client])
@else
    <div class="card empty" style="padding:40px;text-align:center;">
        Pick a payment model in the settings above to start tracking.
    </div>
@endif

@push('head')
<style>
    /* === PAYMENTS PAGE SCOPED STYLES === */
    .pay-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin: 8px 0 20px; }
    .pay-stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 16px 18px;
        box-shadow: 0 1px 2px rgba(15,23,42,.04); transition: box-shadow .15s, transform .15s; }
    .pay-stat-card:hover { box-shadow: 0 4px 12px rgba(15,23,42,.07); transform: translateY(-1px); }
    .pay-stat-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); margin-bottom: 8px; }
    .pay-stat-value { font-size: 24px; font-weight: 700; color: var(--text); line-height: 1.1; letter-spacing: -.5px; }
    .pay-stat-sub { font-size: 11.5px; color: var(--muted); margin-top: 2px; }
    .pay-stat-green .pay-stat-value { color: #059669; }
    .pay-stat-orange .pay-stat-value { color: #ea580c; }

    .pay-settings-card { background: linear-gradient(135deg,var(--surface-2),var(--surface)); border:1px solid var(--border); border-radius:14px; padding:18px 22px; margin-bottom:18px; }
    .pay-settings-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px; }
    .pay-settings-title { font-size:15px; font-weight:700; color:var(--text); display:flex; align-items:center; gap:10px; }
    .pay-settings-title svg { color:#2563eb; }
    .pay-model-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; }
    .pay-pill-per-round { background:#dbeafe; color:#1e40af; }
    .pay-pill-hourly { background:#ede9fe; color:#5b21b6; }

    .pay-settings-grid { display:grid; grid-template-columns: repeat(4, 1fr); gap:12px 16px; align-items:end; }
    .pay-settings-grid label { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); font-weight:600; margin-bottom:4px; }
    .pay-settings-grid input, .pay-settings-grid select { width:100%; box-sizing:border-box; padding:7px 10px; border:1px solid var(--border); border-radius:8px; font-size:13px; background:var(--surface); }

    /* Per-round matrix */
    .pay-matrix { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow-x:auto; box-shadow:0 1px 2px rgba(15,23,42,.04); }
    .pay-matrix table { width:100%; border-collapse:collapse; }
    .pay-matrix th, .pay-matrix td { padding:12px 14px; text-align:left; border-bottom:1px solid var(--surface-2); }
    .pay-matrix thead th { background:var(--surface-2); font-size:11px; text-transform:uppercase; letter-spacing:.6px; color:var(--muted); font-weight:600; }
    .pay-matrix tbody td.pay-client { font-weight:600; color:var(--text); }
    .pay-matrix tbody td.pay-client a { color:#1e40af; text-decoration:none; }
    .pay-matrix tbody td.pay-client a:hover { text-decoration:underline; }
    .pay-matrix tbody tr:last-child td { border-bottom:0; }
    .pay-cell { text-align:center; min-width:110px; }
    .pay-cell-paid { background:#ecfdf5; color:#065f46; font-weight:600; font-size:12px; padding:6px 10px; border-radius:8px; display:inline-flex; align-items:center; gap:6px; cursor:pointer; }
    .pay-cell-paid svg { width:14px; height:14px; }
    .pay-cell-due button { background:#fff7ed; color:#9a3412; border:1px dashed #fed7aa; font-weight:600; font-size:12px; padding:6px 12px; border-radius:8px; cursor:pointer; }
    .pay-cell-due button:hover { background:#ffedd5; }
    .pay-cell-idle { color:var(--muted); font-size:18px; }

    /* Hourly */
    .pay-block { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:18px 20px; margin-bottom:18px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
    .pay-block-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .pay-block-title { font-size:15px; font-weight:700; color:var(--text); }
    .pay-btn-primary { background:var(--surface); color:#2563eb; border:1px solid #dbeafe; font-size:13px; font-weight:600; padding:7px 14px; border-radius:8px; cursor:pointer; }
    .pay-btn-primary:hover { background:#eff6ff; }
    .pay-time-table { width:100%; border-collapse:collapse; }
    .pay-time-table th, .pay-time-table td { padding:10px 12px; text-align:left; border-bottom:1px solid var(--surface-2); font-size:13px; }
    .pay-time-table thead th { background:var(--surface-2); font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); font-weight:600; }
    .pay-time-table .hours-col { width:90px; font-weight:600; color:var(--text); }
    .pay-time-table .date-col { width:120px; color:var(--muted); }
    .pay-time-table .actions-col { width:90px; text-align:right; }
    .pay-period-current { background: linear-gradient(135deg,#eff6ff,#dbeafe); border:1px solid #bfdbfe; border-radius:10px; padding:10px 14px; margin-bottom:8px; display:flex; align-items:center; gap:14px; }
    .pay-period-current strong { color:#1e40af; }
    .pay-payout-row { padding:12px 0; border-bottom:1px solid var(--surface-2); display:grid; grid-template-columns: 1.8fr 1fr 1fr auto; gap:10px; align-items:center; }
    .pay-payout-row:last-child { border-bottom:0; }
    .pay-payout-paid { color:#059669; font-weight:600; }
    .pay-payout-pending { color:#ea580c; font-weight:600; }
    .pay-empty { padding:30px; text-align:center; color:var(--muted); font-size:14px; }

    @media (max-width: 1100px) {
        .pay-stats, .pay-settings-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .pay-stats, .pay-settings-grid { grid-template-columns: 1fr; }
        .pay-payout-row { grid-template-columns: 1fr 1fr; }
    }
:root[data-theme="dark"] .pay-stat-card,:root[data-theme="dark"] .pay-settings-card,:root[data-theme="dark"] .pay-block{background:var(--pro-card);border-color:var(--pro-line);}
:root[data-theme="dark"] .pay-stat-label,:root[data-theme="dark"] .pay-stat-sub,:root[data-theme="dark"] .pay-empty,:root[data-theme="dark"] .pay-settings-grid label{color:var(--pro-muted);}
:root[data-theme="dark"] .pay-stat-value,:root[data-theme="dark"] .pay-block-title,:root[data-theme="dark"] .pay-matrix tbody td.pay-client{color:var(--pro-text);}
:root[data-theme="dark"] .pay-matrix thead th,:root[data-theme="dark"] .pay-time-table thead th{background:rgba(255,255,255,.04);color:var(--pro-muted);}
:root[data-theme="dark"] .pay-matrix th,:root[data-theme="dark"] .pay-matrix td,:root[data-theme="dark"] .pay-time-table th,:root[data-theme="dark"] .pay-time-table td{border-color:var(--pro-line);}
:root[data-theme="dark"] .pay-settings-grid input,:root[data-theme="dark"] .pay-settings-grid select{background:#10152a;border-color:var(--pro-line);color:var(--pro-text);}
</style>
@endpush

@endsection
