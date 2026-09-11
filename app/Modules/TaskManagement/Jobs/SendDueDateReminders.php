<?php

namespace App\Modules\TaskManagement\Jobs;

use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies assignees about work due soon.
 *
 * This is the producer for Notification::GROUP_DEADLINES, which the audit found
 * declared as a constant with nothing ever creating it.
 */
class SendDueDateReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $daysAhead = 1) {}

    public function handle(NotificationService $notifications, WorkflowService $workflows): void
    {
        $doneKeys = $workflows->doneStatusKeys();
        $target = now()->addDays($this->daysAhead)->toDateString();

        Task::query()
            ->notArchived()
            ->whereNotNull('due_date')
            ->whereDate('due_date', $target)
            ->whereNull('completed_at')
            ->whereNotIn('status', $doneKeys ?: ['__none__'])
            ->whereNotNull('assignee_id')
            ->with('project:id,key')
            ->chunkById(200, function ($tasks) use ($notifications) {
                foreach ($tasks as $task) {
                    $when = $this->daysAhead === 0 ? 'today' : "in {$this->daysAhead} day(s)";

                    $notifications->push((int) $task->assignee_id, [
                        'group' => Notification::GROUP_DEADLINES,
                        'type' => 'task.due-soon',
                        'title' => "A task is due {$when}",
                        'body' => $task->key_label.' · '.$task->title,
                        'icon' => 'calendar-clock',
                        'tone' => 'amber',
                        'link' => route('tasks.show', $task->id, false),
                        'data' => ['task_id' => $task->id, 'due_date' => $task->due_date?->toDateString()],
                    ]);
                }
            });
    }
}
