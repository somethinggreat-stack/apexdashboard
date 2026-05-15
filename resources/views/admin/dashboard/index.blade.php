@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Clients</div>
        <div class="stat-value">{{ $stats['total_end_users'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Clients</div>
        <div class="stat-value">{{ $stats['active_end_users'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Steps This Week</div>
        <div class="stat-value">{{ $stats['steps_this_week'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Deletions This Week</div>
        <div class="stat-value">{{ $stats['deletions_this_week'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Documents This Week</div>
        <div class="stat-value">{{ $stats['documents_this_week'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Monthly Revenue</div>
        <div class="stat-value">${{ number_format($stats['monthly_revenue'], 2) }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Recent Process Steps</h2>
        <div class="card-actions">
            <a href="{{ route('admin.client-selector.index') }}" class="btn btn-secondary">Switch Business Owner</a>
            <a href="{{ route('admin.end-users.index') }}" class="btn btn-primary">View Clients</a>
        </div>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Client</th>
                <th>Round</th>
                <th>Week</th>
                <th>Step Type</th>
                <th>Metrics</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recentSteps as $step)
                @php
                    $totalDeletions = ($step->experian_accounts_disputed ?? 0) + ($step->transunion_accounts_disputed ?? 0) + ($step->equifax_accounts_disputed ?? 0);
                @endphp
                <tr>
                    <td>{{ $step->step_date?->format('M d, Y') }}</td>
                    <td><a href="{{ route('admin.end-users.show', $step->end_user_id) }}">{{ $step->endUser?->full_name ?? '—' }}</a></td>
                    <td>R{{ $step->round }}</td>
                    <td>W{{ $step->week }}</td>
                    <td>{{ $step->step_type_label }}</td>
                    <td class="muted">
                        @if ($totalDeletions > 0) {{ $totalDeletions }} accounts @endif
                        @if ($step->credit_score_now) · score → {{ $step->credit_score_now }} @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No process steps logged yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
