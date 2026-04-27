<?php

namespace App\Modules\TaskManagement\Http\Requests;

use App\Modules\TaskManagement\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusChangeRequest extends FormRequest
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

        return $task->assignee_id === $user->id && $user->hasPermission('tasks.update-status');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Task::STATUSES)],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
