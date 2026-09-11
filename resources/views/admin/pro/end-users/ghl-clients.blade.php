@extends('layouts.admin-pro')

@section('title', 'GHL Clients')
@section('subtitle', 'Onboarding pulled from GoHighLevel, waiting for review.')

@section('content')
<div class="ghl-page">

    {{-- ---------------------------------------------------------------- hero --}}
    <section class="ghl-hero">
        <span class="ghl-blob ghl-blob-a"></span>
        <span class="ghl-blob ghl-blob-b"></span>

        <div class="ghl-hero-main">
            <div class="ghl-hero-copy">
                <div class="ghl-hero-title">
                    <span class="ghl-badge-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </span>
                    <div>
                        <h2>GoHighLevel Onboarding</h2>
                        <p class="ghl-hero-sub">
                            Everything a client submitted on {{ $client->business_name }}'s Credit Repair
                            Onboarding Form — every answer, plus their licence, proof of address and SSN
                            card — brought straight in. Anyone already pulled is skipped, so press it as
                            often as you like.
                        </p>
                    </div>
                </div>

                <div class="ghl-stats">
                    <div class="ghl-stat">
                        <strong>{{ $endUsers->count() }}</strong>
                        <span>Awaiting review</span>
                    </div>
                    <div class="ghl-stat">
                        <strong>{{ $pulledTotal }}</strong>
                        <span>Pulled all time</span>
                    </div>
                    <div class="ghl-stat">
                        <strong>{{ $lastPull ? $lastPull->diffForHumans(null, true) : '—' }}</strong>
                        <span>{{ $lastPull ? 'Since last pull' : 'Never pulled' }}</span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.ghl-clients.sync') }}" class="ghl-sync-form" data-ghl-sync>
                @csrf
                <button class="ghl-sync-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
                    Sync now
                </button>
                <span class="ghl-sync-hint">
                    {{ $lastPull ? 'Last pulled ' . $lastPull->format('M j, g:ia') : 'Not pulled yet' }}
                </span>
            </form>
        </div>
    </section>

    {{-- The three panels below are one sequence. Spelling it out here saves a VA
         guessing which list a client should be in, and in what order. --}}
    <section class="ghl-steps">
        <div class="ghl-step">
            <span class="ghl-step-no">1</span>
            <div>
                <strong>Bring them in</strong>
                <p>Press <em>Sync now</em>. Anyone who filled in the onboarding form arrives with all their details and documents.</p>
            </div>
        </div>
        <span class="ghl-step-arrow">→</span>
        <div class="ghl-step">
            <span class="ghl-step-no">2</span>
            <div>
                <strong>Check the file</strong>
                <p>Open <em>Review</em>, make sure the details and documents are right, then <em>Move to In Progress</em>.</p>
            </div>
        </div>
        <span class="ghl-step-arrow">→</span>
        <div class="ghl-step">
            <span class="ghl-step-no">3</span>
            <div>
                <strong>Send to DisputeFox</strong>
                <p>Tick the client and press <em>Push to DisputeFox</em>. Their file is created there, ready to work.</p>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------ awaiting review --}}
    <section class="ghl-card">
        <header class="ghl-card-head">
            <span class="ghl-step-badge">Step 2</span>
            <h3>Check these files</h3>
            <span class="ghl-pill {{ $endUsers->count() ? 'hot' : 'calm' }}">{{ $endUsers->count() }}</span>
            <p class="ghl-card-sub">
                Just arrived from GoHighLevel and nobody has looked at them yet. Open each one, check the
                details and documents look right, then move them on. Anything wrong — send it to Errors.
            </p>
        </header>

        @if ($endUsers->isEmpty())
            <div class="ghl-empty">
                <span class="ghl-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <p class="ghl-empty-title">Nothing waiting</p>
                <p class="ghl-empty-sub">Every onboarding submission has been dealt with. Press <strong>Sync now</strong> to check GoHighLevel for new ones.</p>
            </div>
        @else
            <div class="ghl-table-wrap">
                <table class="ghl-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Documents</th>
                            <th>Onboarded</th>
                            <th class="ghl-th-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($endUsers as $eu)
                            @php
                                $missing = collect([
                                    'Licence'  => $eu->photo_id_path,
                                    'Address'  => $eu->proof_of_address_path,
                                    'SSN card' => $eu->ssn_picture_path,
                                ])->filter(fn ($path) => blank($path))->keys();

                                // The GHL form cannot enforce any of this, so the
                                // reviewer is the last line of defence. Flag it where
                                // they are already looking rather than in a log.
                                $flags = [];
                                if (blank($eu->ghl_dob_raw) && !$eu->date_of_birth) {
                                    $flags[] = 'No date of birth';
                                }
                                if (blank($eu->ssn) || strlen(preg_replace('/\D/', '', (string) $eu->ssn)) !== 9) {
                                    $flags[] = 'SSN not 9 digits';
                                }
                                if (blank($eu->cfpb_email)) {
                                    $flags[] = 'No CFPB login';
                                }
                                if (blank($eu->credit_monitoring_username)) {
                                    $flags[] = 'No monitoring login';
                                }
                            @endphp
                            <tr>
                                <td>
                                    <div class="ghl-person">
                                        <span class="ghl-avatar">{{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}</span>
                                        <div>
                                            <a href="{{ route('admin.end-users.show', $eu) }}" class="ghl-person-name">{{ $eu->full_name }}</a>
                                            <span class="ghl-tag">GHL</span>
                                            @if ($eu->intake_review_note)
                                                <div class="ghl-note-line">⚠ Sent back: {{ $eu->intake_review_note }}</div>
                                            @endif
                                            @if ($flags)
                                                <div class="ghl-flags">
                                                    @foreach ($flags as $flag)
                                                        <span class="ghl-flag">{{ $flag }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="ghl-contact">{{ $eu->email }}</div>
                                    <div class="ghl-contact ghl-muted">{{ $eu->phone ?: '—' }}</div>
                                </td>
                                <td>
                                    @if ($missing->isEmpty())
                                        <span class="ghl-chip ok">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            All 3 received
                                        </span>
                                    @else
                                        <span class="ghl-chip warn">Missing: {{ $missing->implode(', ') }}</span>
                                    @endif
                                </td>
                                <td class="ghl-muted">
                                    {{ $eu->intake_submitted_at?->format('M j, Y') ?: '—' }}
                                    @if ($eu->intake_submitted_at)
                                        <div class="ghl-time">{{ $eu->intake_submitted_at->format('g:ia') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="ghl-actions">
                                        <a href="{{ route('admin.end-users.show', $eu) }}" class="ghl-act view">Review</a>
                                        <form method="POST" action="{{ route('admin.new-clients.approve', $eu->id) }}"
                                              data-confirm-action data-confirm-message="Are you sure you want to move {{ $eu->full_name }} to In Progress?">
                                            @csrf
                                            <button class="ghl-act go">Move to In Progress</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.end-users.to-errors', $eu->id) }}" class="err-form">
                                            @csrf
                                            <input type="hidden" name="note" value="">
                                            <button type="button" class="ghl-act warn" onclick="moveToErrors(this, '{{ addslashes($eu->full_name) }}')">Errors</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.end-users.hold', $eu->id) }}">
                                            @csrf
                                            <button class="ghl-act hold">Hold</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.end-users.destroy', $eu->id) }}"
                                              data-confirm-delete
                                              data-confirm-title="Delete this client?"
                                              data-confirm-message="{{ $eu->full_name }} and their uploaded documents go to the Recycle Bin, where they can be restored for 10 days. The next sync will not bring them back — it remembers the submission even after deletion.">
                                            @csrf @method('DELETE')
                                            <button class="ghl-act danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- --------------------------------------------------- push to disputefox --}}
    <section class="ghl-card">
        <header class="ghl-card-head">
            <span class="ghl-step-badge">Step 3</span>
            <h3>Send to DisputeFox</h3>
            <span class="ghl-pill {{ $pushable->count() ? 'info' : 'calm' }}">{{ $pushable->count() }}</span>
            <p class="ghl-card-sub">
                Not in DisputeFox yet. Tick the ones you want to send and press the button — their
                details and documents are created over there automatically. You will be asked to
                confirm the names first.
                <br>
                There is no "send everyone" button on purpose: DisputeFox cannot spot a duplicate,
                so a client sent twice becomes two files someone has to clean up.
                @if ($pushedTotal)
                    <strong>{{ $pushedTotal }}</strong> {{ $pushedTotal === 1 ? 'client has' : 'clients have' }} already been sent.
                @endif
            </p>
        </header>

        @if ($pushable->isEmpty())
            <div class="ghl-empty">
                <span class="ghl-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <p class="ghl-empty-title">Everyone has been sent</p>
                <p class="ghl-empty-sub">Every client pulled from GoHighLevel is already in DisputeFox.</p>
            </div>
        @else
            <form method="POST" action="{{ route('admin.ghl-clients.push-df') }}" data-df-push>
                @csrf
                <div class="ghl-table-wrap">
                    <table class="ghl-table">
                        <thead>
                            <tr>
                                <th class="ghl-th-tick"><input type="checkbox" id="dfAll" aria-label="Select all"></th>
                                <th>Client</th>
                                <th>Email</th>
                                <th>Documents</th>
                                <th>Where they are in Apex</th>
                                <th>Last time we tried</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pushable as $eu)
                                @php
                                    $docCount = collect([$eu->photo_id_path, $eu->proof_of_address_path, $eu->ssn_picture_path])
                                        ->filter()->count();
                                @endphp
                                <tr>
                                    <td class="ghl-th-tick">
                                        <input type="checkbox" name="end_user_ids[]" value="{{ $eu->id }}" class="df-tick">
                                    </td>
                                    <td>
                                        <div class="ghl-person">
                                            <span class="ghl-avatar sm">{{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}</span>
                                            <a href="{{ route('admin.end-users.show', $eu) }}" class="ghl-person-name">{{ $eu->full_name }}</a>
                                        </div>
                                    </td>
                                    <td class="ghl-muted">{{ $eu->email }}</td>
                                    <td>
                                        <span class="ghl-chip {{ $docCount === 3 ? 'ok' : 'warn' }}">{{ $docCount }} of 3</span>
                                    </td>
                                    <td class="ghl-muted">{{ ucfirst(str_replace('_', ' ', (string) $eu->intake_status ?: 'in progress')) }}</td>
                                    <td class="ghl-muted">
                                        @if ($eu->disputefox_result)
                                            <span class="ghl-flag">{{ Str::limit($eu->disputefox_result, 60) }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="df-bar">
                    <span class="df-count" id="dfCount">None selected</span>
                    <button class="ghl-sync-btn" id="dfPushBtn" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        Push to DisputeFox
                    </button>
                </div>
            </form>
        @endif
    </section>

    {{-- --------------------------------------------------------- pull history --}}
    @if ($recent->isNotEmpty())
        <section class="ghl-card">
            <header class="ghl-card-head">
                <h3>Everyone from GoHighLevel</h3>
                <span class="ghl-pill calm">{{ $pulledTotal }}</span>
                <p class="ghl-card-sub">
                    The full record of who has been brought in, where they are now, and whether they
                    have reached DisputeFox. Nobody on this list can arrive twice — a repeat sync skips them.
                </p>
            </header>

            <div class="ghl-table-wrap">
                <table class="ghl-table">
                    <thead>
                        <tr>
                            <th>Client</th><th>Email</th><th>Where they are</th>
                            <th>In DisputeFox</th><th>Onboarded</th><th>Brought in</th>
                            <th class="ghl-th-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $eu)
                            @php
                                $status = (string) $eu->intake_status;
                                $tone = match ($status) {
                                    'done'                 => 'ok',
                                    'pending_review'       => 'info',
                                    'error', 'round_error' => 'warn',
                                    default                => 'calm',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="ghl-person">
                                        <span class="ghl-avatar sm">{{ mb_strtoupper(mb_substr($eu->first_name, 0, 1) . mb_substr($eu->last_name, 0, 1)) }}</span>
                                        <a href="{{ route('admin.end-users.show', $eu) }}" class="ghl-person-name">{{ $eu->full_name }}</a>
                                    </div>
                                </td>
                                <td class="ghl-muted">{{ $eu->email }}</td>
                                <td><span class="ghl-chip {{ $tone }}">{{ ucfirst(str_replace('_', ' ', $status ?: 'in progress')) }}</span></td>
                                <td>
                                    @if ($eu->disputefox_pushed_at)
                                        <span class="ghl-chip ok" title="Sent {{ $eu->disputefox_pushed_at->format('M j, Y g:ia') }}">
                                            Sent {{ $eu->disputefox_pushed_at->format('M j') }}
                                        </span>
                                    @else
                                        <span class="ghl-chip calm">Not sent yet</span>
                                    @endif
                                </td>
                                <td class="ghl-muted">{{ $eu->intake_submitted_at?->format('M j, Y') ?: '—' }}</td>
                                <td class="ghl-muted">{{ $eu->ghl_synced_at?->format('M j, g:ia') ?: '—' }}</td>
                                <td>
                                    <div class="ghl-actions">
                                        <a href="{{ route('admin.end-users.show', $eu) }}" class="ghl-act view">Open</a>
                                        <form method="POST" action="{{ route('admin.end-users.destroy', $eu->id) }}"
                                              data-confirm-delete
                                              data-confirm-title="Delete this client?"
                                              data-confirm-message="{{ $eu->full_name }} and their uploaded documents go to the Recycle Bin, where they can be restored for 10 days. The next sync will not bring them back.{{ $eu->disputefox_pushed_at ? ' Note: their file in DisputeFox is NOT removed — delete it there separately.' : '' }}">
                                            @csrf @method('DELETE')
                                            <button class="ghl-act danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

</div>

@include('admin.end-users._ghl-sync-modal')

@endsection

@push('head')
<style>
    .ghl-page {
        --g1:#6366f1; --g2:#a855f7; --ink:#111827; --soft:#6b7280; --line:#ecedf3;
        display:flex; flex-direction:column; gap:18px;
    }

    /* ----------------------------------------------------------------- hero */
    .ghl-hero {
        position:relative; overflow:hidden; border-radius:18px; padding:26px 28px;
        background:linear-gradient(135deg,#f3f4ff 0%, #fbf6ff 42%, #ffffff 100%);
        border:1px solid #e8e6fb;
        box-shadow:0 1px 2px rgba(16,24,40,.04);
    }
    .ghl-blob { position:absolute; border-radius:50%; filter:blur(46px); opacity:.5; pointer-events:none; }
    .ghl-blob-a { width:230px; height:230px; right:-60px; top:-110px; background:#c7d2fe; }
    .ghl-blob-b { width:180px; height:180px; right:120px; bottom:-120px; background:#f0abfc; opacity:.35; }

    .ghl-hero-main { position:relative; display:flex; gap:26px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; }
    .ghl-hero-copy { flex:1 1 420px; min-width:0; }
    .ghl-hero-title { display:flex; gap:14px; align-items:flex-start; }
    .ghl-hero-title h2 { margin:0 0 5px; font-size:19px; font-weight:700; color:var(--ink); letter-spacing:-.015em; }
    .ghl-hero-sub { margin:0; font-size:13px; line-height:1.6; color:var(--soft); max-width:62ch; }

    .ghl-badge-icon {
        flex:none; width:42px; height:42px; border-radius:13px; display:grid; place-items:center; color:#fff;
        background:linear-gradient(140deg, var(--g1), var(--g2));
        box-shadow:0 8px 20px -8px rgba(99,102,241,.9);
    }
    .ghl-badge-icon svg { width:20px; height:20px; }

    .ghl-stats { display:flex; gap:10px; margin-top:20px; flex-wrap:wrap; }
    .ghl-stat {
        padding:10px 16px; border-radius:12px; background:rgba(255,255,255,.75);
        border:1px solid #e9e9f4; min-width:104px;
    }
    .ghl-stat strong { display:block; font-size:17px; font-weight:700; color:var(--ink); line-height:1.25; }
    .ghl-stat span { font-size:10.5px; text-transform:uppercase; letter-spacing:.06em; color:#8b90a3; font-weight:600; }

    .ghl-sync-form { display:flex; flex-direction:column; align-items:flex-end; gap:7px; margin:0; }
    .ghl-sync-btn {
        display:inline-flex; align-items:center; gap:9px; white-space:nowrap;
        padding:12px 22px; border:0; border-radius:12px; cursor:pointer;
        background:linear-gradient(140deg, var(--g1), #7c3aed 60%, var(--g2));
        color:#fff; font-size:14px; font-weight:650;
        box-shadow:0 12px 26px -12px rgba(99,102,241,1);
        transition:transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }
    .ghl-sync-btn svg { width:16px; height:16px; }
    .ghl-sync-btn:hover { transform:translateY(-1px); filter:brightness(1.06); box-shadow:0 16px 32px -12px rgba(99,102,241,1); }
    .ghl-sync-btn:active { transform:translateY(0); }
    .ghl-sync-btn:disabled { opacity:.65; cursor:default; transform:none; }
    .ghl-sync-hint { font-size:11.5px; color:#9096a8; }

    /* ---------------------------------------------------------------- cards */
    .ghl-card { background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:0 1px 2px rgba(16,24,40,.04); overflow:hidden; }
    .ghl-card-head { display:flex; align-items:center; gap:10px; padding:18px 22px; border-bottom:1px solid var(--line); flex-wrap:wrap; }
    .ghl-card-head h3 { margin:0; font-size:15px; font-weight:700; color:var(--ink); }
    .ghl-card-sub { margin:0; flex:1 1 100%; font-size:12.5px; color:var(--soft); }

    .ghl-pill { min-width:24px; padding:2px 9px; border-radius:999px; font-size:12px; font-weight:700; text-align:center; }
    .ghl-pill.hot  { background:#fee2e2; color:#b91c1c; }
    .ghl-pill.calm { background:#eef0f6; color:#5a6072; }

    /* --------------------------------------------------------------- tables */
    .ghl-table-wrap { overflow-x:auto; }
    .ghl-table { width:100%; border-collapse:collapse; font-size:13.5px; }
    .ghl-table th {
        text-align:left; padding:11px 22px; font-size:10.5px; font-weight:700;
        text-transform:uppercase; letter-spacing:.07em; color:#9096a8;
        background:#fbfbfd; border-bottom:1px solid var(--line); white-space:nowrap;
    }
    .ghl-table td { padding:15px 22px; border-bottom:1px solid #f4f5f9; vertical-align:middle; color:var(--ink); }
    .ghl-table tbody tr { transition:background .14s ease; }
    .ghl-table tbody tr:hover { background:#fafaff; }
    .ghl-table tbody tr:last-child td { border-bottom:0; }
    .ghl-th-actions { text-align:right; }
    .ghl-muted { color:var(--soft); }
    .ghl-time { font-size:11.5px; color:#a2a7b6; margin-top:2px; }
    .ghl-contact { line-height:1.5; }

    .ghl-person { display:flex; align-items:center; gap:11px; }
    .ghl-avatar {
        flex:none; width:36px; height:36px; border-radius:11px; display:grid; place-items:center;
        font-size:12.5px; font-weight:700; color:#4c1d95;
        background:linear-gradient(140deg,#ede9fe,#f5e8ff); border:1px solid #e6ddfb;
    }
    .ghl-avatar.sm { width:30px; height:30px; border-radius:9px; font-size:11px; }
    .ghl-person-name { font-weight:650; color:var(--ink); text-decoration:none; }
    .ghl-person-name:hover { color:#4f46e5; }
    .ghl-note-line { margin-top:3px; font-size:11.5px; color:#b45309; }
    /* ------------------------------------------------------- the three steps */
    .ghl-steps {
        display:flex; align-items:stretch; gap:10px; flex-wrap:wrap;
        background:#fff; border:1px solid var(--line); border-radius:16px; padding:16px 18px;
        box-shadow:0 1px 2px rgba(16,24,40,.04);
    }
    .ghl-step { display:flex; gap:11px; align-items:flex-start; flex:1 1 220px; min-width:0; }
    .ghl-step-no {
        flex:none; width:24px; height:24px; border-radius:50%; display:grid; place-items:center;
        background:linear-gradient(140deg, var(--g1), var(--g2)); color:#fff;
        font-size:12px; font-weight:700; margin-top:1px;
    }
    .ghl-step strong { display:block; font-size:13.5px; color:var(--ink); margin-bottom:2px; }
    .ghl-step p { margin:0; font-size:12.5px; line-height:1.5; color:var(--soft); }
    .ghl-step em { font-style:normal; font-weight:650; color:#4f46e5; }
    .ghl-step-arrow { align-self:center; color:#c9ccd8; font-size:17px; }
    @media (max-width:900px) { .ghl-step-arrow { display:none; } }

    .ghl-step-badge {
        padding:3px 9px; border-radius:999px; font-size:10.5px; font-weight:800; letter-spacing:.05em;
        text-transform:uppercase; background:#eef2ff; color:#4338ca; border:1px solid #dcdffb;
    }

    /* ------------------------------------------------------ disputefox push */
    .ghl-th-tick { width:42px; text-align:center; }
    .df-tick, #dfAll { width:16px; height:16px; accent-color:#6366f1; cursor:pointer; }
    .df-bar {
        display:flex; align-items:center; justify-content:flex-end; gap:14px;
        padding:16px 22px; border-top:1px solid var(--line); background:#fbfbfd; flex-wrap:wrap;
    }
    .df-count { font-size:12.5px; color:var(--soft); font-weight:600; }
    #dfPushBtn:disabled { opacity:.45; cursor:not-allowed; transform:none; box-shadow:none; }

    .ghl-flags { display:flex; flex-wrap:wrap; gap:4px; margin-top:5px; }
    .ghl-flag {
        padding:2px 7px; border-radius:5px; font-size:10.5px; font-weight:650;
        background:#fff7ed; color:#c2410c; border:1px solid #fde3c8;
    }

    .ghl-tag {
        display:inline-block; margin-left:7px; padding:2px 7px; border-radius:5px; vertical-align:1px;
        background:linear-gradient(140deg,#ede9fe,#fae8ff); color:#6d28d9; border:1px solid #e6ddfb;
        font-size:9.5px; font-weight:800; letter-spacing:.06em;
    }

    .ghl-chip {
        display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:999px;
        font-size:12px; font-weight:650; white-space:nowrap;
    }
    .ghl-chip svg { width:11px; height:11px; }
    .ghl-chip.ok   { background:#ecfdf5; color:#047857; border:1px solid #c7f0dd; }
    .ghl-chip.warn { background:#fffbeb; color:#b45309; border:1px solid #fde9b8; }
    .ghl-chip.info { background:#eef2ff; color:#4338ca; border:1px solid #dcdffb; }
    .ghl-chip.calm { background:#f4f5f9; color:#5a6072; border:1px solid #e8eaf1; }

    /* -------------------------------------------------------------- actions */
    .ghl-actions { display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap; }
    .ghl-actions form { margin:0; }
    .ghl-act {
        display:inline-block; padding:6px 12px; border-radius:8px; border:1px solid transparent;
        font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; white-space:nowrap;
        transition:transform .12s ease, filter .12s ease;
    }
    .ghl-act:hover { transform:translateY(-1px); filter:brightness(.97); }
    .ghl-act.view { background:#eef2ff; color:#4338ca; border-color:#dcdffb; }
    .ghl-act.go   { background:#ecfdf5; color:#047857; border-color:#c7f0dd; }
    .ghl-act.warn { background:#fffbeb; color:#b45309; border-color:#fde9b8; }
    .ghl-act.hold { background:#f4f5f9; color:#5a6072; border-color:#e8eaf1; }
    .ghl-act.danger { background:#fef2f2; color:#b91c1c; border-color:#fbd5d5; }

    /* ---------------------------------------------------------------- empty */
    .ghl-empty { padding:52px 24px; text-align:center; }
    .ghl-empty-icon {
        width:52px; height:52px; margin:0 auto 14px; border-radius:50%; display:grid; place-items:center;
        background:linear-gradient(140deg,#ecfdf5,#eef2ff); color:#059669; border:1px solid #dcf3e8;
    }
    .ghl-empty-icon svg { width:24px; height:24px; }
    .ghl-empty-title { margin:0 0 5px; font-size:14.5px; font-weight:650; color:var(--ink); }
    .ghl-empty-sub { margin:0 auto; max-width:46ch; font-size:13px; color:var(--soft); line-height:1.6; }

    @media (max-width:760px) {
        .ghl-hero { padding:22px 18px; }
        .ghl-sync-form { align-items:stretch; width:100%; }
        .ghl-sync-btn { width:100%; justify-content:center; }
        .ghl-sync-hint { text-align:center; }
        .ghl-actions { justify-content:flex-start; }
        .ghl-table th, .ghl-table td { padding-left:14px; padding-right:14px; }
    }
</style>
@endpush

@push('scripts')
<script>
// ---- DisputeFox: selection, and a confirm naming who is about to be sent ----
(function () {
    var form = document.querySelector('[data-df-push]');
    if (!form) return;

    var all    = document.getElementById('dfAll');
    var ticks  = Array.prototype.slice.call(form.querySelectorAll('.df-tick'));
    var count  = document.getElementById('dfCount');
    var button = document.getElementById('dfPushBtn');

    function selected() {
        return ticks.filter(function (t) { return t.checked; });
    }

    function refresh() {
        var n = selected().length;
        button.disabled = n === 0;
        count.textContent = n === 0 ? 'None selected'
            : n + (n === 1 ? ' client selected' : ' clients selected');
        if (all) {
            all.checked = n === ticks.length && n > 0;
            all.indeterminate = n > 0 && n < ticks.length;
        }
    }

    ticks.forEach(function (t) { t.addEventListener('change', refresh); });
    if (all) {
        all.addEventListener('change', function () {
            ticks.forEach(function (t) { t.checked = all.checked; });
            refresh();
        });
    }
    refresh();

    // Creating a client file in someone else's live system deserves a name check.
    form.addEventListener('submit', function (e) {
        var chosen = selected();
        if (!chosen.length) { e.preventDefault(); return; }

        var names = chosen.map(function (t) {
            var row = t.closest('tr');
            var link = row ? row.querySelector('.ghl-person-name') : null;
            return link ? link.textContent.trim() : 'this client';
        });

        var list = names.length > 6
            ? names.slice(0, 6).join('\n') + '\nand ' + (names.length - 6) + ' more'
            : names.join('\n');

        if (!window.confirm('Push these ' + names.length + ' client(s) into DisputeFox?\n\n' + list
            + '\n\nThis creates their file in DisputeFox. It cannot be undone from here.')) {
            e.preventDefault();
        }
    });
})();

window.moveToErrors = function (btn, name) {
    var note = prompt('What is the error for ' + name + '?', '');
    if (note === null) return;            // cancelled
    note = note.trim();
    if (note === '') { alert('Please enter the error.'); return; }
    var form = btn.closest('form');
    form.querySelector('input[name="note"]').value = note;
    form.submit();
};
</script>
@endpush
