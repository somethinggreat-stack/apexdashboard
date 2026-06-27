<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EndUser extends Model
{
    use HasFactory;

    public const ROUND_OPTIONS = [
        '1st Round',
        '2nd Round',
        '3rd Round',
        '4th Round',
        '5th Round',
    ];

    /** A round runs 30 days; the "days left" countdown is measured against this. */
    public const ROUND_LENGTH_DAYS = 30;

    protected static function booted(): void
    {
        static::deleting(function (EndUser $user) {
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
        'current_address', 'city', 'state', 'zipcode',
        'ssn', 'ssn_picture_path', 'photo_id_path', 'proof_of_address_path', 'collage_path',
        'credit_monitoring_name', 'credit_monitoring_username', 'credit_monitoring_password',
        'credit_monitoring_security_answer',
        'cfpb_email', 'cfpb_password',
        'current_score', 'goal_score', 'status', 'rounds', 'round_dates', 'start_date',
        'per_round_fee', 'per_round_fees',
        'intake_status', 'intake_submitted_ip', 'intake_submitted_at',
    ];
    protected $casts = [
        'start_date' => 'date',
        'date_of_birth' => 'date',
        'per_round_fee' => 'decimal:2',
        'per_round_fees' => 'array',
        'rounds' => 'array',
        'round_dates' => 'array',
        'intake_submitted_at' => 'datetime',
        'ssn' => 'encrypted',
        'credit_monitoring_password' => 'encrypted',
        'credit_monitoring_security_answer' => 'encrypted',
        'cfpb_password' => 'encrypted',
    ];
    protected $hidden = ['ssn', 'credit_monitoring_password', 'credit_monitoring_security_answer', 'cfpb_password'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeForAdmin($query, $adminId)
    {
        return $query->whereHas('client', fn ($q) => $q->where('admin_id', $adminId));
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
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
        $start = $this->current_round_start_date;
        if (!$start) {
            return null;
        }
        return Carbon::parse($start)->addMonthNoOverflow()->toDateString();
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
