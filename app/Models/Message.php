<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    public const SENDER_ADMIN  = 'admin';
    public const SENDER_CLIENT = 'client';
    public const SENDER_SYSTEM = 'system';

    protected $fillable = [
        'client_id', 'sender_type', 'sender_id', 'body',
        'admin_read_at', 'client_read_at',
        'reply_to_id', 'pinned_at', 'starred_at', 'note',
    ];

    protected $casts = [
        'admin_read_at'  => 'datetime',
        'client_read_at' => 'datetime',
        'pinned_at'      => 'datetime',
        'starred_at'     => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function replyTo()
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function sender()
    {
        return match ($this->sender_type) {
            self::SENDER_ADMIN  => $this->belongsTo(Admin::class, 'sender_id'),
            self::SENDER_CLIENT => $this->belongsTo(Client::class, 'sender_id'),
            default             => null,
        };
    }

    public static function postSystem(int $clientId, string $body): self
    {
        return static::create([
            'client_id'   => $clientId,
            'sender_type' => self::SENDER_SYSTEM,
            'sender_id'   => null,
            'body'        => $body,
        ]);
    }

    /**
     * An automated message TO the business owner that reads as coming from the
     * team ("Apex Growth Team"). Sent as an admin message with no specific
     * sender — the owner never sees an individual VA. Used for the automatic
     * client-added, round-started and nightly-summary updates.
     */
    public static function postFromTeam(int $clientId, string $body): self
    {
        return static::create([
            'client_id'   => $clientId,
            'sender_type' => self::SENDER_ADMIN,
            'sender_id'   => null,
            'body'        => $body,
        ]);
    }

    public function isFromAdmin(): bool  { return $this->sender_type === self::SENDER_ADMIN; }
    public function isFromClient(): bool { return $this->sender_type === self::SENDER_CLIENT; }
    public function isSystem(): bool     { return $this->sender_type === self::SENDER_SYSTEM; }
}
