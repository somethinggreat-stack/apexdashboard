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

    protected static function booted(): void
    {
        static::deleting(function (EndUser $user) {
            // Force-delete each document via Eloquent so its own deleting hook
            // (which removes the file from disk) fires. The DB cascade alone
            // would delete the rows but skip Eloquent events, leaving orphan files.
            $user->documents()->each(fn ($doc) => $doc->delete());

            foreach (['photo_id_path', 'proof_of_address_path', 'ssn_picture_path'] as $col) {
                if ($user->{$col} && Storage::disk('private')->exists($user->{$col})) {
                    Storage::disk('private')->delete($user->{$col});
                }
            }
        });
    }

    protected $fillable = [
        'client_id',
        'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'phone', 'date_of_birth',
        'ssn', 'ssn_picture_path', 'photo_id_path', 'proof_of_address_path',
        'credit_monitoring_name', 'credit_monitoring_username', 'credit_monitoring_password',
        'credit_monitoring_security_answer',
        'cfpb_email', 'cfpb_password',
        'current_score', 'goal_score', 'status', 'rounds', 'start_date',
        'intake_status', 'intake_submitted_ip', 'intake_submitted_at',
    ];
    protected $casts = [
        'start_date' => 'date',
        'date_of_birth' => 'date',
        'rounds' => 'array',
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

    private function identityUrl(string $type): ?string
    {
        $column = match ($type) {
            'photo_id' => 'photo_id_path',
            'proof_of_address' => 'proof_of_address_path',
            'ssn_picture' => 'ssn_picture_path',
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
