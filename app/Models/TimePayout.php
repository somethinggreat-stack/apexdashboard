<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimePayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'period_start', 'period_end',
        'hours_in_period', 'amount_paid', 'paid_at',
        'method', 'notes', 'created_by_admin_id',
    ];

    protected $casts = [
        'period_start'    => 'date',
        'period_end'      => 'date',
        'paid_at'         => 'date',
        'hours_in_period' => 'decimal:2',
        'amount_paid'     => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
