<?php

namespace App\Providers;

use App\Modules\Automation\Listeners\RunAutomationRules;
use App\Modules\ProjectManagement\Listeners\RecalculateProjectProgress;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Policies\ProjectPolicy;
use App\Modules\TaskManagement\Events\TaskAssigned;
use App\Modules\TaskManagement\Events\TaskCommented;
use App\Modules\TaskManagement\Events\TaskCreated;
use App\Modules\TaskManagement\Events\TaskStatusChanged;
use App\Modules\TaskManagement\Events\TaskUpdated;
use App\Modules\TaskManagement\Listeners\NudgeParentWhenSubtasksFinish;
use App\Modules\TaskManagement\Listeners\RecordTaskActivity;
use App\Modules\TaskManagement\Listeners\SendTaskNotifications;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Policies\TaskPolicy;
use App\Modules\UserManagement\Models\PermissionScheme;
use App\Modules\UserManagement\Models\PermissionSchemeGrant;
use App\Modules\UserManagement\Services\ProjectPermissionResolver;
use App\Modules\Workflow\Console\AssignWorkflowCommand;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowAssignment;
use App\Modules\Workflow\Models\WorkflowStatus;
use App\Modules\Workflow\Models\WorkflowTransition;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires task domain events to their listeners.
 *
 * Keeping this explicit (rather than relying on event discovery) makes the
 * side effects of every task mutation readable in one file.
 */
class TaskEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([AssignWorkflowCommand::class]);
        }

        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);

        // Any workflow edit drops the memoised graph, so a rule saved in the
        // admin UI applies to the very next status change.
        foreach ([Workflow::class, WorkflowStatus::class, WorkflowTransition::class, WorkflowAssignment::class] as $model) {
            $model::saved(fn () => WorkflowService::flushCache());
            $model::deleted(fn () => WorkflowService::flushCache());
        }

        // Same contract for permission schemes: editing a grant takes effect
        // on the next authorisation check, not the next request.
        foreach ([PermissionScheme::class, PermissionSchemeGrant::class] as $model) {
            $model::saved(fn () => ProjectPermissionResolver::flushCache());
            $model::deleted(fn () => ProjectPermissionResolver::flushCache());
        }

        Event::listen(TaskCreated::class, [RecordTaskActivity::class, 'handleCreated']);
        Event::listen(TaskUpdated::class, [RecordTaskActivity::class, 'handleUpdated']);
        Event::listen(TaskStatusChanged::class, [RecordTaskActivity::class, 'handleStatusChanged']);

        Event::listen(TaskAssigned::class, [SendTaskNotifications::class, 'handleAssigned']);
        Event::listen(TaskStatusChanged::class, [SendTaskNotifications::class, 'handleStatusChanged']);
        Event::listen(TaskCommented::class, [SendTaskNotifications::class, 'handleCommented']);

        // A parent whose sub-tasks have all landed is worth pointing at.
        Event::listen(TaskStatusChanged::class, NudgeParentWhenSubtasksFinish::class);

        // Project percentage is derived from the work, so it has to move when
        // the work does — see ProjectProgressService.
        Event::listen(TaskCreated::class, [RecalculateProjectProgress::class, 'handleCreated']);
        Event::listen(TaskStatusChanged::class, [RecalculateProjectProgress::class, 'handleStatusChanged']);

        // Automation runs last, after the audit trail and notifications, so a
        // rule that changes the task sees the same record a person would.
        Event::listen(TaskCreated::class, [RunAutomationRules::class, 'handleCreated']);
        Event::listen(TaskUpdated::class, [RunAutomationRules::class, 'handleUpdated']);
        Event::listen(TaskStatusChanged::class, [RunAutomationRules::class, 'handleStatusChanged']);
        Event::listen(TaskAssigned::class, [RunAutomationRules::class, 'handleAssigned']);
        Event::listen(TaskCommented::class, [RunAutomationRules::class, 'handleCommented']);
    }
}
