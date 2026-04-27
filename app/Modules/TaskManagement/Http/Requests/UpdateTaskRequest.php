<?php

namespace App\Modules\TaskManagement\Http\Requests;

use App\Modules\TaskManagement\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');
        $user = $this->user();

        if (! $user || ! $task) {
            return false;
        }

        if ($user->hasPermission('tasks.update')) {
            return true;
        }

        // Employees: can only update tasks assigned to them, status field only.
        if ($task->assignee_id === $user->id && $user->hasPermission('tasks.update-status')) {
            return $this->only(array_keys($this->rules())) === array_intersect_key(
                $this->only(array_keys($this->rules())),
                array_flip(['status']),
            );
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:8000'],
            'status' => ['sometimes', 'required', Rule::in(Task::STATUSES)],
            'priority' => ['sometimes', 'required', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'subtasks' => ['array'],
            'subtasks.*.title' => ['required_with:subtasks', 'string', 'max:200'],
            'subtasks.*.completed' => ['boolean'],
            'subtasks.*.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'subtasks.*.due_date' => ['nullable', 'date'],
        ];
    }
}
