<?php

namespace App\Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('roles.update') ?? false;
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'permissions' => ['sometimes', 'present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
            'slug' => [
                'sometimes', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('roles', 'slug')->ignore($role?->id),
            ],
        ];
    }
}
