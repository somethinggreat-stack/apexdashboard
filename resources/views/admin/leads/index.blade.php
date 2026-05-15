<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Lead Dashboard | Apex Growth Systems</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
  --white:#FFFFFF; --paper:#F8FAFC; --ivory:#F1F5F9; --bone:#E2E8F0;
  --ink:#0F2043; --charcoal:#1E3A5F; --smoke:#475569; --ash:#64748B; --dust:#94A3B8;
  --blue:#1A6FC4; --blue-light:#2196F3; --blue-deep:#0F2043; --blue-soft:#DBEAFE;
  --green:#16A34A; --green-soft:#F0FDF4;
  --amber:#D97706; --amber-soft:#FFFBEB;
  --red:#DC2626; --red-soft:#FEF2F2;
  --purple:#7C3AED; --purple-soft:#F3E8FF;
  --display:'Geist',-apple-system,BlinkMacSystemFont,sans-serif;
  --mono:'IBM Plex Mono','SF Mono',monospace;
  --ease:cubic-bezier(0.22,1,0.36,1);
}
* { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body {
  font-family:var(--display);
  background:var(--paper);
  color:var(--ink);
  line-height:1.5;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}
a { color:inherit; text-decoration:none; }
button { font-family:inherit; cursor:pointer; border:none; background:none; color:inherit; }

.dash-shell { max-width:1320px; margin:0 auto; padding:32px 28px 64px; }

/* Header */
.dash-head {
  display:flex; align-items:center; justify-content:space-between;
  gap:16px; margin-bottom:32px; flex-wrap:wrap;
}
.dash-brand { display:flex; align-items:center; gap:12px; }
.dash-brand-mark {
  width:40px; height:40px;
  display:grid; place-items:center;
  background:linear-gradient(135deg,var(--blue-light),var(--blue),var(--blue-deep));
  color:#fff; border-radius:10px;
  font-weight:700; font-size:18px; letter-spacing:-0.02em;
  box-shadow:0 8px 24px rgba(26,111,196,0.25);
}
.dash-brand-text strong{
  display:block; font-size:15px; font-weight:600;
  letter-spacing:-0.01em; color:var(--ink);
}
.dash-brand-text span{
  font-family:var(--mono); font-size:10px;
  letter-spacing:0.14em; text-transform:uppercase;
  color:var(--ash);
}
.dash-actions { display:flex; gap:10px; align-items:center; }
.dash-link {
  font-size:13px; color:var(--smoke);
  padding:8px 14px; border-radius:8px;
  transition:all 0.25s var(--ease);
}
.dash-link:hover { color:var(--ink); background:var(--ivory); }
.dash-btn {
  display:inline-flex; align-items:center; gap:8px;
  padding:9px 16px;
  background:var(--ink); color:#fff;
  border-radius:8px; font-size:13px; font-weight:500;
  transition:all 0.25s var(--ease);
  font-family:inherit;
}
.dash-btn:hover { background:var(--blue); transform:translateY(-1px); }
.dash-btn.outline {
  background:transparent; color:var(--smoke); border:1px solid var(--bone);
}
.dash-btn.outline:hover { background:var(--ivory); color:var(--ink); }
.dash-user {
  display:flex; align-items:center; gap:8px;
  padding:6px 12px 6px 8px;
  background:#fff;
  border:1px solid var(--bone);
  border-radius:100px;
  font-size:12px; color:var(--smoke);
}
.dash-user-avatar{
  width:24px; height:24px; border-radius:50%;
  background:linear-gradient(135deg,var(--blue-soft),#fff);
  border:1px solid var(--bone); color:var(--blue);
  display:grid; place-items:center;
  font-size:10px; font-weight:600;
}

.dash-title h1 {
  font-size:30px; font-weight:600; letter-spacing:-0.025em;
  color:var(--ink); margin-bottom:6px;
}
.dash-title p { font-size:14px; color:var(--ash); }
.dash-title { margin-bottom:28px; }

/* Stat cards */
.dash-stats {
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:16px; margin-bottom:24px;
}
.stat-card {
  background:#fff;
  border:1px solid var(--bone);
  border-radius:14px;
  padding:20px 22px;
  position:relative; overflow:hidden;
  transition:all 0.3s var(--ease);
}
.stat-card:hover {
  border-color:#cfdcec;
  box-shadow:0 12px 32px rgba(15,32,67,0.06);
  transform:translateY(-2px);
}
.stat-card::before{
  content:''; position:absolute; top:0; left:0; right:0; height:3px;
  background:linear-gradient(90deg,var(--blue-light),var(--blue));
  opacity:0.85;
}
.stat-card.amber::before { background:linear-gradient(90deg,#F59E0B,#D97706); }
.stat-card.green::before { background:linear-gradient(90deg,#22C55E,#16A34A); }
.stat-card.red::before   { background:linear-gradient(90deg,#F87171,#DC2626); }
.stat-card.purple::before{ background:linear-gradient(90deg,#A78BFA,#7C3AED); }
.stat-card .stat-label{
  font-family:var(--mono); font-size:10px;
  letter-spacing:0.14em; text-transform:uppercase;
  color:var(--ash); margin-bottom:10px;
}
.stat-card .stat-value{
  font-size:30px; font-weight:600; letter-spacing:-0.03em;
  color:var(--ink); line-height:1;
}
.stat-card .stat-sub{ margin-top:6px; font-size:12px; color:var(--ash); }

.source-stats{
  display:grid; grid-template-columns:repeat(2,minmax(0,1fr));
  gap:16px; margin-bottom:28px;
}

/* Charts row */
.dash-charts {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:16px; margin-bottom:28px;
}
.chart-card {
  background:#fff;
  border:1px solid var(--bone);
  border-radius:14px;
  padding:22px 24px;
}
.chart-card h3 {
  font-size:13px; font-weight:600; color:var(--ink);
  margin-bottom:18px;
  display:flex; align-items:center; justify-content:space-between;
}
.chart-card h3 small {
  font-family:var(--mono); font-size:10px;
  letter-spacing:0.12em; text-transform:uppercase;
  color:var(--ash); font-weight:500;
}
.bar-row {
  display:flex; align-items:center; gap:12px;
  margin-bottom:11px; font-size:13px;
}
.bar-row:last-child { margin-bottom:0; }
.bar-label {
  flex:0 0 130px;
  color:var(--smoke); font-size:12px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.bar-track {
  flex:1; height:8px;
  background:var(--ivory); border-radius:100px; overflow:hidden;
}
.bar-fill {
  height:100%;
  background:linear-gradient(90deg,var(--blue-light),var(--blue));
  border-radius:100px;
  transition:width 0.6s var(--ease);
}
.bar-count {
  flex:0 0 36px;
  text-align:right;
  font-family:var(--mono); font-size:12px;
  color:var(--ink); font-weight:500;
}
.chart-empty {
  text-align:center; padding:24px 0;
  font-size:13px; color:var(--dust);
}

/* Tabs */
.tabs{
  display:flex; gap:6px;
  background:#fff;
  border:1px solid var(--bone);
  border-radius:12px;
  padding:5px;
  margin-bottom:16px;
  overflow-x:auto;
}
.tab{
  flex:1 0 auto;
  padding:10px 16px;
  font-family:inherit; font-size:13px; font-weight:500;
  color:var(--smoke);
  background:transparent;
  border-radius:8px;
  transition:all 0.25s var(--ease);
  display:inline-flex; align-items:center; gap:8px;
  justify-content:center;
  white-space:nowrap;
}
.tab:hover{ color:var(--ink); background:var(--ivory); }
.tab.active{
  background:linear-gradient(135deg,var(--blue-light),var(--blue));
  color:#fff;
  box-shadow:0 6px 16px rgba(26,111,196,0.25);
}
.tab .tab-count{
  font-family:var(--mono); font-size:10px; letter-spacing:0.06em;
  background:rgba(15,32,67,0.08); color:inherit;
  padding:2px 7px; border-radius:100px;
}
.tab.active .tab-count{ background:rgba(255,255,255,0.22); }

/* Leads table */
.leads-card {
  background:#fff;
  border:1px solid var(--bone);
  border-radius:14px;
  overflow:hidden;
}
.leads-head {
  display:flex; align-items:center; justify-content:space-between;
  gap:14px;
  padding:18px 24px;
  border-bottom:1px solid var(--bone);
}
.leads-head h3 { font-size:14px; font-weight:600; color:var(--ink); }
.leads-head .count {
  font-family:var(--mono); font-size:11px;
  letter-spacing:0.1em;
  color:var(--blue);
  background:var(--blue-soft);
  padding:4px 10px; border-radius:100px;
}
.leads-search {
  display:flex; align-items:center; gap:8px;
  padding:10px 14px;
  background:var(--paper);
  border:1px solid var(--bone);
  border-radius:8px;
  width:280px; max-width:50vw;
  transition:all 0.25s var(--ease);
}
.leads-search:focus-within {
  border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(26,111,196,0.1);
  background:#fff;
}
.leads-search input {
  flex:1; border:none; outline:none; background:transparent;
  font-family:inherit; font-size:13px; color:var(--ink);
}
.leads-search input::placeholder { color:var(--dust); }
.leads-search-icon { color:var(--dust); font-size:14px; }

.leads-table-wrap { overflow-x:auto; }
table.leads-table {
  width:100%; border-collapse:collapse;
  font-size:13px;
}
.leads-table thead th {
  text-align:left;
  font-family:var(--mono);
  font-size:10px; letter-spacing:0.12em; text-transform:uppercase;
  color:var(--ash); font-weight:500;
  padding:12px 24px;
  background:var(--paper);
  border-bottom:1px solid var(--bone);
  white-space:nowrap;
}
.leads-table tbody td {
  padding:14px 24px;
  border-bottom:1px solid var(--ivory);
  vertical-align:middle;
}
.leads-table tbody tr {
  transition:background 0.2s ease;
  cursor:pointer;
}
.leads-table tbody tr:hover { background:var(--paper); }
.leads-table tbody tr:last-child td { border-bottom:none; }
.leads-table tbody tr.expanded { background:var(--paper); }
.leads-table tbody tr.detail-row { cursor:default; }
.leads-table tbody tr.detail-row:hover { background:var(--paper); }
.leads-table tbody tr.detail-row td{
  padding:0 24px 18px;
  border-bottom:1px solid var(--ivory);
}
.detail-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
  gap:12px;
  background:#fff;
  border:1px solid var(--bone);
  border-radius:10px;
  padding:16px 18px;
}
.detail-grid .item{
  display:flex; flex-direction:column; gap:4px;
}
.detail-grid .key{
  font-family:var(--mono); font-size:10px;
  letter-spacing:0.12em; text-transform:uppercase;
  color:var(--ash);
}
.detail-grid .val{
  font-size:13px; color:var(--ink); word-break:break-word;
}
.detail-grid .full{ grid-column:1/-1; }
.detail-grid .val.message{
  background:var(--paper);
  border-left:3px solid var(--blue);
  padding:10px 14px; border-radius:6px;
  white-space:pre-wrap;
}

.lead-name {
  font-weight:500; color:var(--ink);
  display:flex; align-items:center; gap:10px;
}
.lead-avatar {
  width:30px; height:30px; border-radius:50%;
  display:grid; place-items:center;
  background:linear-gradient(135deg,var(--blue-soft),#fff);
  color:var(--blue);
  font-size:11px; font-weight:600;
  border:1px solid var(--bone);
  flex-shrink:0;
}
.lead-meta {
  font-size:12px; color:var(--ash);
}
.lead-meta a:hover { color:var(--blue); }

.tag {
  display:inline-block;
  font-family:var(--mono);
  font-size:10px; letter-spacing:0.06em;
  padding:3px 9px; border-radius:4px;
  background:var(--ivory); color:var(--smoke);
  font-weight:500;
  white-space:nowrap;
}
.tag.urgent { background:var(--red-soft); color:var(--red); }
.tag.warm   { background:var(--amber-soft); color:var(--amber); }
.tag.cool   { background:var(--green-soft); color:var(--green); }
.tag.score-low  { background:var(--red-soft); color:var(--red); }
.tag.score-mid  { background:var(--amber-soft); color:var(--amber); }
.tag.score-high { background:var(--green-soft); color:var(--green); }
.tag.type-popup   { background:var(--blue-soft); color:var(--blue); }
.tag.type-contact { background:var(--purple-soft); color:var(--purple); }

.lead-when {
  font-family:var(--mono);
  font-size:11px; color:var(--ash);
  white-space:nowrap;
}

.empty-state {
  padding:64px 24px; text-align:center;
}
.empty-state .empty-icon {
  width:56px; height:56px;
  margin:0 auto 16px;
  display:grid; place-items:center;
  background:var(--blue-soft); color:var(--blue);
  border-radius:50%;
  font-size:24px;
}
.empty-state h4 {
  font-size:16px; font-weight:600; color:var(--ink);
  margin-bottom:6px;
}
.empty-state p {
  font-size:13px; color:var(--ash);
  max-width:340px; margin:0 auto;
}

/* Responsive */
@media (max-width:1024px) {
  .dash-stats, .source-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .dash-charts { grid-template-columns:1fr; }
}
@media (max-width:640px) {
  .dash-shell { padding:20px 16px 48px; }
  .dash-title h1 { font-size:24px; }
  .leads-head { flex-direction:column; align-items:stretch; gap:12px; }
  .leads-search { width:100%; max-width:none; }
  .leads-table thead { display:none; }
  .leads-table tbody td { padding:10px 16px; }
}
</style>
</head>
<body>
<div class="dash-shell">

  <!-- Header -->
  <div class="dash-head">
    <div class="dash-brand">
      <div class="dash-brand-mark">A</div>
      <div class="dash-brand-text">
        <strong>Apex Growth Systems</strong>
        <span>Admin Console</span>
      </div>
    </div>
    <div class="dash-actions">
      <div class="dash-user">
        <span class="dash-user-avatar">{{ strtoupper(substr(Auth::guard('admin')->user()->full_name ?? 'A',0,1)) }}</span>
        <span>{{ Auth::guard('admin')->user()->email }}</span>
      </div>
      <a href="/" class="dash-link">View Site</a>
      <a href="{{ route('admin.client-selector.index') }}" class="dash-btn outline">VA Console</a>
      <a href="{{ route('admin.leads.index') }}" class="dash-btn outline">
        <span>&#x21bb;</span><span>Refresh</span>
      </a>
      <form method="POST" action="{{ route('admin.logout') }}" style="margin:0;">
        @csrf
        <button type="submit" class="dash-btn">Sign Out</button>
      </form>
    </div>
  </div>

  <!-- Page title -->
  <div class="dash-title">
    <h1>Captured Leads</h1>
    <p>All submissions from across the site &mdash; popup and contact form.</p>
  </div>

  <!-- Top stats -->
  <div class="dash-stats">
    <div class="stat-card">
      <div class="stat-label">Total Leads</div>
      <div class="stat-value">{{ number_format($stats['total']) }}</div>
      <div class="stat-sub">All sources, all time</div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Today</div>
      <div class="stat-value">{{ number_format($stats['today']) }}</div>
      <div class="stat-sub">Since midnight</div>
    </div>
    <div class="stat-card amber">
      <div class="stat-label">Last 7 Days</div>
      <div class="stat-value">{{ number_format($stats['last_7_days']) }}</div>
      <div class="stat-sub">Rolling week</div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Urgent (ASAP)</div>
      <div class="stat-value">{{ number_format($stats['urgent']) }}</div>
      <div class="stat-sub">Popup &mdash; immediate</div>
    </div>
  </div>

  <!-- Per-source -->
  <div class="source-stats">
    <div class="stat-card">
      <div class="stat-label">Popup Leads</div>
      <div class="stat-value">{{ number_format($stats['popup']) }}</div>
      <div class="stat-sub">Qualifying popup</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Contact Form</div>
      <div class="stat-value">{{ number_format($stats['contact']) }}</div>
      <div class="stat-sub">From /contact</div>
    </div>
  </div>

  <!-- Charts -->
  <div class="dash-charts">
    <div class="chart-card">
      <h3>Where They're Drowning <small>Most-requested fulfillment workstreams</small></h3>
      @php
        $goalLabels = [
          'remove-negatives' => 'Letter Prep',
          'buy-home'         => 'Bureau Follow-Up Calls',
          'get-funding'      => 'CFPB / FTC Documentation',
          'lower-rates'      => 'Weekly Reporting',
          'build-credit'     => 'Full Fulfillment Handoff',
        ];
        $maxGoal = $goalCounts->max() ?: 1;
      @endphp
      @forelse($goalCounts as $key => $count)
        <div class="bar-row">
          <div class="bar-label">{{ $goalLabels[$key] ?? $key }}</div>
          <div class="bar-track">
            <div class="bar-fill" style="width: {{ ($count / $maxGoal) * 100 }}%"></div>
          </div>
          <div class="bar-count">{{ $count }}</div>
        </div>
      @empty
        <div class="chart-empty">No goal data yet</div>
      @endforelse
    </div>

    <div class="chart-card">
      <h3>Active Client Load <small>Self-reported</small></h3>
      @php
        $scoreLabels = [
          '300-449'  => '1-25 active clients',
          '450-549'  => '26-75 active clients',
          '550-649'  => '76-200 active clients',
          '650-749'  => '200+ active clients',
          '750+'     => 'Just starting',
          'not-sure' => 'Not sure',
        ];
        $maxScore = $scoreCounts->max() ?: 1;
      @endphp
      @forelse($scoreCounts as $key => $count)
        <div class="bar-row">
          <div class="bar-label">{{ $scoreLabels[$key] ?? $key }}</div>
          <div class="bar-track">
            <div class="bar-fill" style="width: {{ ($count / $maxScore) * 100 }}%"></div>
          </div>
          <div class="bar-count">{{ $count }}</div>
        </div>
      @empty
        <div class="chart-empty">No score data yet</div>
      @endforelse
    </div>
  </div>

  <!-- Type tabs -->
  @php
    $base = route('admin.leads.index');
    $tabDefs = [
      'all'     => ['All Sources',    $stats['total']],
      'popup'   => ['Popup',          $stats['popup']],
      'contact' => ['Contact Form',   $stats['contact']],
    ];
  @endphp
  <div class="tabs">
    @foreach($tabDefs as $key => $def)
      <a href="{{ $base }}{{ $key === 'all' ? '' : '?type=' . $key }}"
         class="tab {{ $activeType === $key ? 'active' : '' }}">
        <span>{{ $def[0] }}</span>
        <span class="tab-count">{{ $def[1] }}</span>
      </a>
    @endforeach
  </div>

  <!-- Leads table -->
  <div class="leads-card">
    <div class="leads-head">
      <div style="display:flex; align-items:center; gap:14px;">
        <h3>
          @if($activeType === 'all') Recent Leads
          @elseif($activeType === 'popup') Popup Submissions
          @elseif($activeType === 'contact') Contact Form Messages
          @endif
        </h3>
        <span class="count">{{ $leads->count() }} shown</span>
      </div>
      <div class="leads-search">
        <span class="leads-search-icon">&#128269;</span>
        <input type="text" id="leadFilter" placeholder="Search name, email, phone, message…">
      </div>
    </div>

    @if($leads->isEmpty())
      <div class="empty-state">
        <div class="empty-icon">&#128229;</div>
        <h4>No leads in this view</h4>
        <p>Submissions from this source will appear here as soon as visitors complete the form.</p>
      </div>
    @else
      <div class="leads-table-wrap">
        <table class="leads-table" id="leadsTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>Contact</th>
              <th>Source</th>
              <th>Highlight</th>
              <th>Page</th>
              <th>When</th>
            </tr>
          </thead>
          <tbody>
            @php
              $urgencyMap = [
                'asap'       => ['label' => 'Immediately',    'class' => 'urgent'],
                'this-week'  => ['label' => 'This week',      'class' => 'warm'],
                'this-month' => ['label' => 'Within 30 days', 'class' => 'warm'],
                'exploring'  => ['label' => 'Exploring',      'class' => 'cool'],
              ];
              $scoreClass = function ($s) {
                if (in_array($s, ['300-449','450-549'])) return 'score-low';
                if ($s === '550-649')                    return 'score-mid';
                if (in_array($s, ['650-749','750+']))    return 'score-high';
                return '';
              };
              $typeLabel = [
                'popup'   => 'Popup',
                'contact' => 'Contact',
              ];
            @endphp
            @foreach($leads as $lead)
              @php
                $initials = strtoupper(
                  substr($lead->first_name, 0, 1) . substr($lead->last_name, 0, 1)
                ) ?: 'L';
                $urg = $urgencyMap[$lead->urgency] ?? null;
              @endphp
              <tr data-row="{{ $lead->id }}" onclick="toggleDetail({{ $lead->id }})">
                <td>
                  <div class="lead-name">
                    <span class="lead-avatar">{{ $initials }}</span>
                    <span>{{ $lead->fullName() ?: '—' }}</span>
                  </div>
                </td>
                <td class="lead-meta">
                  <div><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></div>
                  @if($lead->phone)
                    <div><a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a></div>
                  @endif
                </td>
                <td>
                  <span class="tag type-{{ $lead->type }}">
                    {{ $typeLabel[$lead->type] ?? ucfirst($lead->type) }}
                  </span>
                </td>
                <td>
                  @if($lead->type === 'popup')
                    @if($lead->score)
                      <span class="tag {{ $scoreClass($lead->score) }}">
                        {{ $scoreLabels[$lead->score] ?? $lead->score }}
                      </span>
                    @endif
                    @if($urg)
                      <span class="tag {{ $urg['class'] }}">{{ $urg['label'] }}</span>
                    @endif
                    @if(!$lead->score && !$urg)
                      <span class="tag">—</span>
                    @endif
                  @elseif($lead->type === 'contact')
                    <span class="tag">{{ $lead->subject ?: '—' }}</span>
                  @else
                    <span class="tag">—</span>
                  @endif
                </td>
                <td class="lead-meta" style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                  {{ $lead->source_page ?: '—' }}
                </td>
                <td class="lead-when">
                  <div>{{ $lead->created_at->diffForHumans() }}</div>
                  <div style="color:var(--dust); font-size:10px;">{{ $lead->created_at->format('M j, Y g:ia') }}</div>
                </td>
              </tr>
              <tr class="detail-row" id="detail-{{ $lead->id }}" style="display:none;">
                <td colspan="6">
                  <div class="detail-grid">
                    <div class="item">
                      <span class="key">Lead ID</span>
                      <span class="val">#{{ $lead->id }}</span>
                    </div>
                    <div class="item">
                      <span class="key">Type</span>
                      <span class="val">{{ $typeLabel[$lead->type] ?? $lead->type }}</span>
                    </div>
                    <div class="item">
                      <span class="key">Email</span>
                      <span class="val">{{ $lead->email }}</span>
                    </div>
                    <div class="item">
                      <span class="key">Phone</span>
                      <span class="val">{{ $lead->phone ?: '—' }}</span>
                    </div>
                    <div class="item">
                      <span class="key">IP Address</span>
                      <span class="val">{{ $lead->ip_address ?: '—' }}</span>
                    </div>
                    <div class="item">
                      <span class="key">Source Page</span>
                      <span class="val">{{ $lead->source_page ?: '—' }}</span>
                    </div>

                    @if($lead->type === 'popup')
                      <div class="item">
                        <span class="key">Score</span>
                        <span class="val">{{ $scoreLabels[$lead->score] ?? ($lead->score ?: '—') }}</span>
                      </div>
                      <div class="item">
                        <span class="key">Goal</span>
                        <span class="val">{{ $goalLabels[$lead->goal] ?? ($lead->goal ?: '—') }}</span>
                      </div>
                      <div class="item">
                        <span class="key">Urgency</span>
                        <span class="val">{{ $urg['label'] ?? ($lead->urgency ?: '—') }}</span>
                      </div>
                    @elseif($lead->type === 'contact')
                      <div class="item">
                        <span class="key">Subject</span>
                        <span class="val">{{ $lead->subject ?: '—' }}</span>
                      </div>
                      @if($lead->message)
                        <div class="item full">
                          <span class="key">Message</span>
                          <span class="val message">{{ $lead->message }}</span>
                        </div>
                      @endif
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

</div>

<script>
function toggleDetail(id) {
  var detail = document.getElementById('detail-' + id);
  var row = document.querySelector('tr[data-row="' + id + '"]');
  if (!detail || !row) return;
  var isOpen = detail.style.display !== 'none';
  detail.style.display = isOpen ? 'none' : 'table-row';
  row.classList.toggle('expanded', !isOpen);
}

(function() {
  var input = document.getElementById('leadFilter');
  var table = document.getElementById('leadsTable');
  if (!input || !table) return;
  input.addEventListener('input', function() {
    var q = input.value.trim().toLowerCase();
    table.querySelectorAll('tbody tr[data-row]').forEach(function(row) {
      var id = row.getAttribute('data-row');
      var detail = document.getElementById('detail-' + id);
      var combined = row.textContent + ' ' + (detail ? detail.textContent : '');
      var match = !q || combined.toLowerCase().indexOf(q) !== -1;
      row.style.display = match ? '' : 'none';
      if (detail && !match) {
        detail.style.display = 'none';
        row.classList.remove('expanded');
      }
    });
  });
})();
</script>

</body>
</html>
