<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageAttachment extends Model
{
    protected $fillable = ['disk_path', 'original_name', 'mime', 'size', 'width', 'height'];

    protected $casts = ['size' => 'integer', 'width' => 'integer', 'height' => 'integer'];

    public function message()
    {
        return $this->belongsTo(TeamMessage::class, 'team_message_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/')
            || in_array(strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /** e.g. "2.4 MB" */
    public function humanSize(): string
    {
        $b = (int) $this->size;
        if ($b < 1024) return $b . ' B';
        $units = ['KB', 'MB', 'GB', 'TB'];
        $i = -1;
        do { $b /= 1024; $i++; } while ($b >= 1024 && $i < count($units) - 1);

        return round($b, $b >= 10 || $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}
