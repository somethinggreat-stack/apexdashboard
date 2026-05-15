@extends('layouts.admin')

@section('title', $client->business_name)

@section('content')
<div class="card">
    <div class="card-header">
        <h2>{{ $client->business_name }}</h2>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('admin.clients.edit', $client) }}" class="btn">Edit</a>
            <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">← Back</a>
        </div>
    </div>
    <div class="info-grid">
        <div><label>Email</label><div>{{ $client->email }}</div></div>
        <div><label>Phone</label><div>{{ $client->phone ?? '—' }}</div></div>
        <div><label>Monthly Fee</label><div>${{ number_format($client->monthly_fee, 2) }}</div></div>
        <div><label>Clients</label><div>{{ $client->endUsers->count() }}</div></div>
        <div><label>Monthly Revenue</label><div>${{ number_format($client->monthly_revenue, 2) }}</div></div>
        <div><label>Status</label><div><span class="pill pill-{{ $client->status }}">{{ $client->status }}</span></div></div>
    </div>
</div>

{{-- ============================================
     INTAKE FORM — secret-token public link
     ============================================ --}}
<div class="card">
    <div class="card-header">
        <h2>Public Intake Form</h2>
        <form method="POST" action="{{ route('admin.clients.regenerate-intake-token', $client) }}"
              onsubmit="return confirm('Regenerate the intake link? The current link will stop working immediately and a brand new URL will be generated. Anyone who has the old link will no longer be able to submit through it.');">
            @csrf
            <button type="submit" class="btn btn-secondary">Regenerate Link</button>
        </form>
    </div>

    <p style="font-size:13px; color:#64748b; margin: 6px 0 14px;">
        Share this link with <strong>{{ $client->business_name }}</strong>. End clients fill out the form, and a new client record is automatically created under this business owner — marked <em>Pending Review</em>.
        The public form only displays the logo and the display name below. The business owner's name is never shown.
    </p>

    @php $intakeUrl = $client->intakeUrl(); @endphp
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:18px;">
        <input type="text" value="{{ $intakeUrl }}" readonly
               id="intakeLinkField"
               style="flex:1; min-width:280px; padding:10px 12px; border:1px solid #E2E8F0; border-radius:8px; font-family:'IBM Plex Mono', monospace; font-size:12px; background:#f8fafc; color:#1E3A5F;"
               onclick="this.select();">
        <button type="button" class="btn" onclick="(function(){
            var f=document.getElementById('intakeLinkField'); f.select();
            navigator.clipboard.writeText(f.value).then(function(){
                var b=event.target; var orig=b.textContent; b.textContent='Copied!'; setTimeout(function(){ b.textContent=orig; }, 1500);
            });
        })();">Copy</button>
        <a href="{{ $intakeUrl }}" target="_blank" class="btn">Open</a>
    </div>

    <h4 style="font-size:12px; text-transform:uppercase; letter-spacing:0.1em; color:#64748b; margin-bottom:10px;">Form Branding</h4>
    <div class="info-grid">
        <div>
            <label>Logo (shown at top of form)</label>
            <div>
                @if ($logoUrl = $client->intakeLogoUrl())
                    <img src="{{ $logoUrl }}" alt="" style="max-height:64px; max-width:220px; margin-bottom:8px;">
                    <div>
                        <form method="POST" action="{{ route('admin.clients.intake-logo.remove', $client) }}" style="display:inline" onsubmit="return confirm('Remove the intake logo?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Remove Logo</button>
                        </form>
                    </div>
                @else
                    <span class="muted">No logo set. Upload one from the <a href="{{ route('admin.clients.edit', $client) }}">Edit page</a>.</span>
                @endif
            </div>
        </div>
        <div>
            <label>Display Name (shown under logo)</label>
            <div>{{ $client->intake_display_name ?: '— (defaults to ' . $client->business_name . ')' }}</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Clients ({{ $client->endUsers->count() }})</h2>
        <a href="{{ route('admin.end-users.index', ['client_id' => $client->id]) }}" class="btn btn-primary">+ Add Client</a>
    </div>
    <table class="data-table">
        <thead>
            <tr><th>Name</th><th>Email</th><th>Source</th><th>Status</th><th>Started</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($client->endUsers as $eu)
                <tr class="row-link" data-href="{{ route('admin.end-users.show', $eu) }}">
                    <td>{{ $eu->full_name }}</td>
                    <td class="no-link">{{ $eu->email }}</td>
                    <td class="no-link">
                        @if ($eu->intake_status === 'pending_review')
                            <span class="pill" style="background:#fef3c7; color:#92400e;">📝 Pending Review</span>
                        @elseif ($eu->intake_submitted_at)
                            <span class="pill" style="background:#dbeafe; color:#1e40af;">Form intake</span>
                        @else
                            <span class="muted">Manual</span>
                        @endif
                    </td>
                    <td class="no-link"><span class="pill pill-{{ $eu->status }}">{{ $eu->status }}</span></td>
                    <td class="no-link">{{ $eu->start_date?->format('M d, Y') }}</td>
                    <td class="no-link">
                        <a href="{{ route('admin.end-users.show', $eu) }}" class="btn btn-sm">Open</a>
                        <form method="POST" action="{{ route('admin.end-users.destroy', $eu) }}" style="display:inline" onsubmit="return confirm('Delete client {{ $eu->full_name }} and all their documents? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No clients yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
