<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMessage extends Model
{
    protected $fillable = ['sender_id', 'recipient_id', 'body', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function sender()
    {
        return $this->belongsTo(Admin::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(Admin::class, 'recipient_id');
    }

    /** Messages exchanged between two admins, in either direction. */
    public function scopeBetween($query, int $a, int $b)
    {
        return $query->where(function ($w) use ($a, $b) {
            $w->where(fn ($q) => $q->where('sender_id', $a)->where('recipient_id', $b))
              ->orWhere(fn ($q) => $q->where('sender_id', $b)->where('recipient_id', $a));
        });
    }
}
