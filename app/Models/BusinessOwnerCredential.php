<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessOwnerCredential extends Model
{
    protected $fillable = [
        'client_id', 'software_name', 'login_url', 'username', 'email', 'password', 'notes', 'sort_order',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
