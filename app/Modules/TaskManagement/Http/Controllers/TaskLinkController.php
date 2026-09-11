<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskLink;
use App\Modules\TaskManagement\Services\TaskLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskLinkController extends Controller
{
    public function __construct(private readonly TaskLinkService $links) {}

    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('link', $task);

        $data = $request->validate([
            'target_task_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(TaskLink::SELECTABLE)],
        ]);

        // Scope the target to what this user may see, so linking cannot be used
        // to probe for tasks outside their visibility.
        $target = Task::query()->visibleTo($request->user())->findOrFail($data['target_task_id']);

        $this->links->link($task, $target, $data['type'], $request->user());

        return back()->with('status', 'Tasks linked.');
    }

    public function destroy(Request $request, Task $task, TaskLink $link): RedirectResponse
    {
        $this->authorize('link', $task);

        if ($link->source_task_id !== $task->id) {
            abort(404);
        }

        $this->links->unlink($link, $request->user());

        return back()->with('status', 'Link removed.');
    }

    /**
     * Type-ahead for the link picker, scoped to what the user may see.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $exclude = $request->integer('exclude');

        $results = Task::query()
            ->visibleTo($request->user())
            ->notArchived()
            ->when($exclude, fn ($q) => $q->where('tasks.id', '!=', $exclude))
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('tasks.title', 'like', "%{$term}%");
                    if (ctype_digit($term)) {
                        $inner->orWhere('tasks.number', (int) $term);
                    }
                });
            })
            ->with('project:id,key')
            ->orderByDesc('tasks.id')
            ->limit(15)
            ->get(['id', 'number', 'project_id', 'title', 'status', 'priority']);

        return response()->json(
            $results->map(fn (Task $t) => [
                'id' => $t->id,
                'key' => $t->key_label,
                'title' => $t->title,
                'status' => $t->status,
            ])
        );
    }
}
