<?php

namespace App\Modules\ProjectManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use SoftDeletes;

    public const STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const COLORS = ['violet', 'blue', 'emerald', 'amber', 'rose', 'pink', 'sky', 'slate'];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'status',
        'priority',
        'color',
        'start_date',
        'end_date',
        'budget',
        'progress',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
            'progress' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (! $project->slug) {
                $project->slug = static::uniqueSlug($project->title);
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

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->hasPermission('projects.create')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
