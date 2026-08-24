{{-- Per-client audit timeline — super-admin only. Every move, hold, step,
     comment, result change and upload, with the VA and a timestamp. --}}
<div class="card">
    <div class="card-header">
        <h3>Activity &amp; Tracking</h3>
        <span class="muted" style="font-size:12.5px;">Super-admin only · {{ count($activity) }} events · who did what, and when</span>
    </div>

    @php
        $icons = [
            'created'  => ['➕', '#e0e7ff', '#4338ca'],
            'moved'    => ['🔀', '#dbeafe', '#1d4ed8'],
            'held'     => ['⏸️', '#fef3c7', '#92400e'],
            'resumed'  => ['▶️', '#dcfce7', '#166534'],
            'profile'  => ['✏️', '#f1f5f9', '#475569'],
            'step'     => ['✅', '#dcfce7', '#166534'],
            'comment'  => ['💬', '#ede9fe', '#5b21b6'],
            'result'   => ['📊', '#cffafe', '#0e7490'],
            'doc'      => ['📎', '#ffedd5', '#9a3412'],
        ];
    @endphp

    @if (empty($activity))
        <p class="muted" style="padding:8px 2px;">No activity recorded yet.</p>
    @else
        <ul class="act-list">
            @foreach ($activity as $e)
                @php $ic = $icons[$e['kind']] ?? ['•', '#f1f5f9', '#475569']; @endphp
                <li class="act-item">
                    <span class="act-ico" style="background:{{ $ic[1] }}; color:{{ $ic[2] }};">{{ $ic[0] }}</span>
                    <div class="act-body">
                        <div class="act-text">{{ $e['text'] }}</div>
                        <div class="act-meta">
                            <span class="act-who">{{ $e['who'] }}</span>
                            <span class="act-dot">·</span>
                            <span class="act-when">{{ $e['at']?->timezone('America/New_York')->format('M j, Y · g:i A') }} ET</span>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>

@push('head')
<style>
    .act-list { list-style:none; margin:0; padding:6px 2px 2px; position:relative; }
    .act-list::before { content:''; position:absolute; left:17px; top:14px; bottom:14px; width:2px; background:var(--pro-line,#e6ebf2); }
    .act-item { display:flex; gap:14px; align-items:flex-start; padding:9px 0; position:relative; }
    .act-ico { flex:0 0 auto; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; z-index:1; box-shadow:0 0 0 3px var(--pro-bg,#fff); }
    .act-body { padding-top:2px; }
    .act-text { font-size:14px; font-weight:600; color:var(--pro-text,#0f172a); }
    .act-meta { font-size:12px; color:var(--pro-muted,#64748b); margin-top:2px; }
    .act-who { font-weight:700; color:var(--pro-text,#334155); }
    .act-dot { margin:0 5px; }
    :root[data-theme="dark"] .act-ico { box-shadow:0 0 0 3px var(--pro-panel,#0f1424); }
</style>
@endpush
