<?php

namespace App\Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStatus extends Model
{
    public const CATEGORY_TODO = 'todo';

    public const CATEGORY_IN_PROGRESS = 'in_progress';

    public const CATEGORY_DONE = 'done';

    public const CATEGORIES = [self::CATEGORY_TODO, self::CATEGORY_IN_PROGRESS, self::CATEGORY_DONE];

    protected $table = 'workflow_statuses';

    protected $fillable = ['workflow_id', 'key', 'name', 'category', 'color', 'position', 'is_initial'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_initial' => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function isDone(): bool
    {
        return $this->category === self::CATEGORY_DONE;
    }
}
