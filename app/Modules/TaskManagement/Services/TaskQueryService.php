<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\User;
use App\Modules\TaskManagement\Models\Task;
use Illuminate\Database\Eloquent\Builder;

/**
 * One place that turns a filter array into a query.
 *
 * The task list, CSV export and saved filters all run through this so a saved
 * filter can never mean something different from the list it was saved from.
 */
class TaskQueryService
{
    /** Columns a client is allowed to sort by. */
    public const SORTABLE = [
        'created_at', 'updated_at', 'due_date', 'start_date',
        'priority', 'status', 'title', 'position', 'number',
    ];

    public const FILTER_KEYS = [
        'search', 'project', 'priority', 'assignee', 'team', 'status', 'label',
        'type', 'creator', 'due_from', 'due_to', 'overdue', 'archived', 'view',
        'sort', 'direction', 'unassigned',
    ];

    private const PRIORITY_ORDER = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function build(User $user, array $filters, bool $rootOnly = true): Builder
    {
        $query = Task::query()->visibleTo($user);

        if ($rootOnly) {
            $query->root();
        }

        // Archived work is hidden unless explicitly requested.
        if (! empty($filters['archived'])) {
            $query->archived();
        } else {
            $query->notArchived();
        }

        $this->applyFilters($query, $filters, $user);
        $this->applySort($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters, User $user): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $q->where(function (Builder $inner) use ($search) {
                    $inner->where('tasks.title', 'like', "%{$search}%")
                        ->orWhere('tasks.description', 'like', "%{$search}%");

                    // Bare number, or WEB-142 — match the human-readable key too.
                    if (preg_match('/^([A-Za-z][A-Za-z0-9]*)-(\d+)$/', trim($search), $m)) {
                        $inner->orWhere(function (Builder $k) use ($m) {
                            $k->where('tasks.number', (int) $m[2])
                                ->whereExists(function ($p) use ($m) {
                                    $p->selectRaw('1')->from('projects')
                                        ->whereColumn('projects.id', 'tasks.project_id')
                                        ->where('projects.key', strtoupper($m[1]));
                                });
                        });
                    } elseif (ctype_digit(trim($search))) {
                        $inner->orWhere('tasks.number', (int) $search)
                            ->orWhere('tasks.id', (int) $search);
                    }
                });
            })
            ->when($filters['project'] ?? null, fn (Builder $q, $slug) => $q->whereExists(
                fn ($p) => $p->selectRaw('1')->from('projects')
                    ->whereColumn('projects.id', 'tasks.project_id')
                    ->whereIn('projects.slug', (array) $slug)
            ))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->whereIn('tasks.priority', (array) $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->whereIn('tasks.status', (array) $v))
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->whereExists(
                fn ($t) => $t->selectRaw('1')->from('task_types')
                    ->whereColumn('task_types.id', 'tasks.task_type_id')
                    ->whereIn('task_types.key', (array) $v)
            ))
            ->when($filters['assignee'] ?? null, function (Builder $q, $v) use ($user) {
                $v === 'me'
                    ? $q->where('tasks.assignee_id', $user->id)
                    : $q->whereIn('tasks.assignee_id', array_map('intval', (array) $v));
            })
            ->when(! empty($filters['unassigned']), fn (Builder $q) => $q->whereNull('tasks.assignee_id'))
            ->when($filters['team'] ?? null, function (Builder $q, $v) use ($user) {
                $v === 'mine'
                    ? $q->whereIn('tasks.team_id', $user->teamIds() ?: [0])
                    : $q->whereIn('tasks.team_id', array_map('intval', (array) $v));
            })
            ->when($filters['creator'] ?? null, function (Builder $q, $v) use ($user) {
                $v === 'me'
                    ? $q->where('tasks.created_by_id', $user->id)
                    : $q->whereIn('tasks.created_by_id', array_map('intval', (array) $v));
            })
            ->when($filters['label'] ?? null, fn (Builder $q, $v) => $q->whereExists(
                fn ($l) => $l->selectRaw('1')->from('label_task')
                    ->join('labels', 'labels.id', '=', 'label_task.label_id')
                    ->whereColumn('label_task.task_id', 'tasks.id')
                    ->whereIn('labels.slug', (array) $v)
            ))
            ->when($filters['due_from'] ?? null, fn (Builder $q, $d) => $q->whereDate('tasks.due_date', '>=', $d))
            ->when($filters['due_to'] ?? null, fn (Builder $q, $d) => $q->whereDate('tasks.due_date', '<=', $d))
            ->when(! empty($filters['overdue']), fn (Builder $q) => $q
                ->whereNotNull('tasks.due_date')
                ->whereDate('tasks.due_date', '<', now())
                ->whereNull('tasks.completed_at'));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applySort(Builder $query, array $filters): Builder
    {
        $sort = $filters['sort'] ?? null;
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, self::SORTABLE, true)) {
            // Board order by default, which is what the Kanban expects.
            return $query->orderBy('tasks.position')->orderByDesc('tasks.id');
        }

        if ($sort === 'priority') {
            // Rank by severity rather than alphabetically.
            $cases = [];
            foreach (self::PRIORITY_ORDER as $key => $rank) {
                $cases[] = "WHEN '{$key}' THEN {$rank}";
            }

            return $query
                ->orderByRaw('CASE tasks.priority '.implode(' ', $cases).' ELSE 0 END '.$direction)
                ->orderByDesc('tasks.id');
        }

        // Nulls last for dates, so undated work does not crowd the top.
        if (in_array($sort, ['due_date', 'start_date'], true)) {
            return $query
                ->orderByRaw("CASE WHEN tasks.{$sort} IS NULL THEN 1 ELSE 0 END")
                ->orderBy("tasks.{$sort}", $direction)
                ->orderByDesc('tasks.id');
        }

        return $query->orderBy("tasks.{$sort}", $direction)->orderByDesc('tasks.id');
    }

    /**
     * Eager loads the list and board views need — kept here so every caller
     * gets the same set and no screen silently reintroduces an N+1.
     *
     * @return array<int, string>
     */
    public function listRelations(): array
    {
        return [
            // workflow_id is required: without it every project resolves to the
            // default workflow and the board offers illegal columns.
            'project:id,slug,key,title,color,workflow_id',
            'assignee:id,name,avatar',
            'creator:id,name,avatar',
            'team:id,name,slug,color',
            'type:id,key,name,icon,color',
            'labels:id,name,slug,color',
        ];
    }

    /**
     * Per-status counts for the current filter set, in one grouped query
     * rather than one COUNT per column.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function statusCounts(User $user, array $filters): array
    {
        $counts = $this->build($user, $filters)
            ->reorder()
            ->groupBy('tasks.status')
            ->selectRaw('tasks.status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'tasks.status')
            ->all();

        return array_map('intval', $counts);
    }

    /**
     * Strip anything that is not a recognised filter before persisting.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sanitise(array $input): array
    {
        return array_filter(
            array_intersect_key($input, array_flip(self::FILTER_KEYS)),
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}
