@php
    $steps = $endUser->processSteps->sortBy(['step_date', 'round', 'week']);
    $groups = $steps->groupBy(fn ($s) => 'Round ' . $s->round)->map(
        fn ($byRound) => $byRound->groupBy(fn ($s) => 'Week ' . $s->week)
    );
    $rounds = $endUser->rounds ?? [];
    $latestScore = $endUser->current_score;
    $totalDisputes = $steps->sum(fn ($s) =>
        (int) $s->experian_accounts_disputed
        + (int) $s->transunion_accounts_disputed
        + (int) $s->equifax_accounts_disputed
    );
@endphp

<div class="status-report">
    <div class="status-report-header">
        <div class="status-report-title">
            <h2>{{ $endUser->full_name }}</h2>
            <p class="muted">Backend fulfillment status report &middot; Generated {{ now()->format('M d, Y') }}</p>
        </div>
        <div class="status-report-stats">
            <div>
                <span class="muted">Started</span>
                <strong>{{ $endUser->start_date?->format('M d, Y') ?? '—' }}</strong>
            </div>
            <div>
                <span class="muted">Days Active</span>
                <strong>{{ $endUser->days_active }}</strong>
            </div>
            <div>
                <span class="muted">Active Rounds</span>
                <strong>{{ !empty($rounds) ? implode(', ', $rounds) : '—' }}</strong>
            </div>
            <div>
                <span class="muted">Status</span>
                <strong style="text-transform:capitalize;">{{ $endUser->status }}</strong>
            </div>
            <div>
                <span class="muted">Latest Score</span>
                <strong>{{ $latestScore ?? '—' }}</strong>
            </div>
            <div>
                <span class="muted">Disputes Filed</span>
                <strong>{{ $totalDisputes }}</strong>
            </div>
        </div>
    </div>

    @if ($steps->isEmpty())
        <p class="status-report-empty">No process activity logged yet. Once Round 1, Week 1 actions are recorded, the timeline will appear here in paragraph form, ready to forward to the end client.</p>
    @else
        <div class="status-report-intro">
            <p>The following is a chronological narrative of every action our team has executed against this file. Each paragraph corresponds to a logged process step and is generated automatically from the documentation captured at the time the work was done.</p>
        </div>

        @foreach ($groups as $roundLabel => $byWeek)
            <section class="status-report-round">
                <h3 class="status-report-round-title">{{ $roundLabel }}</h3>
                @foreach ($byWeek as $weekLabel => $weekSteps)
                    <div class="status-report-week">
                        <h4>{{ $weekLabel }}</h4>
                        @foreach ($weekSteps as $step)
                            <p class="status-report-step">{{ $step->narrative }}</p>
                        @endforeach
                    </div>
                @endforeach
            </section>
        @endforeach
    @endif

    <div class="status-report-footer">
        <p class="muted">
            Results vary by client profile, documentation, creditor response, and bureau investigation. We do not guarantee deletion of accurate or verifiable information.
        </p>
    </div>
</div>

<style>
.status-report { font-size: 14px; line-height: 1.7; color: #1e293b; }
.status-report-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 20px; margin-bottom: 24px; border-bottom: 2px solid #e2e8f0; flex-wrap: wrap; }
.status-report-title h2 { margin: 0 0 4px; font-size: 22px; color: #0f172a; }
.status-report-stats { display: grid; grid-template-columns: repeat(3, minmax(120px, 1fr)); gap: 12px 24px; }
.status-report-stats > div { display: flex; flex-direction: column; }
.status-report-stats span.muted { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
.status-report-stats strong { font-size: 15px; color: #0f172a; }
.status-report-intro { background: #f8fafc; border-left: 3px solid var(--primary, #1a6fc4); padding: 12px 16px; border-radius: 4px; margin-bottom: 24px; }
.status-report-intro p { margin: 0; color: #475569; font-size: 13px; }
.status-report-round { margin-bottom: 28px; }
.status-report-round-title { font-size: 18px; color: #0f172a; margin: 0 0 12px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
.status-report-week { margin-bottom: 16px; padding-left: 16px; border-left: 2px solid #e2e8f0; }
.status-report-week h4 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin: 0 0 6px; }
.status-report-step { margin: 0 0 10px; }
.status-report-step:last-child { margin-bottom: 0; }
.status-report-empty { color: #64748b; font-style: italic; padding: 16px; background: #f8fafc; border-radius: 6px; }
.status-report-footer { margin-top: 28px; padding-top: 16px; border-top: 1px dashed #e2e8f0; }
.status-report-footer p { font-size: 11px; margin: 0; }
@media print {
    body * { visibility: hidden; }
    #tab-status-report, #tab-status-report * { visibility: visible; }
    #tab-status-report { position: absolute; left: 0; top: 0; width: 100%; padding: 0; }
    #tab-status-report .card-header button { display: none; }
}
@media (max-width: 720px) {
    .status-report-header { flex-direction: column; }
    .status-report-stats { grid-template-columns: 1fr 1fr; width: 100%; }
}
</style>
