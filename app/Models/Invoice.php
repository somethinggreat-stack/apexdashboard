<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'invoice_number', 'invoice_date',
        'items', 'total', 'created_by_admin_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'items'        => 'array',
        'total'        => 'decimal:2',
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
