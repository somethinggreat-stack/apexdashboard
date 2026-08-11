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
        'cfpb_email', 'cfpb_password', 'cfpb_round_credentials',
        'current_score', 'goal_score', 'status', 'held_at', 'rounds', 'round_dates', 'start_date',
        'per_round_fee', 'per_round_fees',
        'intake_status', 'intake_submitted_ip', 'intake_submitted_at', 'intake_review_note', 'error_type',
        'error_resolved_by_client_at',
        'next_round_override', 'custom_list',
        'deleted_by_admin_id', 'deleted_with_owner',
    ];
    protected $casts = [
        'start_date' => 'date',
        'next_round_override' => 'date',
        'deleted_with_owner' => 'boolean',
        'held_at' => 'datetime',
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

    public function deletedBy()
    {
        return $this->belongsTo(Admin::class, 'deleted_by_admin_id');
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

        $labelToNum = [
            '1st Round' => 1, '2nd Round' => 2, '3rd Round' => 3, '4th Round' => 4,
            '5th Round' => 5, '6th Round' => 6, '7th Round' => 7, '8th Round' => 8,
        ];
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

    public function getDaysActiveAttribute()
    {
        return (int) Carbon::parse($this->start_date)->diffInDays(now()) + 1;
    }

    /**
     * Returns the first missing-week number based on days_active,
     * or null if the client is on track. Rules:
     *   day  1–7   → must have a Week 1 step
     *   day  8–14  → must have a Week 2 step
     *   day 15–21  → must have a Week 3 step
     *   day 22+    → must have a Week 4 step
     * (Earlier weeks are also checked, so a client at day 30 with
     *  no Week 1 step shows "Week 1 not logged".)
     */
    public function getMissingWeekAttribute(): ?int
    {
        $days = $this->days_active;

        if ($days >= 1  && (int) ($this->week1_count ?? 0) === 0) return 1;
        if ($days >= 8  && (int) ($this->week2_count ?? 0) === 0) return 2;
        if ($days >= 15 && (int) ($this->week3_count ?? 0) === 0) return 3;
        if ($days >= 22 && (int) ($this->week4_count ?? 0) === 0) return 4;
        return null;
    }

    public function getIncompleteReasonAttribute(): ?string
    {
        $w = $this->missing_week;
        return $w ? "Week {$w} not logged" : null;
    }

    public function getIsIncompleteAttribute(): bool
    {
        return $this->missing_week !== null;
    }

    public function getCurrentRoundAttribute(): int
    {
        return max(1, count($this->rounds ?? []));
    }

    /**
     * The date a given round label was first started, or null if not recorded.
     * Round dates are stamped automatically when a round is added (see
     * EndUserController::update). The 1st round's date is the client start_date.
     */
    public function roundStartedAt(string $label): ?string
    {
        if ($label === '1st Round') {
            return $this->start_date ? Carbon::parse($this->start_date)->toDateString() : null;
        }
        return $this->round_dates[$label] ?? null;
    }

    public function getSecondRoundStartedAtAttribute(): ?string
    {
        return $this->roundStartedAt('2nd Round');
    }

    /**
     * Ordered map of each started round label => its start date (or null),
     * for every round the client is currently on. Used to render the
     * "Round Started" column. 1st Round resolves to the client start_date;
     * 2nd–5th rounds resolve to their auto-stamped round_dates entry.
     */
    public function getRoundTimelineAttribute(): array
    {
        $selected = $this->rounds ?? [];
        $out = [];
        foreach (self::ROUND_OPTIONS as $label) {
            if (in_array($label, $selected, true)) {
                $out[$label] = $this->roundStartedAt($label);
            }
        }
        return $out;
    }

    /**
     * The start date of the round the client is currently on (the highest
     * round they're in). Falls back to the client start_date for round 1.
     */
    public function getCurrentRoundStartDateAttribute(): ?string
    {
        $selected = $this->rounds ?? [];
        $highest = null;
        foreach (self::ROUND_OPTIONS as $label) {
            if (in_array($label, $selected, true)) {
                $highest = $label;
            }
        }
        if ($highest === null) {
            return $this->start_date ? Carbon::parse($this->start_date)->toDateString() : null;
        }
        return $this->roundStartedAt($highest);
    }

    /**
     * The date the next round is due to start — one calendar month after the
     * current round's start date (e.g. 1st round Jun 23 → Jul 23; once the
     * 2nd round starts Jul 23 → Aug 23). No-overflow so end-of-month dates
     * clamp to the last day of the shorter month.
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
        return Carbon::parse($start)->addMonthNoOverflow()->toDateString();
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

    /** The label of the round the client is currently on (highest selected). */
    public function getCurrentRoundLabelAttribute(): string
    {
        $selected = $this->rounds ?? [];
        $highest = '1st Round';
        foreach (self::ROUND_OPTIONS as $label) {
            if (in_array($label, $selected, true)) {
                $highest = $label;
            }
        }
        return $highest;
    }

    /** The date the current 30-day round is due to end. */
    public function getRoundEndDateAttribute(): ?string
    {
        $start = $this->current_round_start_date;
        if (!$start) {
            return null;
        }
        return Carbon::parse($start)->startOfDay()->addDays(self::ROUND_LENGTH_DAYS)->toDateString();
    }

    /**
     * Days remaining in the current round. Counts down from ROUND_LENGTH_DAYS
     * on the round's start date and goes negative once the round is overdue
     * (e.g. -1, -2 …). Resets when a new round is started.
     */
    public function getDaysLeftInRoundAttribute(): ?int
    {
        $start = $this->current_round_start_date;
        if (!$start) {
            return null;
        }
        $roundEnd = Carbon::parse($start)->startOfDay()->addDays(self::ROUND_LENGTH_DAYS);
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
