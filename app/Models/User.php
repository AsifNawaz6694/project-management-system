<?php

namespace App\Models;

use App\Modules\Feedback\Models\FeedbackRequest;
use App\Modules\Teams\Models\Team;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\UserManagement\Models\Department;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\TwoFactorCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'phone',
        'job_title',
        'department_id',
        'status',
        'two_factor_enabled',
        'last_login_at',
        'last_login_ip',
        'email_digest',
        'digest_sent_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'initials',
    ];

    /** @var array<int, int>|null */
    private ?array $teamIdsCache = null;

    /** @var array<int, int>|null */
    private ?array $ledTeamIdsCache = null;

    /** @var array<int, int>|null */
    private ?array $ledTeamMemberIdsCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'digest_sent_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function departmentName(): ?string
    {
        return $this->department?->name;
    }

    /**
     * Permissions granted to this person specifically, on top of whatever their
     * roles already carry. This is how a management responsibility is expressed
     * without inventing a role per job title.
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'subject_user_id');
    }

    public function performedActivities(): HasMany
    {
        return $this->hasMany(Activity::class, 'user_id');
    }

    public function twoFactorCodes(): HasMany
    {
        return $this->hasMany(TwoFactorCode::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')->withPivot('role')->withTimestamps();
    }

    /**
     * Team ids this user belongs to, memoised per request — the task visibility
     * scope needs them on every list query.
     *
     * @return array<int, int>
     */
    public function teamIds(): array
    {
        return $this->teamIdsCache ??= $this->relationLoaded('teams')
            ? $this->teams->pluck('id')->all()
            : $this->teams()->pluck('teams.id')->all();
    }

    /**
     * Teams this user leads, either as the team's named lead or through a
     * `lead` row on the membership pivot. A lead's visibility extends over
     * their team's work — see the `visibleTo` scopes.
     *
     * @return array<int, int>
     */
    public function ledTeamIds(): array
    {
        return $this->ledTeamIdsCache ??= Team::query()
            ->where('lead_id', $this->id)
            ->orWhereExists(fn ($q) => $q->selectRaw('1')
                ->from('team_user')
                ->whereColumn('team_user.team_id', 'teams.id')
                ->where('team_user.user_id', $this->id)
                ->where('team_user.role', 'lead'))
            ->pluck('id')
            ->all();
    }

    /**
     * Everyone who sits on a team this user leads.
     *
     * @return array<int, int>
     */
    public function ledTeamMemberIds(): array
    {
        if ($this->ledTeamMemberIdsCache !== null) {
            return $this->ledTeamMemberIdsCache;
        }

        $teamIds = $this->ledTeamIds();

        return $this->ledTeamMemberIdsCache = $teamIds === []
            ? []
            : DB::table('team_user')->whereIn('team_id', $teamIds)->distinct()->pluck('user_id')->all();
    }

    public function feedbackRequestsAsReviewer(): HasMany
    {
        return $this->hasMany(FeedbackRequest::class, 'reviewer_id');
    }

    public function feedbackRequestsAsSubject(): HasMany
    {
        return $this->hasMany(FeedbackRequest::class, 'subject_user_id');
    }

    public function hasRole(string ...$slugs): bool
    {
        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function isManager(): bool
    {
        return $this->hasRole(Role::MANAGER);
    }

    public function isEmployee(): bool
    {
        return $this->hasRole(Role::EMPLOYEE);
    }

    /**
     * Effective permissions: everything the user's roles carry, plus the direct
     * grants that encode their individual responsibilities.
     */
    public function permissions(): Collection
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap->permissions
            ->concat($this->loadMissing('directPermissions')->directPermissions)
            ->unique('id')
            ->values();
    }

    /**
     * Replace this user's direct grants with exactly the given slugs.
     *
     * @param  array<int, string>  $slugs
     */
    public function syncDirectPermissionsBySlug(array $slugs): void
    {
        $ids = Permission::query()->whereIn('slug', $slugs)->pluck('id')->all();
        $this->directPermissions()->sync($ids);
        $this->unsetRelation('directPermissions');
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->permissions()->contains('slug', $slug);
    }

    public function permissionSlugs(): array
    {
        if ($this->isAdmin()) {
            return Permission::query()->pluck('slug')->all();
        }

        return $this->permissions()->pluck('slug')->all();
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function primaryRole(): ?Role
    {
        return $this->roles->sortBy(fn (Role $role) => match ($role->slug) {
            Role::ADMIN => 0,
            Role::MANAGER => 1,
            Role::EMPLOYEE => 2,
            default => 99,
        })->first();
    }
}
