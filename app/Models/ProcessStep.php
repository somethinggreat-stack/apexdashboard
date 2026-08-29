<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'end_user_id', 'round', 'week', 'step_type', 'step_date',
        'experian_accounts_disputed', 'experian_inquiries_disputed',
        'transunion_accounts_disputed', 'transunion_inquiries_disputed',
        'equifax_accounts_disputed', 'equifax_inquiries_disputed',
        'previous_credit_score', 'credit_score_now',
        // Round outcome metrics (pull report / record deletions)
        'total_deletions', 'updated_to_positive', 'updated_to_negative', 'items_added',
        'experian_score_before', 'experian_score_now',
        'transunion_score_before', 'transunion_score_now',
        'equifax_score_before', 'equifax_score_now',
        'created_by_admin_id',
    ];
    protected $casts = [
        'step_date' => 'date',
        'round' => 'integer',
        'week' => 'integer',
    ];

    public function endUser()
    {
        return $this->belongsTo(EndUser::class);
    }

    public function scopeForAdmin($query, $adminId)
    {
        return $query->whereHas('endUser.client', fn ($q) => $q->where('admin_id', $adminId));
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->whereHas('endUser', fn ($q) => $q->where('client_id', $clientId));
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function getDayNumberAttribute()
    {
        $startDate = $this->endUser?->start_date;
        if (!$startDate) {
            return null;
        }
        return (int) $startDate->diffInDays($this->step_date) + 1;
    }

    public static function rounds(): array
    {
        $out = [];
        foreach (range(1, count(EndUser::ROUND_OPTIONS)) as $n) {
            $out[$n] = "Round {$n}";
        }
        return $out;
    }

    /** Week labels for a cycle: 30-day → 4 weeks, 20-day → 3 weeks. */
    public static function weeks(int $cycleDays = 30): array
    {
        $out = [];
        foreach (range(1, self::weekCount($cycleDays)) as $w) {
            $out[$w] = "Week {$w}";
        }
        return $out;
    }

    /** Number of process weeks in a round for the given cycle. */
    public static function weekCount(int $cycleDays = 30): int
    {
        return $cycleDays <= 20 ? 3 : 4;
    }

    /**
     * The same nine steps, grouped into weeks — but the grouping compresses for
     * short cycles. A 30-day round runs 4 weeks (front-loaded week 1); a 20-day
     * round runs 3 weeks: all disputing work in weeks 1–2, then week 3 is the
     * aggressive follow-ups + pull report + record deletions closeout.
     */
    public static function stepTypesByWeek(int $cycleDays = 30): array
    {
        if ($cycleDays <= 20) {
            return [
                1 => [
                    'ex_tu_eq_letters_generated' => 'EX, TU, EQ Letter Generated',
                    'phone_call_disputes' => 'Phone Call Disputes (EX & TU)',
                    'ftc_and_freezes' => 'FTC + Freezes (All Small Bureaus)',
                    'cfpb_3b_and_innovis' => 'CFPB (All 3B) & Innovis',
                    'experian_upload' => 'Experian Upload',
                ],
                2 => [
                    'tu_ex_call_followups' => 'TransUnion & Experian Call Follow-Ups',
                ],
                3 => [
                    'aggressive_bureau_followup' => 'Call Bureaus Follow-Up Aggressively',
                    'pull_latest_report' => 'Pull Latest Report',
                    'record_deletions' => 'Record Deletions / Update Deletions',
                ],
            ];
        }

        return [
            1 => [
                'ex_tu_eq_letters_generated' => 'EX, TU, EQ Letter Generated',
                'phone_call_disputes' => 'Phone Call Disputes (EX & TU)',
                'ftc_and_freezes' => 'FTC + Freezes (All Small Bureaus)',
                'cfpb_3b_and_innovis' => 'CFPB (All 3B) & Innovis',
                'experian_upload' => 'Experian Upload',
            ],
            2 => [
                'tu_ex_call_followups' => 'TransUnion & Experian Call Follow-Ups',
            ],
            3 => [
                'aggressive_bureau_followup' => 'Call Bureaus Follow-Up Aggressively',
            ],
            4 => [
                'pull_latest_report' => 'Pull Latest Report',
                'record_deletions' => 'Record Deletions / Update Deletions',
            ],
        ];
    }

    public static function allStepTypes(): array
    {
        // All nine step types (from the 30-day grouping, which contains them all).
        return array_merge(...array_values(self::stepTypesByWeek(30)));
    }

    public static function stepTypeLabel(?string $key): ?string
    {
        return self::allStepTypes()[$key] ?? $key;
    }

    public function getStepTypeLabelAttribute(): ?string
    {
        return self::stepTypeLabel($this->step_type);
    }

    /**
     * Human-readable paragraph describing what was done in this step.
     * Used by the client Status Report tab.
     */
    public function getNarrativeAttribute(): string
    {
        $round = $this->round;
        $date  = $this->step_date?->format('M d, Y') ?? 'an undated entry';

        $ex = (int) $this->experian_accounts_disputed;
        $tu = (int) $this->transunion_accounts_disputed;
        $eq = (int) $this->equifax_accounts_disputed;
        $exInq = (int) $this->experian_inquiries_disputed;
        $tuInq = (int) $this->transunion_inquiries_disputed;
        $eqInq = (int) $this->equifax_inquiries_disputed;

        $bureauList = function () use ($ex, $tu, $eq) {
            $parts = [];
            if ($ex) $parts[] = "Experian ({$ex})";
            if ($tu) $parts[] = "TransUnion ({$tu})";
            if ($eq) $parts[] = "Equifax ({$eq})";
            return $parts ? implode(', ', $parts) : null;
        };

        $inquiryNote = function () use ($exInq, $tuInq, $eqInq) {
            $total = $exInq + $tuInq + $eqInq;
            if (!$total) return '';
            $parts = [];
            if ($exInq) $parts[] = "{$exInq} on Experian";
            if ($tuInq) $parts[] = "{$tuInq} on TransUnion";
            if ($eqInq) $parts[] = "{$eqInq} on Equifax";
            return ' Inquiry disputes were also included (' . implode(', ', $parts) . ').';
        };

        $scoreNote = '';
        if ($this->credit_score_now && $this->previous_credit_score) {
            $delta = $this->credit_score_now - $this->previous_credit_score;
            $sign  = $delta >= 0 ? '+' : '';
            $scoreNote = " Score movement on this milestone: {$this->previous_credit_score} → {$this->credit_score_now} ({$sign}{$delta}).";
        } elseif ($this->credit_score_now) {
            $scoreNote = " Score recorded at this milestone: {$this->credit_score_now}.";
        }

        switch ($this->step_type) {
            case 'ex_tu_eq_letters_generated':
                $list = $bureauList() ?? 'all three major bureaus';
                return "On {$date}, Round {$round} certified dispute letters were prepared and dispatched — {$list}. This opens the 30-day response window for each bureau."
                    . $inquiryNote();

            case 'phone_call_disputes':
                return "On {$date}, follow-up phone disputes were placed with Experian and TransUnion to confirm receipt of the Round {$round} letters and capture rep names plus ticket numbers in the file for audit.";

            case 'ftc_and_freezes':
                return "On {$date}, FTC complaint documentation was prepared in support of the Round {$round} dispute trail, and security-freeze requests were submitted to the small bureaus (ChexSystems, ARS, Clarity, SageStream, LexisNexis).";

            case 'cfpb_3b_and_innovis':
                return "On {$date}, CFPB complaints were filed against the three major bureaus where the file profile supported it, and a separate Innovis dispute package was submitted for Round {$round}.";

            case 'experian_upload':
                return "On {$date}, Round {$round} disputes were uploaded directly to Experian's online portal so the request runs in parallel with the certified mail.";

            case 'tu_ex_call_followups':
                return "On {$date}, Week 2 follow-up calls were placed to TransUnion and Experian to push on the still-open Round {$round} investigations and document the bureau response so far.";

            case 'aggressive_bureau_followup':
                return "On {$date}, Week 3 escalation calls were placed across all three major bureaus, citing failure-to-investigate language where appropriate to drive action before the 30-day window closes.";

            case 'pull_latest_report':
                return "On {$date}, the latest credit report was pulled to evaluate Round {$round} outcomes against the prior baseline." . $scoreNote;

            case 'record_deletions':
                $list = $bureauList();
                $deletionLine = $list
                    ? " Deletions logged against the file this round: {$list}."
                    : ' Bureau responses for this round have been recorded against the file.';
                return "On {$date}, the Round {$round} response window closed and outcomes were recorded." . $deletionLine . $scoreNote;

            default:
                $label = $this->step_type_label ?? 'a process step';
                return "On {$date}, {$label} was completed for Round {$round}, Week {$this->week}.";
        }
    }
}
