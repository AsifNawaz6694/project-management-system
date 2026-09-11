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

    public const GROUP_MENTIONS = 'mentions';

    public const GROUP_DEADLINES = 'deadlines';

    public const GROUP_SYSTEM = 'system';

    /** Every group, for filter controls. */
    public const GROUPS = [
        self::GROUP_TASKS,
        self::GROUP_PROJECTS,
        self::GROUP_MENTIONS,
        self::GROUP_DEADLINES,
        self::GROUP_SYSTEM,
    ];

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'entity_key',
        'event_count',
        'group',
        'title',
        'body',
        'icon',
        'tone',
        'link',
        'data',
        'read_at',
        'emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'emailed_at' => 'datetime',
            'event_count' => 'integer',
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
