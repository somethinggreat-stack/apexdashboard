@extends('layouts.chat')

@section('title', 'Chat Overview')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/team-chat.css') }}?v={{ @filemtime(public_path('css/team-chat.css')) }}">
@endpush

@php
    $mb = function ($bytes) {
        if ($bytes <= 0) return '—';
        $mb = $bytes / 1048576;
        return $mb >= 1 ? round($mb, 1) . ' MB' : max(1, round($bytes / 1024)) . ' KB';
    };
@endphp

@section('content')
<div class="tc-ov">
    <div class="tc-ov-head">
        <div>
            <h1>Chat overview</h1>
            <p>Everything happening in your team's chat. Only you can see this page.</p>
        </div>
        <div class="tc-ov-headacts">
            <a class="tc-ov-btn ghost" href="{{ route('admin.team-messages.overview.files') }}">All files</a>
            <a class="tc-ov-back" href="{{ route('admin.team-messages.index', ['standalone' => 1]) }}">← Back to chat</a>
        </div>
    </div>

    @if (session('status'))
        <div class="tc-ov-done">{{ session('status') }}</div>
    @endif

    <div class="tc-ov-note">
        You are seeing <b>who talked to whom, and how much</b> — not what anyone said.
        No message text, no file names, and personal notes-to-self are not listed.
        The chat keeps only the last <b>{{ $retentionDays }} days</b>, so everything below is from that window.
    </div>

    <div class="tc-ov-tiles">
        <div class="tc-ov-tile"><span>{{ $totals['groups'] }}</span>Groups</div>
        <div class="tc-ov-tile"><span>{{ $totals['dms'] }}</span>Direct chats used</div>
        <div class="tc-ov-tile"><span>{{ $totals['messages'] }}</span>Messages</div>
        <div class="tc-ov-tile"><span>{{ $totals['files'] }}</span>Files · {{ $mb($totals['bytes']) }}</div>
    </div>

    <h2>Tell everyone</h2>
    <form method="POST" action="{{ route('admin.team-messages.announce') }}" class="tc-ov-announce"
          data-confirm="Send this to every teammate? It arrives in each person&rsquo;s own chat with you.">
        @csrf
        <textarea name="body" rows="3" maxlength="5000" required
                  placeholder="One message to the whole team — it lands in each person's chat with you, so they get it the same way they get anything else."></textarea>
        <button type="submit" class="tc-ov-btn">Send to everyone</button>
    </form>

    @if (count($announcements))
        <table class="tc-ov-table tc-ov-anns">
            <thead><tr><th>Announcement</th><th>Sent</th><th>Read by</th><th>Still waiting on</th></tr></thead>
            <tbody>
            @foreach ($announcements as $a)
                <tr>
                    <td class="tc-ov-anntext">{{ $a['body'] }}</td>
                    <td>{{ $a['when'] }}</td>
                    <td><b>{{ count($a['read']) }}</b> of {{ count($a['read']) + count($a['unread']) }}</td>
                    <td class="tc-ov-members">{{ count($a['unread']) ? implode(', ', $a['unread']) : '— everyone has read it' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>Groups</h2>
    @if (! count($groups))
        <p class="tc-ov-empty">No groups yet.</p>
    @else
        <table class="tc-ov-table">
            <thead><tr>
                <th>Group</th><th>Members</th><th class="n">Messages</th><th class="n">Files</th><th>Last activity</th><th></th>
            </tr></thead>
            <tbody>
            @foreach ($groups as $g)
                <tr>
                    <td><span class="tc-ov-icon">{{ $g['icon'] }}</span> <b>{{ $g['name'] }}</b>
                        @if ($g['creator'])<div class="tc-ov-sub">created by {{ $g['creator'] }}</div>@endif
                    </td>
                    <td class="tc-ov-members">{{ count($g['members']) }} — {{ implode(', ', $g['members']) }}</td>
                    <td class="n">{{ $g['messages'] }}</td>
                    <td class="n">{{ $g['files'] ?: '—' }}</td>
                    <td>{{ $g['last'] ?? 'never' }}</td>
                    {{-- The whole point of this page: groups that exist without you. Joining is
                         a deliberate, VISIBLE act — the group is told — unlike simply looking. --}}
                    <td class="tc-ov-act">
                        @unless ($g['mine'])
                            <span class="tc-ov-flag">You're not in this group</span>
                            <form method="POST" action="{{ route('admin.team-messages.group.join', $g['id']) }}"
                                  data-confirm="Join &ldquo;{{ $g['name'] }}&rdquo;? Everyone in the group will see that you joined.">
                                @csrf
                                <button type="submit" class="tc-ov-btn">Join</button>
                            </form>
                        @else
                            <a class="tc-ov-btn ghost" href="{{ route('admin.team-messages.index', ['c' => $g['id'], 'standalone' => 1]) }}">Open</a>
                            <a class="tc-ov-btn ghost" href="{{ route('admin.team-messages.overview.export', $g['id']) }}">Save a copy</a>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>Direct chats</h2>
    @if (! count($dms))
        <p class="tc-ov-empty">No direct chats yet.</p>
    @else
        <table class="tc-ov-table">
            <thead><tr>
                <th>Between</th><th class="n">Messages</th><th class="n">Files</th><th>Last activity</th><th></th>
            </tr></thead>
            <tbody>
            @foreach ($dms as $d)
                <tr class="{{ $d['messages'] ? '' : 'tc-ov-quiet' }}">
                    <td>{{ implode('  ↔  ', $d['people']) }}</td>
                    <td class="n">{{ $d['messages'] }}</td>
                    <td class="n">{{ $d['files'] ?: '—' }}</td>
                    <td>{{ $d['last'] ?? 'never' }}</td>
                    <td class="tc-ov-act">
                        @unless ($d['mine'])
                            <span class="tc-ov-flag soft">Not with you</span>
                        @else
                            <a class="tc-ov-btn ghost" href="{{ route('admin.team-messages.overview.export', $d['id']) }}">Save a copy</a>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>People</h2>
    <table class="tc-ov-table">
        <thead><tr>
            <th>Name</th><th></th><th class="n">Messages sent</th><th class="n">Groups</th><th>Last message</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        @foreach ($people as $p)
            <tr>
                <td><b>{{ $p['name'] }}</b></td>
                <td><span class="tc-ov-role">{{ $p['role'] }}</span></td>
                <td class="n">{{ $p['messages'] }}</td>
                <td class="n">{{ $p['groups'] ?: '—' }}</td>
                <td>{{ $p['lastMsg'] ?? 'never' }}</td>
                <td>@if ($p['online'])<span class="tc-ov-on">Online</span>@else{{ $p['lastSeen'] }}@endif</td>
                {{-- Offboarding: take someone out of every group at once. Direct chats are left
                     alone — a DM is a pair, and clearing one would take the other person's
                     history with it. --}}
                <td class="tc-ov-act">
                    @if ($p['id'] !== $me->id && $p['groups'] > 0)
                        <form method="POST" action="{{ route('admin.team-messages.people.remove-everywhere', $p['id']) }}"
                              data-confirm="Remove {{ $p['name'] }} from all {{ $p['groups'] }} group(s)? Each group will see that you removed them. This cannot be undone.">
                            @csrf
                            <button type="submit" class="tc-ov-btn danger">Remove from all groups</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<script>
    // Confirmations for the owner's actions. The text lives in data-confirm rather than an
    // inline onsubmit: a group or person named "Sam's team" would break a quoted JS string,
    // and these actions are the ones you least want failing silently.
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('form[data-confirm]');
        if (f && !window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
</script>
@endsection
