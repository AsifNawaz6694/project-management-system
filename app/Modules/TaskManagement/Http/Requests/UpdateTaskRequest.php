<?php

namespace App\Modules\TaskManagement\Http\Requests;

use App\Modules\TaskManagement\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Full edit requires the update ability. A user who may only move status
     * (the assignee, or a member of the owning team) is handled by the dedicated
     * status endpoint, so this request no longer tries to infer intent from the
     * shape of the payload.
     */
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user()?->can('update', $task) ?? false);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:8000'],
            'status' => ['sometimes', 'required', 'string', 'max:40'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'priority' => ['sometimes', 'required', Rule::in(Task::PRIORITIES)],
            'task_type_id' => ['sometimes', 'nullable', 'integer', 'exists:task_types,id'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'estimate_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999'],
            'story_points' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999'],
            'sprint_id' => ['sometimes', 'nullable', 'integer', 'exists:sprints,id'],
            // Keyed by field id; the service decides which apply here.
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:teams,id'],
            'labels' => ['sometimes', 'nullable', 'array', 'max:20'],
            'labels.*' => ['nullable'],
            'subtasks' => ['sometimes', 'array', 'max:100'],
            'subtasks.*.id' => ['nullable', 'integer'],
            'subtasks.*.title' => ['required_with:subtasks', 'string', 'max:200'],
            'subtasks.*.description' => ['nullable', 'string', 'max:2000'],
            'subtasks.*.completed' => ['boolean'],
            'subtasks.*.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'subtasks.*.due_date' => ['nullable', 'date'],
        ];
    }
}
