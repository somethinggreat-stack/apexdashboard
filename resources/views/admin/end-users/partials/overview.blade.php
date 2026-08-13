@php
    use App\Models\ProcessStep;

    // Portal: 'admin' (VA) or 'client' (BO). Determines route names and
    // whether write actions are surfaced. Default 'admin' for backward compat.
    $portal   = $portal ?? 'admin';
    $canEdit  = $portal === 'admin';
    $rPrefix  = $portal . '.end-users';

    $allSteps          = $endUser->processSteps;
    $stepsByRound      = $allSteps->groupBy('round');
    $currentRoundNum   = max(1, (int) ($stepsByRound->keys()->max() ?? 1));
    $currentRoundSteps = $stepsByRound->get($currentRoundNum, collect());

    $stepTypesByWeek    = ProcessStep::stepTypesByWeek();
    $totalStepsPerRound = collect($stepTypesByWeek)->sum(fn ($w) => count($w));

    $currentRoundCompleted = $currentRoundSteps->count();
    $currentRoundPercent   = $totalStepsPerRound > 0
        ? min(100, (int) round(($currentRoundCompleted / $totalStepsPerRound) * 100))
        : 0;

    $allRoundsTotalSteps = $allSteps->count();

    $totalDeletions = $allSteps->sum(fn ($s) =>
        (int) $s->experian_accounts_disputed
        + (int) $s->transunion_accounts_disputed
        + (int) $s->equifax_accounts_disputed
    );
    $currentRoundDeletions = $currentRoundSteps->sum(fn ($s) =>
        (int) $s->experian_accounts_disputed
        + (int) $s->transunion_accounts_disputed
        + (int) $s->equifax_accounts_disputed
    );

    $roundLabels = [1=>'1st Round', 2=>'2nd Round', 3=>'3rd Round', 4=>'4th Round', 5=>'5th Round'];
    $currentRoundLabel = $roundLabels[$currentRoundNum] ?? "Round {$currentRoundNum}";

    $loggedTypes = $currentRoundSteps->pluck('step_type')->toArray();
    $nextAction = null;
    foreach ($stepTypesByWeek as $week => $stepTypes) {
        foreach ($stepTypes as $key => $label) {
            if (!in_array($key, $loggedTypes, true)) {
                $nextAction = preg_replace('/^Step \d+:\s*/', '', $label);
                break 2;
            }
        }
    }
    if (!$nextAction) $nextAction = 'Round complete';

    $estCompletion = $endUser->start_date
        ? $endUser->start_date->copy()->addDays(28 * $currentRoundNum)->format('M d, Y')
        : '—';

    // Compute the week-by-week schedule locally so we don't depend on
    // withCount (the controller's show() doesn't add it). This makes
    // is_incomplete reliable for the overview regardless of how endUser
    // was loaded.
    $weekCounts = [];
    for ($w = 1; $w <= 4; $w++) {
        $weekCounts[$w] = $allSteps->where('week', $w)->count();
    }
    $daysActive = $endUser->days_active;
    $missingWeek = null;
    if ($daysActive >= 1  && $weekCounts[1] === 0) $missingWeek = 1;
    elseif ($daysActive >= 8  && $weekCounts[2] === 0) $missingWeek = 2;
    elseif ($daysActive >= 15 && $weekCounts[3] === 0) $missingWeek = 3;
    elseif ($daysActive >= 22 && $weekCounts[4] === 0) $missingWeek = 4;
    $isOnTrack    = $missingWeek === null;
    $statusCaption = $isOnTrack ? 'On Track' : "Week {$missingWeek} due";
    $statusMessage = $isOnTrack
        ? 'Everything is on track. Keep going!'
        : "Time to log Week {$missingWeek} — keep the momentum going.";

    // Week 1 progress display — match the mockup's per-step list
    $week1Types = $stepTypesByWeek[1] ?? [];
    $week1Display = [];
    $hitInProgress = false;
    $stepIdx = 0;
    foreach ($week1Types as $key => $label) {
        $stepIdx++;
        $done = in_array($key, $loggedTypes, true);
        $cleanLabel = preg_replace('/^Step \d+:\s*/', '', $label);
        if ($done) {
            $state = 'completed';
        } elseif (!$hitInProgress) {
            $state = 'in_progress';
            $hitInProgress = true;
        } else {
            $state = 'upcoming';
        }
        $week1Display[] = [
            'num'   => $stepIdx,
            'label' => $cleanLabel,
            'state' => $state,
            'date'  => $endUser->start_date?->copy()->addDays($stepIdx - 1)?->format('M d, Y') ?? '—',
        ];
    }

    // Counts for round summary card
    $completedCount  = $currentRoundCompleted;
    $inProgressCount = $nextAction !== 'Round complete' ? 1 : 0;
    $upcomingCount   = max(0, $totalStepsPerRound - $completedCount - $inProgressCount);

    // Activity timeline — combine recent steps, documents, notes
    $activity = collect();
    foreach ($allSteps->sortByDesc('created_at')->take(8) as $step) {
        $activity->push([
            'kind'  => 'step',
            'date'  => $step->created_at,
            'title' => 'Step logged',
            'sub'   => $step->step_type_label,
        ]);
    }
    foreach ($endUser->documents->take(8) as $doc) {
        $activity->push([
            'kind'  => 'doc',
            'date'  => $doc->created_at,
            'title' => 'Document uploaded',
            'sub'   => $doc->file_name ?? $doc->original_name ?? 'Document',
        ]);
    }
    foreach ($endUser->notes->take(8) as $note) {
        $activity->push([
            'kind'  => 'note',
            'date'  => $note->created_at,
            'title' => 'Comment added',
            'sub'   => \Illuminate\Support\Str::limit($note->note_text, 50),
        ]);
    }
    $activity = $activity->sortByDesc('date')->take(6);

    $recentNotes = $endUser->notes->take(2);

    // Top 3 docs to show, with file-extension icon
    $topDocs = collect();
    if ($endUser->collage_path) {
        $topDocs->push([
            'name'  => 'Collage',
            'ext'   => strtoupper(pathinfo($endUser->collage_path, PATHINFO_EXTENSION) ?: 'FILE'),
            'date'  => $endUser->start_date?->format('M d, Y') ?? '—',
            'desc'  => 'Identity collage',
            'url'   => $endUser->collage_url,
        ]);
    }
    if ($endUser->photo_id_path) {
        $topDocs->push([
            'name'  => 'Government Photo ID',
            'ext'   => strtoupper(pathinfo($endUser->photo_id_path, PATHINFO_EXTENSION) ?: 'FILE'),
            'date'  => $endUser->start_date?->format('M d, Y') ?? '—',
            'desc'  => 'Uploaded with profile',
            'url'   => $endUser->photo_id_url,
        ]);
    }
    if ($endUser->proof_of_address_path) {
        $topDocs->push([
            'name'  => 'Proof of Address',
            'ext'   => strtoupper(pathinfo($endUser->proof_of_address_path, PATHINFO_EXTENSION) ?: 'FILE'),
            'date'  => $endUser->start_date?->format('M d, Y') ?? '—',
            'desc'  => 'Uploaded with profile',
            'url'   => $endUser->proof_of_address_url,
        ]);
    }
    foreach ($endUser->documents->take(2) as $doc) {
        $topDocs->push([
            'name'  => $doc->file_name ?? 'Document',
            'ext'   => strtoupper($doc->file_type ?? 'FILE'),
            'date'  => $doc->created_at?->format('M d, Y') ?? '—',
            'desc'  => $doc->description ?? '—',
            'url'   => $doc->url,
        ]);
    }
    $topDocs = $topDocs->take(3);
@endphp

<div id="tab-overview" class="tab-panel active">

    {{-- ============ TOP STAT CARDS ============ --}}
    <div class="ov-stats">
        {{-- Days Active --}}
        <div class="ov-stat-card">
            <div class="ov-stat-head">
                <div class="ov-icon ov-icon-purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 17 9 11 13 15 21 7"/><polyline points="14 7 21 7 21 14"/></svg>
                </div>
                <div class="ov-stat-label">Days Active</div>
            </div>
            <div class="ov-stat-value">{{ $endUser->days_active }} <span class="ov-stat-unit">Days</span></div>
            <div class="ov-stat-sub">Since {{ $endUser->start_date?->format('M d, Y') ?? '—' }}</div>
        </div>

        {{-- Total Rounds --}}
        <div class="ov-stat-card">
            <div class="ov-stat-head">
                <div class="ov-icon ov-icon-teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                </div>
                <div class="ov-stat-label">Current Round</div>
                <div class="ov-donut" style="--p:{{ $currentRoundPercent }};">
                    <span>{{ $currentRoundPercent }}%</span>
                </div>
            </div>
            <div class="ov-stat-value">{{ $currentRoundLabel }}</div>
            <div class="ov-stat-sub">Total Steps: {{ $allRoundsTotalSteps }}</div>
        </div>

        {{-- Disputes Filed --}}
        <div class="ov-stat-card">
            <div class="ov-stat-head">
                <div class="ov-icon ov-icon-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                </div>
                <div class="ov-stat-label">Disputes Filed</div>
            </div>
            <div class="ov-stat-value">{{ $currentRoundCompleted }}</div>
            <div class="ov-stat-sub">This Round</div>
        </div>

        {{-- Status --}}
        <div class="ov-stat-card">
            <div class="ov-stat-head">
                <div class="ov-icon {{ $isOnTrack ? 'ov-icon-green' : 'ov-icon-red' }}">
                    @if ($isOnTrack)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12" y2="16"/></svg>
                    @endif
                </div>
                <div class="ov-stat-label">Status</div>
            </div>
            <div class="ov-stat-value" style="text-transform:capitalize;">{{ $endUser->status }}</div>
            <div class="ov-stat-sub">{{ $statusCaption }}</div>
        </div>
    </div>

    {{-- ============ ROUNDS & SCHEDULE ============ --}}
    @php
        $ovTimeline = $endUser->round_timeline;
        $ovDaysLeft = $endUser->days_left_in_round;
    @endphp
    <div class="ov-block ov-rounds-block">
        <div class="ov-block-head">
            <div class="ov-block-title">
                <div class="ov-block-icon ov-icon-teal-soft">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                </div>
                <div>
                    <div class="ov-block-h">Rounds &amp; Schedule</div>
                    <div class="ov-block-sub">Every round reached, when each started, and what's next</div>
                </div>
            </div>
            @if ($canEdit)
                <button class="ov-btn-primary" onclick="openModal('roundScheduleModal')">Edit Rounds &amp; Dates</button>
            @endif
        </div>

        <div class="ov-rounds-strip">
            @forelse ($ovTimeline as $ovLabel => $ovDate)
                <div class="ov-round-item">
                    <div class="ov-round-badge">{{ \Illuminate\Support\Str::before($ovLabel, ' Round') }}</div>
                    <div class="ov-round-meta">
                        <div class="ov-round-name">{{ $ovLabel }}</div>
                        <div class="ov-round-date">{{ $ovDate ? 'Started ' . \Carbon\Carbon::parse($ovDate)->format('M j, Y') : 'Date not set' }}</div>
                    </div>
                </div>
            @empty
                <div class="ov-round-item">
                    <div class="ov-round-badge">1st</div>
                    <div class="ov-round-meta">
                        <div class="ov-round-name">1st Round</div>
                        <div class="ov-round-date">{{ $endUser->start_date ? 'Started ' . $endUser->start_date->format('M j, Y') : 'Not started' }}</div>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="ov-rounds-foot">
            <div class="ov-rounds-foot-item">
                <span class="ov-rounds-foot-label">Next Round Date</span>
                <span class="ov-rounds-foot-val">{{ $endUser->next_round_date ? \Carbon\Carbon::parse($endUser->next_round_date)->format('M j, Y') : '—' }}</span>
                @if ($endUser->next_round_override)<span class="ov-rounds-tag">manually set</span>@endif
            </div>
            <div class="ov-rounds-foot-item">
                <span class="ov-rounds-foot-label">Days Left in Round</span>
                <span class="ov-rounds-foot-val {{ $ovDaysLeft !== null && $ovDaysLeft < 0 ? 'is-over' : ($ovDaysLeft !== null && $ovDaysLeft <= 3 ? 'is-soon' : '') }}">{{ $ovDaysLeft === null ? '—' : $ovDaysLeft }}</span>
                @if ($endUser->round_end_date)<span class="ov-rounds-tag">ends {{ \Carbon\Carbon::parse($endUser->round_end_date)->format('M j, Y') }}</span>@endif
            </div>
        </div>
    </div>

    {{-- ============ MAIN 2-COLUMN AREA ============ --}}
    <div class="ov-main">
        <div class="ov-col-left">

            {{-- Process Timeline --}}
            <div class="ov-block">
                <div class="ov-block-head">
                    <div class="ov-block-title">
                        <div class="ov-block-icon ov-icon-purple-soft">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <div>
                            <div class="ov-block-h">Process Timeline</div>
                            <div class="ov-block-sub">Track progress across all steps and rounds</div>
                        </div>
                    </div>
                    @if ($canEdit)
                        <button class="ov-btn-primary" onclick="openModal('addStepModal')">+ Add Process Step</button>
                    @endif
                </div>

                <div class="ov-round-chip">ROUND {{ $currentRoundNum }}</div>

                <div class="ov-timeline">
                    @foreach ($week1Display as $item)
                        <div class="ov-tl-row">
                            <div class="ov-tl-bubble ov-tl-{{ $item['state'] }}">
                                @if ($item['state'] === 'completed')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                @else
                                    {{ $item['num'] }}
                                @endif
                            </div>
                            <div class="ov-tl-label">{{ $item['label'] }}</div>
                            <div class="ov-tl-pill ov-tl-pill-{{ $item['state'] }}">
                                @switch($item['state'])
                                    @case('completed') Completed @break
                                    @case('in_progress') In Progress @break
                                    @default Upcoming
                                @endswitch
                            </div>
                            <div class="ov-tl-date">{{ $item['date'] }}</div>
                        </div>
                    @endforeach
                </div>

                <button class="ov-link-btn" type="button" onclick="document.querySelector('[data-target=tab-timeline]').click()">View All Steps &rarr;</button>
            </div>

            {{-- Round Summary --}}
            <div class="ov-block ov-block-summary">
                <div class="ov-block-head">
                    <div class="ov-block-h">Round {{ $currentRoundNum }} Summary</div>
                </div>
                <div class="ov-summary-grid">
                    <div class="ov-summary-donut">
                        <div class="ov-donut-lg" style="--p:{{ $currentRoundPercent }};">
                            <div class="ov-donut-inner">
                                <div class="ov-donut-pct">{{ $currentRoundPercent }}%</div>
                                <div class="ov-donut-cap">Completed</div>
                            </div>
                        </div>
                        <div class="ov-summary-caption">{{ $completedCount }} of {{ $totalStepsPerRound }} Steps Completed</div>
                    </div>
                    <div class="ov-summary-list">
                        <div class="ov-summary-row"><span class="ov-dot ov-dot-green"></span> Completed <strong>{{ $completedCount }}</strong></div>
                        <div class="ov-summary-row"><span class="ov-dot ov-dot-blue"></span> In Progress <strong>{{ $inProgressCount }}</strong></div>
                        <div class="ov-summary-row"><span class="ov-dot ov-dot-gray"></span> Upcoming <strong>{{ $upcomingCount }}</strong></div>
                        <div class="ov-summary-row ov-summary-row-est"><span class="ov-dot ov-dot-amber"></span> Est. Completion <strong>{{ $estCompletion }}</strong></div>
                    </div>
                </div>
            </div>

            {{-- Documents --}}
            <div class="ov-block">
                <div class="ov-block-head">
                    <div class="ov-block-title">
                        <div class="ov-block-icon ov-icon-blue-soft">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                        </div>
                        <div class="ov-block-h">Documents</div>
                    </div>
                    <button class="ov-link-btn" type="button" onclick="document.querySelector('[data-target=tab-docs]').click()">View All Documents &rarr;</button>
                </div>
                <div class="ov-docs-grid">
                    @foreach ($topDocs as $doc)
                        <div class="ov-doc-card">
                            <div class="ov-doc-ext ov-ext-{{ strtolower($doc['ext']) }}">{{ $doc['ext'] }}</div>
                            <div class="ov-doc-name">{{ \Illuminate\Support\Str::limit($doc['name'], 24) }}</div>
                            <div class="ov-doc-meta">{{ $doc['desc'] }}</div>
                            <div class="ov-doc-meta">{{ $doc['date'] }}</div>
                            <div class="ov-doc-actions">
                                @if (!empty($doc['url']))
                                    <a href="{{ $doc['url'] }}" target="_blank" class="ov-doc-btn" title="Open">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    @if ($canEdit)
                        <div class="ov-doc-card ov-doc-upload" onclick="openUploadModal(null)">
                            <div class="ov-doc-upload-icon">
                                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            </div>
                            <div class="ov-doc-name">Upload Document</div>
                            <div class="ov-doc-meta">Drag &amp; drop or click to upload</div>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        <div class="ov-col-right">

            {{-- Status Summary --}}
            <div class="ov-block">
                <div class="ov-block-head">
                    <div class="ov-block-title">
                        <div class="ov-block-icon ov-icon-green-soft">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div class="ov-block-h">Status Summary</div>
                    </div>
                    <a class="ov-link-btn" href="{{ route($rPrefix . '.status-report', $endUser) }}" target="_blank">View Full Report &rarr;</a>
                </div>

                <div class="ov-shield-row">
                    <div class="ov-shield {{ $isOnTrack ? 'ov-shield-on' : 'ov-shield-off' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <div class="ov-shield-label" style="text-transform:capitalize;">{{ $endUser->status }}</div>
                        <div class="ov-shield-sub">{{ $statusCaption }}</div>
                    </div>
                </div>
                <div class="ov-shield-msg">{{ $statusMessage }}</div>

                <div class="ov-kv">
                    <div class="ov-kv-row"><span>Current Round</span><strong>{{ $currentRoundLabel }}</strong></div>
                    <div class="ov-kv-row"><span>Days Active</span><strong>{{ $endUser->days_active }}</strong></div>
                    <div class="ov-kv-row"><span>Started On</span><strong>{{ $endUser->start_date?->format('M d, Y') ?? '—' }}</strong></div>
                    <div class="ov-kv-row"><span>Steps This Round</span><strong>{{ $currentRoundCompleted }}</strong></div>
                    <div class="ov-kv-row"><span>Next Action</span><strong>{{ $nextAction }}</strong></div>
                </div>
            </div>

            {{-- Recent Notes --}}
            <div class="ov-block">
                <div class="ov-block-head">
                    <div class="ov-block-title">
                        <div class="ov-block-icon ov-icon-amber-soft">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="ov-block-h">Recent Comments</div>
                    </div>
                    @if ($canEdit)
                        <button class="ov-link-btn" type="button" onclick="openModal('addNoteModal')">+ Add Comment</button>
                    @endif
                </div>
                @forelse ($recentNotes as $note)
                    <div class="ov-note">
                        <div class="ov-note-icon"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                        <div class="ov-note-body">
                            <div class="ov-note-text">{{ \Illuminate\Support\Str::limit($note->note_text, 80) }}</div>
                            <div class="ov-note-meta">{{ $note->createdBy?->full_name ?? 'Admin' }} &middot; {{ $note->created_at?->format('M d, Y g:i A') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="ov-empty">No comments yet.</div>
                @endforelse
                @if ($endUser->notes->count() > $recentNotes->count())
                    <button class="ov-link-btn" type="button" onclick="document.querySelector('[data-target=tab-notes]').click()">View All Comments &rarr;</button>
                @endif
            </div>

            {{-- Activity Timeline --}}
            <div class="ov-block">
                <div class="ov-block-head">
                    <div class="ov-block-title">
                        <div class="ov-block-icon ov-icon-purple-soft">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div class="ov-block-h">Activity Timeline</div>
                    </div>
                    <button class="ov-link-btn" type="button" onclick="document.querySelector('[data-target=tab-timeline]').click()">View All Activity &rarr;</button>
                </div>
                @forelse ($activity as $a)
                    <div class="ov-activity-row">
                        <div class="ov-activity-icon ov-act-{{ $a['kind'] }}">
                            @switch($a['kind'])
                                @case('step')
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    @break
                                @case('doc')
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    @break
                                @default
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                            @endswitch
                        </div>
                        <div class="ov-activity-body">
                            <div class="ov-activity-title">{{ $a['title'] }}</div>
                            <div class="ov-activity-sub">{{ $a['sub'] }}</div>
                        </div>
                        <div class="ov-activity-date">{{ optional($a['date'])->format('M d, Y') }}<br><span>{{ optional($a['date'])->format('g:i A') }}</span></div>
                    </div>
                @empty
                    <div class="ov-empty">No activity yet.</div>
                @endforelse
            </div>

        </div>
    </div>

</div>

@if ($canEdit)
{{-- ============ EDIT ROUNDS & DATES MODAL (VA only) ============ --}}
<div id="roundScheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Rounds &amp; Dates</h3>
            <button type="button" class="modal-close" onclick="closeModal('roundScheduleModal')">&times;</button>
        </div>
        <p class="muted" style="margin:0 0 14px; font-size:13px;">
            Check each round the client has reached and set the date it started. The <strong>1st Round</strong> date is
            the client's start date. Leave <strong>Next Round Date</strong> blank to auto-calculate (one month after the
            current round's start).
        </p>
        <form method="POST" action="{{ route($rPrefix . '.update', $endUser) }}">
            @csrf @method('PUT')
            <input type="hidden" name="round_schedule_present" value="1">

            <div class="rs-grid">
                @foreach (\App\Models\EndUser::ROUND_OPTIONS as $rsLabel)
                    @php
                        $rsReached = in_array($rsLabel, $endUser->rounds ?? [], true);
                        $rsDate    = $endUser->roundStartedAt($rsLabel);
                    @endphp
                    <div class="rs-row">
                        <label class="rs-check">
                            <input type="checkbox" name="rounds[]" value="{{ $rsLabel }}" {{ $rsReached ? 'checked' : '' }} onchange="rsToggle(this)">
                            <span>{{ $rsLabel }}</span>
                        </label>
                        <input type="date" class="rs-date" name="round_start_dates[{{ $rsLabel }}]"
                               value="{{ $rsDate ? \Carbon\Carbon::parse($rsDate)->format('Y-m-d') : '' }}"
                               {{ $rsReached ? '' : 'disabled' }}>
                    </div>
                @endforeach
            </div>

            <div class="rs-next">
                <label>Next Round Date <span class="muted">(leave blank to auto-calculate)</span></label>
                <input type="date" name="next_round_override"
                       value="{{ $endUser->next_round_override ? \Carbon\Carbon::parse($endUser->next_round_override)->format('Y-m-d') : '' }}">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('roundScheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Rounds &amp; Dates</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('head')
<style>
    /* ===================== OVERVIEW TAB STYLES (scoped to #tab-overview) ===================== */
    #tab-overview { padding: 0; }

    /* Top stat cards row */
    #tab-overview .ov-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }
    #tab-overview .ov-stat-card {
        background: #fff;
        border: 1px solid #eef0f4;
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 1px 3px rgba(15,23,42,.03);
        transition: box-shadow .15s, transform .15s;
    }
    #tab-overview .ov-stat-card:hover {
        box-shadow: 0 4px 12px rgba(15,23,42,.07), 0 1px 3px rgba(15,23,42,.04);
        transform: translateY(-1px);
    }
    #tab-overview .ov-stat-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    #tab-overview .ov-stat-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #64748b; flex: 1; }
    #tab-overview .ov-stat-value { font-size: 26px; font-weight: 700; color: #0f172a; line-height: 1.1; margin-bottom: 4px; letter-spacing: -.5px; }
    #tab-overview .ov-stat-unit { font-size: 14px; color: #64748b; font-weight: 500; }
    #tab-overview .ov-stat-sub { font-size: 11.5px; color: #94a3b8; }

    /* Icon squares */
    #tab-overview .ov-icon {
        width: 38px; height: 38px;
        border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    #tab-overview .ov-icon svg { width: 18px; height: 18px; }
    #tab-overview .ov-icon-purple { background: linear-gradient(135deg,#ede9fe,#ddd6fe); color: #7c3aed; }
    #tab-overview .ov-icon-teal   { background: linear-gradient(135deg,#ccfbf1,#a7f3d0); color: #0d9488; }
    #tab-overview .ov-icon-orange { background: linear-gradient(135deg,#ffedd5,#fed7aa); color: #ea580c; }
    #tab-overview .ov-icon-blue   { background: linear-gradient(135deg,#dbeafe,#bfdbfe); color: #2563eb; }
    #tab-overview .ov-icon-green  { background: linear-gradient(135deg,#d1fae5,#a7f3d0); color: #059669; }
    #tab-overview .ov-icon-red    { background: linear-gradient(135deg,#fee2e2,#fecaca); color: #dc2626; }

    /* Donut on Current Round card */
    #tab-overview .ov-donut {
        --size: 40px; --p: 0;
        width: var(--size); height: var(--size); border-radius: 50%;
        background: conic-gradient(#0d9488 calc(var(--p)*1%), #e2e8f0 0);
        display: grid; place-items: center;
        margin-left: auto;
    }
    #tab-overview .ov-donut span {
        font-size: 10px; font-weight: 700; color: #0f172a;
        background: #fff; width: 28px; height: 28px;
        border-radius: 50%; display: grid; place-items: center;
    }

    /* ============ Main 2-col area ============ */
    #tab-overview .ov-main {
        display: grid;
        grid-template-columns: 1.7fr 1fr;
        gap: 18px;
    }
    #tab-overview .ov-block {
        background: #fff;
        border: 1px solid #eef0f4;
        border-radius: 14px;
        padding: 18px 20px;
        margin-bottom: 16px;
        box-shadow: 0 1px 2px rgba(15,23,42,.04);
    }
    #tab-overview .ov-block-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; margin-bottom: 14px;
    }
    #tab-overview .ov-block-title { display: flex; align-items: center; gap: 12px; }
    #tab-overview .ov-block-icon {
        width: 36px; height: 36px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    #tab-overview .ov-block-icon svg { width: 17px; height: 17px; }
    #tab-overview .ov-icon-purple-soft { background: #f5f3ff; color: #7c3aed; }
    #tab-overview .ov-icon-blue-soft   { background: #eff6ff; color: #2563eb; }
    #tab-overview .ov-icon-green-soft  { background: #ecfdf5; color: #059669; }
    #tab-overview .ov-icon-amber-soft  { background: #fffbeb; color: #d97706; }

    #tab-overview .ov-block-h { font-size: 15px; font-weight: 700; color: #0f172a; }
    #tab-overview .ov-block-sub { font-size: 12px; color: #64748b; margin-top: 2px; }

    #tab-overview .ov-btn-primary {
        background: #ffffff; color: #2563eb; border: 1px solid #dbeafe;
        font-size: 13px; font-weight: 600;
        padding: 7px 14px; border-radius: 8px; cursor: pointer;
        transition: background .15s;
    }
    #tab-overview .ov-btn-primary:hover { background: #eff6ff; }

    #tab-overview .ov-link-btn {
        background: none; border: 0; color: #2563eb;
        font-size: 12.5px; font-weight: 600; cursor: pointer; padding: 0;
    }
    #tab-overview .ov-link-btn:hover { text-decoration: underline; }

    /* Process Timeline */
    #tab-overview .ov-round-chip {
        display: inline-block;
        background: #ede9fe; color: #5b21b6;
        font-size: 10.5px; font-weight: 700; letter-spacing: .6px;
        padding: 4px 12px; border-radius: 999px;
        margin-bottom: 12px;
    }
    #tab-overview .ov-timeline { display: flex; flex-direction: column; gap: 4px; }
    #tab-overview .ov-tl-row {
        display: grid;
        grid-template-columns: 36px 1fr auto auto;
        align-items: center; gap: 12px;
        padding: 10px 4px;
        border-bottom: 1px solid #f1f5f9;
    }
    #tab-overview .ov-tl-row:last-child { border-bottom: 0; }
    #tab-overview .ov-tl-bubble {
        width: 28px; height: 28px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700;
    }
    #tab-overview .ov-tl-bubble svg { width: 13px; height: 13px; }
    #tab-overview .ov-tl-completed { background: #10b981; color: #fff; }
    #tab-overview .ov-tl-in_progress { background: #3b82f6; color: #fff; }
    #tab-overview .ov-tl-upcoming { background: #e2e8f0; color: #64748b; }

    #tab-overview .ov-tl-label { font-size: 13.5px; color: #1e293b; }
    #tab-overview .ov-tl-pill {
        font-size: 10.5px; font-weight: 700; padding: 3px 10px;
        border-radius: 999px; text-transform: none; letter-spacing: 0;
    }
    #tab-overview .ov-tl-pill-completed { background: #d1fae5; color: #065f46; }
    #tab-overview .ov-tl-pill-in_progress { background: #ede9fe; color: #5b21b6; }
    #tab-overview .ov-tl-pill-upcoming { background: #f1f5f9; color: #64748b; }
    #tab-overview .ov-tl-date { font-size: 11.5px; color: #94a3b8; min-width: 72px; text-align: right; }

    /* Round summary */
    #tab-overview .ov-block-summary { padding-bottom: 22px; }
    #tab-overview .ov-summary-grid {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 24px; align-items: center;
    }
    #tab-overview .ov-donut-lg {
        --size: 140px; --p: 0;
        width: var(--size); height: var(--size); border-radius: 50%;
        background: conic-gradient(#10b981 calc(var(--p)*1%), #e2e8f0 0);
        display: grid; place-items: center;
        position: relative;
    }
    #tab-overview .ov-donut-inner {
        width: 110px; height: 110px; background: #fff;
        border-radius: 50%;
        display: grid; place-items: center; text-align: center;
    }
    #tab-overview .ov-donut-pct { font-size: 28px; font-weight: 700; color: #0f172a; line-height: 1; }
    #tab-overview .ov-donut-cap { font-size: 11px; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: .8px; }
    #tab-overview .ov-summary-caption { text-align: center; font-size: 12px; color: #64748b; margin-top: 10px; }
    #tab-overview .ov-summary-list { display: flex; flex-direction: column; gap: 10px; }
    #tab-overview .ov-summary-row {
        display: flex; align-items: center; gap: 10px;
        font-size: 13px; color: #1e293b;
        padding: 8px 0; border-bottom: 1px solid #f1f5f9;
    }
    #tab-overview .ov-summary-row:last-child { border-bottom: 0; }
    #tab-overview .ov-summary-row strong { margin-left: auto; color: #0f172a; }
    #tab-overview .ov-summary-row-est strong { font-size: 12.5px; }
    #tab-overview .ov-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    #tab-overview .ov-dot-green { background: #10b981; }
    #tab-overview .ov-dot-blue  { background: #3b82f6; }
    #tab-overview .ov-dot-gray  { background: #cbd5e1; }
    #tab-overview .ov-dot-amber { background: #f59e0b; }

    /* Documents grid */
    #tab-overview .ov-docs-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }
    #tab-overview .ov-doc-card {
        background: #fff; border: 1px solid #eef0f4; border-radius: 12px;
        padding: 14px 12px;
        display: flex; flex-direction: column; gap: 4px;
        min-height: 130px;
        transition: border-color .15s, box-shadow .15s;
    }
    #tab-overview .ov-doc-card:hover { border-color: #c7d2fe; box-shadow: 0 2px 6px rgba(15,23,42,.06); }
    #tab-overview .ov-doc-ext {
        width: 32px; height: 32px; border-radius: 8px;
        background: #e0e7ff; color: #4338ca;
        font-size: 9.5px; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center;
        margin-bottom: 6px;
    }
    #tab-overview .ov-ext-jpg, #tab-overview .ov-ext-jpeg, #tab-overview .ov-ext-png { background: #d1fae5; color: #047857; }
    #tab-overview .ov-ext-pdf { background: #fee2e2; color: #b91c1c; }
    #tab-overview .ov-doc-name { font-size: 13px; font-weight: 600; color: #0f172a; }
    #tab-overview .ov-doc-meta { font-size: 11px; color: #94a3b8; }
    #tab-overview .ov-doc-actions { margin-top: auto; display: flex; gap: 6px; }
    #tab-overview .ov-doc-btn {
        width: 28px; height: 28px; border-radius: 6px;
        background: #f1f5f9; color: #475569;
        display: inline-flex; align-items: center; justify-content: center;
    }
    #tab-overview .ov-doc-upload {
        border: 2px dashed #cbd5e1; background: #f8fafc;
        align-items: center; justify-content: center; text-align: center;
        cursor: pointer;
    }
    #tab-overview .ov-doc-upload:hover { border-color: #93c5fd; background: #eff6ff; }
    #tab-overview .ov-doc-upload-icon {
        width: 48px; height: 48px; border-radius: 50%;
        background: #dbeafe; color: #2563eb;
        display: inline-flex; align-items: center; justify-content: center;
        margin-bottom: 8px;
    }

    /* Right column: Status Summary */
    #tab-overview .ov-shield-row { display: flex; gap: 14px; align-items: center; margin-bottom: 10px; }
    #tab-overview .ov-shield {
        width: 64px; height: 64px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
    }
    #tab-overview .ov-shield svg { width: 32px; height: 32px; }
    #tab-overview .ov-shield-on  { background: linear-gradient(135deg,#d1fae5,#a7f3d0); color: #047857; }
    #tab-overview .ov-shield-off { background: linear-gradient(135deg,#fee2e2,#fecaca); color: #b91c1c; }
    #tab-overview .ov-shield-label { font-size: 16px; font-weight: 700; color: #0f172a; }
    #tab-overview .ov-shield-sub { font-size: 12px; color: #64748b; }
    #tab-overview .ov-shield-msg { font-size: 11.5px; color: #64748b; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; margin-bottom: 4px; }
    #tab-overview .ov-kv { display: flex; flex-direction: column; }
    #tab-overview .ov-kv-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 9px 0; border-bottom: 1px solid #f1f5f9;
        font-size: 12.5px; color: #64748b;
    }
    #tab-overview .ov-kv-row:last-child { border-bottom: 0; }
    #tab-overview .ov-kv-row strong { color: #0f172a; font-weight: 600; }

    /* Notes */
    #tab-overview .ov-note { display: flex; gap: 10px; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
    #tab-overview .ov-note:last-of-type { border-bottom: 0; }
    #tab-overview .ov-note-icon {
        width: 28px; height: 28px; border-radius: 8px;
        background: #fffbeb; color: #d97706;
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    #tab-overview .ov-note-text { font-size: 13px; color: #0f172a; font-weight: 600; }
    #tab-overview .ov-note-meta { font-size: 11px; color: #94a3b8; margin-top: 2px; }

    /* Activity */
    #tab-overview .ov-activity-row {
        display: grid;
        grid-template-columns: 28px 1fr auto;
        gap: 10px; align-items: center;
        padding: 10px 0; border-bottom: 1px solid #f1f5f9;
    }
    #tab-overview .ov-activity-row:last-of-type { border-bottom: 0; }
    #tab-overview .ov-activity-icon {
        width: 28px; height: 28px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
    }
    #tab-overview .ov-act-step { background: #d1fae5; color: #047857; }
    #tab-overview .ov-act-doc  { background: #ede9fe; color: #7c3aed; }
    #tab-overview .ov-act-note { background: #fffbeb; color: #d97706; }
    #tab-overview .ov-activity-title { font-size: 13px; font-weight: 600; color: #0f172a; }
    #tab-overview .ov-activity-sub { font-size: 11.5px; color: #64748b; }
    #tab-overview .ov-activity-date {
        font-size: 11px; color: #94a3b8; text-align: right; line-height: 1.4;
    }
    #tab-overview .ov-activity-date span { color: #cbd5e1; }

    #tab-overview .ov-empty { font-size: 13px; color: #94a3b8; padding: 12px 0; }

    /* Rounds & Schedule block */
    #tab-overview .ov-icon-teal-soft { background: #ecfeff; color: #0d9488; }
    #tab-overview .ov-rounds-block { margin-bottom: 20px; }
    #tab-overview .ov-rounds-strip { display: flex; flex-wrap: wrap; gap: 12px; }
    #tab-overview .ov-round-item {
        display: flex; align-items: center; gap: 12px;
        flex: 1 1 180px; min-width: 180px;
        background: #f8fafc; border: 1px solid #eef0f4; border-radius: 12px;
        padding: 12px 14px;
    }
    #tab-overview .ov-round-badge {
        width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
        background: linear-gradient(135deg,#ccfbf1,#a7f3d0); color: #0d766e;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 14px; font-weight: 800; letter-spacing: -.5px;
    }
    #tab-overview .ov-round-name { font-size: 13.5px; font-weight: 700; color: #0f172a; }
    #tab-overview .ov-round-date { font-size: 12px; color: #64748b; margin-top: 2px; }
    #tab-overview .ov-rounds-foot {
        display: flex; flex-wrap: wrap; gap: 28px;
        margin-top: 14px; padding-top: 14px; border-top: 1px solid #f1f5f9;
    }
    #tab-overview .ov-rounds-foot-item { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
    #tab-overview .ov-rounds-foot-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; }
    #tab-overview .ov-rounds-foot-val { font-size: 18px; font-weight: 700; color: #0f172a; }
    #tab-overview .ov-rounds-foot-val.is-over { color: #dc2626; }
    #tab-overview .ov-rounds-foot-val.is-soon { color: #d97706; }
    #tab-overview .ov-rounds-tag {
        font-size: 10.5px; font-weight: 600; color: #64748b;
        background: #eef2f7; padding: 2px 8px; border-radius: 999px; align-self: center;
    }

    /* Edit Rounds & Dates modal (unscoped — modal renders outside #tab-overview) */
    #roundScheduleModal .rs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 18px; }
    #roundScheduleModal .rs-row { display: flex; align-items: center; gap: 10px; justify-content: space-between; }
    #roundScheduleModal .rs-check { display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; color: #0f172a; cursor: pointer; white-space: nowrap; }
    #roundScheduleModal .rs-check input { width: 16px; height: 16px; }
    #roundScheduleModal .rs-date { padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 7px; font-size: 12.5px; }
    #roundScheduleModal .rs-date:disabled { background: #f8fafc; color: #cbd5e1; }
    #roundScheduleModal .rs-next { margin-top: 14px; }
    #roundScheduleModal .rs-next label { display: block; font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 5px; }
    #roundScheduleModal .rs-next input { padding: 7px 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; }
    @media (max-width: 560px) { #roundScheduleModal .rs-grid { grid-template-columns: 1fr; } }

    /* Responsive */
    @media (max-width: 1280px) {
        #tab-overview .ov-stats { grid-template-columns: repeat(2, 1fr); }
        #tab-overview .ov-docs-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 900px) {
        #tab-overview .ov-main { grid-template-columns: 1fr; }
        #tab-overview .ov-stats { grid-template-columns: repeat(2, 1fr); }
        #tab-overview .ov-summary-grid { grid-template-columns: 1fr; }
        #tab-overview .ov-docs-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        #tab-overview .ov-stats { grid-template-columns: 1fr; }
        #tab-overview .ov-docs-grid { grid-template-columns: 1fr; }
        #tab-overview .ov-tl-row { grid-template-columns: 28px 1fr; }
        #tab-overview .ov-tl-pill, #tab-overview .ov-tl-date { grid-column: 2; }
    }
</style>
@endpush

@if ($canEdit)
@push('scripts')
<script>
// Enable/disable a round's date input as its checkbox toggles; prefill today
// when a round is newly checked so it isn't left blank.
window.rsToggle = function (cb) {
    var row = cb.closest('.rs-row');
    if (!row) return;
    var input = row.querySelector('.rs-date');
    if (!input) return;
    input.disabled = !cb.checked;
    if (cb.checked && !input.value) {
        var t = new Date();
        input.value = t.getFullYear() + '-' +
            String(t.getMonth() + 1).padStart(2, '0') + '-' +
            String(t.getDate()).padStart(2, '0');
    }
};
</script>
@endpush
@endif
