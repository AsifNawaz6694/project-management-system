<?php

namespace App\Modules\Automation\Listeners;

use App\Modules\Automation\Models\AutomationRule;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\TaskManagement\Events\TaskAssigned;
use App\Modules\TaskManagement\Events\TaskCommented;
use App\Modules\TaskManagement\Events\TaskCreated;
use App\Modules\TaskManagement\Events\TaskStatusChanged;
use App\Modules\TaskManagement\Events\TaskUpdated;

/**
 * Translates each task event into the flat context the engine reasons about.
 *
 * Keeping the mapping here means the engine never has to know which event
 * shape produced a fact — a new trigger is one method, not a change to the
 * matching logic.
 */
class RunAutomationRules
{
    public function __construct(private readonly AutomationEngine $engine) {}

    public function handleCreated(TaskCreated $event): void
    {
        $this->engine->run(AutomationRule::TRIGGER_CREATED, $event->task, [
            'actor_id' => $event->actor->id,
        ]);
    }

    public function handleStatusChanged(TaskStatusChanged $event): void
    {
        $this->engine->run(AutomationRule::TRIGGER_STATUS_CHANGED, $event->task, [
            'actor_id' => $event->actor?->id,
            'from_status' => $event->from,
            'to_status' => $event->to,
            'reason' => $event->reason,
        ]);
    }

    public function handleAssigned(TaskAssigned $event): void
    {
        $this->engine->run(AutomationRule::TRIGGER_ASSIGNED, $event->task, [
            'actor_id' => $event->actor->id,
            'assignee_id' => $event->assigneeId,
            'team_id' => $event->teamId,
        ]);
    }

    public function handleUpdated(TaskUpdated $event): void
    {
        $this->engine->run(AutomationRule::TRIGGER_UPDATED, $event->task, [
            'actor_id' => $event->actor->id,
            'changed_fields' => array_keys($event->changes),
        ]);
    }

    public function handleCommented(TaskCommented $event): void
    {
        $this->engine->run(AutomationRule::TRIGGER_COMMENTED, $event->task, [
            'actor_id' => $event->actor->id,
            'comment_body' => $event->comment->body,
        ]);
    }
}
