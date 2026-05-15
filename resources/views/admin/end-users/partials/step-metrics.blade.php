@php
    $hasBureauData = $step->experian_accounts_disputed !== null
        || $step->experian_inquiries_disputed !== null
        || $step->transunion_accounts_disputed !== null
        || $step->transunion_inquiries_disputed !== null
        || $step->equifax_accounts_disputed !== null
        || $step->equifax_inquiries_disputed !== null;
    $hasScoreData = $step->previous_credit_score !== null || $step->credit_score_now !== null;
@endphp
@if ($hasBureauData)
    <div class="bureau-stats">
        @if ($step->experian_accounts_disputed !== null || $step->experian_inquiries_disputed !== null)
            <div class="bureau-stat">
                <strong>Experian</strong>
                {{ $step->experian_accounts_disputed ?? 0 }} accounts · {{ $step->experian_inquiries_disputed ?? 0 }} inquiries
            </div>
        @endif
        @if ($step->transunion_accounts_disputed !== null || $step->transunion_inquiries_disputed !== null)
            <div class="bureau-stat">
                <strong>TransUnion</strong>
                {{ $step->transunion_accounts_disputed ?? 0 }} accounts · {{ $step->transunion_inquiries_disputed ?? 0 }} inquiries
            </div>
        @endif
        @if ($step->equifax_accounts_disputed !== null || $step->equifax_inquiries_disputed !== null)
            <div class="bureau-stat">
                <strong>Equifax</strong>
                {{ $step->equifax_accounts_disputed ?? 0 }} accounts · {{ $step->equifax_inquiries_disputed ?? 0 }} inquiries
            </div>
        @endif
    </div>
@endif
@if ($hasScoreData)
    <div class="score-line">
        Score:
        {{ $step->previous_credit_score ?? '—' }}
        →
        {{ $step->credit_score_now ?? '—' }}
        @if ($step->previous_credit_score && $step->credit_score_now)
            ({{ ($step->credit_score_now - $step->previous_credit_score) >= 0 ? '+' : '' }}{{ $step->credit_score_now - $step->previous_credit_score }})
        @endif
    </div>
@endif
