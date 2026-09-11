<?php

namespace App\Modules\UserManagement\Http\Requests;

use App\Modules\UserManagement\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * The base permission gates the request; granting roles or raw permissions
     * needs the narrower grant on top, so a user-editor cannot quietly escalate
     * someone (RBAC §15 — enforced server-side, not by hiding the control).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->hasPermission('users.create')) {
            return false;
        }

        if ($this->has('roles') && ! $user->hasPermission('users.assign-roles')) {
            return false;
        }

        return ! $this->has('permissions') || $user->hasPermission('users.assign-permissions');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['nullable', Rule::in(['active', 'invited', 'suspended'])],
            'two_factor_enabled' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in([Role::ADMIN, Role::MANAGER, Role::EMPLOYEE])],
            // Direct grants: the responsibilities layered on top of a role.
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ];
    }
}
