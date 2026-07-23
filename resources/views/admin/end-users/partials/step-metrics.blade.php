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

@php
    // Round outcome metrics (recorded on pull-report / record-deletions steps).
    $outcome = array_filter([
        'Total Deletions'     => $step->total_deletions,
        'Updated to Positive' => $step->updated_to_positive,
        'Updated to Negative' => $step->updated_to_negative,
        'Added'               => $step->items_added,
    ], fn ($v) => $v !== null);

    $bureauScores = array_filter([
        'Experian'   => [$step->experian_score_before, $step->experian_score_now],
        'TransUnion' => [$step->transunion_score_before, $step->transunion_score_now],
        'Equifax'    => [$step->equifax_score_before, $step->equifax_score_now],
    ], fn ($p) => $p[0] !== null || $p[1] !== null);
@endphp

@if ($outcome)
    <div class="bureau-stats">
        @foreach ($outcome as $label => $value)
            <div class="bureau-stat"><strong>{{ $label }}</strong> {{ $value }}</div>
        @endforeach
    </div>
@endif

@if ($bureauScores)
    <div class="bureau-stats">
        @foreach ($bureauScores as $label => [$before, $now])
            <div class="bureau-stat">
                <strong>{{ $label }}</strong>
                {{ $before ?? '—' }} → {{ $now ?? '—' }}
                @if ($before !== null && $now !== null)
                    ({{ ($now - $before) >= 0 ? '+' : '' }}{{ $now - $before }})
                @endif
            </div>
        @endforeach
    </div>
@endif
