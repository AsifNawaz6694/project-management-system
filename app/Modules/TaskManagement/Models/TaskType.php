<?php

namespace App\Modules\TaskManagement\Models;

use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskType extends Model
{
    protected $fillable = ['project_id', 'key', 'name', 'icon', 'color', 'is_subtask_type', 'position'];

    protected function casts(): array
    {
        return [
            'is_subtask_type' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Types usable on a project: its own, plus the workspace-wide ones.
     *
     * A null project_id means "everywhere", so types that predate per-project
     * scoping keep working untouched.
     */
    public function scopeForProject(Builder $query, ?Project $project): Builder
    {
        return $query->where(
            fn (Builder $q) => $q->whereNull('project_id')
                ->when($project, fn (Builder $inner) => $inner->orWhere('project_id', $project->id))
        );
    }
}
