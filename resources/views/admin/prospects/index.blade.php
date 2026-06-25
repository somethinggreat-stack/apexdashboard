@extends('layouts.admin')

@section('title', 'Prospects')

@section('content')
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <div>
            <h2>Prospects</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Prospective business owners who aren't signed yet — drag a card to move it through your pipeline.
            </p>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <div class="view-toggle">
                <a href="{{ route('admin.prospects.index') }}" class="{{ $view === 'board' ? 'active' : '' }}">Board</a>
                <a href="{{ route('admin.prospects.index', ['view' => 'list']) }}" class="{{ $view === 'list' ? 'active' : '' }}">List</a>
            </div>
            <button class="btn btn-primary" onclick="addInStage('new')">+ Add Prospect</button>
        </div>
    </div>
</div>

@if ($view === 'board')
    <div class="pipeline" id="pipeline">
        @foreach (\App\Models\Prospect::STATUSES as $key => $label)
            @php $cards = $byStatus[$key]; $total = $cards->sum('value'); @endphp
            <div class="pipe-col" data-status="{{ $key }}">
                <div class="pipe-col-head pipe-head-{{ $key }}">
                    <div class="pipe-col-title">
                        <span>{{ $label }}</span>
                        <button class="pipe-add" title="Add a prospect to {{ $label }}" onclick="addInStage('{{ $key }}')">+</button>
                    </div>
                    <div class="pipe-col-meta">
                        <span class="pc-count">{{ $cards->count() }}</span> opportunities ·
                        <span class="pc-total">${{ number_format($total, 2) }}</span>
                    </div>
                </div>
                <div class="pipe-col-body" data-status="{{ $key }}">
                    @foreach ($cards as $p)
                        @include('admin.prospects._card', ['p' => $p])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Client WhatsApp</th>
                    <th>Reached Out Via</th>
                    <th>Value</th>
                    <th>Status</th>
                    <th>Discussion / Notes</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prospects as $prospect)
                    <tr>
                        <td>
                            <strong>{{ $prospect->name }}</strong>
                            @if ($prospect->referred_by)
                                <div class="muted" style="font-size:12px; margin-top:2px;">Referred by {{ $prospect->referred_by }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($prospect->whatsapp_digits)
                                <a href="https://wa.me/{{ $prospect->whatsapp_digits }}" target="_blank" rel="noopener" class="wa-link">{{ $prospect->whatsapp }}</a>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($prospect->outreach_whatsapp_digits)
                                <a href="https://wa.me/{{ $prospect->outreach_whatsapp_digits }}" target="_blank" rel="noopener" class="wa-link">{{ $prospect->outreach_whatsapp }}</a>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>{{ $prospect->value ? '$' . number_format($prospect->value, 2) : '—' }}</td>
                        <td><span class="prospect-pill prospect-pill-{{ $prospect->status }}">{{ $prospect->status_label }}</span></td>
                        <td class="prospect-notes">{{ $prospect->notes ?: '—' }}</td>
                        <td class="no-link muted">{{ $prospect->updated_at?->format('M j, Y') }}</td>
                        <td class="no-link">
                            <button type="button" class="btn btn-sm" onclick="editProspect({{ $prospect->id }})">Edit</button>
                            <form method="POST" action="{{ route('admin.prospects.destroy', $prospect) }}" style="display:inline"
                                  onsubmit="return confirm('Remove {{ addslashes($prospect->name) }} from prospects?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No prospects yet — add the first one to start tracking.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

{{-- Hidden JSON store of every prospect, so the edit modal can be populated by id --}}
<script id="prospectsData" type="application/json">@json($prospects->keyBy('id'))</script>

{{-- Add prospect --}}
<div id="createProspectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Prospect</h3>
            <button class="modal-close" onclick="closeModal('createProspectModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.prospects.store') }}">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="form-group">
                    <label>WhatsApp Number of Client</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="+1 469 905 8587">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>WhatsApp Number Used to Reach Out</label>
                    <input type="text" name="outreach_whatsapp" value="{{ old('outreach_whatsapp') }}" placeholder="+1 469 905 8587">
                </div>
                <div class="form-group">
                    <label>Referred By <span class="muted">(optional)</span></label>
                    <input type="text" name="referred_by" value="{{ old('referred_by') }}" placeholder="Who referred them?">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Deal Value ($) <span class="muted">(optional)</span></label>
                    <input type="number" step="0.01" min="0" name="value" value="{{ old('value') }}" placeholder="e.g. 1500">
                </div>
                <div class="form-group">
                    <label>Stage</label>
                    <select name="status">
                        @foreach (\App\Models\Prospect::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', 'new') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Discussion / Notes</label>
                <textarea name="notes" rows="4" placeholder="What was discussed, objections, next steps…">{{ old('notes') }}</textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createProspectModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Prospect</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit prospect (populated via JS) --}}
<div id="editProspectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Prospect</h3>
            <button class="modal-close" onclick="closeModal('editProspectModal')">&times;</button>
        </div>
        <form method="POST" id="editProspectForm">
            @csrf @method('PUT')
            <div class="form-row">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="ep-name" required>
                </div>
                <div class="form-group">
                    <label>WhatsApp Number of Client</label>
                    <input type="text" name="whatsapp" id="ep-whatsapp" placeholder="+1 469 905 8587">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>WhatsApp Number Used to Reach Out</label>
                    <input type="text" name="outreach_whatsapp" id="ep-outreach_whatsapp" placeholder="+1 469 905 8587">
                </div>
                <div class="form-group">
                    <label>Referred By <span class="muted">(optional)</span></label>
                    <input type="text" name="referred_by" id="ep-referred_by" placeholder="Who referred them?">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Deal Value ($) <span class="muted">(optional)</span></label>
                    <input type="number" step="0.01" min="0" name="value" id="ep-value" placeholder="e.g. 1500">
                </div>
                <div class="form-group">
                    <label>Stage</label>
                    <select name="status" id="ep-status">
                        @foreach (\App\Models\Prospect::STATUSES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Discussion / Notes</label>
                <textarea name="notes" id="ep-notes" rows="5" placeholder="What was discussed, objections, next steps…"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProspectModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

@push('head')
<style>
    .view-toggle { display:inline-flex; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
    .view-toggle a { padding:7px 14px; font-size:13px; font-weight:600; color:#475569; text-decoration:none; background:#fff; }
    .view-toggle a + a { border-left:1px solid #e2e8f0; }
    .view-toggle a.active { background:#2563eb; color:#fff; }

    /* Pipeline board */
    .pipeline { display:flex; gap:14px; overflow-x:auto; padding-bottom:14px; align-items:flex-start; }
    .pipe-col { flex:0 0 280px; width:280px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:12px; display:flex; flex-direction:column; }
    .pipe-col-head { padding:12px 14px; border-bottom:2px solid #cbd5e1; border-radius:12px 12px 0 0; }
    .pipe-col-title { display:flex; align-items:center; justify-content:space-between; font-weight:700; font-size:14px; color:#0f172a; }
    .pipe-col-meta { font-size:11px; color:#64748b; margin-top:3px; font-weight:600; }
    .pipe-add { width:22px; height:22px; border:0; border-radius:6px; background:#e2e8f0; color:#475569; font-size:16px; line-height:1; cursor:pointer; }
    .pipe-add:hover { background:#2563eb; color:#fff; }
    /* Stage accent colors on the header underline */
    .pipe-head-new           { border-bottom-color:#38bdf8; }
    .pipe-head-contacted     { border-bottom-color:#8b5cf6; }
    .pipe-head-in_discussion { border-bottom-color:#f59e0b; }
    .pipe-head-follow_up     { border-bottom-color:#d946ef; }
    .pipe-head-won           { border-bottom-color:#10b981; }
    .pipe-head-lost          { border-bottom-color:#ef4444; }

    .pipe-col-body { padding:12px; display:flex; flex-direction:column; gap:10px; min-height:80px; flex:1; }
    .pipe-col-body.over { background:#e0e7ff; outline:2px dashed #6366f1; outline-offset:-6px; border-radius:0 0 12px 12px; }

    .pipe-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; cursor:grab; box-shadow:0 1px 2px rgba(15,23,42,.04); }
    .pipe-card:hover { box-shadow:0 4px 10px rgba(15,23,42,.08); }
    .pipe-card.dragging { opacity:.5; }
    .pc-name { font-weight:700; font-size:14px; color:#0f172a; margin-bottom:6px; }
    .pc-row { display:flex; gap:6px; font-size:12px; margin-top:3px; }
    .pc-row .pc-label { color:#94a3b8; min-width:64px; }
    .pc-row .pc-val { color:#334155; font-weight:600; word-break:break-word; }
    .pc-note { margin-top:8px; font-size:12px; color:#64748b; line-height:1.45; white-space:pre-wrap; word-break:break-word; }
    .pc-actions { display:flex; gap:6px; margin-top:10px; padding-top:10px; border-top:1px solid #f1f5f9; }

    /* Shared pills / links / notes (list view) */
    .prospect-notes { max-width:360px; white-space:pre-wrap; word-break:break-word; font-size:13px; color:#475569; line-height:1.45; }
    .prospect-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.2px; white-space:nowrap; }
    .prospect-pill-new           { background:#e0f2fe; color:#075985; }
    .prospect-pill-contacted     { background:#ede9fe; color:#5b21b6; }
    .prospect-pill-in_discussion { background:#fef3c7; color:#92400e; }
    .prospect-pill-follow_up     { background:#fae8ff; color:#86198f; }
    .prospect-pill-won           { background:#d1fae5; color:#065f46; }
    .prospect-pill-lost          { background:#fee2e2; color:#991b1b; }
    .wa-link { color:#16a34a; font-weight:600; white-space:nowrap; }
    .wa-link:hover { text-decoration:underline; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var DATA = {};
    try { DATA = JSON.parse(document.getElementById('prospectsData').textContent) || {}; } catch (e) {}
    var baseUrl = "{{ url('admin/prospects') }}";
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // ---- Add into a specific stage ----
    window.addInStage = function (status) {
        var sel = document.querySelector('#createProspectModal select[name="status"]');
        if (sel) sel.value = status;
        openModal('createProspectModal');
    };

    // ---- Edit (populate from JSON by id) ----
    window.editProspect = function (id) {
        var p = DATA[id];
        if (!p) return;
        document.getElementById('editProspectForm').action = baseUrl + '/' + id;
        document.getElementById('ep-name').value = p.name || '';
        document.getElementById('ep-whatsapp').value = p.whatsapp || '';
        document.getElementById('ep-outreach_whatsapp').value = p.outreach_whatsapp || '';
        document.getElementById('ep-referred_by').value = p.referred_by || '';
        document.getElementById('ep-value').value = (p.value === null || p.value === undefined) ? '' : p.value;
        document.getElementById('ep-status').value = p.status || 'new';
        document.getElementById('ep-notes').value = p.notes || '';
        openModal('editProspectModal');
    };

    // ---- Drag & drop board ----
    var dragged = null;

    document.querySelectorAll('.pipe-card').forEach(bindCard);
    function bindCard(card) {
        card.addEventListener('dragstart', function (e) {
            dragged = card;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.dataset.id);
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('dragging');
            dragged = null;
            document.querySelectorAll('.pipe-col-body.over').forEach(function (c) { c.classList.remove('over'); });
        });
    }

    document.querySelectorAll('.pipe-col-body').forEach(function (col) {
        col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('over'); });
        col.addEventListener('dragleave', function (e) {
            if (!col.contains(e.relatedTarget)) col.classList.remove('over');
        });
        col.addEventListener('drop', function (e) {
            e.preventDefault();
            col.classList.remove('over');
            if (!dragged) return;
            var newStatus = col.dataset.status;
            var fromCol = dragged.parentElement;
            if (fromCol === col) return;

            col.appendChild(dragged);
            recount();

            var card = dragged;
            fetch(baseUrl + '/' + card.dataset.id + '/status', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ status: newStatus })
            }).then(function (r) {
                if (!r.ok) throw new Error('bad status');
            }).catch(function () {
                fromCol.appendChild(card);     // revert on failure
                recount();
                alert('Could not move that card — please try again.');
            });
        });
    });

    function recount() {
        document.querySelectorAll('.pipe-col').forEach(function (col) {
            var cards = col.querySelectorAll('.pipe-card');
            var total = 0;
            cards.forEach(function (c) { total += parseFloat(c.dataset.value || '0') || 0; });
            col.querySelector('.pc-count').textContent = cards.length;
            col.querySelector('.pc-total').textContent = '$' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        });
    }

    // Re-open the add modal if a submission bounced back with validation errors.
    @if ($errors->any())
        if (typeof openModal === 'function') openModal('createProspectModal');
    @endif
})();
</script>
@endpush
@endsection
