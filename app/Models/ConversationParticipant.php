<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationParticipant extends Model
{
    protected $fillable = ['conversation_id', 'admin_id', 'role', 'last_read_message_id', 'muted', 'favorite', 'joined_at'];

    protected $casts = [
        'muted'                => 'boolean',
        'favorite'             => 'boolean',
        'joined_at'            => 'datetime',
        'last_read_message_id' => 'integer',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
