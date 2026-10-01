@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Mailboxes')
@section('subtitle', 'Throwaway email addresses for CFPB one-time codes — create one, read the code in webmail, delete it when you are done.')

@php
    // Initials for the little avatar discs, and a stable colour per person so the
    // same client always looks the same here and in the dialog.
    $initials = function (?string $name) {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : mb_substr($parts[0] ?? '', 1, 1);
        return mb_strtoupper($a . $b) ?: '—';
    };
    $tints = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ec4899','#8b5cf6','#14b8a6','#f43f5e'];
    $tintFor = function (?string $s) use ($tints) {
        // Mirrors tintOf() in the script below, so a client keeps one colour.
        $h = 0;
        foreach (str_split((string) $s) as $ch) {
            $h = ($h * 31 + ord($ch)) % 4294967296;
        }
        return $tints[$h % count($tints)];
    };
@endphp

@section('content')

<style>
    /* Colours come from the console's own --pro-* tokens, which the theme switch
       already redefines under :root[data-theme="dark"]. Hard-coding light values
       here made the headings invisible in dark mode. Only the indigo accents are
       restated below, because those have no token of their own. */
    .mb-wrap {
        --ink:   var(--pro-text);
        --muted: var(--pro-muted);
        --line:  var(--pro-line);
        --card:  var(--pro-card);
        --well:  #f8fafc;          /* inset surfaces: address chips, table head */
        --soft:  #eef2ff;          /* indigo wash */
        --softi: #3730a3;          /* ink on that wash */
        --brand: var(--pro-indigo);
        --edge:  #dfe4ee;          /* input borders */
    }
    :root[data-theme="dark"] .mb-wrap {
        --well:  rgba(255,255,255,.045);
        --soft:  rgba(99,102,241,.18);
        --softi: #c7d2fe;
        --edge:  var(--pro-line);
    }

    /* ---------- header ---------- */
    .mb-head { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; padding:16px 18px; }
    .mb-head-l { display:flex; align-items:center; gap:12px; min-width:0; }
    .mb-ico { width:38px; height:38px; border-radius:11px; display:grid; place-items:center; flex:none;
              background:linear-gradient(140deg,#818cf8,#4338ca); color:#fff; box-shadow:0 6px 16px rgba(67,56,202,.28); }
    .mb-ico svg { width:19px; height:19px; }
    .mb-head h2 { margin:0; font-size:19px; letter-spacing:-.01em; color:var(--ink); }
    .mb-pills { display:flex; gap:6px; margin-top:3px; flex-wrap:wrap; }
    .mb-pill { font-size:11.5px; font-weight:600; padding:2.5px 9px; border-radius:999px; background:var(--soft); color:var(--softi); }
    .mb-pill.ghost { background:var(--well); color:var(--muted); }
    .mb-actions { display:flex; align-items:center; gap:9px; }

    .mb-btn { display:inline-flex; align-items:center; gap:7px; border:1px solid var(--edge); background:var(--card); color:var(--ink);
              padding:8px 14px; border-radius:9px; font-size:13.5px; font-weight:600; cursor:pointer; text-decoration:none;
              transition:background .14s, border-color .14s, transform .08s; white-space:nowrap; }
    .mb-btn:hover { background:var(--well); }
    .mb-btn:active { transform:translateY(1px); }
    .mb-btn-primary { background:var(--brand); border-color:var(--brand); color:#fff; box-shadow:0 4px 14px rgba(79,70,229,.3); }
    .mb-btn-primary:hover { background:#4338ca; border-color:#4338ca; color:#fff; }
    .mb-btn-tiny { padding:4px 10px; font-size:12px; border-radius:7px; }
    .mb-btn-danger { color:#dc2626; border-color:rgba(220,38,38,.35); background:rgba(220,38,38,.08); }
    .mb-btn-danger:hover { background:rgba(220,38,38,.16); }

    /* ---------- flashes ---------- */
    .mb-flash { margin:0 18px 14px; padding:11px 14px; border-radius:10px; font-size:13.5px; display:flex; gap:9px; align-items:flex-start; line-height:1.5; }
    .mb-flash.ok   { background:rgba(16,185,129,.12); color:#059669; border:1px solid rgba(16,185,129,.35); }
    .mb-flash.bad  { background:rgba(239,68,68,.1);   color:#dc2626; border:1px solid rgba(239,68,68,.32); }
    .mb-flash.warn { background:rgba(245,158,11,.12); color:#b45309; border:1px solid rgba(245,158,11,.35); }
    :root[data-theme="dark"] .mb-flash.ok   { color:#6ee7b7; }
    :root[data-theme="dark"] .mb-flash.bad  { color:#fca5a5; }
    :root[data-theme="dark"] .mb-flash.warn { color:#fcd34d; }

    /* ---------- table ---------- */
    .mb-table { width:100%; border-collapse:collapse; }
    .mb-table th { text-align:left; font-size:11px; letter-spacing:.07em; text-transform:uppercase; color:var(--muted);
                   font-weight:700; padding:11px 16px; border-bottom:1px solid var(--line); background:var(--well); }
    .mb-table td { padding:13px 16px; border-bottom:1px solid var(--pro-line-soft); vertical-align:middle; font-size:13.5px; color:var(--ink); }
    .mb-table tbody tr { transition:background .12s; }
    .mb-table tbody tr:hover { background:var(--well); }
    .mb-table tbody tr:last-child td { border-bottom:0; }

    .mb-person { display:flex; align-items:center; gap:10px; min-width:0; }
    .mb-av { width:32px; height:32px; border-radius:50%; flex:none; display:grid; place-items:center;
             color:#fff; font-size:11.5px; font-weight:700; letter-spacing:.02em; }
    .mb-person b { display:block; font-weight:600; color:var(--ink); }
    .mb-sub { color:var(--muted); font-size:11.5px; margin-top:1px; }

    .mb-addr { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; color:var(--ink);
               background:var(--well); border:1px solid var(--line); padding:4px 9px; border-radius:7px; }
    .mb-cell { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }

    .mb-meter { width:92px; height:5px; border-radius:999px; background:var(--well); overflow:hidden; margin-top:5px; }
    .mb-meter i { display:block; height:100%; border-radius:999px; background:linear-gradient(90deg,#818cf8,#4f46e5); }
    .mb-meter.hot i { background:linear-gradient(90deg,#fbbf24,#ef4444); }

    .mb-empty { padding:46px 20px; text-align:center; color:var(--muted); }
    .mb-empty-ico { width:50px; height:50px; border-radius:15px; margin:0 auto 12px; display:grid; place-items:center;
                    background:var(--soft); color:#818cf8; }
    .mb-empty b { display:block; color:var(--ink); font-size:14.5px; margin-bottom:4px; }

    /* ---------- dialog ---------- */
    /* display:none is the closed state, NOT the hidden attribute: an inline
       display:flex outranks the [hidden] rule, so the dialog would sit on screen
       permanently and Cancel would appear to do nothing. */
    .mb-scrim { position:fixed; inset:0; z-index:1000; display:none; align-items:center; justify-content:center;
                padding:20px; background:rgba(8,11,22,.58); backdrop-filter:blur(3px); }
    /* No overflow:hidden here — it clipped the search results at the dialog edge. */
    .mb-dialog { background:var(--card); border:1px solid var(--line); border-radius:16px; width:100%; max-width:500px;
                 box-shadow:0 30px 70px rgba(0,0,0,.45); animation:mbIn .16s ease-out; }
    @keyframes mbIn { from { opacity:0; transform:translateY(8px) scale(.985); } to { opacity:1; transform:none; } }

    .mb-dlg-head { padding:20px 22px 0; }
    .mb-dlg-head h3 { margin:0 0 5px; font-size:17.5px; letter-spacing:-.01em; color:var(--ink); }
    .mb-dlg-head p { margin:0; font-size:13px; color:var(--muted); line-height:1.5; }
    .mb-dlg-head strong { color:var(--ink); }
    .mb-dlg-body { padding:18px 22px 4px; }
    .mb-dlg-foot { display:flex; justify-content:flex-end; gap:9px; padding:16px 22px 20px; margin-top:6px; }

    .mb-label { display:block; font-size:12.5px; font-weight:700; color:var(--pro-text-soft); margin-bottom:6px; letter-spacing:.01em; }
    .mb-label span { font-weight:500; color:var(--muted); }

    .mb-field { position:relative; }
    .mb-input { width:100%; padding:10px 12px; border:1px solid var(--edge); border-radius:10px; font-size:13.5px;
                color:var(--ink); background:var(--card); transition:border-color .14s, box-shadow .14s; }
    .mb-input:focus { outline:0; border-color:#818cf8; box-shadow:0 0 0 3px rgba(99,102,241,.2); }
    .mb-input::placeholder { color:var(--muted); }
    .mb-search { padding-left:36px; }
    .mb-search-ico { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none; }
    .mb-search-ico svg { width:15px; height:15px; display:block; }
    .mb-spin { position:absolute; right:12px; top:50%; width:13px; height:13px; margin-top:-6.5px; display:none;
               border:2px solid var(--line); border-top-color:#818cf8; border-radius:50%; animation:mbSpin .6s linear infinite; }
    @keyframes mbSpin { to { transform:rotate(360deg); } }

    .mb-results { display:none; position:absolute; left:0; right:0; top:calc(100% + 6px); z-index:5; max-height:258px;
                  overflow:auto; background:var(--card); border:1px solid var(--line); border-radius:12px;
                  box-shadow:0 18px 44px rgba(0,0,0,.3); padding:5px; }
    .mb-opt { display:flex; align-items:center; gap:10px; width:100%; text-align:left; padding:8px 9px; border:0;
              background:none; cursor:pointer; border-radius:9px; font:inherit; }
    .mb-opt:hover, .mb-opt.on { background:var(--soft); }
    .mb-opt b { display:block; font-size:13.5px; font-weight:600; color:var(--ink); }
    .mb-note { padding:13px 12px; font-size:13px; color:var(--muted); text-align:center; }

    .mb-chosen { display:none; align-items:center; gap:11px; padding:10px 11px; border:1px solid rgba(99,102,241,.4);
                 background:var(--soft); border-radius:11px; }
    .mb-chosen b { font-size:13.5px; font-weight:600; color:var(--ink); display:block; }

    .mb-preview { margin:7px 0 0; font-size:12px; color:var(--muted); display:flex; gap:6px; align-items:baseline; flex-wrap:wrap; }
    .mb-preview code { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12px; color:var(--softi);
                       background:var(--soft); padding:2px 7px; border-radius:6px; }

    @media (max-width:640px) {
        .mb-head { align-items:flex-start; }
        .mb-actions { width:100%; }
    }
</style>

<div class="mb-wrap">

@if (! $configured)
    <div class="pro-panel" style="margin-bottom:16px; overflow:hidden; padding-top:16px;">
        <div class="mb-flash warn">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" style="flex:none; margin-top:2px;"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <div>
                <strong>Mailbox creation is not switched on for this server yet.</strong><br>
                The cPanel settings are missing from this server's <code>.env</code>
                (<code>CPANEL_HOST</code>, <code>CPANEL_USER</code>, <code>CPANEL_API_TOKEN</code>).
                Existing mailboxes are listed below, but nothing can be created or deleted until those are set.
            </div>
        </div>
    </div>
@endif

<div class="pro-panel" style="overflow:hidden;">

    <div class="mb-head">
        <div class="mb-head-l">
            <span class="mb-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 7l9 6 9-6"/></svg>
            </span>
            <div>
                <h2>Mailboxes</h2>
                <div class="mb-pills">
                    <span class="mb-pill">{{ $mailboxes->total() }} active</span>
                    @if ($totalUsedMb > 0)
                        <span class="mb-pill ghost">{{ $totalUsedMb }} MB used</span>
                    @endif
                    <span class="mb-pill ghost">{{ $quotaMb }} MB each</span>
                </div>
            </div>
        </div>
        <div class="mb-actions">
            <a class="mb-btn" href="{{ $webmailUrl }}" target="_blank" rel="noopener">
                Open Webmail
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
            </a>
            @if ($configured)
                <button type="button" class="mb-btn mb-btn-primary" data-mb-open>
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Create Mailbox
                </button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="mb-flash ok">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="flex:none; margin-top:2px;"><polyline points="20 6 9 17 4 12"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @error('mailbox')
        <div class="mb-flash bad">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" style="flex:none; margin-top:2px;"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
            <span>{{ $message }}</span>
        </div>
    @enderror

    <div class="table-scroll">
    <table class="mb-table">
        <thead>
            <tr>
                <th>Client</th>
                <th>Email address</th>
                <th>Password</th>
                <th>Created</th>
                <th>Size</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($mailboxes as $mb)
            @php
                $u    = $usage[strtolower($mb->address)] ?? null;
                $used = $u['used_mb'] ?? null;
                $name = $mb->endUser ? trim($mb->endUser->first_name . ' ' . $mb->endUser->last_name) : null;
                $pct  = ($used !== null && $mb->quota_mb > 0) ? min(100, (int) round($used / $mb->quota_mb * 100)) : null;
            @endphp
            <tr>
                <td>
                    <div class="mb-person">
                        <span class="mb-av" style="background:{{ $tintFor($name ?: $mb->address) }};">{{ $initials($name ?: 'Spare') }}</span>
                        <div style="min-width:0;">
                            <b>{{ $name ?: 'Spare mailbox' }}</b>
                            @if ($mb->createdBy)
                                <div class="mb-sub">by {{ $mb->createdBy->full_name }}</div>
                            @endif
                        </div>
                    </div>
                </td>

                <td class="no-link">
                    <div class="mb-cell">
                        <span class="mb-addr">{{ $mb->address }}</span>
                        <button type="button" class="mb-btn mb-btn-tiny" data-mb-copy="{{ $mb->address }}">Copy</button>
                    </div>
                </td>

                {{-- Hidden until asked for: the password should not be sitting on screen
                     while a VA shares their window. --}}
                <td class="no-link">
                    <div class="mb-cell">
                        <span class="mb-addr" data-mb-pw>••••••••••</span>
                        <span hidden data-mb-secret>{{ $mb->password }}</span>
                        <button type="button" class="mb-btn mb-btn-tiny" data-mb-reveal>Show</button>
                        <button type="button" class="mb-btn mb-btn-tiny" data-mb-copy-pw>Copy</button>
                    </div>
                </td>

                <td style="color:#475569; white-space:nowrap;">{{ $mb->created_at?->format('M j, Y') }}</td>

                <td style="white-space:nowrap;">
                    @if ($used !== null)
                        <span style="color:#334155;">{{ $used }}</span>
                        <span class="mb-sub" style="display:inline;">/ {{ $mb->quota_mb }} MB</span>
                        <div class="mb-meter {{ $pct >= 80 ? 'hot' : '' }}"><i style="width:{{ max((int) $pct, 2) }}%;"></i></div>
                    @else
                        <span class="mb-sub">—</span>
                    @endif
                </td>

                <td class="no-link" style="text-align:right;">
                    <div class="mb-cell" style="justify-content:flex-end;">
                        {{-- Signs in on the way: the server mints a cPanel webmail
                             session for this one mailbox, so nobody retypes anything.
                             POST because it creates that session. --}}
                        <form method="POST" action="{{ route('admin.mailboxes.webmail', $mb->id) }}" target="_blank">
                            @csrf
                            <button class="mb-btn mb-btn-tiny">
                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                                Login to Webmail
                            </button>
                        </form>
                        @if ($configured)
                            <form method="POST" action="{{ route('admin.mailboxes.destroy', $mb->id) }}"
                                  data-confirm-delete
                                  data-confirm-message="Delete {{ $mb->address }}? Everything in it is destroyed on the mail server and cannot be recovered.">
                                @csrf @method('DELETE')
                                <button class="mb-btn mb-btn-tiny mb-btn-danger">Delete</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <div class="mb-empty">
                        <div class="mb-empty-ico">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 7l9 6 9-6"/></svg>
                        </div>
                        <b>No mailboxes yet</b>
                        Create one for a client, collect the CFPB code in webmail, then delete it.
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>

    @if ($mailboxes->hasPages())
        <div style="padding:14px 18px;">{{ $mailboxes->links() }}</div>
    @endif
</div>

{{-- ---------------- Create ---------------- --}}
<div id="mbModal" class="mb-scrim" role="dialog" aria-modal="true" aria-labelledby="mbTitle">
    <div class="mb-dialog">
        <form method="POST" action="{{ route('admin.mailboxes.store') }}" autocomplete="off">
            @csrf

            <div class="mb-dlg-head">
                <h3 id="mbTitle">Create a mailbox</h3>
                <p>Created on <strong>{{ $domain }}</strong> with a {{ $quotaMb }} MB limit. The address and password appear in the list straight away.</p>
            </div>

            <div class="mb-dlg-body">
                <label class="mb-label" for="mbSearch">Client <span>— optional</span></label>

                {{-- Searched, not listed: every client across every business owner.
                     The chosen id rides in the hidden input. Attaching a client is
                     only a label on this page — nothing is ever written to their
                     own record. --}}
                <input type="hidden" name="end_user_id" id="mbClientId" value="">

                <div id="mbPicked" class="mb-chosen">
                    <span class="mb-av" id="mbPickedAv"></span>
                    <div style="min-width:0;">
                        <b id="mbPickedName"></b>
                        <div class="mb-sub" id="mbPickedBo"></div>
                    </div>
                    <button type="button" id="mbClear" class="mb-btn mb-btn-tiny" style="margin-left:auto;">Change</button>
                </div>

                <div id="mbSearchWrap" class="mb-field">
                    <span class="mb-search-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.4-4.4"/></svg>
                    </span>
                    <input type="text" id="mbSearch" class="mb-input mb-search" autocomplete="off" spellcheck="false"
                           placeholder="Search by name, email or phone…">
                    <span class="mb-spin" id="mbSpin"></span>
                    <div id="mbResults" class="mb-results"></div>
                </div>

                <div style="height:16px;"></div>

                <label class="mb-label" for="mbName">Mailbox name <span>— fills in from the client</span></label>
                <input type="text" id="mbName" name="local_part" maxlength="60" class="mb-input"
                       placeholder="leave blank for a spare mailbox">
                <p class="mb-preview">
                    <span>Address:</span>
                    <code id="mbPreview">—</code>
                </p>
            </div>

            <div class="mb-dlg-foot">
                <button type="button" class="mb-btn" data-mb-close>Cancel</button>
                <button class="mb-btn mb-btn-primary">Create Mailbox</button>
            </div>
        </form>
    </div>
</div>

</div>{{-- /.mb-wrap --}}

<script>
(function () {
    var DOMAIN     = @json($domain);
    var SEARCH_URL = @json(route('admin.mailboxes.clients'));
    var TINTS      = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ec4899','#8b5cf6','#14b8a6','#f43f5e'];

    var modal   = document.getElementById('mbModal');
    var input   = document.getElementById('mbSearch');
    var results = document.getElementById('mbResults');
    var spin    = document.getElementById('mbSpin');
    var hidden  = document.getElementById('mbClientId');
    var picked  = document.getElementById('mbPicked');
    var pName   = document.getElementById('mbPickedName');
    var pBo     = document.getElementById('mbPickedBo');
    var pAv     = document.getElementById('mbPickedAv');
    var wrap    = document.getElementById('mbSearchWrap');
    var clear   = document.getElementById('mbClear');
    var nameIn  = document.getElementById('mbName');
    var preview = document.getElementById('mbPreview');

    // ---------- open / close ----------
    function open() {
        if (!modal) return;
        modal.style.display = 'flex';
        if (input) setTimeout(function () { input.focus(); }, 30);
    }
    function close() { if (modal) modal.style.display = 'none'; }

    // ---------- helpers ----------
    function initialsOf(name) {
        var p = String(name || '').trim().split(/\s+/);
        var a = (p[0] || '').charAt(0);
        var b = p.length > 1 ? (p[p.length - 1] || '').charAt(0) : (p[0] || '').charAt(1);
        return (a + b).toUpperCase() || '—';
    }
    // Mirrors $tintFor in the Blade above, so a client keeps one colour everywhere.
    function tintOf(s) {
        var h = 0, str = String(s || '');
        for (var i = 0; i < str.length; i++) { h = (h * 31 + str.charCodeAt(i)) % 4294967296; }
        return TINTS[h % TINTS.length];
    }
    // The same shape the server builds the address from.
    function slug(s) {
        return String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, '.').replace(/^\.+|\.+$/g, '');
    }
    function paintPreview() {
        var base = slug(nameIn.value);
        preview.textContent = base ? base + '####@' + DOMAIN : '—';
        preview.title = base ? 'Four digits are added so two clients with the same name never clash.' : '';
    }

    function flash(btn, word) {
        var was = btn.textContent;
        btn.textContent = word;
        setTimeout(function () { btn.textContent = was; }, 1200);
    }
    function copy(text, btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { flash(btn, 'Copied'); },
                                                     function () { fallback(text, btn); });
        } else { fallback(text, btn); }
    }
    // Older WebView / http contexts have no async clipboard.
    function fallback(text, btn) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); flash(btn, 'Copied'); } catch (e) { flash(btn, 'Ctrl+C'); }
        ta.remove();
    }

    // ---------- list actions ----------
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t.closest('[data-mb-open]'))  { open();  return; }
        if (t.closest('[data-mb-close]')) { close(); return; }

        var copyBtn = t.closest('[data-mb-copy]');
        if (copyBtn) { copy(copyBtn.getAttribute('data-mb-copy'), copyBtn); return; }

        var cell = t.closest('td');

        var reveal = t.closest('[data-mb-reveal]');
        if (reveal && cell) {
            var pw     = cell.querySelector('[data-mb-pw]');
            var secret = cell.querySelector('[data-mb-secret]');
            var shown  = reveal.textContent.trim() === 'Hide';
            pw.textContent     = shown ? '••••••••••' : secret.textContent;
            reveal.textContent = shown ? 'Show' : 'Hide';
            return;
        }

        var copyPw = t.closest('[data-mb-copy-pw]');
        if (copyPw && cell) { copy(cell.querySelector('[data-mb-secret]').textContent, copyPw); return; }
    });

    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    // ---------- client type-ahead ----------
    if (!input) return;

    var timer = null, seq = 0, rows = [], cursor = -1;

    function hideResults() { results.style.display = 'none'; results.innerHTML = ''; rows = []; cursor = -1; }
    function note(text) {
        results.innerHTML = '';
        var d = document.createElement('div');
        d.className = 'mb-note';
        d.textContent = text;
        results.appendChild(d);
        results.style.display = 'block';
    }

    function choose(row) {
        hidden.value      = row.id;
        pName.textContent = row.name;
        pBo.textContent   = row.bo_name || '';
        pAv.textContent   = initialsOf(row.name);
        pAv.style.background = tintOf(row.name);
        picked.style.display = 'flex';
        wrap.style.display   = 'none';

        // Picking a client fills the mailbox name in at once. It stays editable —
        // a suggestion, not a lock.
        nameIn.value = slug(row.name);
        paintPreview();
        hideResults();
    }

    function unchoose() {
        hidden.value = '';
        picked.style.display = 'none';
        wrap.style.display   = 'block';
        input.value  = '';
        nameIn.value = '';
        paintPreview();
        input.focus();
    }

    function highlight(i) {
        var opts = results.querySelectorAll('.mb-opt');
        if (!opts.length) return;
        cursor = (i + opts.length) % opts.length;
        Array.prototype.forEach.call(opts, function (o, n) { o.classList.toggle('on', n === cursor); });
        opts[cursor].scrollIntoView({ block: 'nearest' });
    }

    function render(list) {
        rows = list; cursor = -1;
        if (!list.length) { note('No client found.'); return; }

        results.innerHTML = '';
        list.forEach(function (row) {
            var opt = document.createElement('button');
            opt.type = 'button';
            opt.className = 'mb-opt';

            var av = document.createElement('span');
            av.className = 'mb-av';
            av.style.background = tintOf(row.name);
            av.textContent = initialsOf(row.name);

            var box = document.createElement('div');
            box.style.minWidth = '0';
            // textContent throughout: a client's own name must never be able to
            // inject markup into this list.
            var b = document.createElement('b');
            b.textContent = row.name;
            var sub = document.createElement('div');
            sub.className = 'mb-sub';
            sub.textContent = [row.bo_name, row.email].filter(Boolean).join(' · ');

            box.appendChild(b); box.appendChild(sub);
            opt.appendChild(av); opt.appendChild(box);
            opt.addEventListener('click', function () { choose(row); });
            results.appendChild(opt);
        });
        results.style.display = 'block';
    }

    function search() {
        var q = input.value.trim();
        if (q.length < 2) { hideResults(); return; }

        // Only the newest reply is allowed to paint: typing fast otherwise lets a
        // slower earlier request overwrite a later one.
        var mine = ++seq;
        spin.style.display = 'block';

        fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function (r) { return r.ok ? r.json() : { results: [] }; })
            .then(function (d) { if (mine === seq) { spin.style.display = 'none'; render(d.results || []); } })
            .catch(function ()  { if (mine === seq) { spin.style.display = 'none'; note('Could not search just now.'); } });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(search, 200);
    });

    input.addEventListener('keydown', function (e) {
        if (results.style.display !== 'block') return;
        if (e.key === 'ArrowDown')      { e.preventDefault(); highlight(cursor + 1); }
        else if (e.key === 'ArrowUp')   { e.preventDefault(); highlight(cursor - 1); }
        else if (e.key === 'Enter' && cursor > -1 && rows[cursor]) { e.preventDefault(); choose(rows[cursor]); }
    });

    nameIn.addEventListener('input', paintPreview);
    if (clear) clear.addEventListener('click', unchoose);

    // Clicking away closes the list without clearing what was typed.
    document.addEventListener('click', function (e) {
        if (wrap && !wrap.contains(e.target)) hideResults();
    });

    paintPreview();
})();
</script>
@endsection
