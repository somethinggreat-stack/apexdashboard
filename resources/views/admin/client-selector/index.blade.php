@extends('layouts.admin')

@section('title', 'Select Business Owner')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Select a Business Owner to Work On</h2>
        <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">Manage Business Owners</a>
    </div>

    @if ($clients->isEmpty())
        <div class="empty">
            No business owners yet.
            <a href="{{ route('admin.clients.index') }}">Add one to get started.</a>
        </div>
    @else
        <div class="picker-grid">
            @foreach ($clients as $client)
                <form method="POST" action="{{ route('admin.client-selector.select', $client->id) }}" class="picker-card-form">
                    @csrf
                    <button type="submit" class="picker-card">
                        <div class="picker-card-name">{{ $client->business_name }}</div>
                        <div class="picker-card-meta">
                            <span>{{ $client->end_users_count }} clients</span>
                            <span class="pill pill-{{ $client->status }}">{{ $client->status }}</span>
                        </div>
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</div>

@if (!empty($attention))
    @php
        $totNew = array_sum(array_column($attention, 'pending'));
        $totInc = array_sum(array_column($attention, 'incomplete'));
        $totOver = array_sum(array_column($attention, 'overdue'));
    @endphp
    <div class="card" style="margin-top:18px;">
        <div class="card-header">
            <div>
                <h2 style="margin:0;">Needs Attention</h2>
                <p class="muted" style="margin:4px 0 0; font-size:13px;">
                    Across all business owners:
                    <strong>{{ $totNew }}</strong> new intake client{{ $totNew === 1 ? '' : 's' }},
                    <strong>{{ $totInc }}</strong> with incomplete logs,
                    <strong>{{ $totOver }}</strong> overdue round{{ $totOver === 1 ? '' : 's' }}.
                </p>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Business Owner</th>
                    <th>New Intake Clients</th>
                    <th>Incomplete Logs</th>
                    <th>Overdue Rounds</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($attention as $a)
                    <tr>
                        <td><strong>{{ $a['client']->business_name }}</strong></td>
                        <td>@if ($a['pending'])<span class="att-badge att-blue">{{ $a['pending'] }} new</span>@else <span class="muted">—</span>@endif</td>
                        <td>@if ($a['incomplete'])<span class="att-badge att-amber">{{ $a['incomplete'] }} incomplete</span>@else <span class="muted">—</span>@endif</td>
                        <td>@if ($a['overdue'])<span class="att-badge att-red">{{ $a['overdue'] }} overdue</span>@else <span class="muted">—</span>@endif</td>
                        <td class="no-link">
                            <div class="att-actions">
                                @if ($a['pending'])
                                    <form method="POST" action="{{ route('admin.client-selector.select', $a['client']->id) }}">
                                        @csrf
                                        <input type="hidden" name="redirect_to" value="{{ route('admin.new-clients') }}">
                                        <button type="submit" class="btn btn-sm btn-primary">Review New Clients &rarr;</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.client-selector.select', $a['client']->id) }}">
                                    @csrf
                                    <input type="hidden" name="redirect_to" value="{{ route('admin.end-users.index') }}">
                                    <button type="submit" class="btn btn-sm">Open Clients</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@push('head')
<style>
    .att-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
    .att-blue  { background:#e0f2fe; color:#075985; }
    .att-amber { background:#fef3c7; color:#92400e; }
    .att-red   { background:#fee2e2; color:#991b1b; }
    .att-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .att-actions form { display:inline; margin:0; }
    .att-actions .btn { white-space:nowrap; }
</style>
@endpush
@endsection
