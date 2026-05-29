<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'end_user_id', 'round', 'amount', 'paid_at',
        'method', 'notes', 'created_by_admin_id',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'amount'  => 'decimal:2',
        'round'   => 'integer',
    ];

    public function endUser()
    {
        return $this->belongsTo(EndUser::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->whereHas('endUser', fn ($q) => $q->where('client_id', $clientId));
    }
}
