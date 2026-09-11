<?php

namespace App\Modules\TaskManagement\Http\Requests;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\SubtaskRollupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            // Scoped to projects the user can actually see, rather than any id
            // that happens to exist.
            'project_id' => [
                'required', 'integer',
                Rule::exists('projects', 'id')->where(
                    fn ($q) => $q->whereIn('id', Project::visibleTo($user)->select('id'))
                ),
            ],
            'task_type_id' => ['nullable', 'integer', 'exists:task_types,id'],
            'parent_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:8000'],
            'status' => ['nullable', 'string', 'max:40'],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'estimate_minutes' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'story_points' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
            // Keyed by field id; the service decides which apply here.
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'labels' => ['nullable', 'array', 'max:20'],
            'labels.*' => ['nullable'],
            'subtasks' => ['array', 'max:100'],
            'subtasks.*.id' => ['nullable', 'integer'],
            'subtasks.*.title' => ['required_with:subtasks', 'string', 'max:200'],
            'subtasks.*.description' => ['nullable', 'string', 'max:2000'],
            'subtasks.*.completed' => ['boolean'],
            'subtasks.*.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'subtasks.*.due_date' => ['nullable', 'date'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $parentId = $this->input('parent_task_id');
            $projectId = $this->input('project_id');

            if (! $parentId || ! $projectId) {
                return;
            }

            $parent = Task::query()->find($parentId);

            if (! $parent) {
                return;
            }

            // A subtask must live in its parent's project.
            if ((int) $parent->project_id !== (int) $projectId) {
                $v->errors()->add('parent_task_id', 'The parent task belongs to a different project.');
            }

            // Nesting is allowed, but bounded: an unbounded tree makes every
            // board query and roll-up unpredictable.
            $rollup = app(SubtaskRollupService::class);

            if ($rollup->depthOf($parent) + 1 > SubtaskRollupService::MAX_DEPTH - 1) {
                $v->errors()->add(
                    'parent_task_id',
                    'Sub-tasks can only be nested '.SubtaskRollupService::MAX_DEPTH.' levels deep.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'project_id.exists' => 'You do not have access to that project.',
        ];
    }
}
