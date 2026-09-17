<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMessage extends Model
{
    protected $fillable = ['conversation_id', 'type', 'sender_id', 'client_uuid', 'recipient_id', 'reply_to_id', 'body', 'reactions', 'forwarded', 'read_at'];

    protected $casts = [
        'read_at'    => 'datetime',
        'edited_at'  => 'datetime',
        'deleted_at' => 'datetime',
        'pinned_at'  => 'datetime',
        'reactions'    => 'array',
        'forwarded'    => 'boolean',
        'mentions_all' => 'boolean',
    ];

    /** Admins explicitly @mentioned in this message. */
    public function mentionedAdmins()
    {
        return $this->belongsToMany(Admin::class, 'message_mentions', 'team_message_id', 'admin_id');
    }

    /** Admins who "deleted for me" (message hidden for them only). */
    public function hiddenFor()
    {
        return $this->belongsToMany(Admin::class, 'message_hides', 'team_message_id', 'admin_id');
    }

    public function deletedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'deleted_by');
    }

    /** Exclude messages a given admin has hidden ("delete for me"). */
    public function scopeVisibleTo($query, int $adminId)
    {
        return $query->whereDoesntHave('hiddenFor', fn ($q) => $q->where('admin_id', $adminId));
    }

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
}
