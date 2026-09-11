<?php

namespace App\Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStatusHistory extends Model
{
    protected $table = 'task_status_history';

    public const UPDATED_AT = null;

    protected $fillable = ['task_id', 'user_id', 'from_status', 'to_status', 'note', 'duration_seconds', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
