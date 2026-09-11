<?php

namespace App\Modules\Workflow\Services;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowAssignment;
use App\Modules\Workflow\Models\WorkflowStatus;
use App\Modules\Workflow\Models\WorkflowTransition;
use Illuminate\Support\Collection;

/**
 * Resolves which statuses exist and which transitions are legal.
 *
 * Workflows change rarely and are read on every board render, so both the
 * workflow graph and the per-project resolution are memoised for the request.
 */
class WorkflowService
{
    /**
     * Workflows are read on every board render but change rarely, so they are
     * memoised. The cache is invalidated by model events (see
     * TaskEventServiceProvider) whenever a workflow, status or transition is
     * written — without that, an edit saved in the admin UI would appear not to
     * apply until the next request.
     *
     * @var array<int|string, Workflow|int|null>
     */
    private static array $cache = [];

    public static function flushCache(): void
    {
        static::$cache = [];
    }

    public function forProject(Project $project): Workflow
    {
        $workflowId = $project->workflow_id ?: $this->defaultWorkflowId();

        return $this->workflow((int) $workflowId);
    }

    private function defaultWorkflowId(): ?int
    {
        return static::$cache['default'] ??= Workflow::query()->where('is_default', true)->value('id');
    }

    public function forTask(Task $task): Workflow
    {
        $project = $task->relationLoaded('project') && $task->project
            ? $task->project
            : Project::query()->select('id', 'workflow_id')->find($task->project_id);

        return $project
            ? $this->forProject($project)
            : $this->defaultWorkflow();
    }

    public function defaultWorkflow(): Workflow
    {
        return $this->workflow((int) $this->defaultWorkflowId());
    }

    private function workflow(int $id): Workflow
    {
        return static::$cache[$id] ??= Workflow::query()
            ->with(['statuses', 'transitions'])
            ->findOrFail($id);
    }

    /**
     * The chain this person follows, or null when they follow the project's.
     *
     * A workflow can be assigned to a person or to a team (see
     * `workflow_assignments`). Their own assignment wins; failing that, the
     * oldest assignment among the teams they belong to — a person on two
     * narrowed teams needs one defined answer, not the first one the database
     * happens to return. With no assignment anywhere the method returns null
     * and nothing is narrowed, which is how the application ships.
     */
    public function chainFor(?User $user): ?Workflow
    {
        if (! $user) {
            return null;
        }

        $key = 'chain:'.$user->id;

        if (! array_key_exists($key, static::$cache)) {
            static::$cache[$key] = $this->resolveChainId($user);
        }

        $id = static::$cache[$key];

        return $id ? $this->workflow((int) $id) : null;
    }

    private function resolveChainId(User $user): ?int
    {
        $own = WorkflowAssignment::query()
            ->forUsers()
            ->where('assignable_id', $user->id)
            ->value('workflow_id');

        if ($own) {
            return (int) $own;
        }

        $teamIds = $user->teamIds();

        if (empty($teamIds)) {
            return null;
        }

        $viaTeam = WorkflowAssignment::query()
            ->forTeams()
            ->whereIn('assignable_id', $teamIds)
            ->orderBy('id')
            ->value('workflow_id');

        return $viaTeam ? (int) $viaTeam : null;
    }

    /**
     * May this person move work into this stage at all?
     *
     * The project's workflow still defines the graph; an assigned chain only
     * subtracts from it. A stage the chain does not name is not offered and not
     * accepted — and because the target must exist in the project's workflow
     * too, a chain can never introduce a stage the project does not have.
     */
    public function chainPermits(?User $user, string $statusKey): bool
    {
        $chain = $this->chainFor($user);

        return $chain === null || in_array($statusKey, $chain->statusKeys(), true);
    }

    /**
     * Every status in a project's workflow, board-ordered, shaped for the client.
     *
     * @return array<int, array<string, mixed>>
     */
    public function statusesFor(Project $project): array
    {
        return $this->forProject($project)->statuses
            ->map(fn (WorkflowStatus $s) => [
                'key' => $s->key,
                'name' => $s->name,
                'category' => $s->category,
                'color' => $s->color,
                'position' => $s->position,
                'is_initial' => $s->is_initial,
            ])->values()->all();
    }

    /**
     * @return array<int, string>
     */
    public function statusKeysFor(Project $project): array
    {
        return $this->forProject($project)->statusKeys();
    }

    public function initialStatusKey(Project $project): string
    {
        $workflow = $this->forProject($project);

        return ($workflow->statuses->firstWhere('is_initial', true)
            ?? $workflow->statuses->first())?->key ?? 'todo';
    }

    public function statusByKey(Workflow $workflow, ?string $key): ?WorkflowStatus
    {
        if ($key === null) {
            return null;
        }

        return $workflow->statuses->firstWhere('key', $key);
    }

    /**
     * Is `$to` reachable from `$from` in this workflow, for this user?
     *
     * An empty transition set means the workflow is unrestricted — every status
     * is reachable. That keeps simple boards simple: you only define transitions
     * when you actually want to constrain movement.
     */
    public function canTransition(Workflow $workflow, ?string $from, string $to, ?User $user = null): bool
    {
        if ($from === $to) {
            return $this->statusByKey($workflow, $to) !== null;
        }

        return $this->resolveTransition($workflow, $from, $to, $user) !== false;
    }

    /**
     * Find the transition rule governing a move.
     *
     * Returns the matching WorkflowTransition, `null` when the workflow is
     * unrestricted (no rules defined) and therefore imposes no requirements, or
     * `false` when the move is not permitted at all.
     *
     * @return WorkflowTransition|null|false
     */
    public function resolveTransition(Workflow $workflow, ?string $from, string $to, ?User $user = null)
    {
        $target = $this->statusByKey($workflow, $to);

        if (! $target) {
            return false;
        }

        // A person or team narrowed to a shorter chain is not offered — and
        // cannot commit — a move into a stage their chain leaves out. This sits
        // in front of the transition rules on purpose: every caller that asks
        // whether a move is legal comes through here, so the board, the status
        // menu and the write path can never disagree.
        if (! $this->chainPermits($user, $to)) {
            return false;
        }

        $transitions = $workflow->transitions;

        // An empty rule set means unrestricted movement.
        if ($transitions->isEmpty()) {
            return null;
        }

        $source = $this->statusByKey($workflow, $from);

        // Prefer an explicit from→to rule over a "from anywhere" rule, so a
        // specific edge can relax what the wildcard demands.
        $candidates = $transitions->filter(fn ($t) => (int) $t->to_status_id === (int) $target->id
            && ($t->from_status_id === null || ($source && (int) $t->from_status_id === (int) $source->id)));

        $match = $candidates->firstWhere('from_status_id', '!==', null) ?: $candidates->first();

        if (! $match) {
            return false;
        }

        if ($match->required_permission && $user && ! $user->hasPermission($match->required_permission)) {
            return false;
        }

        return $match;
    }

    /**
     * Does this move demand a written reason? Returns the prompt, or null.
     */
    public function requiredCommentLabel(Workflow $workflow, ?string $from, string $to, ?User $user = null): ?string
    {
        $transition = $this->resolveTransition($workflow, $from, $to, $user);

        if (! $transition instanceof WorkflowTransition || ! $transition->requires_comment) {
            return null;
        }

        return $transition->comment_label ?: 'Add a reason for this change.';
    }

    /**
     * Transitions available to this user, each flagged with whether it needs a
     * reason — so the client can prompt before submitting.
     *
     * @return array<int, array{key: string, name: string, requires_comment: bool, comment_label: string|null}>
     */
    public function transitionOptions(Task $task, ?User $user = null): array
    {
        $workflow = $this->forTask($task);

        return $workflow->statuses
            ->filter(fn (WorkflowStatus $s) => $s->key !== $task->status)
            ->map(function (WorkflowStatus $s) use ($workflow, $task, $user) {
                $transition = $this->resolveTransition($workflow, $task->status, $s->key, $user);

                if ($transition === false) {
                    return null;
                }

                $requires = $transition instanceof WorkflowTransition && $transition->requires_comment;

                return [
                    'key' => $s->key,
                    'name' => $s->name,
                    'requires_comment' => $requires,
                    'comment_label' => $requires ? ($transition->comment_label ?: 'Add a reason for this change.') : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Status keys this user may move the task to right now (excluding its current one).
     *
     * @return array<int, string>
     */
    public function availableTransitions(Task $task, ?User $user = null): array
    {
        $workflow = $this->forTask($task);

        return $workflow->statuses
            ->filter(fn (WorkflowStatus $s) => $s->key !== $task->status
                && $this->canTransition($workflow, $task->status, $s->key, $user))
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * Does this status key close the task?
     */
    public function isDoneStatus(Workflow $workflow, string $key): bool
    {
        return (bool) $this->statusByKey($workflow, $key)?->isDone();
    }

    /**
     * Status keys in the "done" bucket across every workflow — used by reporting
     * so completion counts stay correct when projects use different workflows.
     *
     * @return array<int, string>
     */
    public function doneStatusKeys(): array
    {
        return WorkflowStatus::query()
            ->where('category', WorkflowStatus::CATEGORY_DONE)
            ->distinct()
            ->pluck('key')
            ->all();
    }

    /**
     * @return Collection<int, WorkflowStatus>
     */
    public function allStatuses(): Collection
    {
        return WorkflowStatus::query()->orderBy('position')->get();
    }
}
