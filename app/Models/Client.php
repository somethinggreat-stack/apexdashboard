<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Client extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'admin_id', 'business_name', 'email', 'password',
        'phone', 'monthly_fee', 'status',
    ];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed', 'monthly_fee' => 'decimal:2'];

    protected static function booted(): void
    {
        static::deleting(function (Client $client) {
            // Force-delete end_users via Eloquent so their deleting hooks fire
            // (which clean up document files and identity uploads). The FK
            // cascade would otherwise skip Eloquent events.
            $client->endUsers()->each(fn ($u) => $u->delete());
        });
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
}
