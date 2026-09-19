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
        <a class="tc-ov-back" href="{{ route('admin.team-messages.index', ['standalone' => 1]) }}">← Back to chat</a>
    </div>

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
                    {{-- The whole point of this page: groups that exist without you. --}}
                    <td>@unless ($g['mine'])<span class="tc-ov-flag">You're not in this group</span>@endunless</td>
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
                    <td>@unless ($d['mine'])<span class="tc-ov-flag soft">Not with you</span>@endunless</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>People</h2>
    <table class="tc-ov-table">
        <thead><tr>
            <th>Name</th><th></th><th class="n">Messages sent</th><th class="n">Groups</th><th>Last message</th><th>Status</th>
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
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
