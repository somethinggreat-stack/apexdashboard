<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code that arrived on a pool number.
 *
 * This row OUTLIVES the message. Once the conversation is deleted from
 * GoHighLevel it is the only evidence the code ever existed — which number it
 * came to, who was holding that number, and who copied it. Nothing in the app
 * deletes these.
 *
 * `body` is always kept alongside `code`, because a code is something we read
 * out of the text with a pattern, and a pattern can be wrong. The VA must
 * always be able to see what actually arrived.
 */
class GhlOtp extends Model
{
    protected $fillable = [
        'ghl_number_id', 'claimed_by_admin_id', 'from_number', 'code', 'body',
        'received_at', 'conversation_id', 'message_id',
    ];

    protected $casts = [
        'received_at'      => 'datetime',
        'copied_at'        => 'datetime',
        'deleted_from_ghl' => 'boolean',
    ];

    public function number(): BelongsTo
    {
        return $this->belongsTo(GhlNumber::class, 'ghl_number_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'claimed_by_admin_id');
    }

    /**
     * The code inside a message, or null when nothing looks like one.
     *
     * Deliberately conservative: a run of 4-8 digits that is not part of a
     * longer number. Returning null is fine — the page shows the whole message
     * either way, so a miss costs a VA one extra glance, while a wrong guess
     * would have them typing the wrong digits into someone's account.
     */
    public static function extractCode(?string $body): ?string
    {
        $body = (string) $body;

        // Prefer a code that announces itself, which is how nearly every
        // provider writes them.
        if (preg_match('/(?:code|otp|pin|passcode|verification)\D{0,20}(\d{4,8})\b/i', $body, $m)) {
            return $m[1];
        }

        // Otherwise the first standalone 4-8 digit run.
        if (preg_match('/(?<!\d)(\d{4,8})(?!\d)/', $body, $m)) {
            return $m[1];
        }

        return null;
    }
}
