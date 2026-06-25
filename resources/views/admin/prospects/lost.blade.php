@extends('layouts.admin')

@section('title', 'Lost Prospects')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2>Lost Prospects</h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">
                Prospects who went cold — moved out of the active pipeline. Reactivate one to bring it back.
            </p>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Client WhatsApp</th>
                <th>Reached Out Via</th>
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
                    <td class="prospect-notes">{{ $prospect->notes ?: '—' }}</td>
                    <td class="no-link muted">{{ $prospect->updated_at?->format('M j, Y') }}</td>
                    <td class="no-link">
                        <form method="POST" action="{{ route('admin.prospects.reactivate', $prospect) }}" style="display:inline"
                              onsubmit="return confirm('Move {{ addslashes($prospect->name) }} back to Prospects in Contact?')">
                            @csrf
                            <button class="btn btn-sm btn-primary">Reactivate</button>
                        </form>
                        <form method="POST" action="{{ route('admin.prospects.destroy', $prospect) }}" style="display:inline"
                              onsubmit="return confirm('Permanently delete {{ addslashes($prospect->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No lost prospects — nice, everyone's still in play.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@push('head')
<style>
    .prospect-notes { max-width: 360px; white-space: pre-wrap; word-break: break-word; font-size: 13px; color: #475569; line-height: 1.45; }
    .wa-link { color: #16a34a; font-weight: 600; white-space: nowrap; }
    .wa-link:hover { text-decoration: underline; }
</style>
@endpush
@endsection
