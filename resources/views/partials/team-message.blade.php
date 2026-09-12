@php
    $mine = $msg->sender_id === $me->id;
    $del  = (bool) $msg->deleted_at;

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
            'text'   => $msg->replyTo->deleted_at ? 'Deleted message' : \Illuminate\Support\Str::limit($msg->replyTo->body, 90),
        ];
    }
@endphp
<div class="tc-msg {{ $mine ? 'mine' : '' }}" data-id="{{ $msg->id }}">
    <div class="tc-bubble {{ $del ? 'deleted' : '' }}">
        @if ($rep && ! $del)
            <div class="tc-quote"><span class="tc-quote-author">{{ $rep['author'] }}</span><span class="tc-quote-text">{{ $rep['text'] }}</span></div>
        @endif
        @if ($msg->forwarded && ! $del)
            <div class="tc-fwd">↪ Forwarded</div>
        @endif
        <div class="tc-text">{{ $del ? '🚫 This message was deleted' : $msg->body }}</div>
    </div>
    <div class="tc-time">{{ $msg->created_at->timezone($tz)->format('M j · g:i A') }}@if ($mine && ! $del)<span class="tc-btick {{ $msg->read_at ? 'read' : '' }}">@include('partials.tick')</span>@endif</div>
    <div class="tc-reacts">
        @foreach ($rx as $r)
            <span class="tc-react {{ ($r['mine'] ?? false) ? 'mine' : '' }}" data-emoji="{{ $r['emoji'] }}">{{ $r['emoji'] }}{{ $r['count'] > 1 ? ' '.$r['count'] : '' }}</span>
        @endforeach
    </div>
    @unless ($del)
        <button type="button" class="tc-dots" aria-label="Message actions"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg></button>
    @endunless
</div>
