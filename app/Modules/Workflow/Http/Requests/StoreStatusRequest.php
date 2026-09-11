<?php

namespace App\Modules\Workflow\Http\Requests;

use App\Modules\Workflow\Http\Controllers\WorkflowController;
use App\Modules\Workflow\Models\WorkflowStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isAdmin() || $user->hasPermission('workflows.manage'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'category' => ['required', Rule::in(WorkflowStatus::CATEGORIES)],
            'color' => ['nullable', Rule::in(WorkflowController::COLORS)],
            'is_initial' => ['boolean'],
        ];
    }
}
