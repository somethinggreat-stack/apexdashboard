<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProspectLead extends Model
{
    use HasFactory;

    /** Lead channels: key => human label. */
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
        'instagram',
        'website',
        'hot_lead',
    ];

    protected $casts = [
        'hot_lead' => 'boolean',
    ];

    /** Normalized Instagram key for duplicate matching (lowercase, no query/slash). */
    public function getInstagramKeyAttribute(): ?string
    {
        $ig = strtolower(trim((string) $this->instagram));
        $ig = preg_replace('/\?.*$/', '', $ig);   // strip ?hl=en etc.
        $ig = rtrim($ig, '/');
        return $ig !== '' ? $ig : null;
    }

    public function scopeForAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    /** Digits-only WhatsApp number for building a wa.me click-to-chat link. */
    public function getWhatsappDigitsAttribute(): ?string
    {
        if (!$this->whatsapp) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $this->whatsapp);
        return $digits !== '' ? $digits : null;
    }

    /** Ensure a link has a scheme so href works for bare handles/domains. */
    public function linkHref(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        return preg_match('#^https?://#i', $value) ? $value : 'https://' . ltrim($value, '/');
    }
}
