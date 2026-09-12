<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['type', 'name', 'icon', 'data_owner_id', 'created_by', 'last_message_id', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages()
    {
        return $this->hasMany(TeamMessage::class);
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function isDm(): bool
    {
        return $this->type === 'dm';
    }

    /** The participant row for a given admin (relies on ->participants being loaded). */
    public function participantFor(int $adminId): ?ConversationParticipant
    {
        return $this->participants->firstWhere('admin_id', $adminId);
    }

    /** In a DM, the other admin (relies on ->participants.admin being loaded). */
    public function otherAdmin(int $meId): ?Admin
    {
        return optional($this->participants->firstWhere('admin_id', '!=', $meId))->admin;
    }

    /** Does this admin currently belong to the conversation? */
    public function hasMember(int $adminId): bool
    {
        return $this->participants->contains('admin_id', $adminId);
    }
}
