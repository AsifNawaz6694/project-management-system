<?php

namespace App\Modules\Automation\Models;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "When <trigger>, if <conditions>, then <actions>."
 *
 * A rule with no project runs workspace-wide; one with a project is scoped to
 * it. Conditions are ANDed — an empty set always matches.
 */
class AutomationRule extends Model
{
    public const TRIGGER_CREATED = 'task.created';

    public const TRIGGER_STATUS_CHANGED = 'task.status_changed';

    public const TRIGGER_ASSIGNED = 'task.assigned';

    public const TRIGGER_UPDATED = 'task.updated';

    public const TRIGGER_COMMENTED = 'task.commented';

    public const TRIGGERS = [
        self::TRIGGER_CREATED,
        self::TRIGGER_STATUS_CHANGED,
        self::TRIGGER_ASSIGNED,
        self::TRIGGER_UPDATED,
        self::TRIGGER_COMMENTED,
    ];

    protected $fillable = [
        'name',
        'description',
        'project_id',
        'trigger',
        'is_active',
        'run_order',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'run_order' => 'integer',
            'run_count' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(AutomationCondition::class)->orderBy('position');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AutomationAction::class)->orderBy('position');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
