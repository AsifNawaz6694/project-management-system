<?php

namespace App\Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskComment extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'parent_id',
        'body',
        'is_internal',
        'mentions',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'mentions' => 'array',
            'edited_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CommentRevision::class)->orderByDesc('created_at');
    }
}
