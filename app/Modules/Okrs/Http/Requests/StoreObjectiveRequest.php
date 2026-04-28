<?php

namespace App\Modules\Okrs\Http\Requests;

use App\Modules\Okrs\Models\KeyResult;
use App\Modules\Okrs\Models\Objective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('okrs.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'period' => ['required', 'string', 'max:32'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(Objective::STATUSES)],
            'visibility' => ['required', Rule::in(Objective::VISIBILITY)],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'parent_id' => ['nullable', 'integer', 'exists:objectives,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'key_results' => ['array'],
            'key_results.*.title' => ['required_with:key_results', 'string', 'max:200'],
            'key_results.*.metric_type' => ['nullable', Rule::in(KeyResult::METRIC_TYPES)],
            'key_results.*.start_value' => ['nullable', 'numeric'],
            'key_results.*.target_value' => ['required_with:key_results', 'numeric'],
            'key_results.*.current_value' => ['nullable', 'numeric'],
            'key_results.*.unit' => ['nullable', 'string', 'max:32'],
            'key_results.*.owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
