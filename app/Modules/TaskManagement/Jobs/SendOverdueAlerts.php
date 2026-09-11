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
 * Alerts the assignee, and the project owner, about work past its due date.
 *
 * Deduplicated against notifications already sent today so a task that stays
 * overdue does not generate a daily pile of identical alerts.
 */
class SendOverdueAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notifications, WorkflowService $workflows): void
    {
        $doneKeys = $workflows->doneStatusKeys();

        Task::query()
            ->notArchived()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNull('completed_at')
            ->whereNotIn('status', $doneKeys ?: ['__none__'])
            ->with(['project:id,key,owner_id'])
            ->chunkById(200, function ($tasks) use ($notifications) {
                foreach ($tasks as $task) {
                    $recipients = array_values(array_unique(array_filter([
                        $task->assignee_id,
                        $task->project?->owner_id,
                    ])));

                    if ($recipients === []) {
                        continue;
                    }

                    $alreadySent = Notification::query()
                        ->where('type', 'task.overdue')
                        ->whereIn('user_id', $recipients)
                        ->where('created_at', '>=', now()->startOfDay())
                        ->whereJsonContains('data->task_id', $task->id)
                        ->exists();

                    if ($alreadySent) {
                        continue;
                    }

                    $daysLate = (int) now()->startOfDay()->diffInDays($task->due_date, absolute: true);

                    $notifications->push($recipients, [
                        'group' => Notification::GROUP_DEADLINES,
                        'type' => 'task.overdue',
                        'title' => "A task is {$daysLate} day(s) overdue",
                        'body' => $task->key_label.' · '.$task->title,
                        'icon' => 'alert-circle',
                        'tone' => 'rose',
                        'link' => route('tasks.show', $task->id, false),
                        'data' => ['task_id' => $task->id, 'days_late' => $daysLate],
                    ]);
                }
            });
    }
}
