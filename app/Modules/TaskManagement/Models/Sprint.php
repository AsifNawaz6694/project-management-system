<?php

namespace App\Modules\TaskManagement\Models;

use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A time-boxed slice of a project's backlog.
 *
 * A sprint moves through exactly three states. Only one sprint per project may
 * be active at a time — `SprintService` enforces that, not the schema, because
 * the constraint is "at most one" rather than "exactly one".
 */
class Sprint extends Model
{
    public const STATE_FUTURE = 'future';

    public const STATE_ACTIVE = 'active';

    public const STATE_COMPLETED = 'completed';

    public const STATES = [self::STATE_FUTURE, self::STATE_ACTIVE, self::STATE_COMPLETED];

    protected $fillable = [
        'project_id',
        'name',
        'goal',
        'state',
        'starts_at',
        'ends_at',
        'started_at',
        'completed_at',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SprintSnapshot::class)->orderBy('snapshot_date');
    }

    public function isActive(): bool
    {
        return $this->state === self::STATE_ACTIVE;
    }
}
