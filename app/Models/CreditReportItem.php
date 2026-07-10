<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditReportItem extends Model
{
    protected $fillable = [
        'credit_report_id', 'item_type', 'category', 'creditor_name', 'account_number',
        'detail', 'open_date', 'is_negative', 'auto_reason',
        'selected', 'dispute_instruction', 'dispute_reason', 'sort',
    ];

    protected $casts = [
        'detail'      => 'array',
        'open_date'   => 'date',
        'is_negative' => 'boolean',
        'selected'    => 'boolean',
    ];

    public function report()
    {
        return $this->belongsTo(CreditReport::class, 'credit_report_id');
    }
}
