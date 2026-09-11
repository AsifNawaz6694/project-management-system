<?php

namespace App\Modules\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransitionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isAdmin() || $user->hasPermission('workflows.manage'));
    }

    public function rules(): array
    {
        return [
            'transitions' => ['present', 'array'],
            'transitions.*.from' => ['nullable', 'string', 'max:40'],
            'transitions.*.to' => ['required', 'string', 'max:40'],
            'transitions.*.requires_comment' => ['boolean'],
            'transitions.*.comment_label' => ['nullable', 'string', 'max:200'],
            'transitions.*.required_permission' => ['nullable', 'string', 'max:80'],
        ];
    }
}
