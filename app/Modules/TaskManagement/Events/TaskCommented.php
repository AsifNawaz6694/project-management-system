<?php

namespace App\Modules\TaskManagement\Events;

use App\Models\User;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskComment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCommented
{
    use Dispatchable, SerializesModels;

    /** @param array<int, int> $mentioned */
    public function __construct(
        public Task $task,
        public TaskComment $comment,
        public User $actor,
        public array $mentioned = [],
    ) {}
}
