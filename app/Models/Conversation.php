<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['type', 'name', 'icon', 'data_owner_id', 'created_by', 'last_message_id', 'last_message_at', 'dm_key'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Hand group-admin rights over before an admin account disappears. Deleting an account
     * cascades its participant rows away, so a group whose ONLY admin was that account would
     * be left with members but nobody who can add, remove or rename — this promotes the
     * longest-standing remaining member first.
     */
    public static function handOverGroupAdminRoles(int $leavingAdminId): void
    {
        $groups = static::where('type', 'group')
            ->whereHas('participants', fn ($q) => $q->where('admin_id', $leavingAdminId)->where('role', 'admin'))
            ->with('participants')
            ->get();

        foreach ($groups as $group) {
            $others = $group->participants->where('admin_id', '!=', $leavingAdminId);
            if ($others->isEmpty() || $others->contains('role', 'admin')) {
                continue;   // nobody left to promote, or another admin already holds it
            }
            $others->sortBy('id')->first()?->update(['role' => 'admin']);
        }
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
}
