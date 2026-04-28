<?php

namespace App\Modules\NotificationCenter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    public const GROUP_TASKS = 'tasks';

    public const GROUP_PROJECTS = 'projects';

    public const GROUP_EXPENSES = 'expenses';

    public const GROUP_MENTIONS = 'mentions';

    public const GROUP_DEADLINES = 'deadlines';

    public const GROUP_SYSTEM = 'system';

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'group',
        'title',
        'body',
        'icon',
        'tone',
        'link',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
