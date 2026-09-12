<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMessage extends Model
{
    protected $fillable = ['conversation_id', 'type', 'sender_id', 'recipient_id', 'reply_to_id', 'body', 'reactions', 'forwarded', 'read_at'];

    protected $casts = [
        'read_at'    => 'datetime',
        'deleted_at' => 'datetime',
        'pinned_at'  => 'datetime',
        'reactions'  => 'array',
        'forwarded'  => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    public function sender()
    {
        return $this->belongsTo(Admin::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(Admin::class, 'recipient_id');
    }

    /** The message this one is a reply to (quoted). */
    public function replyTo()
    {
        return $this->belongsTo(TeamMessage::class, 'reply_to_id');
    }

    /** Files/images sent with this message (private disk). */
    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class, 'team_message_id');
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
