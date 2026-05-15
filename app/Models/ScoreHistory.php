<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoreHistory extends Model
{
    use HasFactory;

    protected $table = 'score_history';
    protected $fillable = ['end_user_id', 'score', 'bureau', 'recorded_at'];
    protected $casts = ['recorded_at' => 'date'];

    public function endUser()
    {
        return $this->belongsTo(EndUser::class);
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
