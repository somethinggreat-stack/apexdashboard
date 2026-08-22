<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One negative account/item on a client's credit file. See the migration for
 * the field meanings. The monthly results report computes every count purely
 * from opened_on, resolved_at and status — nothing is stored per-month.
 */
class NegativeItem extends Model
{
    protected $fillable = [
        'end_user_id', 'name', 'category', 'goal', 'bureau',
        'status', 'opened_on', 'resolved_at', 'resolved_round', 'created_by_admin_id',
    ];

    protected $casts = [
        'opened_on'   => 'date',
        'resolved_at' => 'date',
    ];

    public const CATEGORIES = [
        'negative_account' => 'Negative Account',
        'inquiry'          => 'Inquiry',
        'bankruptcy'       => 'Bankruptcy',
    ];

    public const GOALS = [
        'delete' => 'Delete',
        'update' => 'Update to positive',
    ];

    public const BUREAUS = [
        'all'        => 'All Bureau',
        'experian'   => 'Experian',
        'transunion' => 'TransUnion',
        'equifax'    => 'Equifax',
    ];

    /** Terminal statuses per goal: a delete-item is "deleted", an update-item is "updated". */
    public const STATUSES = ['reporting', 'deleted', 'updated'];

    public function endUser()
    {
        return $this->belongsTo(EndUser::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    /** Still on the file (not yet deleted/updated). */
    public function scopeReporting($query)
    {
        return $query->where('status', 'reporting');
    }

    /** Resolved — deleted or updated to positive. */
    public function scopeResolved($query)
    {
        return $query->whereIn('status', ['deleted', 'updated']);
    }

    public function isReporting(): bool
    {
        return $this->status === 'reporting';
    }

    public function goalLabel(): string
    {
        return self::GOALS[$this->goal] ?? ucfirst($this->goal);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function bureauLabel(): string
    {
        return self::BUREAUS[$this->bureau] ?? ucfirst((string) $this->bureau);
    }

    /** Human status: Reporting / Deleted / Updated to positive. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'deleted' => 'Deleted',
            'updated' => 'Updated to positive',
            default   => 'Reporting',
        };
    }
}
