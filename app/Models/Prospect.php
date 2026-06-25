<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prospect extends Model
{
    use HasFactory;

    /** Pipeline stages: key => human label. Keep keys stable (stored in DB). */
    public const STATUSES = [
        'new'           => 'New',
        'contacted'     => 'Contacted',
        'in_discussion' => 'In Discussion',
        'follow_up'     => 'Follow-Up',
        'won'           => 'Signed',
        'lost'          => 'Not Interested',
    ];

    protected $fillable = [
        'admin_id',
        'name',
        'phone',
        'status',
        'notes',
    ];

    public function scopeForAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
