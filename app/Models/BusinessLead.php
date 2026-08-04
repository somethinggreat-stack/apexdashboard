<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessLead extends Model
{
    protected $fillable = ['client_id', 'source', 'name', 'email', 'phone', 'notes', 'status'];

    /** Lead pipeline statuses (key => label). */
    public const STATUSES = [
        'new'          => 'New',
        'contacted'    => 'Contacted',
        'interested'   => 'Interested',
        'follow_up'    => 'Follow-Up',
        'converted'    => 'Converted',
        'lost'         => 'Lost',
    ];

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'New';
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
