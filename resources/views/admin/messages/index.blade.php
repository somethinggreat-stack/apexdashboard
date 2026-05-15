@extends('layouts.admin')

@section('title', 'Messages')

@push('head')
<style>
.chat-shell { display:flex; flex-direction:column; height:calc(100vh - 220px); min-height:480px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.chat-header { padding:12px 18px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
.chat-header strong { display:block; font-size:14px; }
.chat-header span { color:#64748b; font-size:12px; }
.chat-scroll { flex:1; overflow-y:auto; padding:18px; background:#f1f5f9; }
.chat-msg { margin-bottom:14px; max-width:75%; }
.chat-msg.from-admin { margin-left:auto; }
.chat-msg.from-client { margin-right:auto; }
.chat-msg.from-system { max-width:100%; text-align:center; margin:14px 0; }
.chat-bubble { padding:10px 14px; border-radius:12px; font-size:14px; line-height:1.45; word-break:break-word; }
.from-admin .chat-bubble { background:#1a6fc4; color:#fff; border-bottom-right-radius:4px; }
.from-client .chat-bubble { background:#fff; color:#0f2043; border:1px solid #e2e8f0; border-bottom-left-radius:4px; }
.from-system .chat-bubble { background:#fef3c7; color:#92400e; border:1px solid #fde68a; display:inline-block; font-style:italic; font-size:13px; }
.chat-meta { font-size:11px; color:#94a3b8; margin-top:4px; }
.from-admin .chat-meta { text-align:right; }
.chat-form { display:flex; gap:8px; padding:12px; background:#fff; border-top:1px solid #e2e8f0; }
.chat-form textarea { flex:1; resize:none; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font:inherit; }
.chat-empty { color:#94a3b8; text-align:center; margin-top:60px; font-size:14px; }
</style>
@endpush

@section('content')
<div class="chat-shell">
    <div class="chat-header">
        <strong>Conversation with {{ $client->business_name }}</strong>
        <span>{{ $client->email }}</span>
    </div>
    <div class="chat-scroll" data-chat-scroll>
        @forelse ($messages as $msg)
            @php
                $cls = $msg->isFromAdmin() ? 'from-admin' : ($msg->isFromClient() ? 'from-client' : 'from-system');
                $who = $msg->isFromAdmin() ? 'You' : ($msg->isFromClient() ? $client->business_name : 'System');
            @endphp
            <div class="chat-msg {{ $cls }}">
                <div class="chat-bubble">{{ $msg->body }}</div>
                <div class="chat-meta">{{ $who }} &middot; {{ $msg->created_at->diffForHumans() }}</div>
            </div>
        @empty
            <div class="chat-empty">No messages yet. Send the first one.</div>
        @endforelse
    </div>
    <form method="POST" action="{{ route('admin.messages.store') }}" class="chat-form">
        @csrf
        <textarea name="body" rows="2" maxlength="5000" placeholder="Type a message&hellip;" required></textarea>
        <button type="submit" class="btn btn-primary">Send</button>
    </form>
</div>
@endsection
