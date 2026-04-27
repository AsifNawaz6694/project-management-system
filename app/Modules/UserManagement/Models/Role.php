<?php

namespace App\Modules\UserManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const ADMIN = 'admin';

    public const MANAGER = 'manager';

    public const EMPLOYEE = 'employee';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function syncPermissionsBySlug(array $slugs): void
    {
        $ids = Permission::whereIn('slug', $slugs)->pluck('id')->all();
        $this->permissions()->sync($ids);
    }
}
