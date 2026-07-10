<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditReport extends Model
{
    protected $fillable = [
        'uploaded_by_admin_id', 'consumer_name', 'report_date', 'source',
        'account_count', 'inquiry_count', 'negative_count',
    ];

    protected $casts = [
        'report_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(CreditReportItem::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_admin_id');
    }
}
