<?php

namespace App\Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', Rule::unique('roles', 'slug')],
            'description' => ['nullable', 'string', 'max:300'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ];
    }
}
