<?php

namespace App\Modules\TaskManagement\Http\Requests;

use App\Modules\TaskManagement\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class StatusChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user()?->can('changeStatus', $task) ?? false);
    }

    /**
     * The status value itself is validated against the project's workflow in
     * TaskService, which also enforces the transition rules — so this only
     * checks shape.
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'max:40'],
            'position' => ['nullable', 'integer', 'min:0'],
            // Required only when the workflow transition demands it; enforced
            // in TaskService so the rule lives with the workflow.
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
