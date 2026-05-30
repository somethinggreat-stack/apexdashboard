@extends('layouts.admin')

@section('title', 'Payments — ' . $client->business_name)

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
    /* === PAYMENTS PAGE SCOPED STYLES ===
       Card recipes, pills, buttons, chips and stat tokens live in admin.css.
       Only page-specific layout + numeric tokens are kept here. */

    /* Stat strip layout (visual styling inherits from .pay-stat-card in admin.css) */
    .pay-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-3); margin: var(--space-2) 0 var(--space-5); }
    .pay-stat-value { font-variant-numeric: tabular-nums; font-feature-settings: "tnum","cv11","ss01"; }
    .pay-stat-green .pay-stat-value  { color: var(--success-700); }
    .pay-stat-orange .pay-stat-value { color: var(--warning-700); }

    /* Settings card — uses the standard pay-block recipe; only layout overrides here */
    .pay-settings-card { margin-bottom: var(--space-4); }
    .pay-settings-head { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); margin-bottom: var(--space-4); }
    .pay-settings-title { display: flex; align-items: center; gap: var(--space-2); font-size: var(--text-md); font-weight: var(--weight-semibold); letter-spacing: -0.01em; color: var(--text); }
    .pay-settings-title svg { color: var(--accent); stroke-width: 1.75; }

    /* Model badge — soft 50-tint bg + 100-tint hairline + 700 text */
    .pay-model-pill { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: var(--radius-full);
        font-size: var(--text-xs); font-weight: var(--weight-semibold); letter-spacing: 0.02em; text-transform: uppercase; }
    .pay-pill-per-round { background: var(--accent-50);  color: var(--accent-700); border: 1px solid var(--accent-100); }
    .pay-pill-hourly    { background: var(--info-50);    color: var(--info-700);   border: 1px solid var(--info-100); }

    .pay-settings-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-3) var(--space-4); align-items: end; }
    .pay-settings-grid label { display: block; margin-bottom: var(--space-1);
        font-size: 10.5px; font-weight: var(--weight-semibold); text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); }
    .pay-settings-grid input,
    .pay-settings-grid select { width: 100%; box-sizing: border-box; padding: 7px 10px;
        border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--surface);
        font: inherit; font-size: var(--text-sm); color: var(--text); transition: border-color var(--dur-fast) var(--ease-out), box-shadow var(--dur-fast) var(--ease-out); }
    .pay-settings-grid input:focus,
    .pay-settings-grid select:focus { outline: none; border-color: var(--accent); box-shadow: var(--shadow-focus); }

    /* Per-round matrix — same card chrome as .pay-block */
    .pay-matrix { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-xs); }
    .pay-matrix table { width: 100%; border-collapse: collapse; }
    .pay-matrix th,
    .pay-matrix td { padding: 11px var(--space-4); text-align: left; border-bottom: 1px solid var(--hairline); font-size: var(--text-sm); }
    .pay-matrix thead th { background: var(--gray-50); color: var(--muted); font-weight: var(--weight-semibold);
        font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em; }
    .pay-matrix tbody td.pay-client { font-weight: var(--weight-semibold); color: var(--text); }
    .pay-matrix tbody td.pay-client a { color: var(--accent); text-decoration: none; }
    .pay-matrix tbody td.pay-client a:hover { color: var(--accent-hover); text-decoration: underline; }
    .pay-matrix tbody tr:last-child td { border-bottom: 0; }
    .pay-matrix tbody tr:hover { background: var(--gray-50); }

    .pay-cell { text-align: center; min-width: 110px; }
    .pay-cell-paid { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
        background: var(--success-50); color: var(--success-700); border: 1px solid var(--success-100);
        border-radius: var(--radius-full); font-size: var(--text-xs); font-weight: var(--weight-semibold);
        font-variant-numeric: tabular-nums; cursor: pointer;
        transition: background var(--dur-fast) var(--ease-out); }
    .pay-cell-paid::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--success-500); }
    .pay-cell-paid:hover { background: var(--success-100); }
    .pay-cell-paid svg { width: 14px; height: 14px; stroke-width: 2; }
    .pay-cell-due button { padding: 4px 12px;
        background: var(--warning-50); color: var(--warning-700); border: 1px dashed var(--warning-100);
        border-radius: var(--radius-full); font: inherit; font-size: var(--text-xs); font-weight: var(--weight-semibold);
        cursor: pointer; transition: background var(--dur-fast) var(--ease-out), border-color var(--dur-fast) var(--ease-out); }
    .pay-cell-due button:hover { background: var(--warning-100); border-style: solid; }
    .pay-cell-idle { color: var(--gray-300); font-size: var(--text-lg); line-height: 1; }

    /* Hourly blocks — base .pay-block lives in admin.css */
    .pay-block { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
        padding: var(--space-5); margin-bottom: var(--space-4); box-shadow: var(--shadow-xs); }
    .pay-block-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4); }
    .pay-block-title { font-size: var(--text-md); font-weight: var(--weight-semibold); letter-spacing: -0.01em; color: var(--text); }

    .pay-btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px;
        background: var(--surface); color: var(--accent); border: 1px solid var(--border);
        border-radius: var(--radius-md); font: inherit; font-size: var(--text-sm); font-weight: var(--weight-semibold);
        cursor: pointer; transition: background var(--dur-fast) var(--ease-out), border-color var(--dur-fast) var(--ease-out), color var(--dur-fast) var(--ease-out); }
    .pay-btn-primary:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

    .pay-time-table { width: 100%; border-collapse: collapse; }
    .pay-time-table th,
    .pay-time-table td { padding: 10px var(--space-3); text-align: left; border-bottom: 1px solid var(--hairline); font-size: var(--text-sm); }
    .pay-time-table thead th { background: var(--gray-50); color: var(--muted); font-weight: var(--weight-semibold);
        font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em; }
    .pay-time-table .hours-col { width: 90px; font-weight: var(--weight-semibold); color: var(--text); font-variant-numeric: tabular-nums; font-family: var(--font-num); }
    .pay-time-table .date-col { width: 120px; color: var(--muted); font-variant-numeric: tabular-nums; }
    .pay-time-table .actions-col { width: 90px; text-align: right; }

    /* Current period banner — flat accent-soft, not gradient */
    .pay-period-current { display: flex; align-items: center; gap: var(--space-4);
        padding: 10px var(--space-4); margin-bottom: var(--space-2);
        background: var(--accent-soft); border: 1px solid var(--accent-100); border-left: 3px solid var(--accent);
        border-radius: var(--radius-md); font-size: var(--text-sm); color: var(--text-soft); }
    .pay-period-current strong { color: var(--accent-700); font-weight: var(--weight-semibold); }

    .pay-payout-row { display: grid; grid-template-columns: 1.8fr 1fr 1fr auto; gap: var(--space-3); align-items: center;
        padding: var(--space-3) 0; border-bottom: 1px solid var(--hairline); font-size: var(--text-sm); font-variant-numeric: tabular-nums; }
    .pay-payout-row:last-child { border-bottom: 0; }
    .pay-payout-paid    { color: var(--success-700); font-weight: var(--weight-semibold); }
    .pay-payout-pending { color: var(--warning-700); font-weight: var(--weight-semibold); }

    .pay-empty { padding: var(--space-8) var(--space-5); text-align: center; color: var(--muted); font-size: var(--text-sm); }

    @media (max-width: 1100px) {
        .pay-stats, .pay-settings-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .pay-stats, .pay-settings-grid { grid-template-columns: 1fr; }
        .pay-payout-row { grid-template-columns: 1fr 1fr; }
    }
</style>
@endpush

@endsection
