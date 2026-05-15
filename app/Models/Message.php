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
    ];

    protected $casts = [
        'admin_read_at'  => 'datetime',
        'client_read_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
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

    public function isFromAdmin(): bool  { return $this->sender_type === self::SENDER_ADMIN; }
    public function isFromClient(): bool { return $this->sender_type === self::SENDER_CLIENT; }
    public function isSystem(): bool     { return $this->sender_type === self::SENDER_SYSTEM; }
}
