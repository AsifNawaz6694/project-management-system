<?php

namespace App\Modules\TaskManagement\Http\Requests;

use App\Modules\TaskManagement\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');
        $user = $this->user();

        if (! $user || ! $task instanceof Task) {
            return false;
        }

        if (! $user->hasPermission('tasks.log-time')) {
            return false;
        }

        return Task::visibleTo($user)->whereKey($task->id)->exists();
    }

    public function rules(): array
    {
        return [
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'started_at' => ['required', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'minutes.max' => 'A single time log cannot exceed 24 hours (1440 minutes). Split into multiple entries.',
            'started_at.before_or_equal' => 'You cannot log time in the future.',
        ];
    }
}
