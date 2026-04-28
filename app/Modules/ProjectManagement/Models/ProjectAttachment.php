<?php

namespace App\Modules\ProjectManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectAttachment extends Model
{
    protected $fillable = [
        'project_id',
        'uploader_id',
        'file_name',
        'disk',
        'path',
        'file_size',
        'mime_type',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function deleteFile(): void
    {
        if ($this->path) {
            Storage::disk($this->disk)->delete($this->path);
        }
    }
}
