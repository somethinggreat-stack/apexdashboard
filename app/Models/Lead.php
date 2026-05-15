<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    public const TYPE_POPUP   = 'popup';
    public const TYPE_CONTACT = 'contact';

    protected $fillable = [
        'type',
        'first_name',
        'last_name',
        'email',
        'phone',
        'score',
        'goal',
        'urgency',
        'subject',
        'message',
        'details',
        'source_page',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
