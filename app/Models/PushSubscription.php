<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $fillable = [
        'admin_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth',
        'content_encoding', 'user_agent', 'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
