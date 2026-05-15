@extends('layouts.client')

@section('title', 'Dashboard')

@section('content')
<div class="welcome">
    <h2>Welcome back, {{ $client->business_name }}</h2>
    <p class="muted">Live monitoring of your VA's credit repair work.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">My Total Clients</div>
        <div class="stat-value">{{ $stats['total_end_users'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Steps This Week</div>
        <div class="stat-value">{{ $stats['steps_this_week'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Deletions</div>
        <div class="stat-value">{{ $stats['total_deletions'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Avg Score Increase</div>
        <div class="stat-value">{{ $stats['avg_score_increase'] > 0 ? '+' : '' }}{{ $stats['avg_score_increase'] }}</div>
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
        <h2>What Your VA Has Been Doing</h2>
        <a href="{{ route('client.end-users.index') }}" class="btn btn-primary">View All My Clients</a>
    </div>
    <table class="data-table">
        <thead><tr><th>Date</th><th>Client</th><th>Round</th><th>Week</th><th>Step Type</th><th>Metrics</th></tr></thead>
        <tbody>
            @forelse ($recentSteps as $step)
                @php
                    $totalDeletions = ($step->experian_accounts_disputed ?? 0) + ($step->transunion_accounts_disputed ?? 0) + ($step->equifax_accounts_disputed ?? 0);
                @endphp
                <tr>
                    <td>{{ $step->step_date?->format('M d, Y') }}</td>
                    <td><a href="{{ route('client.end-users.show', $step->end_user_id) }}">{{ $step->endUser?->full_name ?? '—' }}</a></td>
                    <td>R{{ $step->round }}</td>
                    <td>W{{ $step->week }}</td>
                    <td>{{ $step->step_type_label }}</td>
                    <td class="muted">
                        @if ($totalDeletions > 0) {{ $totalDeletions }} accounts @endif
                        @if ($step->credit_score_now) · score → {{ $step->credit_score_now }} @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No activity yet — your VA will start logging work shortly.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
