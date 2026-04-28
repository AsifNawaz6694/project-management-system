<?php

namespace App\Modules\ProjectManagement\Services;

use App\Models\User;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(private readonly NotificationService $notifications) {}

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
                'budget' => $data['budget'] ?? null,
                'currency' => $data['currency'] ?? Project::DEFAULT_CURRENCY,
                'progress' => $data['progress'] ?? 0,
                'owner_id' => $data['owner_id'] ?? $actor->id,
            ]);

            $this->syncMembers($project, $data['member_ids'] ?? [], $project->owner_id);

            $this->replaceMilestones($project, $data['milestones'] ?? []);

            Activity::log('project.created', [
                'module' => 'projects',
                'description' => "Created project {$project->title}",
                'properties' => ['project_id' => $project->id],
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
            $project->fill(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? null,
                'priority' => $data['priority'] ?? null,
                'color' => $data['color'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'budget' => $data['budget'] ?? null,
                'currency' => $data['currency'] ?? null,
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

            Activity::log('project.updated', [
                'module' => 'projects',
                'description' => "Updated project {$project->title}",
                'properties' => ['project_id' => $project->id],
            ]);

            return $project->load(['owner', 'members', 'milestones']);
        });
    }

    public function delete(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $title = $project->title;
            $id = $project->id;
            $project->delete();

            Activity::log('project.deleted', [
                'module' => 'projects',
                'description' => "Deleted project {$title}",
                'properties' => ['project_id' => $id],
            ]);
        });
    }

    public function toggleMilestone(Milestone $milestone): Milestone
    {
        $milestone->completed_at = $milestone->completed_at ? null : now();
        $milestone->save();

        $project = $milestone->project()->withCount(['milestones as total_milestones'])->first();
        $completed = Milestone::where('project_id', $project->id)->whereNotNull('completed_at')->count();
        $progress = $project->total_milestones > 0
            ? (int) round(($completed / $project->total_milestones) * 100)
            : $project->progress;

        $project->update(['progress' => $progress]);

        Activity::log($milestone->completed_at ? 'milestone.completed' : 'milestone.reopened', [
            'module' => 'projects',
            'description' => ($milestone->completed_at ? 'Completed milestone: ' : 'Reopened milestone: ').$milestone->title,
            'properties' => ['project_id' => $project->id, 'milestone_id' => $milestone->id],
        ]);

        return $milestone;
    }

    private function syncMembers(Project $project, array $userIds, ?int $ownerId): void
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

        $project->members()->sync($sync);
    }

    private function replaceMilestones(Project $project, array $milestones): void
    {
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
    }
}
