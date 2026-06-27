<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prospect extends Model
{
    use HasFactory;

    /** Pipeline stages: key => human label. Keep keys stable (stored in DB). */
    public const STATUSES = [
        'new'           => 'New',
        'contacted'     => 'Contacted',
        'in_discussion' => 'In Discussion',
        'follow_up'     => 'Follow-Up',
        'interested'    => 'Interested',
        'won'           => 'Signed',
        'lost'          => 'Not Interested',
    ];

    /** Pipeline channels: key => human label. */
    public const CHANNELS = [
        'whatsapp'  => 'WhatsApp',
        'phone'     => 'Phone',
        'instagram' => 'Instagram',
    ];

    protected $fillable = [
        'admin_id',
        'channel',
        'name',
        'whatsapp',
        'outreach_whatsapp',
        'instagram',
        'referred_by',
        'status',
        'notes',
    ];

    /** Digits-only client WhatsApp number for a wa.me click-to-chat link. */
    public function getWhatsappDigitsAttribute(): ?string
    {
        return $this->digitsOf($this->whatsapp);
    }

    /** Digits-only outreach WhatsApp number for a wa.me click-to-chat link. */
    public function getOutreachWhatsappDigitsAttribute(): ?string
    {
        return $this->digitsOf($this->outreach_whatsapp);
    }

    private function digitsOf(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);
        return $digits !== '' ? $digits : null;
    }

    public function scopeForAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
