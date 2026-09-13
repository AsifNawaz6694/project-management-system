<?php

namespace App\Modules\TaskManagement\Models;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Teams\Models\Team;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    /**
     * Fallback status keys, used only before any workflow is seeded.
     * The authoritative set now lives in workflow_statuses — resolve it through
     * WorkflowService rather than reading this constant.
     */
    public const STATUSES = ['todo', 'in_progress', 'completed'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'project_id',
        'sprint_id',
        'task_type_id',
        'parent_task_id',
        'assignee_id',
        'team_id',
        'created_by_id',
        'number',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'start_date',
        'estimate_minutes',
        'story_points',
        'position',
        'completed_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'start_date' => 'date',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'estimate_minutes' => 'integer',
            'story_points' => 'float',
            'number' => 'integer',
        ];
    }

    // ------------------------------------------------------------------ relations

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TaskType::class, 'task_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id')->orderBy('position')->orderBy('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest();
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class)->latest('started_at');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'label_task');
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers')->withTimestamps();
    }

    public function links(): HasMany
    {
        return $this->hasMany(TaskLink::class, 'source_task_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class)->latest('created_at');
    }

    // ------------------------------------------------------------------ accessors

    public function getLoggedMinutesAttribute(): int
    {
        if ($this->relationLoaded('timeLogs')) {
            return (int) $this->timeLogs->sum('minutes');
        }

        return (int) $this->timeLogs()->sum('minutes');
    }

    /**
     * Minutes still budgeted, derived rather than stored so it cannot drift.
     */
    public function getRemainingMinutesAttribute(): ?int
    {
        if ($this->estimate_minutes === null) {
            return null;
        }

        return max(0, $this->estimate_minutes - $this->logged_minutes);
    }

    /**
     * Human-readable identifier, e.g. WEB-142.
     */
    public function getKeyLabelAttribute(): string
    {
        $prefix = $this->relationLoaded('project') && $this->project?->key
            ? $this->project->key
            : Project::query()->where('id', $this->project_id)->value('key');

        return $prefix && $this->number ? $prefix.'-'.$this->number : '#'.$this->id;
    }

    // ------------------------------------------------------------------ scopes

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_task_id');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Rows this user may see. Visibility follows direct assignment, authorship,
     * team routing, team leadership or project ownership — never a bare
     * permission, so that a broad grant cannot silently widen data access.
     *
     * Plain project membership is deliberately **not** on that list. Being a
     * member of a project used to reveal every task in it, which made an
     * employee's task list the whole board; a developer now sees the work that
     * is actually theirs — assigned to them, raised by them, or routed to a
     * team they are on (that last one is how unassigned team work stays
     * pickable). Anyone who needs the whole board holds `tasks.view-all`, which
     * the Manager role and the reporting bundles carry.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->hasPermission('tasks.view-all')) {
            return $query;
        }

        // A team lead reaches their team's work even when they are not on the
        // task themselves — that responsibility is what makes them the lead.
        $teamIds = array_values(array_unique([...$user->teamIds(), ...$user->ledTeamIds()]));
        $ledMemberIds = $user->ledTeamMemberIds();

        return $query->where(function ($q) use ($user, $teamIds, $ledMemberIds) {
            $q->where('tasks.assignee_id', $user->id)
                ->orWhere('tasks.created_by_id', $user->id)
                ->orWhereIn('tasks.team_id', $teamIds ?: [0])
                ->when($ledMemberIds !== [], fn ($q) => $q->orWhereIn('tasks.assignee_id', $ledMemberIds))
                // Owning the project still carries accountability for the work
                // inside it; sitting on its member list no longer does.
                ->orWhereExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('projects')
                        ->whereColumn('projects.id', 'tasks.project_id')
                        ->where('projects.owner_id', $user->id);
                });
        });
    }

    /**
     * True when every blocker of this task is closed.
     *
     * @param  array<int, string>  $doneStatusKeys
     */
    public function isBlocked(array $doneStatusKeys): bool
    {
        return TaskLink::query()
            ->where('source_task_id', $this->id)
            ->where('type', TaskLink::BLOCKED_BY)
            ->whereExists(function ($q) use ($doneStatusKeys) {
                $q->selectRaw('1')
                    ->from('tasks as blockers')
                    ->whereColumn('blockers.id', 'task_links.target_task_id')
                    ->whereNull('blockers.deleted_at')
                    ->whereNotIn('blockers.status', $doneStatusKeys ?: ['__none__']);
            })
            ->exists();
    }
}
