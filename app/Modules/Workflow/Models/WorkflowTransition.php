<?php

namespace App\Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransition extends Model
{
    protected $fillable = [
        'workflow_id', 'from_status_id', 'to_status_id', 'name',
        'required_permission', 'requires_comment', 'comment_label',
    ];

    protected function casts(): array
    {
        return ['requires_comment' => 'boolean'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'to_status_id');
    }

    /**
     * A null from_status_id means "from anywhere".
     */
    public function isGlobal(): bool
    {
        return $this->from_status_id === null;
    }
}
