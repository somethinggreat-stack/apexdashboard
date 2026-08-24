<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'end_user_id', 'uploaded_by_admin_id', 'process_step_id',
        'file_name', 'file_type', 'file_path', 'category', 'description',
    ];

    public function uploadedBy()
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_admin_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Document $doc) {
            if ($doc->file_path && Storage::disk('private')->exists($doc->file_path)) {
                Storage::disk('private')->delete($doc->file_path);
            }
        });
    }

    public function endUser()
    {
        return $this->belongsTo(EndUser::class);
    }

    public function processStep()
    {
        return $this->belongsTo(ProcessStep::class);
    }

    public function scopeForAdmin($query, $adminId)
    {
        return $query->whereHas('endUser.client', fn ($q) => $q->where('admin_id', $adminId));
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->whereHas('endUser', fn ($q) => $q->where('client_id', $clientId));
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->id) {
            return null;
        }
        if (Auth::guard('admin')->check()) {
            return route('admin.files.document', $this);
        }
        if (Auth::guard('client')->check()) {
            return route('client.files.document', $this);
        }
        return null;
    }
}
