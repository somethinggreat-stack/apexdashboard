<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = ['end_user_id', 'note_text', 'created_by_admin_id'];

    public function endUser()
    {
        return $this->belongsTo(EndUser::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function scopeForAdmin($query, $adminId)
    {
        return $query->whereHas('endUser.client', fn ($q) => $q->where('admin_id', $adminId));
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->whereHas('endUser', fn ($q) => $q->where('client_id', $clientId));
    }
}
