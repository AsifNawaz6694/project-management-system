<?php

namespace App\Modules\TaskManagement\Events;

use App\Models\User;
use App\Modules\TaskManagement\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes  field => [from, to]
     */
    public function __construct(public Task $task, public User $actor, public array $changes = []) {}
}
