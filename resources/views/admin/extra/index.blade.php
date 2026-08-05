@extends($adminLayout ?? 'layouts.admin')

@php
    // Per-type config. model: 'oneoff' = agreed amount + upfront/full-paid (funnels);
    // 'weekly' = a single recurring price, no upfront (support / ads).
    $config = [
        'funnel'  => [
            'title' => 'Funnels', 'model' => 'oneoff',
            'amountLabel' => 'Amount (one-time)', 'showLink' => true, 'showWa' => false,
            'statuses' => ['in_progress' => 'In Progress', 'completed' => 'Completed'],
        ],
        'support' => [
            'title' => 'Customer Support', 'model' => 'weekly',
            'amountLabel' => 'Price / Week', 'showLink' => false, 'showWa' => true,
            'statuses' => ['in_progress' => 'In Progress', 'paused' => 'Paused (ended)'],
        ],
        'ads'     => [
            'title' => 'Ads', 'model' => 'weekly',
            'amountLabel' => 'Weekly Price', 'showLink' => false, 'showWa' => false,
            'statuses' => ['in_progress' => 'In Progress', 'paused' => 'Paused (ended)'],
        ],
    ][$type];

    $showPaid   = $config['model'] === 'oneoff';   // Paid column only for one-off (funnels)
    $statusLabels = ['in_progress' => 'In Progress', 'waiting' => 'Waiting', 'completed' => 'Completed', 'paused' => 'Paused'];
    // Fixed columns: Client, Amount, Status, Notes, Actions (5) + optional Link/WhatsApp/Paid
    $colspan = 5 + ($config['showLink'] ? 1 : 0) + ($config['showWa'] ? 1 : 0) + ($showPaid ? 1 : 0);

    // Totals (funnels: collected / remaining / value; weekly: total weekly + active)
    $isOneoff       = $config['model'] === 'oneoff';
    $totalCollected = (float) $projects->sum('paid');
    $totalPending   = (float) $projects->sum(fn ($p) => max(0, (float) ($p->amount ?? 0) - (float) ($p->paid ?? 0)));
    $totalWeekly    = (float) $projects->sum('amount');
    $activeCount    = $projects->where('status', 'in_progress')->count();
@endphp

@section('title', $config['title'])

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">{{ $config['title'] }} <span class="ep-count">{{ $projects->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                @if ($config['model'] === 'weekly')
                    A weekly price per client. In Progress = ongoing, Paused = contract ended.
                @else
                    While In Progress, record the agreed amount and any upfront paid. Once Completed, record the full amount paid.
                @endif
            </p>
        </div>
        <button class="btn btn-primary" onclick="openExtra()">+ Add</button>
    </div>

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Client</th>
                @if ($config['showLink'])<th>Funnel Link</th>@endif
                @if ($config['showWa'])<th>WhatsApp</th>@endif
                <th>{{ $config['amountLabel'] }}</th>
                @if ($showPaid)<th>Paid</th>@endif
                <th>Status</th>
                <th>Notes</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projects as $p)
                <tr>
                    <td><strong>{{ $p->client_name }}</strong></td>
                    @if ($config['showLink'])
                        <td>
                            @if ($p->link)
                                <a href="{{ \Illuminate\Support\Str::startsWith($p->link, ['http://','https://']) ? $p->link : 'https://'.$p->link }}" target="_blank" rel="noopener">Open ↗</a>
                            @else — @endif
                        </td>
                    @endif
                    @if ($config['showWa'])
                        <td>
                            @if ($p->whatsapp)
                                <a href="https://wa.me/{{ preg_replace('/\D/', '', $p->whatsapp) }}" target="_blank" rel="noopener">{{ $p->whatsapp }}</a>
                            @else — @endif
                        </td>
                    @endif
                    <td>{{ $p->amount !== null ? '$'.number_format($p->amount, 2) : '—' }}</td>
                    @if ($showPaid)
                        <td>{{ $p->paid !== null ? '$'.number_format($p->paid, 2) : '—' }}</td>
                    @endif
                    <td><span class="ep-pill ep-{{ $p->status }}">{{ $statusLabels[$p->status] ?? $p->status }}</span></td>
                    <td class="ep-notes">{{ $p->notes ?: '—' }}</td>
                    <td class="no-link">
                        <div class="row-actions">
                            <button type="button" class="btn btn-sm"
                                data-id="{{ $p->id }}"
                                data-client_name="{{ $p->client_name }}"
                                data-link="{{ $p->link }}"
                                data-whatsapp="{{ $p->whatsapp }}"
                                data-amount="{{ $p->amount }}"
                                data-paid="{{ $p->paid }}"
                                data-status="{{ $p->status }}"
                                data-notes="{{ $p->notes }}"
                                onclick="openExtra(this)">Edit</button>
                            <form method="POST" action="{{ route('admin.extra.destroy', $p->id) }}"
                                  onsubmit="return confirm('Remove {{ addslashes($p->client_name) }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $colspan }}" class="empty">Nothing here yet — click “+ Add”.</td></tr>
            @endforelse
        </tbody>
    </table></div>

    @if ($projects->count())
        <div class="ep-totals">
            @if ($isOneoff)
                <div class="ep-total"><span class="ept-label">Total Collected</span><span class="ept-val">${{ number_format($totalCollected, 2) }}</span></div>
                <div class="ep-total ept-amber"><span class="ept-label">Pending (remaining)</span><span class="ept-val">${{ number_format($totalPending, 2) }}</span></div>
                <div class="ep-total"><span class="ept-label">Total Value</span><span class="ept-val">${{ number_format($totalCollected + $totalPending, 2) }}</span></div>
            @else
                <div class="ep-total"><span class="ept-label">Total Weekly</span><span class="ept-val">${{ number_format($totalWeekly, 2) }}</span></div>
                <div class="ep-total"><span class="ept-label">Active</span><span class="ept-val">{{ $activeCount }}</span></div>
            @endif
        </div>
    @endif
</div>

{{-- Add / Edit modal --}}
<div id="extraModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="extraTitle">Add {{ $config['title'] }}</h3>
            <button class="modal-close" onclick="closeModal('extraModal')">&times;</button>
        </div>
        <form id="extraForm" method="POST" action="{{ route('admin.extra.store', $type) }}">
            @csrf
            <input type="hidden" name="_method" id="extraMethod" value="POST">
            <div class="form-group"><label>Client Name *</label><input type="text" name="client_name" id="ep_client_name" required></div>
            @if ($config['showLink'])
                <div class="form-group"><label>Funnel Link</label><input type="text" name="link" id="ep_link" placeholder="https://…"></div>
            @endif
            @if ($config['showWa'])
                <div class="form-group"><label>WhatsApp Number</label><input type="text" name="whatsapp" id="ep_whatsapp" placeholder="+1 555 123 4567"></div>
            @endif

            @if ($config['model'] === 'weekly')
                {{-- Weekly: a single recurring price, no upfront --}}
                <div class="form-group"><label>{{ $config['amountLabel'] }} ($)</label><input type="number" step="0.01" min="0" name="amount" id="ep_amount"></div>
            @else
                {{-- One-off (funnels): agreed amount + upfront while In Progress; full paid once Completed --}}
                <div class="form-row" style="display:flex; gap:12px;">
                    <div class="form-group" style="flex:1;" id="ep_amount_group"><label>{{ $config['amountLabel'] }} ($)</label><input type="number" step="0.01" min="0" name="amount" id="ep_amount"></div>
                    <div class="form-group" style="flex:1;"><label id="ep_paid_label">Paid / Upfront ($)</label><input type="number" step="0.01" min="0" name="paid" id="ep_paid"></div>
                </div>
            @endif

            <div class="form-group">
                <label>Status *</label>
                <select name="status" id="ep_status" required>
                    @foreach ($config['statuses'] as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group"><label>Notes</label><textarea name="notes" id="ep_notes" rows="3" placeholder="Waiting on client assets, etc."></textarea></div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('extraModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .ep-count { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#eef2ff; color:#4338ca; font-size:13px; font-weight:700; vertical-align:middle; }
    .ep-notes { max-width:300px; color:#475569; font-size:12.5px; white-space:pre-wrap; word-break:break-word; }
    .ep-pill { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .ep-in_progress { background:#e0f2fe; color:#075985; }
    .ep-waiting { background:#fef3c7; color:#92400e; }
    .ep-completed { background:#dcfce7; color:#166534; }
    .ep-paused { background:#e2e8f0; color:#334155; }
    .row-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
    .row-actions form { display:inline; margin:0; }
    .row-actions .btn { white-space:nowrap; }

    .ep-totals { display:flex; flex-wrap:wrap; gap:12px; margin-top:16px; padding-top:16px; border-top:1px solid var(--border); }
    .ep-total { flex:1 1 160px; background:#f8fafc; border:1px solid #e6ebf2; border-radius:12px; padding:12px 16px; display:flex; flex-direction:column; gap:3px; }
    .ept-label { font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#64748b; }
    .ept-val { font-size:20px; font-weight:800; color:#0f172a; }
    .ep-total.ept-amber .ept-val { color:#b45309; }
:root[data-theme="dark"] .ep-total{background:rgba(255,255,255,.04);border-color:var(--pro-line);}
:root[data-theme="dark"] .ept-label{color:var(--pro-muted);}
:root[data-theme="dark"] .ept-val{color:var(--pro-text);}
:root[data-theme="dark"] .ep-notes{color:var(--pro-text-soft);}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var storeUrl  = @json(route('admin.extra.store', $type));
    var updateTpl = @json(route('admin.extra.update', '__ID__'));
    var form = document.getElementById('extraForm');

    function val(id, v) { var el = document.getElementById(id); if (el) el.value = (v === null || v === undefined) ? '' : v; }

    // Funnels only: when Completed, ask for the full amount paid (hide the agreed
    // amount and relabel the paid field). While In Progress, ask for upfront.
    function applyStatus() {
        var sel = document.getElementById('ep_status');
        if (!sel) return;
        var completed = (sel.value === 'completed');
        var agreedGroup = document.getElementById('ep_amount_group'); // funnels only
        var paidLabel   = document.getElementById('ep_paid_label');   // funnels only
        if (agreedGroup) agreedGroup.style.display = completed ? 'none' : '';
        if (paidLabel)   paidLabel.textContent = completed ? 'Amount Fully Paid ($)' : 'Paid / Upfront ($)';
    }

    window.openExtra = function (btn) {
        var data = (btn && btn.dataset) ? btn.dataset : null;
        if (data && data.id) {
            document.getElementById('extraTitle').textContent = 'Edit';
            form.action = updateTpl.replace('__ID__', data.id);
            document.getElementById('extraMethod').value = 'PUT';
            val('ep_client_name', data.client_name);
            val('ep_link', data.link);
            val('ep_whatsapp', data.whatsapp);
            val('ep_amount', data.amount);
            val('ep_paid', data.paid);
            val('ep_status', data.status);
            val('ep_notes', data.notes);
        } else {
            document.getElementById('extraTitle').textContent = 'Add';
            form.action = storeUrl;
            document.getElementById('extraMethod').value = 'POST';
            ['ep_client_name','ep_link','ep_whatsapp','ep_amount','ep_paid','ep_notes'].forEach(function (id) { val(id, ''); });
            var st = document.getElementById('ep_status');
            if (st) st.selectedIndex = 0;
        }
        applyStatus();
        openModal('extraModal');
    };

    var statusSel = document.getElementById('ep_status');
    if (statusSel) statusSel.addEventListener('change', applyStatus);

    @if ($errors->any())
        if (typeof openModal === 'function') openExtra();
    @endif
})();
</script>
@endpush
@endsection
