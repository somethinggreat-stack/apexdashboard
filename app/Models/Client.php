<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Client extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'admin_id', 'business_name', 'email', 'password',
        'phone', 'monthly_fee', 'status', 'custom_lists_enabled', 'results_tracking',
        'access_revoked', 'access_revoked_message',
        'referred_by_chantal', 'is_commission_referrer', 'referrer_id',
        'intake_token', 'intake_logo_path', 'intake_display_name', 'intake_enabled',
        'intake_monitoring_provider', 'intake_monitoring_enroll_url',
        'intake_api_key', 'intake_external_url', 'intake_security_extra',
        'compensation_model', 'per_round_fee', 'hourly_rate',
        'weekly_hours_target', 'pay_cycle', 'pay_cycle_anchor',
        'round_cycle_days',
        'deleted_by_admin_id',
    ];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'password'             => 'hashed',
        'intake_enabled'       => 'boolean',
        'intake_security_extra' => 'boolean',
        'monthly_fee'          => 'decimal:2',
        'referred_by_chantal'  => 'boolean',
        'is_commission_referrer' => 'boolean',
        'custom_lists_enabled' => 'boolean',
        'results_tracking'     => 'boolean',
        'access_revoked'       => 'boolean',
        'per_round_fee'        => 'decimal:2',
        'hourly_rate'          => 'decimal:2',
        'pay_cycle_anchor'     => 'date',
        'weekly_hours_target'  => 'integer',
        'round_cycle_days'     => 'integer',
    ];

    /** Allowed round-cycle lengths (days) a business owner can run on. */
    public const ROUND_CYCLE_OPTIONS = [20, 30];

    /** Whether the negative-items results system (item entry, EOD + monthly reports) is on for this owner. */
    public function resultsTrackingEnabled(): bool
    {
        return (bool) $this->results_tracking;
    }

    /** This owner's round-cycle length in days (20 or 30); defaults to 30. */
    public function roundCycleDays(): int
    {
        $c = (int) ($this->round_cycle_days ?? 0);
        return in_array($c, self::ROUND_CYCLE_OPTIONS, true) ? $c : 30;
    }

    /** The referrer (another business owner) who referred this business owner. */
    public function referrer()
    {
        return $this->belongsTo(Client::class, 'referrer_id');
    }

    /** Business owners this one referred (only meaningful for a referrer). */
    public function referredBusinessOwners()
    {
        return $this->hasMany(Client::class, 'referrer_id');
    }

    /** Business owners flagged as commission referrers. */
    public function scopeReferrers($query)
    {
        return $query->where('is_commission_referrer', true);
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->intake_token)) {
                $client->intake_token = self::generateIntakeToken();
            }
            // New business owners get the intake link + New Clients by default.
            if ($client->intake_enabled === null) {
                $client->intake_enabled = true;
            }
        });

        static::deleting(function (Client $client) {
            // PERMANENT purge (10-day retention elapsed, or "delete forever").
            // Now the files really go: force-delete each end user via Eloquent so
            // its own deleting hook removes document files + identity uploads,
            // then drop the intake logo.
            if ($client->isForceDeleting()) {
                $client->endUsers()->withTrashed()->get()->each(fn ($u) => $u->forceDelete());

                if ($client->intake_logo_path && Storage::disk('public')->exists($client->intake_logo_path)) {
                    Storage::disk('public')->delete($client->intake_logo_path);
                }
                return;
            }

            // SOFT delete → Recycle Bin. Keep every row and file intact; just
            // send the owner's clients to the bin alongside it, tagged so a
            // restore of the owner brings exactly these back (and no others).
            $client->endUsers()->get()->each(function ($u) use ($client) {
                $u->forceFill([
                    'deleted_with_owner'  => true,
                    'deleted_by_admin_id' => $client->deleted_by_admin_id,
                ])->save();
                $u->delete();
            });
        });

        // Restoring a business owner brings back the clients that were binned
        // with it — but not any client the team had deleted on its own earlier.
        static::restored(function (Client $client) {
            $client->endUsers()->onlyTrashed()
                ->where('deleted_with_owner', true)
                ->get()
                ->each(function ($u) {
                    $u->restore();
                    $u->forceFill(['deleted_with_owner' => false])->save();
                });
        });
    }

    public static function generateIntakeToken(): string
    {
        // 48 base62 chars ~ 286 bits of entropy, url-safe, no collisions in practice.
        return Str::random(48);
    }

    /** Server-to-server intake API key (prefixed so it's recognisable in logs). */
    public static function generateApiKey(): string
    {
        return 'ags_' . Str::random(48);
    }

    /**
     * Payment roll-up for this business owner: money already collected ('done')
     * and money still owed ('pending'). Uses the same rules as the Payments
     * page. For hourly BOs, 'done' is the sum of recorded payouts (round-based
     * pending doesn't apply to them).
     */
    public function paymentTotals(): array
    {
        if (($this->compensation_model ?: 'per_round') === 'hourly') {
            $done = (float) TimePayout::where('client_id', $this->id)->sum('amount_paid');

            return ['done' => $done, 'pending' => 0.0];
        }

        $endUsers = EndUser::forClient($this->id)->billableList()->with('payments')->get();
        $endUsers->each(fn ($eu) => $eu->setRelation('client', $this));

        $done    = (float) ClientPayment::forClient($this->id)->sum('amount');
        $pending = (float) $endUsers->sum(fn ($eu) => $eu->pendingRoundTotal());

        return ['done' => $done, 'pending' => $pending];
    }

    public function intakeUrl(): string
    {
        return url('/intake/' . $this->intake_token);
    }

    public function intakeLogoUrl(): ?string
    {
        if ($this->intake_logo_path && Storage::disk('public')->exists($this->intake_logo_path)) {
            return Storage::disk('public')->url($this->intake_logo_path);
        }
        return null;
    }

    public function intakeDisplayName(): string
    {
        return $this->intake_display_name ?: $this->business_name;
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function deletedBy()
    {
        return $this->belongsTo(Admin::class, 'deleted_by_admin_id');
    }

    public function endUsers()
    {
        return $this->hasMany(EndUser::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function unreadCountForAdmin(): int
    {
        return $this->messages()
            ->whereIn('sender_type', [Message::SENDER_CLIENT, Message::SENDER_SYSTEM])
            ->whereNull('admin_read_at')
            ->count();
    }

    /**
     * All the sidebar badge counts for this business owner in ONE query instead
     * of five separate COUNT()s per page load. Conditional aggregation over the
     * (client_id, intake_status) / (client_id, held_at) indexes; soft-deleted
     * clients are excluded by EndUser's global scope. Unread messages stay a
     * separate small count (different table).
     *
     * @return array{pending:int, errors:int, new_errors_resolved:int, round_errors:int, resolved_by_client:int, hold:int, unread:int}
     */
    public function navCounts(): array
    {
        $row = EndUser::where('client_id', $this->id)
            ->selectRaw(
                "SUM(CASE WHEN held_at IS NULL AND intake_status = 'pending_review' THEN 1 ELSE 0 END) AS pending,
                 SUM(CASE WHEN held_at IS NULL AND intake_status = 'error' AND error_resolved_by_client_at IS NULL     THEN 1 ELSE 0 END) AS errors,
                 SUM(CASE WHEN held_at IS NULL AND intake_status = 'error' AND error_resolved_by_client_at IS NOT NULL THEN 1 ELSE 0 END) AS new_errors_resolved,
                 SUM(CASE WHEN held_at IS NULL AND intake_status = 'round_error' AND error_resolved_by_client_at IS NULL     THEN 1 ELSE 0 END) AS round_errors,
                 SUM(CASE WHEN held_at IS NULL AND intake_status = 'round_error' AND error_resolved_by_client_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved_by_client,
                 SUM(CASE WHEN held_at IS NOT NULL                                   THEN 1 ELSE 0 END) AS hold"
            )
            ->first();

        return [
            'pending'             => (int) ($row->pending ?? 0),
            'errors'              => (int) ($row->errors ?? 0),
            'new_errors_resolved' => (int) ($row->new_errors_resolved ?? 0),
            'round_errors'        => (int) ($row->round_errors ?? 0),
            'resolved_by_client'  => (int) ($row->resolved_by_client ?? 0),
            'hold'                => (int) ($row->hold ?? 0),
            'unread'              => $this->unreadCountForAdmin(),
        ];
    }

    public function unreadCountForClient(): int
    {
        return $this->messages()
            ->whereIn('sender_type', [Message::SENDER_ADMIN, Message::SENDER_SYSTEM])
            ->whereNull('client_read_at')
            ->count();
    }

    public function getMonthlyRevenueAttribute()
    {
        return $this->endUsers()->where('status', 'active')->count() * $this->monthly_fee;
    }

    public function scopeForAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function timePayouts()
    {
        return $this->hasMany(TimePayout::class);
    }
}
