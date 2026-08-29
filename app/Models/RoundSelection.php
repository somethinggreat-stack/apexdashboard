<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One record per round a client was moved onto — written centrally from
 * EndUser when the rounds array grows. Powers the Daily Task and Tasks View,
 * which credit the VA who SELECTED the round, not whoever later logs a step.
 */
class RoundSelection extends Model
{
    public $timestamps = false;

    protected $fillable = ['end_user_id', 'round', 'admin_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (RoundSelection $sel) {
            $sel->created_at ??= now();
        });
    }

    public function endUser()
    {
        return $this->belongsTo(EndUser::class);
    }

    public function selectedBy()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
