<?php

namespace App\Modules\TaskManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a sprint looked like at the end of one day.
 *
 * Burndown cannot be reconstructed from the tasks table alone — a task that
 * was reopened, re-pointed or moved out of the sprint erases its own history.
 */
class SprintSnapshot extends Model
{
    protected $fillable = [
        'sprint_id',
        'snapshot_date',
        'remaining_points',
        'completed_points',
        'remaining_tasks',
        'completed_tasks',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'remaining_points' => 'float',
            'completed_points' => 'float',
            'remaining_tasks' => 'integer',
            'completed_tasks' => 'integer',
        ];
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }
}
