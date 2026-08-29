@extends('layouts.admin-pro')

@php
    $isDone = ($bucket ?? 'in_progress') === 'clients';
    $isSuper = Auth::guard('admin')->user()?->isSuper();
    $statusOptions = ['active','paused','graduated','cancelled'];

    // avatar tint, stable per client name
    $tints = [
        ['#e0e7ff','#4338ca'], ['#d1fae5','#047857'], ['#fee2e2','#b91c1c'],
        ['#dbeafe','#1d4ed8'], ['#fef3c7','#b45309'], ['#ede9fe','#6d28d9'],
        ['#ccfbf1','#0f766e'], ['#ffe4e6','#be123c'],
    ];

    $sortLink = function (string $key) {
        $dir = (request('sort') === $key && request('dir', 'asc') === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $key, 'dir' => $dir, 'page' => null]);
    };
    $curSort = request('sort');
@endphp

@section('title', $isDone ? 'Clients' : 'In Progress')
@section('subtitle', 'Manage and monitor all your clients in one place.')

@section('topbar-action')
    @if ($isSuper)
        <form method="POST" action="{{ route('admin.end-users.clear-incomplete') }}"
              data-confirm-action
              data-confirm-title="Mark all incomplete logs complete?"
              data-confirm-message="This logs the missing weekly steps for every flagged client of this business owner. It will NEVER log Pull Latest Report or Record Deletions."
              data-confirm-ok="Mark all complete">
            @csrf
            <button type="submit" class="btn btn-secondary"
                    title="Log the missing weekly steps for every flagged client — never the closeout steps">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span>Mark All Incomplete Complete</span>
            </button>
        </form>
    @endif
    @if ($isDone && $isSuper)
        <a href="{{ route('admin.client-list.credit-monitoring-export') }}" class="btn btn-secondary"
           title="Download all credit-monitoring logins for this business owner as a CSV">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span>Export Credit Monitoring Logins</span>
        </a>
        <a href="{{ route('admin.client-list.cfpb-export') }}" class="btn btn-secondary"
           title="Download all CFPB logins for this business owner as a CSV">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span>Export CFPB Logins</span>
        </a>
    @endif
    <a href="{{ route('admin.end-users.create') }}" class="pro-cta">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>Add New Client</span>
    </a>
@endsection

@section('content')

@php
    $pct = fn ($n) => $stats['total'] ? round($n / $stats['total'] * 100, 1) . '% of total' : '0% of total';
@endphp
<div data-clients-refresh>
<div class="pro-stats">
    <div class="pro-stat s-indigo">
        <span class="pro-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </span>
        <span class="pro-stat-text">
            <span class="lbl">Total Clients</span>
            <span class="val">{{ $stats['total'] }}</span>
            <span class="sub">{{ $isDone ? 'In the Clients list' : 'In progress' }}</span>
        </span>
    </div>

    <div class="pro-stat s-green">
        <span class="pro-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8.5,12.5 11,15 16,9.5"/></svg>
        </span>
        <span class="pro-stat-text">
            <span class="lbl">Active Clients</span>
            <span class="val">{{ $stats['active'] }}</span>
            <span class="sub">{{ $pct($stats['active']) }}</span>
        </span>
    </div>

    <div class="pro-stat s-amber">
        <span class="pro-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="10" y1="9" x2="10" y2="15"/><line x1="14" y1="9" x2="14" y2="15"/></svg>
        </span>
        <span class="pro-stat-text">
            <span class="lbl">Paused Clients</span>
            <span class="val">{{ $stats['paused'] }}</span>
            <span class="sub">{{ $pct($stats['paused']) }}</span>
        </span>
    </div>

    <div class="pro-stat s-red">
        <span class="pro-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </span>
        <span class="pro-stat-text">
            <span class="lbl">Negative Days</span>
            <span class="val">{{ $stats['negative'] }}</span>
            <span class="sub">Need attention</span>
        </span>
    </div>

    <div class="pro-stat s-blue">
        <span class="pro-stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><line x1="3" y1="9.5" x2="21" y2="9.5"/><line x1="8" y1="2.5" x2="8" y2="6"/><line x1="16" y1="2.5" x2="16" y2="6"/></svg>
        </span>
        <span class="pro-stat-text">
            <span class="lbl">Avg. Days Left</span>
            <span class="val">{{ $stats['avg_days'] }}</span>
            <span class="sub">Across all clients</span>
        </span>
    </div>
</div>

<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h11l5 5v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><polyline points="14,4 14,10 20,10"/></svg>
            </span>
            <h2>{{ $isDone ? 'Clients' : 'In Progress' }} — {{ $selectedClient->business_name }}</h2>
            <span class="pro-panel-count">{{ $endUsers->total() }}</span>
        </div>

        <form method="GET" class="pro-filters">
            @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
            @if (request('dir')) <input type="hidden" name="dir" value="{{ request('dir') }}"> @endif
            <label class="pro-select">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22,3 2,3 10,12.5 10,19 14,21 14,12.5"/></svg>
                <select name="status">
                    <option value="">All Statuses</option>
                    @foreach ($statusOptions as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <svg class="chev" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="6,9 12,15 18,9"/></svg>
            </label>

            <label class="pro-search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search client...">
            </label>

            <button class="pro-filter-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22,3 2,3 10,12.5 10,19 14,21 14,12.5"/></svg>
                Filter
            </button>
        </form>
    </div>

    <div class="pro-table-scroll">
        <table class="pro-table" data-clients-table>
            <thead>
                <tr>
                    @php
                        $cols = [
                            'name'    => 'Client Name',
                            'round'   => 'Round',
                            'started' => 'Round Started',
                            'next'    => 'Next Round Date',
                            'days'    => 'Days Left',
                            'status'  => 'Status',
                            'progress'=> 'Progress',
                        ];
                    @endphp
                    @foreach ($cols as $key => $label)
                        <th>
                            <a href="{{ $sortLink($key) }}">
                                {{ $label }}
                                <svg class="pro-sort {{ $curSort === $key ? 'on' : '' }}" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <polygon points="12,3 17,9 7,9"/><polygon points="12,21 17,15 7,15"/>
                                </svg>
                            </a>
                        </th>
                    @endforeach
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($endUsers as $eu)
                    @php
                        $t  = $tints[crc32($eu->full_name) % count($tints)];
                        $dl = $eu->days_left_in_round;
                        $p  = $eu->progress_percent;
                        $barClass = $p >= 70 ? 'hi' : ($p >= 40 ? 'mid' : 'lo');
                    @endphp
                    <tr data-href="{{ route('admin.end-users.show', $eu) }}">
                        <td>
                            <div class="pro-name">
                                <span class="pro-avatar" style="background:{{ $t[0] }}; color:{{ $t[1] }};">
                                    {{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}
                                </span>
                                <div>
                                    <a href="{{ route('admin.end-users.show', $eu) }}">{{ $eu->full_name }}</a>
                                    @if ($eu->is_incomplete)
                                        @php $inc = $eu->incompleteTarget(); @endphp
                                        <button type="button" class="pro-flag"
                                                title="{{ $eu->incomplete_reason }} — click to log"
                                                onclick="openQuickLog({{ $eu->id }}, '{{ addslashes($eu->full_name) }}', {{ $inc['week'] }}, {{ $eu->current_round }}, {{ $eu->roundCycleDays() }}, {{ \Illuminate\Support\Js::from($inc['steps']) }})">
                                            Incomplete · log
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="inline-edit inline-edit-round pro-rounds"
                                  data-id="{{ $eu->id }}"
                                  data-name="{{ $eu->full_name }}"
                                  data-current="{{ json_encode($eu->rounds ?? []) }}">
                                {{ $eu->started_rounds_full ?: 'Not started' }}
                                <span class="inline-pencil" aria-hidden="true">✎</span>
                            </span>
                        </td>

                        <td>
                            <span class="inline-edit inline-edit-round-started"
                                  data-id="{{ $eu->id }}"
                                  data-name="{{ $eu->full_name }}"
                                  data-title="Edit {{ $eu->current_round_label }} start date"
                                  data-current="{{ $eu->current_round_start_date ?? '' }}"
                                  title="Edit {{ $eu->current_round_label }} start date">
                                <span class="pro-round-dates">
                                    @forelse ($eu->round_timeline as $label => $date)
                                        <div>
                                            <b>{{ \Illuminate\Support\Str::before($label, ' Round') }}</b>
                                            <span>{{ $date ? \Carbon\Carbon::parse($date)->format('M j, Y') : '—' }}</span>
                                        </div>
                                    @empty
                                        —
                                    @endforelse
                                </span>
                                <span class="inline-pencil" aria-hidden="true">✎</span>
                            </span>
                        </td>

                        <td>
                            <span class="inline-edit inline-edit-next"
                                  data-id="{{ $eu->id }}"
                                  data-name="{{ $eu->full_name }}"
                                  data-current="{{ $eu->next_round_date ?? '' }}"
                                  title="Edit next round date{{ $eu->next_round_override ? ' (manually set)' : '' }}">
                                <span class="pro-next {{ $dl !== null && $dl < 0 ? 'over' : '' }}">
                                    {{ $eu->next_round_date ? \Carbon\Carbon::parse($eu->next_round_date)->format('M j, Y') : '—' }}
                                </span>
                                <span class="inline-pencil" aria-hidden="true">✎</span>
                            </span>
                        </td>

                        <td>
                            <span class="pro-days {{ $dl !== null && $dl < 0 ? 'over' : ($dl !== null && $dl <= 3 ? 'soon' : '') }}"
                                  title="{{ $eu->round_end_date ? 'Current round ends '.\Carbon\Carbon::parse($eu->round_end_date)->format('M j, Y') : '' }}">
                                {{ $dl === null ? '—' : $dl }}
                            </span>
                        </td>

                        <td>
                            <span class="inline-edit inline-edit-status"
                                  data-id="{{ $eu->id }}"
                                  data-name="{{ $eu->full_name }}"
                                  data-current="{{ $eu->status }}">
                                <span class="pro-pill {{ $eu->status }}">{{ $eu->status }}</span>
                                <span class="inline-pencil" aria-hidden="true">✎</span>
                            </span>
                        </td>

                        <td>
                            <div class="pro-progress" title="Steps logged in round {{ $eu->current_round }}">
                                <span class="pct">{{ $p }}%</span>
                                <span class="track"><span class="bar {{ $barClass }}" style="width: {{ max($p, 2) }}%;"></span></span>
                            </div>
                        </td>

                        <td>
                            <div class="pro-actions">
                                <a href="{{ route('admin.end-users.show', $eu) }}" class="pro-act view">Open</a>

                                @unless ($isDone)
                                    <form method="POST" action="{{ route('admin.end-users.to-done', $eu->id) }}"
                                          data-confirm-action
                                          data-confirm-title="Move to Clients?"
                                          data-confirm-message="{{ $eu->full_name }} will move to the Clients list and the round clock starts today."
                                          data-confirm-ok="Move to Clients">
                                        @csrf
                                        <button class="pro-act done">Move to Clients</button>
                                    </form>
                                @endunless

                                @if ($isDone)
                                    {{-- Clients list (past round 1): a later-round problem goes to Round Errors with a type + reason. --}}
                                    <button type="button" class="pro-act warn"
                                            onclick="openRoundError({{ $eu->id }}, '{{ addslashes($eu->full_name) }}')">Move to Round Errors</button>
                                @else
                                    {{-- In Progress (1st round): a new-client problem goes to New Client Errors. --}}
                                    <form method="POST" action="{{ route('admin.end-users.to-errors', $eu->id) }}" class="send-back-form">
                                        @csrf
                                        <input type="hidden" name="note" value="">
                                        <button type="button" class="pro-act warn" onclick="moveToErrors(this, '{{ addslashes($eu->full_name) }}')">Move to Errors</button>
                                    </form>
                                @endif

                                <button type="button" class="pro-act move"
                                        onclick="openMoveReason({{ $eu->id }}, '{{ addslashes($eu->full_name) }}', 'new-clients')">Move to New Clients</button>

                                <button type="button" class="pro-act hold"
                                        onclick="openMoveReason({{ $eu->id }}, '{{ addslashes($eu->full_name) }}', 'hold')">Hold/Pause</button>

                                <form method="POST" action="{{ route('admin.end-users.destroy', $eu) }}"
                                      data-confirm-delete
                                      data-confirm-title="Delete this client?"
                                      data-confirm-message="{{ $eu->full_name }} and all their documents will be moved to the Recycle Bin. You can restore them there for 10 days.">
                                    @csrf @method('DELETE')
                                    <button class="pro-act del">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No clients found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($endUsers->total() > 0)
        <div class="pro-foot">
            <div class="pro-foot-text">
                Showing <b>{{ $endUsers->firstItem() }}</b> to <b>{{ $endUsers->lastItem() }}</b>
                of <b>{{ $endUsers->total() }}</b> clients
            </div>
            @if ($endUsers->hasPages())
                <div class="pro-pager">
                    @if ($endUsers->onFirstPage())
                        <span class="off">‹</span>
                    @else
                        <a href="{{ $endUsers->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>
                    @endif

                    @foreach ($endUsers->getUrlRange(max(1, $endUsers->currentPage() - 2), min($endUsers->lastPage(), $endUsers->currentPage() + 2)) as $page => $url)
                        @if ($page == $endUsers->currentPage())
                            <span class="on">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($endUsers->hasMorePages())
                        <a href="{{ $endUsers->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>
                    @else
                        <span class="off">›</span>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>

</div>{{-- /data-clients-refresh --}}
@include('admin.end-users._list-modals')
@include('admin.end-users._list-scripts')
@endsection
