<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * One audit-trail entry for an end user (client). Records list moves, holds,
 * profile edits and result changes with the acting admin/VA + timestamp.
 * Shown only on the super-admin Activity tab.
 */
class ClientEvent extends Model
{
    protected $fillable = ['end_user_id', 'admin_id', 'event', 'description'];

    public function endUser()
    {
        return $this->belongsTo(EndUser::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /** Record an event for a client, stamped with the currently logged-in admin. */
    public static function log(EndUser $endUser, string $event, string $description): void
    {
        static::create([
            'end_user_id' => $endUser->id,
            'admin_id'    => Auth::guard('admin')->id(),
            'event'       => $event,
            'description' => $description,
        ]);
    }

    /** Human label for the bucket an intake_status/hold state represents. */
    public static function bucketLabel(?string $intakeStatus, bool $held): string
    {
        if ($held) {
            return 'Hold / Pause';
        }
        return match ($intakeStatus) {
            'pending_review' => 'New Clients',
            'error'          => 'New Client Errors',
            'round_error'    => 'Round Errors',
            'done'           => 'Clients',
            default          => 'In Progress',
        };
    }
}
