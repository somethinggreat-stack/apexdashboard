<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Money a business owner paid in advance; spent by applying it to unpaid rounds. */
class OwnerAdvance extends Model
{
    protected $fillable = [
        'client_id', 'amount', 'received_at', 'method', 'notes', 'created_by_admin_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'received_at' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
