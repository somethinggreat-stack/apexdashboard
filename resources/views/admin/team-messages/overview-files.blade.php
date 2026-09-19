@extends('layouts.chat')

@section('title', 'Files shared in chat')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/team-chat.css') }}?v={{ @filemtime(public_path('css/team-chat.css')) }}">
@endpush

@php
    $mb = function ($bytes) {
        if ($bytes <= 0) return '—';
        $m = $bytes / 1048576;
        return $m >= 1 ? round($m, 1) . ' MB' : max(1, round($bytes / 1024)) . ' KB';
    };
@endphp

@section('content')
<div class="tc-ov">
    <div class="tc-ov-head">
        <div>
            <h1>Files shared in chat</h1>
            <p>Every file that has gone out through the team's chat. Only you can see this page.</p>
        </div>
        <div class="tc-ov-headacts">
            <a class="tc-ov-btn ghost" href="{{ route('admin.team-messages.overview') }}">← Overview</a>
        </div>
    </div>

    <div class="tc-ov-note">
        This answers <b>"which client files left this company, and to whom"</b>. It lists file names,
        not messages. Files in a chat you are not part of are listed but cannot be opened from here —
        join that group first if you need to open one.
        The chat keeps only the last <b>{{ $retentionDays }} days</b>, and files are deleted with it.
    </div>

    @if (! count($files))
        <p class="tc-ov-empty">No files have been shared yet.</p>
    @else
        <table class="tc-ov-table">
            <thead><tr>
                <th>File</th><th class="n">Size</th><th>Sent by</th><th>In</th><th>When</th>
            </tr></thead>
            <tbody>
            @foreach ($files as $f)
                <tr>
                    <td><b>{{ $f['name'] }}</b></td>
                    <td class="n">{{ $mb($f['size']) }}</td>
                    <td>{{ $f['by'] }}</td>
                    <td>{{ $f['where'] }}@unless ($f['mine']) <span class="tc-ov-flag soft">not your chat</span>@endunless</td>
                    <td>{{ $f['when'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="tc-ov-empty">Showing the most recent {{ count($files) }} file(s).</p>
    @endif
</div>
@endsection
