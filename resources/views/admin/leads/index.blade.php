@extends($adminLayout ?? 'layouts.admin')

@section('title', 'Website Leads')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2>Website Leads</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                All submissions from across the public site — popup and contact form.
            </p>
        </div>
        <div class="lead-search">
            <input type="text" id="leadSearch" placeholder="Search name, email, phone, message…">
        </div>
    </div>

    <div class="lead-tabs">
        <a href="{{ route('admin.leads.index') }}" class="lead-tab {{ $type === 'all' ? 'active' : '' }}">
            All Sources <span class="lead-tab-count">{{ $counts['all'] }}</span>
        </a>
        <a href="{{ route('admin.leads.index', ['type' => 'popup']) }}" class="lead-tab {{ $type === 'popup' ? 'active' : '' }}">
            Popup <span class="lead-tab-count">{{ $counts['popup'] }}</span>
        </a>
        <a href="{{ route('admin.leads.index', ['type' => 'contact']) }}" class="lead-tab {{ $type === 'contact' ? 'active' : '' }}">
            Contact Form <span class="lead-tab-count">{{ $counts['contact'] }}</span>
        </a>
    </div>

    <div class="table-scroll"><table class="data-table" id="leadsTable">
        <thead>
            <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Source</th>
                <th>Details</th>
                <th>Page</th>
                <th>When</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($leads as $lead)
                <tr class="lead-row">
                    <td><strong>{{ $lead->fullName() ?: '—' }}</strong></td>
                    <td>
                        @if ($lead->email)
                            <a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a><br>
                        @endif
                        @if ($lead->phone)
                            <a href="tel:{{ $lead->phone }}" class="muted">{{ $lead->phone }}</a>
                        @endif
                    </td>
                    <td>
                        @if ($lead->type === \App\Models\Lead::TYPE_POPUP)
                            <span class="lead-pill lead-pill-popup">Popup</span>
                        @else
                            <span class="lead-pill lead-pill-contact">Contact</span>
                        @endif
                    </td>
                    <td class="lead-details">
                        @if ($lead->type === \App\Models\Lead::TYPE_CONTACT)
                            @if ($lead->subject)<div><strong>{{ $lead->subject }}</strong></div>@endif
                            @if ($lead->message)<div class="muted">{{ $lead->message }}</div>@endif
                        @else
                            @php
                                $bits = array_filter([
                                    $lead->goal ? 'Goal: ' . $lead->goal : null,
                                    $lead->score ? 'Score: ' . $lead->score : null,
                                    $lead->urgency ? 'Urgency: ' . $lead->urgency : null,
                                ]);
                            @endphp
                            {{ $bits ? implode(' · ', $bits) : '—' }}
                        @endif
                    </td>
                    <td class="muted">{{ $lead->source_page ?: '—' }}</td>
                    <td class="muted" style="white-space:nowrap;">
                        {{ optional($lead->created_at)->diffForHumans() }}<br>
                        <span style="font-size:11px;">{{ optional($lead->created_at)->format('M j, Y g:ia') }}</span>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.leads.destroy', $lead) }}" style="display:inline"
                              onsubmit="return confirm('Delete this lead from {{ addslashes($lead->fullName() ?: $lead->email) }}? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No leads captured yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

@push('head')
<style>
    .lead-search input {
        width: 280px; max-width: 100%; padding: 8px 12px;
        border: 1px solid var(--muted); border-radius: 8px; font-size: 13px;
    }
    .lead-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin: 4px 0 16px; }
    .lead-tab {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;
        color: var(--text-soft); background: var(--surface-2); border: 1px solid var(--border); text-decoration: none;
    }
    .lead-tab:hover { background: var(--border); }
    .lead-tab.active { background: #2563eb; color: var(--on-accent); border-color: #2563eb; }
    .lead-tab-count {
        font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 999px;
        background: rgba(15,23,42,.08); color: inherit;
    }
    .lead-tab.active .lead-tab-count { background: rgba(255,255,255,.25); }
    .lead-pill {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 11px; font-weight: 700; letter-spacing: .2px;
    }
    .lead-pill-popup   { background: #ede9fe; color: #5b21b6; }
    .lead-pill-contact { background: #e0f2fe; color: #075985; }
    .lead-details { max-width: 360px; white-space: pre-wrap; word-break: break-word; font-size: 13px; line-height: 1.45; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var input = document.getElementById('leadSearch');
    var rows = Array.prototype.slice.call(document.querySelectorAll('#leadsTable .lead-row'));
    if (!input) return;
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        rows.forEach(function (row) {
            row.style.display = (!q || row.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
        });
    });
})();
</script>
@endpush
@endsection
