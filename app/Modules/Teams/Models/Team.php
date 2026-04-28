<?php

namespace App\Modules\Teams\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Team extends Model
{
    use SoftDeletes;

    public const COLORS = ['violet', 'blue', 'emerald', 'amber', 'rose', 'pink', 'sky', 'slate'];

    protected $fillable = ['name', 'slug', 'description', 'color', 'lead_id'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')->withPivot('role')->withTimestamps();
    }

    protected static function booted(): void
    {
        static::creating(function (Team $team) {
            if (! $team->slug) {
                $team->slug = static::uniqueSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name') && ! $team->isDirty('slug')) {
                $team->slug = static::uniqueSlug($team->name, $team->id);
            }
        });
    }

    protected static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'team';
        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
