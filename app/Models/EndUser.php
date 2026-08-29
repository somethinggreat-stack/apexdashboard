<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EndUser extends Model
{
    use HasFactory, SoftDeletes;

    public const ROUND_OPTIONS = [
        '1st Round',
        '2nd Round',
        '3rd Round',
        '4th Round',
        '5th Round',
        '6th Round',
        '7th Round',
        '8th Round',
        '9th Round',
        '10th Round',
        '11th Round',
        '12th Round',
        '13th Round',
        '14th Round',
        '15th Round',
    ];

    /**
     * Per-business-owner custom lists (Tycon Stan only for now). key => label.
     * A client tagged into one of these shows only in that list on the owner's
     * portal and drops out of the normal buckets there. Admin/VA views ignore it.
     */
    public const CUSTOM_LISTS = [
        'jumbo'     => 'Jumbo',
        'mr_pierre' => 'Mr Pierre',
        'tycoon'    => 'Tycoon',
    ];

    /** A round runs 30 days; the "days left" countdown is measured against this. */
    public const ROUND_LENGTH_DAYS = 30;

    protected static function booted(): void
    {
        // A client that gets moved to a real work bucket — its intake_status or
        // hold state changes — automatically drops out of any business-owner
        // custom list. Central + invisible: covers every admin/VA move path
        // without touching those controllers or views.
        static::updating(function (EndUser $user) {
            if ($user->custom_list !== null && ($user->isDirty('intake_status') || $user->isDirty('held_at'))) {
                $user->custom_list = null;
            }
        });

        // Stamp when a client enters the Clients (Done) list — from any path
        // (moved to Clients, or added straight in). Powers the Daily Task report.
        static::saving(function (EndUser $user) {
            if ($user->isDirty('intake_status') && $user->intake_status === 'done') {
                $user->listed_at = now();
            }
        });

        // Audit trail (super-admin Activity tab): who moved this client where.
        static::created(function (EndUser $user) {
            \App\Models\ClientEvent::log($user, 'created', 'Client added to ' . \App\Models\ClientEvent::bucketLabel($user->intake_status, (bool) $user->held_at));
        });
        static::updated(function (EndUser $user) {
            if ($user->wasChanged('intake_status')) {
                $reason = $user->move_reason ? " — {$user->move_reason}" : '';
                \App\Models\ClientEvent::log($user, 'moved', 'Moved to ' . \App\Models\ClientEvent::bucketLabel($user->intake_status, false) . $reason);
            }
            if ($user->wasChanged('held_at')) {
                if ($user->held_at) {
                    $reason = $user->move_reason ? " — {$user->move_reason}" : '';
                    \App\Models\ClientEvent::log($user, 'held', "Placed on Hold / Pause{$reason}");
                } else {
                    \App\Models\ClientEvent::log($user, 'resumed', 'Resumed from Hold / Pause');
                }
            }
        });

        static::deleting(function (EndUser $user) {
            // A soft delete (Recycle Bin) must keep everything for a possible
            // restore — leave the documents and identity files exactly where
            // they are. Only a permanent purge actually removes the files.
            if (! $user->isForceDeleting()) {
                return;
            }

            // Force-delete each document via Eloquent so its own deleting hook
            // (which removes the file from disk) fires. The DB cascade alone
            // would delete the rows but skip Eloquent events, leaving orphan files.
            $user->documents()->each(fn ($doc) => $doc->delete());

            foreach (['photo_id_path', 'proof_of_address_path', 'ssn_picture_path', 'collage_path'] as $col) {
                if ($user->{$col} && Storage::disk('private')->exists($user->{$col})) {
                    Storage::disk('private')->delete($user->{$col});
                }
            }
        });
    }

    protected $fillable = [
        'client_id',
        'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'phone', 'date_of_birth',
        'current_address', 'address_line2', 'city', 'state', 'zipcode',
        'ssn', 'ssn_picture_path', 'photo_id_path', 'proof_of_address_path', 'collage_path',
        'credit_monitoring_name', 'credit_monitoring_username', 'credit_monitoring_password',
        'credit_monitoring_security_answer', 'credit_monitoring_security_question', 'credit_monitoring_pin',
        'cfpb_email', 'cfpb_password', 'cfpb_round_credentials', 'cfpb_logged_at', 'cfpb_logged_by_admin_id',
        'current_score', 'goal_score', 'status', 'held_at', 'rounds', 'round_dates', 'start_date', 'listed_at',
        'per_round_fee', 'per_round_fees',
        'intake_status', 'intake_submitted_ip', 'intake_submitted_at', 'intake_review_note', 'error_type', 'move_reason',
        'error_resolved_by_client_at',
        'next_round_override', 'custom_list',
        'round_approval_status', 'round_approval_round', 'round_approval_at',
        'deleted_by_admin_id', 'deleted_with_owner',
    ];
    protected $casts = [
        'start_date' => 'date',
        'next_round_override' => 'date',
        'deleted_with_owner' => 'boolean',
        'held_at' => 'datetime',
        'listed_at' => 'datetime',
        'cfpb_logged_at' => 'datetime',
        'round_approval_at' => 'datetime',
        'date_of_birth' => 'date',
        'per_round_fee' => 'decimal:2',
        'per_round_fees' => 'array',
        'rounds' => 'array',
        'round_dates' => 'array',
        'intake_submitted_at' => 'datetime',
        'error_resolved_by_client_at' => 'datetime',
        'ssn' => 'encrypted',
        'credit_monitoring_password' => 'encrypted',
        'credit_monitoring_security_answer' => 'encrypted',
        'credit_monitoring_pin' => 'encrypted',
        'cfpb_password' => 'encrypted',
        'cfpb_round_credentials' => 'encrypted:array',
    ];
    protected $hidden = ['ssn', 'credit_monitoring_password', 'credit_monitoring_security_answer', 'credit_monitoring_pin', 'cfpb_password', 'cfpb_round_credentials'];

    /**
     * Round numbers this client has reached (from the `rounds` array), sorted
     * ascending. Drives which per-round CFPB credential boxes appear — a new
     * round's box shows once the client is moved into that round. Always at
     * least round 1.
     */
    public function reachedRoundNumbers(): array
    {
        $map  = array_flip(self::ROUND_OPTIONS);   // '1st Round' => 0
        $nums = collect($this->rounds ?? [])
            ->map(fn ($label) => isset($map[$label]) ? $map[$label] + 1 : null)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $nums ?: [1];
    }

    /** CFPB credentials stored for one round: ['email' => ?, 'password' => ?]. */
    public function cfpbForRound(int $round): array
    {
        $creds = $this->cfpb_round_credentials ?? [];
        $r = $creds[(string) $round] ?? ($creds[$round] ?? []);

        return [
            'email'    => $r['email'] ?? null,
            'password' => $r['password'] ?? null,
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /** Negative accounts/items on this client's file (results tracking). */
    public function negativeItems()
    {
        return $this->hasMany(NegativeItem::class);
    }

    /** Audit-trail events (moves, holds, profile edits) — super-admin Activity tab. */
    public function clientEvents()
    {
        return $this->hasMany(ClientEvent::class);
    }

    /**
     * How many negative items are still reporting. Uses the already-loaded
     * relation when present (no extra query); otherwise counts in the DB.
     */
    public function remainingNegativeCount(): int
    {
        if ($this->relationLoaded('negativeItems')) {
            return $this->negativeItems->where('status', 'reporting')->count();
        }
        return $this->negativeItems()->reporting()->count();
    }

    /** True when only 1–2 negative items remain — "nearing completion" (SOP §4). */
    public function isNearingCompletion(): bool
    {
        $left = $this->remainingNegativeCount();
        return $left >= 1 && $left <= 2;
    }

    /** Short human status for the results reports: Hold / New / Error / Active. */
    public function resultsStatusLabel(): string
    {
        if ($this->held_at) {
            return 'On Hold';
        }
        return match ($this->intake_status) {
            'pending_review' => 'New Client',
            'error'          => 'Error',
            'round_error'    => 'Round Error',
            default          => 'Active',
        };
    }

    public function deletedBy()
    {
        return $this->belongsTo(Admin::class, 'deleted_by_admin_id');
    }

    /** The admin/VA who last entered this client's CFPB login. */
    public function cfpbLoggedBy()
    {
        return $this->belongsTo(Admin::class, 'cfpb_logged_by_admin_id');
    }

    public function scopeForAdmin($query, $adminId)
    {
        return $query->whereHas('client', fn ($q) => $q->where('admin_id', $adminId));
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Billable clients — In Progress + Clients Done. Excludes New Clients
     * (pending_review) and Errors. Used for payments/billing.
     */
    public function scopeClientsList($query)
    {
        return $query->where(fn ($q) => $q->whereNull('intake_status')
            ->orWhereNotIn('intake_status', ['pending_review', 'error', 'round_error']));
    }

    /**
     * Billable clients for payments/billing — everything ClientsList covers PLUS
     * Round Errors. A round-error client has already completed at least their 1st
     * round, so the rounds they've reached must still be collectible; only New
     * Clients (pending_review) and New Client Errors (error) — who have finished
     * no round — are excluded. Per-round logic still bills only reached rounds.
     */
    public function scopeBillableList($query)
    {
        return $query->where(fn ($q) => $q->whereNull('intake_status')
            ->orWhereNotIn('intake_status', ['pending_review', 'error']));
    }

    /** "In Progress" — actively worked clients (not new, not error, not done). */
    /**
     * Name/email/phone search that matches however the user types it — first
     * name, last name, middle name, the full name in any order, or a partial.
     * Each word must appear in one of the fields, so "Steve", "Depasse",
     * "Steve Depasse" and "Depasse Steve" all find the same client. No CONCAT so
     * it behaves identically on MySQL and SQLite.
     */
    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        foreach (preg_split('/\s+/', $term) as $word) {
            $like = '%' . $word . '%';
            $query->where(function ($w) use ($like) {
                $w->where('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }
        return $query;
    }

    public function scopeInProgress($query)
    {
        return $query->where(fn ($q) => $q->whereNull('intake_status')
            ->orWhereNotIn('intake_status', ['pending_review', 'error', 'round_error', 'done']));
    }

    /** "Clients Done" — finished clients. Moved here only via the button. */
    public function scopeDone($query)
    {
        return $query->where('intake_status', 'done');
    }

    /** "Round Errors" — clients past round 1 pulled out with an import problem. */
    public function scopeRoundError($query)
    {
        return $query->where('intake_status', 'round_error');
    }

    /** Round Errors still awaiting a fix (the business owner hasn't resolved). */
    public function scopeRoundErrorPending($query)
    {
        return $query->where('intake_status', 'round_error')
            ->whereNull('error_resolved_by_client_at');
    }

    /** Round Errors the business owner has resolved (awaiting the VA to process). */
    public function scopeRoundErrorResolvedByClient($query)
    {
        return $query->where('intake_status', 'round_error')
            ->whereNotNull('error_resolved_by_client_at');
    }

    /** New Client Errors still awaiting a fix (the business owner hasn't resolved). */
    public function scopeNewError($query)
    {
        return $query->where('intake_status', 'error')
            ->whereNull('error_resolved_by_client_at');
    }

    /** New Client Errors the business owner has resolved (awaiting the VA to process). */
    public function scopeNewErrorResolvedByClient($query)
    {
        return $query->where('intake_status', 'error')
            ->whereNotNull('error_resolved_by_client_at');
    }

    /** On Hold / Pause — parked out of the normal buckets. */
    public function scopeOnHold($query)
    {
        return $query->whereNotNull('held_at');
    }

    /** Not held — the default working set (excludes Hold/Pause). */
    public function scopeNotHeld($query)
    {
        return $query->whereNull('held_at');
    }

    /** Not in any business-owner custom list (Jumbo / Mr Pierre / Tycoon). */
    public function scopeNoCustomList($query)
    {
        return $query->whereNull('custom_list');
    }

    /** Clients in a specific business-owner custom list. */
    public function scopeCustomList($query, string $key)
    {
        return $query->where('custom_list', $key);
    }

    public function getIsOnHoldAttribute(): bool
    {
        return $this->held_at !== null;
    }

    public function processSteps()
    {
        return $this->hasMany(ProcessStep::class)->orderBy('step_date', 'asc');
    }

    public function documents()
    {
        return $this->hasMany(Document::class)->orderBy('created_at', 'desc');
    }

    public function scoreHistory()
    {
        return $this->hasMany(ScoreHistory::class)->orderBy('recorded_at');
    }

    public function notes()
    {
        return $this->hasMany(Note::class)->orderBy('created_at', 'desc');
    }

    public function payments()
    {
        return $this->hasMany(ClientPayment::class)->orderBy('round');
    }

    /**
     * Total still owed for this client: the effective fee of every active round
     * they're in that hasn't been marked paid. Mirrors the Payments page's
     * "unpaid" calculation. Requires the `payments` relation and `client` to be
     * loaded (effectiveRoundFee reads $this->client).
     */
    public function pendingRoundTotal(): float
    {
        $paidByRound = $this->payments->keyBy('round');

        $labelToNum = array_map(fn ($i) => $i + 1, array_flip(self::ROUND_OPTIONS));
        $activeRounds = collect($this->rounds ?? [])
            ->map(fn ($label) => $labelToNum[$label] ?? null)
            ->filter()
            ->values()
            ->all();
        if (empty($activeRounds)) {
            $activeRounds = [1];
        }

        $sum = 0.0;
        foreach ($activeRounds as $rn) {
            if (!$paidByRound->has($rn)) {
                $sum += $this->effectiveRoundFee($rn);
            }
        }

        return $sum;
    }

    /**
     * The fee that applies to this client for a given round. Resolution order:
     *   1. a per-round override for that specific round (per_round_fees[$round])
     *   2. the client's flat per-round override (per_round_fee)
     *   3. the business owner's default per_round_fee
     * Call with no $round to get the flat/default rate (ignores per-round
     * overrides) — used for the "all rounds" rate pill.
     */
    public function effectiveRoundFee(?int $round = null): float
    {
        if ($round !== null) {
            $override = $this->roundFeeOverride($round);
            if ($override !== null) {
                return $override;
            }
        }
        if ($this->per_round_fee !== null) {
            return (float) $this->per_round_fee;
        }
        return (float) ($this->client->per_round_fee ?? 0);
    }

    /** Raw per-round override amount for a single round, or null if none set. */
    public function roundFeeOverride(int $round): ?float
    {
        $overrides = $this->per_round_fees ?? [];
        $value = $overrides[(string) $round] ?? null;
        return $value === null ? null : (float) $value;
    }

    /**
     * Whether a custom rate applies. With $round, checks that specific round's
     * override; without, checks the flat per-client override.
     */
    public function hasCustomRoundFee(?int $round = null): bool
    {
        if ($round !== null) {
            return $this->roundFeeOverride($round) !== null;
        }
        return $this->per_round_fee !== null;
    }

    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_name ? " {$this->middle_name}" : '';
        $suffix = $this->suffix && $this->suffix !== 'None' ? " {$this->suffix}" : '';
        return trim("{$this->first_name}{$middle} {$this->last_name}{$suffix}");
    }

    public function getMaskedSsnAttribute(): ?string
    {
        if (!$this->ssn) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $this->ssn);
        if (strlen($digits) < 4) {
            return '***-**-****';
        }
        return '***-**-' . substr($digits, -4);
    }

    /**
     * SSN shown as XXX-XX-XXXX regardless of how it's stored — new records hold
     * 9 plain digits, older ones may already have dashes. Anything that isn't a
     * clean 9-digit value is returned untouched.
     */
    public function getFormattedSsnAttribute(): ?string
    {
        if (!$this->ssn) {
            return null;
        }
        $digits = preg_replace('/\D/', '', (string) $this->ssn);
        if (strlen($digits) !== 9) {
            return $this->ssn;
        }
        return substr($digits, 0, 3) . '-' . substr($digits, 3, 2) . '-' . substr($digits, 5);
    }

    /**
     * Days the client has been ACTIVELY worked — counted from the day the 1st
     * round was marked (its first Week-1 step logged), NOT from the day the
     * client was added to the system. Null until the 1st round is marked, so an
     * unworked client reads "not started" instead of silently ticking up days.
     */
    public function getDaysActiveAttribute(): ?int
    {
        $start = $this->roundStartDate(1);
        if (! $start) {
            return null;
        }
        return (int) Carbon::parse($start)->startOfDay()->diffInDays(now()->startOfDay()) + 1;
    }

    /**
     * The closeout steps — pull the report + record deletions. They live in the
     * LAST week (20-day → Week 3, 30-day → Week 4) and are the only steps that
     * can't be done until the round's response window is over, so they're
     * treated specially: never nagged on the weekly schedule, only once the
     * round is past due (see closeout_due).
     */
    public const CLOSEOUT_STEPS = ['pull_latest_report', 'record_deletions'];

    /**
     * The first missing-week number on the weekly SCHEDULE, or null if on track.
     * Phases become due one "week length" apart (30-day: days 1/8/15/22, 20-day:
     * 1/7/13). A week that holds ONLY closeout steps (30-day Week 4) is skipped
     * here — its steps are handled by closeout_due, past due only.
     */
    public function getMissingWeekAttribute(): ?int
    {
        // Nothing is "due" until the round is actually marked (its first Week-1
        // step logged). An unstarted round is never on the schedule.
        $start = $this->current_round_start_date;
        if (! $start) {
            return null;
        }
        $days   = (int) Carbon::parse($start)->startOfDay()->diffInDays(now()->startOfDay()) + 1;
        $wk     = $this->roundWeekLength();
        $count  = $this->roundWeekCount();
        $byWeek = \App\Models\ProcessStep::stepTypesByWeek($this->roundCycleDays());

        for ($w = 1; $w <= $count; $w++) {
            // Skip a week with no regular (non-closeout) work — nothing is due on
            // the schedule for it.
            $regular = array_diff(array_keys($byWeek[$w] ?? []), self::CLOSEOUT_STEPS);
            if (empty($regular)) {
                continue;
            }
            $dueDay = (($w - 1) * $wk) + 1;
            if ($days >= $dueDay && (int) ($this->{"week{$w}_count"} ?? 0) === 0) {
                return $w;
            }
        }
        return null;
    }

    /**
     * True when the round is PAST DUE (days left negative) and its closeout —
     * Pull Latest Report + Record Deletions — hasn't been fully logged for the
     * CURRENT round. This is the only thing that surfaces those two steps as
     * "incomplete", exactly what the last-week rule is about.
     */
    public function getCloseoutDueAttribute(): bool
    {
        $daysLeft = $this->days_left_in_round;
        if ($daysLeft === null || $daysLeft >= 0) {
            return false;
        }
        $logged = ($this->relationLoaded('processSteps') ? $this->processSteps : $this->processSteps())
            ->where('round', $this->current_round)
            ->pluck('step_type')->unique()->all();

        return array_diff(self::CLOSEOUT_STEPS, $logged) !== [];
    }

    public function getIncompleteReasonAttribute(): ?string
    {
        if ($w = $this->missing_week) {
            return "Week {$w} not logged";
        }
        if ($this->closeout_due) {
            return 'Round past due — pull report / record deletions';
        }
        return null;
    }

    public function getIsIncompleteAttribute(): bool
    {
        return $this->missing_week !== null || $this->closeout_due;
    }

    /**
     * Steps must be logged strictly in order. A round/week can only be worked
     * once EVERY earlier week of the same round — and EVERY earlier round in
     * full, closeout steps included — is completely logged. Returns null when
     * logging (round, week) is allowed, or a human message naming the blocker.
     * Enforced for everyone, VAs and super admins alike.
     */
    public function sequentialBlockReason(int $round, int $week): ?string
    {
        $byWeek    = \App\Models\ProcessStep::stepTypesByWeek($this->roundCycleDays());
        $weekCount = \App\Models\ProcessStep::weekCount($this->roundCycleDays());

        // Every earlier round must be finished in full (through its last week,
        // which holds Pull Latest Report + Record Deletions).
        for ($r = 1; $r < $round; $r++) {
            for ($w = 1; $w <= $weekCount; $w++) {
                if (! $this->weekFullyLogged($r, $w, $byWeek)) {
                    return "Round {$r} isn't finished — complete every step through Week {$weekCount} "
                        . "(including Pull Latest Report and Record Deletions) before starting Round {$round}.";
                }
            }
        }

        // Every earlier week of THIS round must be finished.
        for ($w = 1; $w < $week; $w++) {
            if (! $this->weekFullyLogged($round, $w, $byWeek)) {
                return "Round {$round} Week {$w} isn't finished — complete all of its steps before Week {$week}.";
            }
        }

        return null;
    }

    /** True when every step type that belongs to (round, week) has been logged. */
    public function weekFullyLogged(int $round, int $week, ?array $byWeek = null): bool
    {
        $byWeek ??= \App\Models\ProcessStep::stepTypesByWeek($this->roundCycleDays());
        $needed = array_keys($byWeek[$week] ?? []);
        if (empty($needed)) {
            return true;
        }
        $steps = $this->relationLoaded('processSteps') ? $this->processSteps : $this->processSteps();
        $logged = $steps->where('round', $round)->where('week', $week)->pluck('step_type')->all();

        return empty(array_diff($needed, $logged));
    }

    /**
     * What the quick-log badge should open to: the week + the exact step types
     * that are missing. For a schedule gap that's the missing week's first step;
     * for the past-due closeout it's the LAST week (20-day W3 / 30-day W4) with
     * the missing closeout steps (Pull Latest Report / Record Deletions) — never
     * Week 1 when Week 1 is already done.
     *
     * @return array{week:int, steps:array<int,string>}
     */
    public function incompleteTarget(): array
    {
        $byWeek = \App\Models\ProcessStep::stepTypesByWeek($this->roundCycleDays());

        if ($w = $this->missing_week) {
            $regular = array_values(array_diff(array_keys($byWeek[$w] ?? []), self::CLOSEOUT_STEPS));
            return ['week' => $w, 'steps' => $regular ? [$regular[0]] : []];
        }

        if ($this->closeout_due) {
            $logged = ($this->relationLoaded('processSteps') ? $this->processSteps : $this->processSteps())
                ->where('round', $this->current_round)->pluck('step_type')->unique()->all();
            return [
                'week'  => $this->roundWeekCount(),
                'steps' => array_values(array_diff(self::CLOSEOUT_STEPS, $logged)),
            ];
        }

        return ['week' => 1, 'steps' => []];
    }

    public function getCurrentRoundAttribute(): int
    {
        // Reached rounds (in the rounds array) — a round can be reached before its
        // first step, in which case it's the current round but not yet "started".
        $byRounds = count($this->rounds ?? []);

        // The highest round that actually has a MARKED start date (a hand-set
        // round_dates entry or a logged step). This keeps current_round in step
        // with round_timeline / the "Round Started" column, so the next-round
        // date and days-left are always measured from the latest round shown.
        $byDates = 0;
        foreach (($this->round_dates ?? []) as $label => $date) {
            $idx = array_search($label, self::ROUND_OPTIONS, true);
            if ($idx !== false && ! empty($date)) {
                $byDates = max($byDates, $idx + 1);
            }
        }

        $steps   = $this->relationLoaded('processSteps') ? $this->processSteps : $this->processSteps();
        $bySteps = (int) $steps->max('round');

        return max(1, $byRounds, $byDates, $bySteps);
    }

    /**
     * The authoritative start date of a round — the date it was MARKED. A round
     * is marked when its first Week-1 step is logged (or when a date is set by
     * hand in Edit Rounds & Dates, stored in round_dates). It is NEVER the
     * client's added date: a client with no steps for the round returns null, so
     * every day-count (days active, days left, next round, past-due) stays blank
     * until the team actually starts the round. Applies to every round equally.
     */
    public function roundStartDate(int $round): ?string
    {
        $label = self::ROUND_OPTIONS[$round - 1] ?? null;

        // A hand-set date (Edit Rounds & Dates) wins.
        if ($label && ! empty($this->round_dates[$label])) {
            return Carbon::parse($this->round_dates[$label])->toDateString();
        }

        // Otherwise the round starts on its earliest logged step.
        $steps = $this->relationLoaded('processSteps') ? $this->processSteps : $this->processSteps();
        $earliest = $steps->where('round', $round)->min('step_date');

        return $earliest ? Carbon::parse($earliest)->toDateString() : null;
    }

    /**
     * The date a given round label was marked, or null if it hasn't been. Thin
     * wrapper over roundStartDate() keyed by label.
     */
    public function roundStartedAt(string $label): ?string
    {
        $idx = array_search($label, self::ROUND_OPTIONS, true);
        return $idx === false ? null : $this->roundStartDate($idx + 1);
    }

    /** True once the client's 1st round has ever been marked (they've been worked). */
    public function getEverStartedAttribute(): bool
    {
        return $this->roundStartDate(1) !== null;
    }

    /** True once the CURRENT round has been marked; gates its day-counts. */
    public function getRoundStartedAttribute(): bool
    {
        return $this->current_round_start_date !== null;
    }

    public function getSecondRoundStartedAtAttribute(): ?string
    {
        return $this->roundStartedAt('2nd Round');
    }

    /**
     * Ordered map of each STARTED round label => its marked start date. Only
     * rounds that have actually begun (a step logged, or a hand-set date) appear
     * — a round the client has merely "reached" but not started stays out, so
     * the timeline never shows a round as started before it is.
     */
    public function getRoundTimelineAttribute(): array
    {
        $out = [];
        foreach (self::ROUND_OPTIONS as $i => $label) {
            $date = $this->roundStartDate($i + 1);
            if ($date !== null) {
                $out[$label] = $date;
            }
        }
        return $out;
    }

    /** Short comma list of the rounds actually started, e.g. "R1, R2, R3". */
    public function getStartedRoundsShortAttribute(): string
    {
        return collect(array_keys($this->round_timeline))
            ->map(fn ($l) => 'R' . (array_search($l, self::ROUND_OPTIONS, true) + 1))
            ->implode(', ');
    }

    /**
     * The marked start date of the round the client is currently on, or null if
     * that round hasn't been started yet (no step logged for it). Every
     * round-based counter reads from here, so all of them stay blank until the
     * round is marked.
     */
    public function getCurrentRoundStartDateAttribute(): ?string
    {
        return $this->roundStartDate($this->current_round);
    }

    /**
     * Round-cycle length (days) for this client — inherited live from their
     * business owner (20 or 30). Every round date and week pace derives from it,
     * so flipping the owner instantly re-adjusts all of their clients. Falls
     * back to the 30-day default when no owner/value is present.
     */
    public function roundCycleDays(): int
    {
        return $this->client?->roundCycleDays() ?? self::ROUND_LENGTH_DAYS;
    }

    /** Number of process-week phases in a round for this client's cycle (30→4, 20→3). */
    public function roundWeekCount(): int
    {
        return \App\Models\ProcessStep::weekCount($this->roundCycleDays());
    }

    /** Length (days) of one process-week phase for this cycle (30→7, 20→6). */
    public function roundWeekLength(): int
    {
        return max(1, intdiv($this->roundCycleDays(), $this->roundWeekCount()));
    }

    /**
     * The date the next round is due to start — exactly one round cycle after
     * the current round's start date (20 or 30 days depending on the owner).
     */
    public function getNextRoundDateAttribute(): ?string
    {
        // A hand-set override (from the Clients list) wins over the auto date.
        if ($this->next_round_override) {
            return Carbon::parse($this->next_round_override)->toDateString();
        }
        $start = $this->current_round_start_date;
        if (!$start) {
            return null;
        }
        return Carbon::parse($start)->startOfDay()->addDays($this->roundCycleDays())->toDateString();
    }

    /**
     * A round is "closed out" once BOTH week-4 steps are logged for it:
     * Pull Latest Report and Record Deletions. The next round can't be started
     * until the previous one is closed out.
     */
    public function roundClosedOut(int $round): bool
    {
        $needed = ['pull_latest_report', 'record_deletions'];

        $types = $this->relationLoaded('processSteps')
            ? $this->processSteps->where('round', $round)->pluck('step_type')
            : $this->processSteps()->where('round', $round)->pluck('step_type');

        return empty(array_diff($needed, $types->unique()->values()->all()));
    }

    /**
     * True when the current round has run past its 30 days but the report still
     * hasn't been pulled / deletions recorded — surfaced as a warning on the file.
     */
    public function getNeedsRoundCloseoutAttribute(): bool
    {
        $daysLeft = $this->days_left_in_round;

        return $daysLeft !== null && $daysLeft <= 0 && !$this->roundClosedOut($this->current_round);
    }

    /** The label of the round the client is currently on. */
    public function getCurrentRoundLabelAttribute(): string
    {
        return self::ROUND_OPTIONS[$this->current_round - 1] ?? "Round {$this->current_round}";
    }

    /** The date the current round is due to end (start + the owner's cycle). */
    public function getRoundEndDateAttribute(): ?string
    {
        $start = $this->current_round_start_date;
        if (!$start) {
            return null;
        }
        return Carbon::parse($start)->startOfDay()->addDays($this->roundCycleDays())->toDateString();
    }

    /**
     * Days remaining in the current round. Counts down from the owner's cycle
     * length (20 or 30) on the round's start date and goes negative once the
     * round is overdue (e.g. -1, -2 …). Resets when a new round is started.
     */
    public function getDaysLeftInRoundAttribute(): ?int
    {
        $start = $this->current_round_start_date;
        if (!$start) {
            return null;
        }
        $roundEnd = Carbon::parse($start)->startOfDay()->addDays($this->roundCycleDays());
        return (int) now()->startOfDay()->diffInDays($roundEnd, false);
    }

    /**
     * How far through the current round this client is, 0–100.
     * Derived purely from process steps: distinct step types logged for the
     * current round, over the number of step types a full round contains (9).
     * Read-only — nothing is stored, so this can never drift from the log.
     */
    public function getProgressPercentAttribute(): int
    {
        $total = count(ProcessStep::allStepTypes());
        if ($total === 0) {
            return 0;
        }

        $round = $this->current_round;

        $done = $this->relationLoaded('processSteps')
            ? $this->processSteps->where('round', $round)->pluck('step_type')->unique()->count()
            : $this->processSteps()->where('round', $round)->distinct()->count('step_type');

        return (int) min(100, round($done / $total * 100));
    }

    public function getTotalDeletionsAttribute()
    {
        return (int) $this->processSteps->sum(function ($s) {
            return ($s->experian_accounts_disputed ?? 0)
                + ($s->transunion_accounts_disputed ?? 0)
                + ($s->equifax_accounts_disputed ?? 0);
        });
    }

    public function getScoreChangeAttribute()
    {
        $firstScore = $this->scoreHistory()->orderBy('recorded_at')->first();
        if (!$firstScore || $this->current_score === null) {
            return 0;
        }
        return $this->current_score - $firstScore->score;
    }

    public function getPhotoIdUrlAttribute(): ?string
    {
        return $this->identityUrl('photo_id');
    }

    public function getProofOfAddressUrlAttribute(): ?string
    {
        return $this->identityUrl('proof_of_address');
    }

    public function getSsnPictureUrlAttribute(): ?string
    {
        return $this->identityUrl('ssn_picture');
    }

    public function getCollageUrlAttribute(): ?string
    {
        return $this->identityUrl('collage');
    }

    private function identityUrl(string $type): ?string
    {
        $column = match ($type) {
            'photo_id' => 'photo_id_path',
            'proof_of_address' => 'proof_of_address_path',
            'ssn_picture' => 'ssn_picture_path',
            'collage' => 'collage_path',
            default => null,
        };
        if (!$column || !$this->{$column} || !$this->id) {
            return null;
        }
        if (Auth::guard('admin')->check()) {
            return route('admin.files.identity', ['endUser' => $this->id, 'type' => $type]);
        }
        if (Auth::guard('client')->check()) {
            return route('client.files.identity', ['endUser' => $this->id, 'type' => $type]);
        }
        return null;
    }
}
