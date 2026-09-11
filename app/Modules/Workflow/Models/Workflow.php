<?php

namespace App\Modules\Workflow\Models;

use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Workflow extends Model
{
    protected $fillable = ['name', 'description', 'is_default', 'is_system'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(WorkflowStatus::class)->orderBy('position')->orderBy('id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * People and teams narrowed to this chain. See WorkflowAssignment.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WorkflowAssignment::class);
    }

    /**
     * @return array<int, int>
     */
    public function assignedUserIds(): array
    {
        return $this->assignments()->forUsers()->pluck('assignable_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<int, int>
     */
    public function assignedTeamIds(): array
    {
        return $this->assignments()->forTeams()->pluck('assignable_id')->map(fn ($id) => (int) $id)->all();
    }

    public static function default(): self
    {
        return static::query()->where('is_default', true)->firstOrFail();
    }

    /**
     * Status keys belonging to this workflow, in board order.
     *
     * @return array<int, string>
     */
    public function statusKeys(): array
    {
        return $this->statuses->pluck('key')->all();
    }

    /**
     * Statuses in the "done" bucket — used to decide completion.
     *
     * @return Collection<int, WorkflowStatus>
     */
    public function doneStatuses(): Collection
    {
        return $this->statuses->where('category', WorkflowStatus::CATEGORY_DONE);
    }
}
