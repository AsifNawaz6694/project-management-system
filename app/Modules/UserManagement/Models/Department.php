<?php

namespace App\Modules\UserManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Department extends Model
{
    public const DTT = 'dtt';

    public const HR = 'hr';

    public const IT = 'it';

    protected $fillable = ['slug', 'name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Department $department) {
            if (! $department->slug) {
                $department->slug = Str::slug($department->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
