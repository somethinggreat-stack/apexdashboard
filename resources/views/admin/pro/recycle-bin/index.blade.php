@extends('layouts.admin-pro')

@section('title', 'Recycle Bin')

@section('content')
@php
    use App\Support\RecycleBin;
    $pill = function (int $days) {
        $cls = $days <= 2 ? 'rb-soon' : ($days <= 5 ? 'rb-mid' : 'rb-ok');
        $txt = $days <= 0 ? 'purging today' : ($days === 1 ? '1 day left' : "{$days} days left");
        return "<span class=\"rb-days {$cls}\">{$txt}</span>";
    };
@endphp

<div class="pro-panel" style="margin-bottom:18px;">
    <div class="pro-panel-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#fb7185,#e11d48);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
            </span>
            <h2>Recycle Bin</h2>
        </div>
        @if ($owners->count() || $clients->count())
            <form method="POST" action="{{ route('admin.recycle-bin.empty') }}" id="rbEmptyForm" style="margin:0;">
                @csrf @method('DELETE')
                <button type="button" class="pro-act del rb-empty-all" onclick="rbConfirmEmpty()">
                    Empty Recycle Bin ({{ $owners->count() + $clients->count() }})
                </button>
            </form>
        @endif
    </div>

    <p style="margin:0; padding:0 22px 8px; font-size:13px; color:var(--pro-muted);">
        Deleted business owners and clients land here and stay recoverable for <strong>{{ $retentionDays }} days</strong>,
        then they're removed for good — records and files. Restoring a business owner brings back every client that was
        deleted along with it.
    </p>
</div>

{{-- Deleted business owners --}}
<div class="pro-panel" style="margin-bottom:18px;">
    <div class="pro-panel-head">
        <div class="pro-panel-title"><h2 style="font-size:15px;">Deleted Business Owners</h2></div>
    </div>
    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr><th>Business Owner</th><th>Clients</th><th>Deleted</th><th>Deleted By</th><th>Retention</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($owners as $o)
                    <tr>
                        <td><strong>{{ $o->business_name }}</strong><div class="rb-sub">{{ $o->email }}</div></td>
                        <td>{{ $o->end_users_count }}</td>
                        <td>{{ $o->deleted_at?->format('M j, Y g:i A') }}</td>
                        <td class="muted">{{ $o->deletedBy?->full_name ?? '—' }}</td>
                        <td>{!! $pill(RecycleBin::daysLeft($o->deleted_at)) !!}</td>
                        <td class="rb-actions">
                            <form method="POST" action="{{ route('admin.recycle-bin.client.restore', $o->id) }}">
                                @csrf
                                <button class="pro-act" type="submit">Restore</button>
                            </form>
                            <form method="POST" action="{{ route('admin.recycle-bin.client.force', $o->id) }}"
                                  data-confirm-delete data-confirm-message="Permanently delete {{ $o->business_name }} and all {{ $o->end_users_count }} of its clients, including every document? This cannot be undone.">
                                @csrf @method('DELETE')
                                <button class="pro-act del" type="submit">Delete forever</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No deleted business owners.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Deleted individual clients --}}
<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title"><h2 style="font-size:15px;">Deleted Clients</h2></div>
    </div>
    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr><th>Client</th><th>Business Owner</th><th>Deleted</th><th>Deleted By</th><th>Retention</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($clients as $c)
                    <tr>
                        <td><strong>{{ $c->full_name }}</strong>@if ($c->email)<div class="rb-sub">{{ $c->email }}</div>@endif</td>
                        <td class="muted">{{ $c->client?->business_name ?? '—' }}</td>
                        <td>{{ $c->deleted_at?->format('M j, Y g:i A') }}</td>
                        <td class="muted">{{ $c->deletedBy?->full_name ?? '—' }}</td>
                        <td>{!! $pill(RecycleBin::daysLeft($c->deleted_at)) !!}</td>
                        <td class="rb-actions">
                            <form method="POST" action="{{ route('admin.recycle-bin.end-user.restore', $c->id) }}">
                                @csrf
                                <button class="pro-act" type="submit">Restore</button>
                            </form>
                            <form method="POST" action="{{ route('admin.recycle-bin.end-user.force', $c->id) }}"
                                  data-confirm-delete data-confirm-message="Permanently delete {{ $c->full_name }} and their documents? This cannot be undone.">
                                @csrf @method('DELETE')
                                <button class="pro-act del" type="submit">Delete forever</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No deleted clients.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('head')
<style>
    .rb-sub { font-size:11.5px; color:var(--muted); margin-top:2px; }
    .rb-actions { white-space:nowrap; }
    .rb-actions form { display:inline-block; margin:0 2px; }
    .rb-days { display:inline-block; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:700; }
    .rb-ok   { background:#ecfdf5; color:#047857; }
    .rb-mid  { background:#fffbeb; color:#b45309; }
    .rb-soon { background:#fef2f2; color:#b91c1c; }
    .pro-act { border:1px solid var(--border); background:var(--surface); color:var(--text); border-radius:8px; padding:6px 12px; font-size:12.5px; font-weight:600; cursor:pointer; }
    .pro-act:hover { border-color:var(--muted); }
    .pro-act.del { border-color:#fecaca; color:#b91c1c; }
    .pro-act.del:hover { background:#fef2f2; border-color:#f87171; }
    .rb-empty-all { background:#e11d48; color:var(--on-accent, #fff); border-color:#e11d48; font-weight:700; }
    .rb-empty-all:hover { background:#be123c; border-color:#be123c; color:var(--on-accent, #fff); }
:root[data-theme="dark"] .rb-sub{color:var(--pro-muted);}
:root[data-theme="dark"] .rb-mid{background:var(--pro-card);border-color:var(--pro-line);color:var(--pro-text);}
</style>
@endpush
@push('scripts')
<script>
function rbConfirmEmpty() {
    var msg = 'This PERMANENTLY deletes EVERYTHING in the recycle bin — all deleted '
        + 'business owners and clients, plus every document. This cannot be undone.\n\n'
        + 'Type DELETE to confirm:';
    var ans = window.prompt(msg);
    if (ans !== null && ans.trim().toUpperCase() === 'DELETE') {
        document.getElementById('rbEmptyForm').submit();
    }
}
window.rbConfirmEmpty = rbConfirmEmpty;
</script>
@endpush
@endsection
