@extends('layouts.admin-pro')

@section('title', 'Letter Generator')
@section('subtitle', 'Upload a 3-bureau report, audit it, and pick what to dispute.')

@section('content')
<div class="pro-panel" style="padding:24px; margin-bottom:20px;">
    <div class="pro-panel-title" style="margin-bottom:6px;">
        <span class="pro-panel-chip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h11l5 5v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><polyline points="14 4 14 10 20 10"/></svg>
        </span>
        <h2>Upload a Credit Report</h2>
    </div>
    <p class="muted" style="margin:0 0 16px; font-size:13.5px;">
        Save the client's <strong>MyFreeScoreNow</strong> 3-bureau report from the browser as an
        <strong>HTML file</strong> (right-click → Save As → “Webpage, HTML Only”), then upload it here.
        It's audited against your rules and the file is discarded — nothing is stored on disk.
    </p>

    <form method="POST" action="{{ route('admin.letter-generator.store') }}" enctype="multipart/form-data" class="lg-upload">
        @csrf
        <label class="lg-drop" id="lgDrop">
            <input type="file" name="report" accept=".html,.htm" required id="lgFile" hidden>
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 9 12 4 17 9"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
            <span class="lg-drop-main" id="lgDropText">Choose the saved report HTML file</span>
            <span class="lg-drop-sub">.html or .htm · up to 20 MB</span>
        </label>
        @error('report')<div class="field-error" style="color:#dc2626; font-size:13px; margin-top:8px;">{{ $message }}</div>@enderror
        <button type="submit" class="pro-cta" style="margin-top:14px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <span>Audit Report</span>
        </button>
    </form>
</div>

<div class="pro-panel">
    <div class="pro-panel-head">
        <div class="pro-panel-title">
            <span class="pro-panel-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v4H3z"/><path d="M3 10h18v11H3z"/></svg></span>
            <h2>Audited Reports</h2>
            <span class="pro-panel-count">{{ $reports->count() }}</span>
        </div>
    </div>
    <div class="pro-table-scroll">
        <table class="pro-table">
            <thead>
                <tr><th>Consumer</th><th>Report Date</th><th>Accounts</th><th>Inquiries</th><th>Flagged</th><th>Uploaded</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($reports as $r)
                    <tr data-href="{{ route('admin.letter-generator.show', $r) }}">
                        <td><a href="{{ route('admin.letter-generator.show', $r) }}" style="font-weight:600; color:var(--pro-text); text-decoration:none;">{{ $r->consumer_name ?: 'Unnamed report' }}</a></td>
                        <td>{{ $r->report_date?->format('M j, Y') ?: '—' }}</td>
                        <td>{{ $r->account_count }}</td>
                        <td>{{ $r->inquiry_count }}</td>
                        <td>@if ($r->negative_count)<span class="lg-pill lg-red">{{ $r->negative_count }} flagged</span>@else <span class="muted">0</span>@endif</td>
                        <td class="muted">{{ $r->created_at->diffForHumans() }}</td>
                        <td>
                            <div class="pro-actions">
                                <a href="{{ route('admin.letter-generator.show', $r) }}" class="pro-act view">Review</a>
                                <form method="POST" action="{{ route('admin.letter-generator.destroy', $r) }}"
                                      onsubmit="return confirm(@js('Delete this audited report for ' . ($r->consumer_name ?: 'this consumer') . '?'))">
                                    @csrf @method('DELETE')
                                    <button class="pro-act del">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No reports audited yet — upload one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('head')
<style>
    .lg-upload { max-width:640px; }
    .lg-drop {
        display:flex; flex-direction:column; align-items:center; gap:6px; text-align:center;
        padding:34px 20px; border:2px dashed var(--pro-line); border-radius:16px;
        background:var(--pro-line-soft); color:var(--pro-text-soft); cursor:pointer;
        transition:border-color .15s, background .15s;
    }
    .lg-drop:hover, .lg-drop.drag { border-color:var(--pro-indigo); background:#eef2ff; }
    .lg-drop svg { color:var(--pro-indigo); }
    .lg-drop-main { font-size:15px; font-weight:700; color:var(--pro-text); }
    .lg-drop-sub { font-size:12px; color:var(--pro-muted); }
    .lg-drop.has-file .lg-drop-main { color:#047857; }
    .lg-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; }
    .lg-red { background:#fee2e2; color:#b91c1c; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var drop = document.getElementById('lgDrop'), file = document.getElementById('lgFile'), txt = document.getElementById('lgDropText');
    if (!drop) return;
    file.addEventListener('change', function () {
        if (file.files.length) { drop.classList.add('has-file'); txt.textContent = file.files[0].name; }
    });
    ['dragover','dragenter'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.add('drag'); }));
    ['dragleave','drop'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.remove('drag'); }));
    drop.addEventListener('drop', ev => { if (ev.dataTransfer.files.length) { file.files = ev.dataTransfer.files; file.dispatchEvent(new Event('change')); } });
})();
</script>
@endpush
@endsection
