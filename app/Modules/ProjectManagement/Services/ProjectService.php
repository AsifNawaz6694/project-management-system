<?php

namespace App\Modules\ProjectManagement\Services;

use App\Models\User;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\UserManagement\Services\ProjectPermissionResolver;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /**
     * Fields whose before/after values are recorded on update.
     *
     * `status` is deliberately absent: it gets its own activity row, the way a
     * task's status does, so a stage change is never reported twice.
     */
    private const TRACKED = [
        'title', 'description', 'priority', 'color',
        'start_date', 'end_date', 'progress', 'owner_id',
    ];

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ProjectProgressService $progress,
    ) {}

    public function create(array $data, User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor) {
            $project = Project::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'planning',
                'priority' => $data['priority'] ?? 'medium',
                'color' => $data['color'] ?? 'violet',
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'progress' => $data['progress'] ?? 0,
                'owner_id' => $data['owner_id'] ?? $actor->id,
            ]);

            $this->syncMembers($project, $data['member_ids'] ?? [], $project->owner_id, record: false);

            $this->replaceMilestones($project, $data['milestones'] ?? [], record: false);

            Activity::logFor('project.created', Activity::SUBJECT_PROJECT, $project->id, [
                'user_id' => $actor->id,
                'module' => 'projects',
                'description' => "Created project {$project->title}",
            ]);

            $memberIds = array_filter(array_unique([...($data['member_ids'] ?? []), $project->owner_id]));
            $this->notifications->push($memberIds, [
                'group' => Notification::GROUP_PROJECTS,
                'type' => 'project.added',
                'title' => "{$actor->name} added you to a project",
                'body' => $project->title,
                'icon' => 'folder-kanban',
                'tone' => 'blue',
                'link' => route('projects.show', $project->slug, false),
                'data' => ['project_id' => $project->id],
            ], $actor->id);

            return $project->load(['owner', 'members', 'milestones']);
        });
    }

    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            // Snapshot before anything moves, so the diff is a real before/after
            // rather than a guess reconstructed afterwards.
            $before = $this->snapshotTracked($project);
            $statusBefore = $project->status;

            $project->fill(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? null,
                'priority' => $data['priority'] ?? null,
                'color' => $data['color'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
            ], fn ($v) => $v !== null));

            if (array_key_exists('progress', $data)) {
                $project->progress = max(0, min(100, (int) $data['progress']));
            }

            if (array_key_exists('owner_id', $data)) {
                $project->owner_id = $data['owner_id'];
            }

            $project->save();

            if (array_key_exists('member_ids', $data)) {
                $this->syncMembers($project, $data['member_ids'], $project->owner_id);
            }

            if (array_key_exists('milestones', $data)) {
                $this->replaceMilestones($project, $data['milestones']);
            }

            // A stage change is its own fact, and completing or reopening a
            // project are the two stages worth naming.
            if ($project->status !== $statusBefore) {
                Activity::logFor($this->statusAction($statusBefore, $project->status), Activity::SUBJECT_PROJECT, $project->id, [
                    'module' => 'projects',
                    'description' => "Moved project {$project->title} from {$statusBefore} to {$project->status}",
                    'properties' => ['from' => $statusBefore, 'to' => $project->status],
                ]);
            }

            // Nothing is written when nothing moved — an edit form saved
            // untouched used to leave a "project.updated" row behind it.
            Activity::logChanges('project.updated', Activity::SUBJECT_PROJECT, $project->id, $this->diffTracked($before, $project), [
                'module' => 'projects',
                'description' => "Updated project {$project->title}",
            ]);

            return $project->load(['owner', 'members', 'milestones']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotTracked(Project $project): array
    {
        $values = [];
        foreach (self::TRACKED as $field) {
            $values[$field] = $this->scalarise($project->getAttribute($field));
        }

        return $values;
    }

    /**
     * Field-level before/after for everything that actually moved.
     *
     * @param  array<string, mixed>  $before
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function diffTracked(array $before, Project $project): array
    {
        $changes = [];
        foreach (self::TRACKED as $field) {
            $after = $this->scalarise($project->getAttribute($field));

            if ((string) ($before[$field] ?? '') !== (string) ($after ?? '')) {
                $changes[$field] = [$before[$field] ?? null, $after];
            }
        }

        return $changes;
    }

    /**
     * Dates compare as dates; everything else compares as it is stored.
     */
    private function scalarise(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    private function statusAction(?string $from, string $to): string
    {
        return match (true) {
            $to === 'completed' => 'project.completed',
            $from === 'completed' => 'project.reopened',
            default => 'project.status-changed',
        };
    }

    public function delete(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $title = $project->title;
            $id = $project->id;
            $project->delete();

            Activity::logFor('project.deleted', Activity::SUBJECT_PROJECT, $id, [
                'module' => 'projects',
                'description' => "Deleted project {$title}",
            ]);
        });
    }

    public function toggleMilestone(Milestone $milestone): Milestone
    {
        $milestone->completed_at = $milestone->completed_at ? null : now();
        $milestone->save();

        $project = $milestone->project()->first();
        $progress = $this->progress->recalculate($project);

        Activity::logFor($milestone->completed_at ? 'milestone.completed' : 'milestone.reopened', Activity::SUBJECT_PROJECT, $project->id, [
            'module' => 'projects',
            'description' => ($milestone->completed_at ? 'Completed milestone: ' : 'Reopened milestone: ').$milestone->title,
            'properties' => ['milestone_id' => $milestone->id],
        ]);

        // Reaching a milestone is worth telling the team about; reopening one is
        // routine, so it stays quiet.
        if ($milestone->completed_at) {
            $memberIds = $project->members()->pluck('users.id')->push($project->owner_id)->filter()->unique()->all();

            $this->notifications->push($memberIds, [
                'group' => Notification::GROUP_PROJECTS,
                'type' => 'project.milestone-completed',
                'title' => 'A milestone was reached',
                'body' => $milestone->title.' · '.$project->title." ({$progress}% complete)",
                'icon' => 'flag',
                'tone' => 'emerald',
                'link' => route('projects.show', $project->slug, false),
                'data' => ['project_id' => $project->id, 'milestone_id' => $milestone->id],
            ], auth()->id());
        }

        return $milestone;
    }

    private function syncMembers(Project $project, array $userIds, ?int $ownerId, bool $record = true): void
    {
        $userIds = array_unique(array_filter(array_map('intval', $userIds)));

        if ($ownerId && ! in_array($ownerId, $userIds, true)) {
            $userIds[] = $ownerId;
        }

        $sync = [];
        foreach ($userIds as $userId) {
            $sync[$userId] = [
                'role' => $userId === $ownerId ? 'owner' : 'member',
                'joined_at' => now(),
            ];
        }

        $before = $project->members()->pluck('users.id')->all();
        $project->members()->sync($sync);
        ProjectPermissionResolver::flushCache();

        // sync() is silent about what it did, so the difference is computed
        // here; an unchanged roster writes nothing.
        $added = array_values(array_diff($userIds, $before));
        $removed = array_values(array_diff($before, $userIds));

        // Creating a project necessarily seeds its roster; saying so a second
        // time would put a membership row above the creation row it came from.
        if ($record) {
            Activity::logChanges('project.members-changed', Activity::SUBJECT_PROJECT, $project->id,
                array_filter(['added' => $added, 'removed' => $removed]), [
                    'module' => 'projects',
                    'description' => $this->describeMembership(count($added), count($removed), $project),
                ]);
        }
    }

    private function replaceMilestones(Project $project, array $milestones, bool $record = true): void
    {
        // Milestones are replaced wholesale, so the honest record is the set
        // before and the set after — not one row per delete and re-create.
        $before = $project->milestones()->orderBy('position')->pluck('title')->all();

        $project->milestones()->delete();
        foreach (array_values($milestones) as $i => $m) {
            if (empty($m['title'])) {
                continue;
            }

            $project->milestones()->create([
                'title' => $m['title'],
                'description' => $m['description'] ?? null,
                'due_date' => $m['due_date'] ?? null,
                'completed_at' => ! empty($m['completed']) ? now() : null,
                'position' => $i,
            ]);
        }

        $after = $project->milestones()->orderBy('position')->pluck('title')->all();

        if ($record && $before !== $after) {
            Activity::logChanges('project.milestones-changed', Activity::SUBJECT_PROJECT, $project->id,
                ['milestones' => [$before, $after]], [
                    'module' => 'projects',
                    'description' => "Updated milestones on {$project->title}",
                ]);
        }
    }

    private function describeMembership(int $added, int $removed, Project $project): string
    {
        $parts = [];
        if ($added > 0) {
            $parts[] = "added {$added}";
        }
        if ($removed > 0) {
            $parts[] = "removed {$removed}";
        }

        return "Project {$project->title} membership: ".implode(', ', $parts);
    }
}
