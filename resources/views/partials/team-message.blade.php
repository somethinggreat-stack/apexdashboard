@php
    $isGroup = $isGroup ?? false;
    $showSender = $showSender ?? false;
@endphp

@if ($msg->isSystem())
    <div class="tc-sys"><span>{{ $msg->body }}</span></div>
@else
@php
    $mine = $msg->sender_id === $me->id;
    $del  = (bool) $msg->deleted_at;
    $hasAtts = ! $del && $msg->attachments->isNotEmpty();
    $onlyMedia = $hasAtts && $msg->body === '';

    $rx = [];
    foreach (($msg->reactions ?? []) as $uid => $e) {
        $rx[$e]['emoji'] = $e;
        $rx[$e]['count'] = ($rx[$e]['count'] ?? 0) + 1;
        if ((int) $uid === $me->id) $rx[$e]['mine'] = true;
    }
    $rx = array_values($rx);
    usort($rx, fn ($a, $b) => $b['count'] <=> $a['count']);

    $rep = null;
    if ($msg->replyTo) {
        $rep = [
            'author' => $msg->replyTo->sender_id === $me->id ? 'You' : ($msg->replyTo->sender->full_name ?? 'Teammate'),
            'text'   => $msg->replyTo->deleted_at ? 'Deleted message' : \Illuminate\Support\Str::limit($msg->replyTo->body !== '' ? $msg->replyTo->body : '📎 Attachment', 90),
        ];
    }

    // Sender chip (group, incoming only).
    $s = $msg->sender;
    $sName = $s->full_name ?? 'Someone';
    $sParts = preg_split('/\s+/', trim($sName ?: '?'));
    $sMono = mb_strtoupper(mb_substr($sParts[0], 0, 1) . (count($sParts) > 1 ? mb_substr(end($sParts), 0, 1) : ''));
    $sPalette = ['#4f46e5','#0ea5e9','#10b981','#f59e0b','#ec4899','#14b8a6','#f43f5e','#7c3aed','#0891b2'];
    $sN = 0; foreach (str_split($sName ?: '?') as $ch) $sN += ord($ch);
    $sColor = $sPalette[$sN % count($sPalette)];
    $sAvatar = $s ? $s->avatarUrl() : null;
@endphp
<div class="tc-msg {{ $mine ? 'mine' : '' }} {{ $isGroup && ! $mine ? 'tc-msg--grp' : '' }}" data-id="{{ $msg->id }}">
    @if ($isGroup && ! $mine && $showSender)
        <div class="tc-sender">
            @if ($sAvatar)
                <span class="tc-avatar xs has-img"><img src="{{ $sAvatar }}" alt=""></span>
            @else
                <span class="tc-avatar xs" style="background:{{ $sColor }}">{{ $sMono }}</span>
            @endif
            <span class="tc-sender-name" style="color:{{ $sColor }}">{{ $sName }}</span>
        </div>
    @endif
    <div class="tc-bubble {{ $del ? 'deleted' : '' }} {{ $onlyMedia ? 'tc-bubble--media' : '' }}">
        @if ($rep && ! $del)
            <div class="tc-quote"><span class="tc-quote-author">{{ $rep['author'] }}</span><span class="tc-quote-text">{{ $rep['text'] }}</span></div>
        @endif
        @if ($msg->forwarded && ! $del)
            <div class="tc-fwd">↪ Forwarded</div>
        @endif
        @if ($hasAtts)
            <div class="tc-atts">
                @foreach ($msg->attachments as $att)
                    @php $u = route('admin.team-messages.attachment', $att->id); @endphp
                    @if ($att->isImage())
                        <a class="tc-att-img" href="{{ $u }}" data-lightbox><img src="{{ $u }}" alt="{{ $att->original_name }}" loading="lazy"></a>
                    @else
                        <a class="tc-att-file" href="{{ $u }}?dl=1">
                            <span class="tc-att-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                            <span class="tc-att-meta"><span class="tc-att-name">{{ $att->original_name }}</span><span class="tc-att-size">{{ $att->humanSize() }}</span></span>
                            <svg class="tc-att-dl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
        @if ($del)
            <div class="tc-text">🚫 This message was deleted</div>
        @elseif ($msg->body !== '')
            <div class="tc-text">{{ $msg->body }}</div>
        @endif
    </div>
    <div class="tc-time">{{ $msg->created_at->timezone($tz)->format('M j · g:i A') }}@if ($mine && ! $del)<span class="tc-btick {{ ($readUpTo ?? 0) >= $msg->id ? 'read' : '' }}">@include('partials.tick')</span>@endif</div>
    <div class="tc-reacts">
        @foreach ($rx as $r)
            <span class="tc-react {{ ($r['mine'] ?? false) ? 'mine' : '' }}" data-emoji="{{ $r['emoji'] }}">{{ $r['emoji'] }}{{ $r['count'] > 1 ? ' '.$r['count'] : '' }}</span>
        @endforeach
    </div>
    @unless ($del)
        <button type="button" class="tc-dots" aria-label="Message actions"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button>
    @endunless
</div>
@endif
