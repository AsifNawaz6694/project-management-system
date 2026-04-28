<?php

namespace App\Modules\MeetingManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendaItem extends Model
{
    public const STATUSES = ['pending', 'discussed', 'skipped', 'parked'];

    protected $fillable = [
        'meeting_id',
        'presenter_id',
        'title',
        'description',
        'notes',
        'time_allocation_minutes',
        'position',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'time_allocation_minutes' => 'integer',
            'position' => 'integer',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }
}
