<?php

namespace App\Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Label extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'created_by_id'];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'label_task');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Label $label) {
            if (! $label->slug) {
                $label->slug = static::uniqueSlug($label->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'label';
        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
