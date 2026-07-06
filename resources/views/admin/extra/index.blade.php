@extends('layouts.admin')

@php
    $meta = [
        'funnel'  => ['title' => 'Funnels',          'amountLabel' => 'Amount (one-time)', 'showLink' => true,  'showWa' => false],
        'support' => ['title' => 'Customer Support',  'amountLabel' => 'Price / Week',      'showLink' => false, 'showWa' => true],
        'ads'     => ['title' => 'Ads',               'amountLabel' => 'Weekly Price',      'showLink' => false, 'showWa' => false],
    ][$type];
    $colspan = 4 + ($meta['showLink'] ? 1 : 0) + ($meta['showWa'] ? 1 : 0) + 1; // client + amount + paid + status + notes + actions (+link/wa)
@endphp

@section('title', $meta['title'])

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2 style="margin:0;">{{ $meta['title'] }} <span class="ep-count">{{ $projects->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">Track clients, amounts (incl. upfront paid) and status. Edit any field anytime.</p>
        </div>
        <button class="btn btn-primary" onclick="openExtra()">+ Add</button>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Client</th>
                @if ($meta['showLink'])<th>Funnel Link</th>@endif
                @if ($meta['showWa'])<th>WhatsApp</th>@endif
                <th>{{ $meta['amountLabel'] }}</th>
                <th>Paid</th>
                <th>Status</th>
                <th>Notes</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projects as $p)
                <tr>
                    <td><strong>{{ $p->client_name }}</strong></td>
                    @if ($meta['showLink'])
                        <td>
                            @if ($p->link)
                                <a href="{{ \Illuminate\Support\Str::startsWith($p->link, ['http://','https://']) ? $p->link : 'https://'.$p->link }}" target="_blank" rel="noopener">Open ↗</a>
                            @else — @endif
                        </td>
                    @endif
                    @if ($meta['showWa'])
                        <td>
                            @if ($p->whatsapp)
                                <a href="https://wa.me/{{ preg_replace('/\D/', '', $p->whatsapp) }}" target="_blank" rel="noopener">{{ $p->whatsapp }}</a>
                            @else — @endif
                        </td>
                    @endif
                    <td>{{ $p->amount !== null ? '$'.number_format($p->amount, 2) : '—' }}</td>
                    <td>{{ $p->paid !== null ? '$'.number_format($p->paid, 2) : '—' }}</td>
                    <td><span class="ep-pill ep-{{ $p->status }}">{{ $statuses[$p->status] ?? $p->status }}</span></td>
                    <td class="ep-notes">{{ $p->notes ?: '—' }}</td>
                    <td class="no-link">
                        <div class="row-actions">
                            <button type="button" class="btn btn-sm"
                                onclick='openExtra(@json([
                                    "id" => $p->id,
                                    "client_name" => $p->client_name,
                                    "link" => $p->link,
                                    "whatsapp" => $p->whatsapp,
                                    "amount" => $p->amount,
                                    "paid" => $p->paid,
                                    "status" => $p->status,
                                    "notes" => $p->notes,
                                ]))'>Edit</button>
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
    </table>
</div>

{{-- Add / Edit modal --}}
<div id="extraModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="extraTitle">Add {{ $meta['title'] }}</h3>
            <button class="modal-close" onclick="closeModal('extraModal')">&times;</button>
        </div>
        <form id="extraForm" method="POST" action="{{ route('admin.extra.store', $type) }}">
            @csrf
            <input type="hidden" name="_method" id="extraMethod" value="POST">
            <div class="form-group"><label>Client Name *</label><input type="text" name="client_name" id="ep_client_name" required></div>
            @if ($meta['showLink'])
                <div class="form-group"><label>Funnel Link</label><input type="text" name="link" id="ep_link" placeholder="https://…"></div>
            @endif
            @if ($meta['showWa'])
                <div class="form-group"><label>WhatsApp Number</label><input type="text" name="whatsapp" id="ep_whatsapp" placeholder="+1 555 123 4567"></div>
            @endif
            <div class="form-row" style="display:flex; gap:12px;">
                <div class="form-group" style="flex:1;"><label>{{ $meta['amountLabel'] }} ($)</label><input type="number" step="0.01" min="0" name="amount" id="ep_amount"></div>
                <div class="form-group" style="flex:1;"><label>Paid / Upfront ($)</label><input type="number" step="0.01" min="0" name="paid" id="ep_paid"></div>
            </div>
            <div class="form-group">
                <label>Status *</label>
                <select name="status" id="ep_status" required>
                    @foreach ($statuses as $key => $label)
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
</style>
@endpush

@push('scripts')
<script>
(function () {
    var storeUrl  = @json(route('admin.extra.store', $type));
    var updateTpl = @json(route('admin.extra.update', '__ID__'));
    var form = document.getElementById('extraForm');

    function val(id, v) { var el = document.getElementById(id); if (el) el.value = (v === null || v === undefined) ? '' : v; }

    window.openExtra = function (data) {
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
            val('ep_status', 'in_progress');
        }
        openModal('extraModal');
    };

    @if ($errors->any())
        if (typeof openModal === 'function') openExtra();
    @endif
})();
</script>
@endpush
@endsection
