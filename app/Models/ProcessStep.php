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
        return [1 => 'Round 1', 2 => 'Round 2', 3 => 'Round 3', 4 => 'Round 4'];
    }

    public static function weeks(): array
    {
        return [1 => 'Week 1', 2 => 'Week 2', 3 => 'Week 3', 4 => 'Week 4'];
    }

    public static function stepTypesByWeek(): array
    {
        return [
            1 => [
                'ex_tu_eq_letters_generated' => 'Step 1: EX, TU, EQ Letter Generated',
                'phone_call_disputes' => 'Step 2: Phone Call Disputes (EX & TU)',
                'ftc_and_freezes' => 'Step 3: FTC + Freezes (All Small Bureaus)',
                'cfpb_3b_and_innovis' => 'Step 4: CFPB (All 3B) & Innovis',
                'letterstream' => 'Step 5: LetterStream (if required)',
                'experian_upload' => 'Step 6: Experian Upload',
            ],
            2 => [
                'tu_ex_call_followups' => 'Step 1: TransUnion & Experian Call Follow-Ups',
            ],
            3 => [
                'aggressive_bureau_followup' => 'Step 1: Call Bureaus Follow-Up Aggressively',
            ],
            4 => [
                'pull_latest_report' => 'Step 1: Pull Latest Report',
                'record_deletions' => 'Step 2: Record Deletions / Update Deletions',
            ],
        ];
    }

    public static function allStepTypes(): array
    {
        return array_merge(...array_values(self::stepTypesByWeek()));
    }

    public static function stepTypeLabel(?string $key): ?string
    {
        return self::allStepTypes()[$key] ?? $key;
    }

    public function getStepTypeLabelAttribute(): ?string
    {
        return self::stepTypeLabel($this->step_type);
    }
}
