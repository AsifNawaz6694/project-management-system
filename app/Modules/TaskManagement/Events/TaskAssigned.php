<?php

namespace App\Modules\TaskManagement\Events;

use App\Models\User;
use App\Modules\TaskManagement\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $actor,
        public ?int $assigneeId,
        public ?int $teamId = null,
    ) {}
}
