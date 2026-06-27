<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProspectLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'name',
        'whatsapp',
        'instagram',
        'website',
        'hot_lead',
    ];

    protected $casts = [
        'hot_lead' => 'boolean',
    ];

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
