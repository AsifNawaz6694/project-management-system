<?php

namespace App\Modules\ProjectManagement\Http\Requests;

use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('projects.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'priority' => ['required', Rule::in(Project::PRIORITIES)],
            'color' => ['nullable', Rule::in(Project::COLORS)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'milestones' => ['array'],
            'milestones.*.title' => ['required_with:milestones', 'string', 'max:160'],
            'milestones.*.description' => ['nullable', 'string', 'max:1000'],
            'milestones.*.due_date' => ['nullable', 'date'],
            'milestones.*.completed' => ['boolean'],
        ];
    }
}
