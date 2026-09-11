<?php

namespace App\Modules\Workflow\Services;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowAssignment;
use App\Modules\Workflow\Models\WorkflowStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Write-side operations for workflows.
 *
 * The guards here exist because tasks reference a status by its *key*: renaming
 * a key or deleting a status in use would orphan live work.
 */
class WorkflowEditorService
{
    public function create(array $data): Workflow
    {
        return DB::transaction(function () use ($data) {
            $workflow = Workflow::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => false,
                'is_system' => false,
            ]);

            // A workflow with no statuses is unusable, so seed a minimal set.
            $seed = [
                ['key' => 'todo', 'name' => 'To do', 'category' => 'todo', 'color' => 'slate', 'is_initial' => true],
                ['key' => 'in_progress', 'name' => 'In progress', 'category' => 'in_progress', 'color' => 'amber'],
                ['key' => 'done', 'name' => 'Done', 'category' => 'done', 'color' => 'emerald'],
            ];

            foreach ($seed as $i => $row) {
                $workflow->statuses()->create($row + ['position' => $i + 1]);
            }

            $this->log('workflow.created', "Created workflow \"{$workflow->name}\"", $workflow);

            return $workflow->fresh('statuses');
        });
    }

    public function update(Workflow $workflow, array $data): Workflow
    {
        return DB::transaction(function () use ($workflow, $data) {
            $workflow->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            if (! empty($data['is_default'])) {
                Workflow::query()->where('id', '!=', $workflow->id)->update(['is_default' => false]);
                $workflow->update(['is_default' => true]);
            }

            $this->log('workflow.updated', "Updated workflow \"{$workflow->name}\"", $workflow);

            return $workflow->fresh(['statuses', 'transitions']);
        });
    }

    public function delete(Workflow $workflow): void
    {
        if ($workflow->is_default) {
            throw ValidationException::withMessages([
                'workflow' => 'The default workflow cannot be deleted. Make another workflow the default first.',
            ]);
        }

        $inUse = Project::query()->where('workflow_id', $workflow->id)->count();

        if ($inUse > 0) {
            throw ValidationException::withMessages([
                'workflow' => "{$inUse} project(s) still use this workflow. Move them to another workflow first.",
            ]);
        }

        $name = $workflow->name;
        $workflow->delete();

        $this->log('workflow.deleted', "Deleted workflow \"{$name}\"", null);
    }

    // ------------------------------------------------------------------ statuses

    public function addStatus(Workflow $workflow, array $data): WorkflowStatus
    {
        $key = $this->uniqueStatusKey($workflow, $data['key'] ?? $data['name']);

        $position = (int) $workflow->statuses()->max('position');

        $status = $workflow->statuses()->create([
            'key' => $key,
            'name' => $data['name'],
            'category' => $data['category'],
            'color' => $data['color'] ?? 'slate',
            'position' => $position + 1,
            'is_initial' => false,
        ]);

        $this->log('workflow.status-added', "Added status \"{$status->name}\" to \"{$workflow->name}\"", $workflow);

        return $status;
    }

    /**
     * Display attributes only. The key is deliberately immutable — tasks store
     * it, so changing it would strand every task sitting on that status.
     */
    public function updateStatus(WorkflowStatus $status, array $data): WorkflowStatus
    {
        return DB::transaction(function () use ($status, $data) {
            $status->update([
                'name' => $data['name'],
                'category' => $data['category'],
                'color' => $data['color'] ?? $status->color,
            ]);

            if (! empty($data['is_initial'])) {
                WorkflowStatus::query()
                    ->where('workflow_id', $status->workflow_id)
                    ->where('id', '!=', $status->id)
                    ->update(['is_initial' => false]);

                $status->update(['is_initial' => true]);
            }

            return $status->fresh();
        });
    }

    public function deleteStatus(WorkflowStatus $status): void
    {
        $workflow = $status->workflow;

        if ($workflow->statuses()->count() <= 1) {
            throw ValidationException::withMessages([
                'status' => 'A workflow must keep at least one status.',
            ]);
        }

        // Refuse while live work sits on it.
        $projectIds = Project::query()->where('workflow_id', $workflow->id)->pluck('id');

        $inUse = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', $status->key)
            ->count();

        if ($inUse > 0) {
            throw ValidationException::withMessages([
                'status' => "{$inUse} task(s) are currently in \"{$status->name}\". Move them to another status first.",
            ]);
        }

        if ($status->is_initial && $workflow->statuses()->count() > 1) {
            // Promote the next status so the workflow keeps a starting point.
            $workflow->statuses()
                ->where('id', '!=', $status->id)
                ->orderBy('position')
                ->first()
                ?->update(['is_initial' => true]);
        }

        $name = $status->name;
        $status->delete();

        $this->log('workflow.status-removed', "Removed status \"{$name}\" from \"{$workflow->name}\"", $workflow);
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorderStatuses(Workflow $workflow, array $orderedIds): void
    {
        DB::transaction(function () use ($workflow, $orderedIds) {
            foreach (array_values($orderedIds) as $i => $id) {
                WorkflowStatus::query()
                    ->where('workflow_id', $workflow->id)
                    ->whereKey($id)
                    ->update(['position' => $i + 1]);
            }
        });
    }

    // ------------------------------------------------------------------ transitions

    /**
     * Replace the whole transition set in one save.
     *
     * Editing edge-by-edge would make the matrix fiddly and leave the workflow
     * in half-valid states; a bulk replace keeps it atomic.
     *
     * @param  array<int, array{from: string|null, to: string, requires_comment?: bool, comment_label?: string|null, required_permission?: string|null}>  $rules
     */
    public function replaceTransitions(Workflow $workflow, array $rules): void
    {
        $byKey = $workflow->statuses()->get()->keyBy('key');

        DB::transaction(function () use ($workflow, $rules, $byKey) {
            $workflow->transitions()->delete();

            $seen = [];

            foreach ($rules as $rule) {
                $toKey = $rule['to'] ?? null;
                $fromKey = $rule['from'] ?? null;

                if (! $toKey || ! isset($byKey[$toKey])) {
                    continue;
                }

                if ($fromKey !== null && ! isset($byKey[$fromKey])) {
                    continue;
                }

                if ($fromKey !== null && $fromKey === $toKey) {
                    continue;
                }

                $signature = ($fromKey ?? '*').'>'.$toKey;
                if (isset($seen[$signature])) {
                    continue;
                }
                $seen[$signature] = true;

                $workflow->transitions()->create([
                    'from_status_id' => $fromKey === null ? null : $byKey[$fromKey]->id,
                    'to_status_id' => $byKey[$toKey]->id,
                    'name' => $byKey[$toKey]->name,
                    'requires_comment' => (bool) ($rule['requires_comment'] ?? false),
                    'comment_label' => ($rule['requires_comment'] ?? false)
                        ? (($rule['comment_label'] ?? null) ?: 'Add a reason for this change.')
                        : null,
                    'required_permission' => $rule['required_permission'] ?? null,
                ]);
            }

            $this->log(
                'workflow.transitions-updated',
                'Updated transition rules for "'.$workflow->name.'"',
                $workflow,
            );
        });
    }

    // ------------------------------------------------------------------ assignment

    /**
     * @param  array<int, int>  $projectIds
     */
    public function assignProjects(Workflow $workflow, array $projectIds): int
    {
        $count = Project::query()->whereIn('id', $projectIds)->update(['workflow_id' => $workflow->id]);

        $this->log(
            'workflow.assigned',
            "Assigned {$count} project(s) to workflow \"{$workflow->name}\"",
            $workflow,
        );

        return $count;
    }

    /**
     * Narrow these people to this chain, and release anyone dropped from the
     * list. Sync semantics, like the project assignment above: the submitted
     * set *is* the set, so unticking somebody hands them back the project's
     * full chain rather than leaving a stale row behind.
     *
     * @param  array<int, int>  $userIds
     */
    public function assignPeople(Workflow $workflow, array $userIds): int
    {
        return $this->syncAssignments($workflow, WorkflowAssignment::TYPE_USER, $userIds, 'person');
    }

    /**
     * @param  array<int, int>  $teamIds
     */
    public function assignTeams(Workflow $workflow, array $teamIds): int
    {
        return $this->syncAssignments($workflow, WorkflowAssignment::TYPE_TEAM, $teamIds, 'team');
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function syncAssignments(Workflow $workflow, string $type, array $ids, string $noun): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        DB::transaction(function () use ($workflow, $type, $ids) {
            WorkflowAssignment::query()
                ->where('workflow_id', $workflow->id)
                ->where('assignable_type', $type)
                ->whereNotIn('assignable_id', $ids ?: [0])
                ->delete();

            foreach ($ids as $id) {
                // A subject follows one chain, so claiming it here releases it
                // from whichever chain held it before.
                WorkflowAssignment::query()->updateOrCreate(
                    ['assignable_type' => $type, 'assignable_id' => $id],
                    ['workflow_id' => $workflow->id],
                );
            }
        });

        $count = count($ids);

        $this->log(
            'workflow.assigned-'.$noun,
            "{$count} {$noun}(s) now follow the \"{$workflow->name}\" chain",
            $workflow,
        );

        return $count;
    }

    // ------------------------------------------------------------------ helpers

    private function uniqueStatusKey(Workflow $workflow, string $source): string
    {
        $base = Str::snake(Str::ascii($source));
        $base = preg_replace('/[^a-z0-9_]/', '', $base) ?: 'status';
        $base = substr($base, 0, 34);

        $key = $base;
        $i = 2;

        while ($workflow->statuses()->where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }

        return $key;
    }

    private function log(string $action, string $description, ?Workflow $workflow): void
    {
        Activity::log($action, [
            'module' => 'workflows',
            'description' => $description,
            'properties' => $workflow ? ['workflow_id' => $workflow->id] : [],
        ]);
    }
}
