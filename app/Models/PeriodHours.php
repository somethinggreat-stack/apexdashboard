<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodHours extends Model
{
    use HasFactory;

    protected $table = 'period_hours';

    protected $fillable = [
        'client_id', 'period_start', 'period_end', 'hours',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'hours'        => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
