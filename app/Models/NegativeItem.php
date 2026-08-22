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
        'end_user_id', 'name', 'detail', 'category', 'goal', 'bureau',
        'status', 'opened_on', 'resolved_at', 'resolved_round', 'created_by_admin_id',
    ];

    protected $casts = [
        'opened_on'   => 'date',
        'resolved_at' => 'date',
    ];

    public const CATEGORIES = [
        'negative_account'     => 'Negative Account',
        'inquiry'              => 'Inquiry',
        'bankruptcy'           => 'Bankruptcy',
        // Personal Information covers addresses, employers, names — one free-text box.
        'personal_information' => 'Personal Information',
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

    /**
     * The allowed goal for a category. Only a Negative Account can be "updated to
     * positive"; every other item type (inquiry, bankruptcy, personal info,
     * employers) can only be deleted — so their goal is forced to 'delete'.
     */
    public static function goalForCategory(string $category, string $goal): string
    {
        if ($category !== 'negative_account') {
            return 'delete';
        }
        return array_key_exists($goal, self::GOALS) ? $goal : 'delete';
    }

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

    /** What the `detail` field means for this item's category (empty when N/A). */
    public function detailLabel(): string
    {
        return self::detailLabelFor($this->category);
    }

    public static function detailLabelFor(string $category): string
    {
        return match ($category) {
            'negative_account' => 'Acct #',
            'inquiry'          => 'Date',
            'bankruptcy'       => 'Ref #',
            default            => '',   // personal_information: no detail
        };
    }

    /** Keep the detail only for categories that have one; drop it otherwise. */
    public static function detailForCategory(string $category, ?string $detail): ?string
    {
        if (self::detailLabelFor($category) === '') {
            return null;
        }
        $detail = trim((string) $detail);
        return $detail !== '' ? $detail : null;
    }

    /** "Name — detail" for reports/exports (e.g. "SYNCHRONY BANK — 224825XX"). */
    public function displayName(): string
    {
        $detail = trim((string) $this->detail);
        return $detail !== '' ? "{$this->name} — {$detail}" : $this->name;
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
