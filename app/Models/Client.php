<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Client extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'admin_id', 'business_name', 'email', 'password',
        'phone', 'monthly_fee', 'status',
        'intake_token', 'intake_logo_path', 'intake_display_name',
        'compensation_model', 'per_round_fee', 'hourly_rate',
        'weekly_hours_target', 'pay_cycle', 'pay_cycle_anchor',
    ];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'password'             => 'hashed',
        'monthly_fee'          => 'decimal:2',
        'per_round_fee'        => 'decimal:2',
        'hourly_rate'          => 'decimal:2',
        'pay_cycle_anchor'     => 'date',
        'weekly_hours_target'  => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->intake_token)) {
                $client->intake_token = self::generateIntakeToken();
            }
        });

        static::deleting(function (Client $client) {
            // Force-delete end_users via Eloquent so their deleting hooks fire
            // (which clean up document files and identity uploads). The FK
            // cascade would otherwise skip Eloquent events.
            $client->endUsers()->each(fn ($u) => $u->delete());

            // Clean up the intake logo when the BO is removed.
            if ($client->intake_logo_path && Storage::disk('public')->exists($client->intake_logo_path)) {
                Storage::disk('public')->delete($client->intake_logo_path);
            }
        });
    }

    public static function generateIntakeToken(): string
    {
        // 48 base62 chars ~ 286 bits of entropy, url-safe, no collisions in practice.
        return Str::random(48);
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
