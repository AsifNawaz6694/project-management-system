<?php

namespace App\Modules\UserManagement\Models;

use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of project-level permission grants.
 *
 * Workspace roles decide what a user may do *at all*; a permission scheme
 * decides who, inside one project, may actually do it. A project without a
 * scheme falls back to the default scheme, and the default scheme ships fully
 * open so adopting the feature is opt-in per project.
 */
class PermissionScheme extends Model
{
    protected $fillable = ['name', 'description', 'is_default', 'is_system'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function grants(): HasMany
    {
        return $this->hasMany(PermissionSchemeGrant::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }
}
