@extends('layouts.admin')

@section('title', $title)

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h2>{{ $title }} <span class="lead-count-badge">{{ $prospects->count() }}</span></h2>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">{{ $blurb }}</p>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Channel</th>
                <th>Contact</th>
                <th>Discussion / Comments</th>
                <th>Last Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($prospects as $prospect)
                @php $ch = $prospect->channel ?: 'whatsapp'; @endphp
                <tr>
                    <td>
                        <strong>{{ $prospect->name }}</strong>
                        @if ($prospect->referred_by)
                            <div class="muted" style="font-size:12px; margin-top:2px;">Referred by {{ $prospect->referred_by }}</div>
                        @endif
                    </td>
                    <td class="no-link">{{ \App\Models\Prospect::CHANNELS[$ch] ?? ucfirst($ch) }}</td>
                    <td>
                        @if ($ch === 'instagram')
                            @if ($prospect->instagram)
                                <a href="{{ \Illuminate\Support\Str::startsWith($prospect->instagram, ['http']) ? $prospect->instagram : 'https://' . ltrim($prospect->instagram, '/') }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($prospect->instagram, 32) }}</a>
                            @else
                                <span class="muted">—</span>
                            @endif
                        @elseif ($prospect->whatsapp_digits)
                            @if ($ch === 'phone')
                                <a href="tel:+{{ $prospect->whatsapp_digits }}" class="wa-link">{{ $prospect->whatsapp }}</a>
                            @else
                                <a href="https://wa.me/{{ $prospect->whatsapp_digits }}" target="_blank" rel="noopener" class="wa-link">{{ $prospect->whatsapp }}</a>
                            @endif
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="prospect-notes">{{ $prospect->notes ?: '—' }}</td>
                    <td class="no-link muted">{{ $prospect->updated_at?->format('M j, Y') }}</td>
                    <td class="no-link">
                        <form method="POST" action="{{ route('admin.prospects.reactivate', $prospect) }}" style="display:inline"
                              onsubmit="return confirm('Move {{ addslashes($prospect->name) }} back to its pipeline?')">
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
                <tr><td colspan="6" class="empty">{{ $emptyMsg }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@push('head')
<style>
    .lead-count-badge { display:inline-block; margin-left:8px; padding:2px 10px; border-radius:999px; background:#dbeafe; color:#1e40af; font-size:13px; font-weight:700; vertical-align:middle; }
    .prospect-notes { max-width: 360px; white-space: pre-wrap; word-break: break-word; font-size: 13px; color: #475569; line-height: 1.45; }
    .wa-link { color: #16a34a; font-weight: 600; white-space: nowrap; }
    .wa-link:hover { text-decoration: underline; }
</style>
@endpush
@endsection
