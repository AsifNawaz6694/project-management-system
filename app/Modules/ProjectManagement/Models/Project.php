<?php

namespace App\Modules\ProjectManagement\Models;

use App\Models\User;
use App\Modules\Communication\Models\ProjectComment;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\PermissionScheme;
use App\Modules\Workflow\Models\Workflow;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    public const STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const COLORS = ['violet', 'blue', 'emerald', 'amber', 'rose', 'pink', 'sky', 'slate'];

    protected $fillable = [
        'title',
        'slug',
        'key',
        'workflow_id',
        'permission_scheme_id',
        'description',
        'status',
        'priority',
        'color',
        'start_date',
        'end_date',
        'progress',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'progress' => 'integer',
            'task_sequence' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (! $project->slug) {
                $project->slug = static::uniqueSlug($project->title);
            }

            if (! $project->key) {
                $project->key = static::uniqueKey($project->title);
            }

            if (! $project->workflow_id) {
                $project->workflow_id = Workflow::query()->where('is_default', true)->value('id');
            }
        });

        static::updating(function (Project $project) {
            if ($project->isDirty('title') && ! $project->isDirty('slug')) {
                $project->slug = static::uniqueSlug($project->title, $project->id);
            }
        });
    }

    protected static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'project';
        $slug = $base;
        $i = 2;
        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Falls back to the default scheme when null — see ProjectPermissionResolver.
     */
    public function permissionScheme(): BelongsTo
    {
        return $this->belongsTo(PermissionScheme::class, 'permission_scheme_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('position')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class)->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Allocate the next per-project task number atomically.
     *
     * Uses an in-place increment plus a re-read rather than MAX(number)+1 so two
     * concurrent creates cannot be handed the same key.
     */
    public function nextTaskNumber(): int
    {
        static::query()->whereKey($this->id)->increment('task_sequence');

        return (int) static::query()->whereKey($this->id)->value('task_sequence');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->hasPermission('projects.view-all')) {
            return $query;
        }

        // A team lead also sees the projects their teams are working in —
        // either through a member sitting on the project, or through a task
        // routed to one of their teams.
        $ledTeamIds = $user->ledTeamIds();
        $ledMemberIds = $user->ledTeamMemberIds();

        return $query->where(function ($q) use ($user, $ledTeamIds, $ledMemberIds) {
            $q->where('projects.owner_id', $user->id)
                ->orWhereExists(function ($m) use ($user) {
                    $m->selectRaw('1')
                        ->from('project_user')
                        ->whereColumn('project_user.project_id', 'projects.id')
                        ->where('project_user.user_id', $user->id);
                })
                ->when($ledMemberIds !== [], fn ($q) => $q->orWhereExists(function ($m) use ($ledMemberIds) {
                    $m->selectRaw('1')
                        ->from('project_user')
                        ->whereColumn('project_user.project_id', 'projects.id')
                        ->whereIn('project_user.user_id', $ledMemberIds);
                }))
                ->when($ledTeamIds !== [], fn ($q) => $q->orWhereExists(function ($t) use ($ledTeamIds) {
                    $t->selectRaw('1')
                        ->from('tasks')
                        ->whereColumn('tasks.project_id', 'projects.id')
                        ->whereNull('tasks.deleted_at')
                        ->whereIn('tasks.team_id', $ledTeamIds);
                }));
        });
    }

    /**
     * Short uppercase code used to build task keys (WEB-142).
     */
    public static function uniqueKey(string $title, ?int $ignoreId = null): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $base = count($words) >= 2
            ? strtoupper(substr($words[0], 0, 1).substr($words[1], 0, 2))
            : strtoupper(substr(($words[0] ?? 'PRJ'), 0, 3));

        $base = preg_replace('/[^A-Z0-9]/', '', $base) ?: 'PRJ';
        $base = str_pad(substr($base, 0, 4), 2, 'X');

        $key = $base;
        $i = 2;
        while (static::query()->where('key', $key)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $key = $base.$i++;
        }

        return $key;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
