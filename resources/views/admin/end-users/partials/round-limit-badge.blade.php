{{-- Round-limit badge (2-round list) — shown next to the client's name on every list. --}}
@if ($eu->round_limit_flag === 'over_limit')
    <span class="pro-limit" title="{{ $eu->round_limit }}-round limit — flagged for review">Over limit (Round {{ $eu->current_round }})</span>
@elseif ($eu->round_limit_flag === 'at_limit')
    <span class="pro-limit" title="{{ $eu->round_limit }}-round limit">No more rounds</span>
@endif
