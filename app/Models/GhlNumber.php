<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One phone number in the shared pool VAs claim to collect one-time codes.
 *
 * Claiming is deliberately NOT done by setting the attribute and saving: see
 * claim(), which is a conditional update so two VAs pressing the button at the
 * same moment cannot both end up holding the same number.
 */
class GhlNumber extends Model
{
    protected $fillable = ['phone', 'ghl_sid', 'label', 'active'];

    protected $casts = [
        'claimed_at' => 'datetime',
        'active'     => 'boolean',
    ];

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'claimed_by_admin_id');
    }

    public function otps(): HasMany
    {
        return $this->hasMany(GhlOtp::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    /** How long a claim may stand before it is treated as abandoned. */
    public static function claimMinutes(): int
    {
        return max(1, (int) config('ghl_numbers.claim_minutes', 10));
    }

    /** A claim nobody released — the VA closed the laptop, or simply forgot. */
    public function claimExpired(): bool
    {
        return $this->claimed_at !== null
            && $this->claimed_at->lte(now()->subMinutes(self::claimMinutes()));
    }

    /** Free, or held by a claim that has run out of time. */
    public function isAvailable(): bool
    {
        return $this->claimed_by_admin_id === null || $this->claimExpired();
    }

    /**
     * Take this number, if it is actually free.
     *
     * One UPDATE with the condition in the WHERE clause, so the database decides
     * the winner. Reading first and then writing would let two VAs both see
     * "free" and both save — which is exactly the collision this page exists to
     * prevent.
     */
    public function claim(int $adminId): bool
    {
        $cutoff = now()->subMinutes(self::claimMinutes());

        $taken = static::whereKey($this->getKey())
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('claimed_by_admin_id')
                ->orWhere('claimed_at', '<=', $cutoff))
            ->update([
                'claimed_by_admin_id' => $adminId,
                'claimed_at'          => now(),
                'updated_at'          => now(),
            ]);

        if ($taken) {
            $this->refresh();
        }

        return $taken > 0;
    }

    /** Give it back. Only the holder may, which the caller checks. */
    public function release(): void
    {
        $this->forceFill(['claimed_by_admin_id' => null, 'claimed_at' => null])->save();
    }
}
