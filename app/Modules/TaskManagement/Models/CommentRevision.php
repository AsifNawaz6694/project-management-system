<?php

namespace App\Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The text a comment used to hold, kept so an edit is auditable rather than
 * just a word next to a timestamp.
 */
class CommentRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['task_comment_id', 'body', 'edited_by_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'task_comment_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_id');
    }
}
