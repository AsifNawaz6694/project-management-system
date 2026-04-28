<?php

namespace App\Modules\Okrs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeyResult extends Model
{
    public const METRIC_TYPES = ['number', 'percentage', 'currency', 'boolean'];

    public const STATUSES = ['on_track', 'at_risk', 'off_track', 'achieved'];

    protected $fillable = [
        'objective_id',
        'owner_id',
        'title',
        'description',
        'metric_type',
        'start_value',
        'target_value',
        'current_value',
        'unit',
        'position',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_value' => 'decimal:2',
            'target_value' => 'decimal:2',
            'current_value' => 'decimal:2',
        ];
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(KrUpdate::class)->latest('recorded_at');
    }

    public function getProgressAttribute(): int
    {
        $start = (float) $this->start_value;
        $target = (float) $this->target_value;
        $current = (float) $this->current_value;
        $denom = $target - $start;
        if (abs($denom) < 0.0001) {
            return $current >= $target ? 100 : 0;
        }
        $ratio = ($current - $start) / $denom;

        return (int) max(0, min(100, round($ratio * 100)));
    }
}
