<?php

namespace App\Models;

use App\Modules\Feedback\Models\FeedbackRequest;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\TwoFactorCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

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
        'department',
        'status',
        'two_factor_enabled',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'initials',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
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

    public function permissions(): Collection
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap->permissions
            ->unique('id')
            ->values();
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
