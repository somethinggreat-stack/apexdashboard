<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cPanel mailbox this dashboard created for the CFPB workflow.
 *
 * `deleted_at` is set by hand rather than by SoftDeletes: a deleted mailbox is
 * gone from cPanel, and the row survives only as an audit record. Treating it
 * as a soft-deleted model would invite withTrashed()->restore(), which would
 * hand back an address that no longer exists.
 */
class Mailbox extends Model
{
    protected $fillable = [
        'admin_id', 'created_by_admin_id', 'end_user_id',
        'local_part', 'domain', 'address', 'password', 'quota_mb',
    ];

    // The VA reads this to sign into webmail, so it has to be recoverable —
    // but never in plain text in the database. Same cast as ssn / cfpb_password.
    protected $casts = [
        'password'   => 'encrypted',
        'deleted_at' => 'datetime',
        'quota_mb'   => 'integer',
    ];

    // Never let the password ride along in a toArray()/toJson() anywhere.
    protected $hidden = ['password'];

    /** Mailboxes that still exist on cPanel. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNull('deleted_at');
    }

    /** Everything belonging to one organisation (admins.dataOwnerId()). */
    public function scopeForOrg(Builder $q, int $adminId): Builder
    {
        return $q->where('admin_id', $adminId);
    }

    public function endUser(): BelongsTo
    {
        return $this->belongsTo(EndUser::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}
