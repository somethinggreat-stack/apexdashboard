<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionPayout extends Model
{
    protected $fillable = [
        'referrer_name', 'referrer_id', 'amount', 'paid_at', 'note', 'created_by_admin_id',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'amount'  => 'decimal:2',
    ];

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function referrer()
    {
        return $this->belongsTo(Client::class, 'referrer_id');
    }
}
