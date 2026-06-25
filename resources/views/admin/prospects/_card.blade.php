<div class="pipe-card" draggable="true" data-id="{{ $p->id }}" data-value="{{ (float) $p->value }}">
    <div class="pc-name">{{ $p->name }}</div>

    @if ($p->referred_by)
        <div class="pc-row"><span class="pc-label">Source</span><span class="pc-val">{{ $p->referred_by }}</span></div>
    @endif

    @if ($p->whatsapp_digits)
        <div class="pc-row">
            <span class="pc-label">WhatsApp</span>
            <a href="https://wa.me/{{ $p->whatsapp_digits }}" target="_blank" rel="noopener" class="wa-link" onclick="event.stopPropagation()">{{ $p->whatsapp }}</a>
        </div>
    @endif

    @if ($p->value)
        <div class="pc-row"><span class="pc-label">Value</span><span class="pc-val">${{ number_format($p->value, 2) }}</span></div>
    @endif

    @if ($p->notes)
        <div class="pc-note">{{ \Illuminate\Support\Str::limit($p->notes, 140) }}</div>
    @endif

    <div class="pc-actions">
        <button type="button" class="btn btn-sm" onclick="editProspect({{ $p->id }})">Edit</button>
        <form method="POST" action="{{ route('admin.prospects.destroy', $p) }}" style="display:inline"
              onsubmit="return confirm('Remove {{ addslashes($p->name) }} from prospects?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-danger">Delete</button>
        </form>
    </div>
</div>
