<?php

namespace App\Modules\ProjectManagement\Listeners;

use App\Modules\ProjectManagement\Services\ProjectProgressService;
use App\Modules\TaskManagement\Events\TaskCreated;
use App\Modules\TaskManagement\Events\TaskStatusChanged;

/**
 * Keeps a project's percentage honest as its work moves.
 */
class RecalculateProjectProgress
{
    public function __construct(private readonly ProjectProgressService $progress) {}

    public function handleCreated(TaskCreated $event): void
    {
        $this->progress->recalculateFor($event->task->project_id);
    }

    public function handleStatusChanged(TaskStatusChanged $event): void
    {
        $this->progress->recalculateFor($event->task->project_id);
    }
}
