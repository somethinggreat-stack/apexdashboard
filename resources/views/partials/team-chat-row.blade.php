@php
    $u = $it['muted'] ? 0 : $it['unread'];
    $p = $it['preview'];
    $isActive = $active
        ? ($it['conversation_id'] && $active->id === $it['conversation_id'])
        : ($peer && ! $it['is_group'] && $it['peer_id'] === ($peer->id ?? null) && ! $it['conversation_id']);
@endphp
<a href="{{ route('admin.team-messages.index', array_merge($it['href'], request()->boolean('standalone') ? ['standalone' => 1] : [])) }}"
   class="tc-contact {{ $isActive ? 'active' : '' }}"
   data-key="{{ $it['active_key'] }}"
   data-name="{{ \Illuminate\Support\Str::lower($it['name']) }}"
   data-unread="{{ $it['unread'] > 0 ? 1 : 0 }}"
   data-fav="{{ $it['favorite'] ? 1 : 0 }}"
   data-muted="{{ $it['muted'] ? 1 : 0 }}"
   data-group="{{ $it['is_group'] ? 1 : 0 }}"
   @if ($it['conversation_id']) data-conversation="{{ $it['conversation_id'] }}" @endif
   @if ($it['peer_id']) data-peer="{{ $it['peer_id'] }}" @endif>
    @if ($it['is_group'])
        <span class="tc-avatar tc-avatar--group">{{ $it['icon'] }}</span>
    @else
        <span class="tc-av">{!! $avatar($it['peer']) !!}<i class="tc-dot {{ $it['online'] ? 'on' : '' }}" data-dot="{{ $it['peer_id'] }}"></i></span>
    @endif
    <span class="tc-c-body">
        <span class="tc-c-top">
            <span class="tc-c-name">{{ $it['name'] }}</span>
            <span class="tc-c-time {{ $u > 0 ? 'unread' : '' }}" data-time>{{ $p['at'] ?? '' }}</span>
        </span>
        <span class="tc-c-sub">
            <span class="tc-c-preview {{ $u > 0 ? 'unread' : '' }}" data-preview>
                @if ($p)
                    @if ($p['mine'])<span class="tc-tick {{ $p['read'] ? 'read' : '' }}" data-tick>@include('partials.tick')</span>@endif
                    <span data-preview-text>{{ \Illuminate\Support\Str::limit($p['text'], 40) }}</span>
                @elseif ($it['is_group'])
                    <span class="tc-c-muted">{{ $it['members_count'] }} members</span>
                @else
                    <span class="tc-c-muted">{{ $it['peer']->isSuper() ? 'Super Admin' : 'VA' }} · Tap to message</span>
                @endif
            </span>
            @if ($it['muted'])<span class="tc-mute-ic" title="Muted">@include('partials.mute-icon')</span>@endif
            @if (($it['mentions'] ?? 0) > 0)<span class="tc-mention-badge" title="You were mentioned">@</span>@endif
            @if ($u > 0)<span class="tc-unread" data-badge>{{ $u }}</span>@endif
        </span>
    </span>
    @if ($it['conversation_id'])
        <span class="tc-row-actions">
            <button type="button" class="tc-row-act tc-fav-btn {{ $it['favorite'] ? 'on' : '' }}" data-fav-toggle title="{{ $it['favorite'] ? 'Unfavorite' : 'Favorite' }}">
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </button>
            <button type="button" class="tc-row-act tc-mute-btn {{ $it['muted'] ? 'on' : '' }}" data-mute-toggle title="{{ $it['muted'] ? 'Unmute' : 'Mute' }}">@include('partials.mute-icon')</button>
        </span>
    @endif
</a>
