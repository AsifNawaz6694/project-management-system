<?php

namespace App\Modules\MeetingManagement\Services;

use App\Models\User;
use App\Modules\MeetingManagement\Models\ActionItem;
use App\Modules\MeetingManagement\Models\AgendaItem;
use App\Modules\MeetingManagement\Models\Meeting;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MeetingService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function create(array $data, User $organizer): Meeting
    {
        return DB::transaction(function () use ($data, $organizer) {
            $seriesId = ($data['recurrence_rule'] ?? 'none') !== 'none'
                ? (int) (Meeting::max('series_id') ?? 0) + 1
                : null;

            $occurrences = RecurrenceExpander::expand(
                CarbonImmutable::parse($data['starts_at']),
                $data['recurrence_rule'] ?? 'none',
                isset($data['recurrence_until']) ? CarbonImmutable::parse($data['recurrence_until']) : null,
            );

            $first = null;
            $duration = isset($data['ends_at'])
                ? CarbonImmutable::parse($data['starts_at'])->diffInMinutes(CarbonImmutable::parse($data['ends_at']))
                : null;

            foreach ($occurrences as $i => $start) {
                $meeting = Meeting::create([
                    'organizer_id' => $organizer->id,
                    'project_id' => $data['project_id'] ?? null,
                    'template_id' => $data['template_id'] ?? null,
                    'series_id' => $seriesId,
                    'title' => $data['title'],
                    'kind' => $data['kind'] ?? 'team',
                    'description' => $data['description'] ?? null,
                    'location' => $data['location'] ?? null,
                    'starts_at' => $start,
                    'ends_at' => $duration ? $start->addMinutes($duration) : null,
                    'status' => 'scheduled',
                    'recurrence_rule' => $i === 0 ? ($data['recurrence_rule'] ?? null) : null,
                    'recurrence_until' => $i === 0 ? ($data['recurrence_until'] ?? null) : null,
                ]);

                $this->syncParticipants($meeting, $data['participants'] ?? [], $organizer);
                $this->replaceAgendaItems($meeting, $data['agenda_items'] ?? []);

                if ($i === 0) {
                    $first = $meeting;

                    Activity::log('meeting.created', [
                        'module' => 'meetings',
                        'description' => "Scheduled \"{$meeting->title}\" for ".$start->toDayDateTimeString(),
                        'properties' => ['meeting_id' => $meeting->id, 'series_id' => $seriesId],
                    ]);

                    foreach (($data['participants'] ?? []) as $p) {
                        $userId = is_array($p) ? ($p['user_id'] ?? null) : $p;
                        if ($userId && $userId !== $organizer->id) {
                            $this->notifications->push((int) $userId, [
                                'group' => Notification::GROUP_SYSTEM,
                                'type' => 'meeting.invited',
                                'title' => "{$organizer->name} invited you to a meeting",
                                'body' => $meeting->title,
                                'icon' => 'calendar',
                                'tone' => 'sky',
                                'link' => '/meetings/'.$meeting->id,
                                'data' => ['meeting_id' => $meeting->id],
                            ], $organizer->id);
                        }
                    }
                }
            }

            return $first->load(['organizer', 'participants', 'agendaItems']);
        });
    }

    public function update(Meeting $meeting, array $data, User $actor): Meeting
    {
        return DB::transaction(function () use ($meeting, $data, $actor) {
            $meeting->fill(array_filter([
                'title' => $data['title'] ?? null,
                'kind' => $data['kind'] ?? null,
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'status' => $data['status'] ?? null,
                'project_id' => $data['project_id'] ?? null,
            ], fn ($v) => $v !== null));
            $meeting->save();

            if (array_key_exists('participants', $data)) {
                $this->syncParticipants($meeting, $data['participants'], $actor);
            }
            if (array_key_exists('agenda_items', $data)) {
                $this->replaceAgendaItems($meeting, $data['agenda_items']);
            }

            Activity::log('meeting.updated', [
                'module' => 'meetings',
                'description' => "Updated meeting \"{$meeting->title}\"",
                'properties' => ['meeting_id' => $meeting->id],
            ]);

            return $meeting->refresh()->load(['organizer', 'participants', 'agendaItems']);
        });
    }

    public function delete(Meeting $meeting): void
    {
        $title = $meeting->title;
        $meeting->delete();
        Activity::log('meeting.cancelled', [
            'module' => 'meetings',
            'description' => "Cancelled \"{$title}\"",
            'properties' => ['meeting_id' => $meeting->id],
        ]);
    }

    public function saveNotes(Meeting $meeting, ?string $notes, User $actor): Meeting
    {
        $meeting->update(['notes' => $notes]);
        Activity::log('meeting.notes-updated', [
            'module' => 'meetings',
            'description' => "Updated notes on \"{$meeting->title}\"",
            'properties' => ['meeting_id' => $meeting->id, 'updated_by' => $actor->id],
        ]);

        return $meeting;
    }

    public function addActionItem(Meeting $meeting, array $data, User $creator): ActionItem
    {
        return DB::transaction(function () use ($meeting, $data, $creator) {
            $item = $meeting->actionItems()->create([
                'agenda_item_id' => $data['agenda_item_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'created_by_id' => $creator->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => 'open',
            ]);

            Activity::log('meeting.action-item-added', [
                'module' => 'meetings',
                'description' => "Added action item \"{$item->title}\" on \"{$meeting->title}\"",
                'properties' => ['meeting_id' => $meeting->id, 'action_item_id' => $item->id],
            ]);

            if ($item->assignee_id && $item->assignee_id !== $creator->id) {
                $this->notifications->push((int) $item->assignee_id, [
                    'group' => Notification::GROUP_TASKS,
                    'type' => 'meeting.action-assigned',
                    'title' => "{$creator->name} assigned you an action item",
                    'body' => $item->title,
                    'icon' => 'list-checks',
                    'tone' => 'amber',
                    'link' => '/meetings/'.$meeting->id,
                    'data' => ['meeting_id' => $meeting->id, 'action_item_id' => $item->id],
                ], $creator->id);
            }

            return $item->load(['assignee', 'creator']);
        });
    }

    public function updateActionItem(ActionItem $item, array $data): ActionItem
    {
        $item->fill(array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($v) => $v !== null));

        if (($data['status'] ?? null) === 'done' && ! $item->completed_at) {
            $item->completed_at = now();
        }
        if (isset($data['status']) && $data['status'] !== 'done') {
            $item->completed_at = null;
        }

        $item->save();

        Activity::log('meeting.action-item-updated', [
            'module' => 'meetings',
            'description' => "Updated action item \"{$item->title}\"",
            'properties' => ['meeting_id' => $item->meeting_id, 'action_item_id' => $item->id],
        ]);

        return $item;
    }

    public function deleteActionItem(ActionItem $item): void
    {
        $title = $item->title;
        $meetingId = $item->meeting_id;
        $item->delete();
        Activity::log('meeting.action-item-deleted', [
            'module' => 'meetings',
            'description' => "Removed action item \"{$title}\"",
            'properties' => ['meeting_id' => $meetingId, 'action_item_id' => $item->id],
        ]);
    }

    public function convertActionItemToTask(ActionItem $item, int $projectId, User $actor): Task
    {
        return DB::transaction(function () use ($item, $projectId, $actor) {
            $position = (int) Task::where('project_id', $projectId)
                ->whereNull('parent_task_id')
                ->where('status', 'todo')
                ->max('position');

            $task = Task::create([
                'project_id' => $projectId,
                'assignee_id' => $item->assignee_id,
                'created_by_id' => $actor->id,
                'title' => $item->title,
                'description' => trim(($item->description ?? '')."\n\nFrom meeting: \"{$item->meeting->title}\""),
                'status' => 'todo',
                'priority' => 'medium',
                'due_date' => $item->due_date,
                'position' => $position + 1,
            ]);

            $item->update(['task_id' => $task->id, 'status' => 'in_progress']);

            Activity::log('meeting.action-item-promoted', [
                'module' => 'meetings',
                'description' => "Promoted action item \"{$item->title}\" to a task",
                'properties' => ['meeting_id' => $item->meeting_id, 'action_item_id' => $item->id, 'task_id' => $task->id],
            ]);

            return $task;
        });
    }

    /**
     * @param  array<int, array{user_id: int, role?: string}>|array<int, int>  $participants
     */
    private function syncParticipants(Meeting $meeting, array $participants, User $organizer): void
    {
        $rows = [$organizer->id => ['role' => 'organizer', 'rsvp_status' => 'accepted']];

        foreach ($participants as $p) {
            $uid = is_array($p) ? ($p['user_id'] ?? null) : $p;
            if (! $uid) {
                continue;
            }
            $uid = (int) $uid;
            if ($uid === $organizer->id) {
                continue;
            }
            $rows[$uid] = [
                'role' => is_array($p) ? ($p['role'] ?? 'attendee') : 'attendee',
                'rsvp_status' => 'pending',
            ];
        }

        $meeting->participants()->sync($rows);
    }

    /**
     * @param  array<int, array{title: string, description?: string|null, time_allocation_minutes?: int|null, presenter_id?: int|null}>  $items
     */
    private function replaceAgendaItems(Meeting $meeting, array $items): void
    {
        $meeting->agendaItems()->delete();

        foreach (array_values($items) as $i => $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            AgendaItem::create([
                'meeting_id' => $meeting->id,
                'presenter_id' => $row['presenter_id'] ?? null,
                'title' => Str::limit($title, 200, ''),
                'description' => $row['description'] ?? null,
                'time_allocation_minutes' => isset($row['time_allocation_minutes']) ? (int) $row['time_allocation_minutes'] : null,
                'position' => $i,
                'status' => 'pending',
            ]);
        }
    }
}
