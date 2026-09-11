<?php

namespace App\Modules\TaskManagement\Events;

use App\Models\User;
use App\Modules\TaskManagement\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Task $task,
        public ?User $actor,
        public ?string $from,
        public string $to,
        public bool $completed = false,
        /** Reason captured at transition time, e.g. a QA verdict. */
        public ?string $reason = null,
    ) {}
}
