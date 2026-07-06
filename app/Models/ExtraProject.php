<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraProject extends Model
{
    protected $fillable = [
        'admin_id', 'type', 'client_name', 'link', 'whatsapp',
        'amount', 'paid', 'status', 'notes', 'created_by_admin_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid'   => 'decimal:2',
    ];

    public function scopeForAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
