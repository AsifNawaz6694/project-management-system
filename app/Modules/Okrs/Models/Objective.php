<?php

namespace App\Modules\Okrs\Models;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Objective extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'active', 'completed', 'cancelled'];

    public const VISIBILITY = ['private', 'team', 'company'];

    protected $fillable = [
        'owner_id',
        'parent_id',
        'team_id',
        'project_id',
        'title',
        'description',
        'period',
        'starts_at',
        'ends_at',
        'status',
        'visibility',
        'progress',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'progress' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(KeyResult::class)->orderBy('position');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->hasPermission('okrs.manage')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('visibility', 'company')
                ->orWhere('owner_id', $user->id)
                ->orWhereHas('team', fn ($t) => $t->whereHas('members', fn ($m) => $m->where('users.id', $user->id)))
                ->orWhereHas('keyResults', fn ($k) => $k->where('owner_id', $user->id));
        });
    }
}
