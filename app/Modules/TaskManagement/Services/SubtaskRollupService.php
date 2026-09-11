<?php

namespace App\Modules\TaskManagement\Services;

use App\Modules\TaskManagement\Models\Task;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Support\Collection;

/**
 * Subtree arithmetic: how much of a parent's work is actually finished, and how
 * deep the tree is allowed to go.
 *
 * Subtasks were a single flat level. Nesting them makes two things necessary:
 * a cycle guard (a task must never become its own ancestor) and a depth cap,
 * because an unbounded tree makes every board query unpredictable.
 */
class SubtaskRollupService
{
    /**
     * Levels of task *including* the root, so 3 allows
     * root → child → grandchild and refuses a fourth.
     */
    public const MAX_DEPTH = 3;

    /** @var array<int, string>|null */
    private ?array $doneKeys = null;

    public function __construct(private readonly WorkflowService $workflows) {}

    /**
     * @return array<int, string>
     */
    private function doneKeys(): array
    {
        return $this->doneKeys ??= $this->workflows->doneStatusKeys();
    }

    /**
     * How far below a root this task sits.
     */
    public function depthOf(Task $task): int
    {
        $depth = 0;
        $parentId = $task->parent_task_id;
        $seen = [];

        while ($parentId !== null && $depth <= self::MAX_DEPTH + 1) {
            if (isset($seen[$parentId])) {
                break; // Corrupt data: stop rather than spin.
            }

            $seen[$parentId] = true;
            $parentId = Task::query()->whereKey($parentId)->value('parent_task_id');
            $depth++;
        }

        return $depth;
    }

    /**
     * May $child be placed under $parent?
     *
     * False when it would exceed the depth cap, or when the parent is already
     * somewhere beneath the child — the cycle that would otherwise make every
     * tree walk infinite.
     */
    public function canNest(Task $child, ?Task $parent): bool
    {
        if ($parent === null) {
            return true; // Promoting to root is always allowed.
        }

        if ($parent->id === $child->id) {
            return false;
        }

        if ($this->isDescendantOf($parent, $child)) {
            return false;
        }

        // depthOf() is a zero-based index, so the deepest allowed is MAX_DEPTH - 1.
        return $this->depthOf($parent) + 1 <= self::MAX_DEPTH - 1;
    }

    /**
     * Is $candidate somewhere in the subtree beneath $ancestor?
     */
    public function isDescendantOf(Task $candidate, Task $ancestor): bool
    {
        $parentId = $candidate->parent_task_id;
        $guard = 0;

        while ($parentId !== null && $guard++ <= self::MAX_DEPTH + 2) {
            if ($parentId === $ancestor->id) {
                return true;
            }

            $parentId = Task::query()->whereKey($parentId)->value('parent_task_id');
        }

        return false;
    }

    /**
     * Every task beneath this one, to the depth cap.
     *
     * MySQL 5.7 has no recursive CTE, so this is a bounded breadth-first walk —
     * at most MAX_DEPTH queries regardless of how wide the tree is.
     *
     * @return Collection<int, Task>
     */
    public function descendants(Task $task): Collection
    {
        $all = collect();
        $frontier = [$task->id];

        for ($level = 0; $level < self::MAX_DEPTH && $frontier !== []; $level++) {
            $children = Task::query()
                ->whereIn('parent_task_id', $frontier)
                ->get();

            if ($children->isEmpty()) {
                break;
            }

            $all = $all->concat($children);
            $frontier = $children->pluck('id')->all();
        }

        return $all;
    }

    /**
     * Completion of a parent, measured across its whole subtree.
     *
     * @return array{total: int, done: int, percent: int}
     */
    public function progressOf(Task $task): array
    {
        $descendants = $this->descendants($task);

        if ($descendants->isEmpty()) {
            return ['total' => 0, 'done' => 0, 'percent' => 0];
        }

        $doneKeys = $this->doneKeys();
        $done = $descendants->filter(fn (Task $t) => in_array($t->status, $doneKeys, true))->count();

        return [
            'total' => $descendants->count(),
            'done' => $done,
            'percent' => (int) round($done / $descendants->count() * 100),
        ];
    }

    /**
     * True when every subtask of this task is finished.
     *
     * A task with no subtasks answers false: "all of nothing is done" is not a
     * useful statement, and callers use this to decide whether to nudge.
     */
    public function allChildrenDone(Task $task): bool
    {
        $children = Task::query()->where('parent_task_id', $task->id)->get(['id', 'status']);

        if ($children->isEmpty()) {
            return false;
        }

        $doneKeys = $this->doneKeys();

        return $children->every(fn (Task $c) => in_array($c->status, $doneKeys, true));
    }

    /**
     * True when a parent still has unfinished work beneath it.
     */
    public function hasOpenChildren(Task $task): bool
    {
        $doneKeys = $this->doneKeys();

        return Task::query()
            ->where('parent_task_id', $task->id)
            ->when($doneKeys !== [], fn ($q) => $q->whereNotIn('status', $doneKeys))
            ->exists();
    }
}
