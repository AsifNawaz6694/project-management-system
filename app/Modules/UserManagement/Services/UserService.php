<?php

namespace App\Modules\UserManagement\Services;

use App\Models\User;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\UserManagement\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'department' => $data['department'] ?? null,
                'status' => $data['status'] ?? 'active',
                'two_factor_enabled' => $data['two_factor_enabled'] ?? true,
                'email_verified_at' => now(),
            ]);

            $this->syncRoles($user, $data['roles'] ?? []);

            Activity::log('user.created', [
                'subject_user_id' => $user->id,
                'module' => 'users',
                'description' => "Created user {$user->name}",
            ]);

            return $user->load('roles');
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->fill(array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'department' => $data['department'] ?? null,
                'status' => $data['status'] ?? null,
            ], fn ($v) => $v !== null));

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            if (array_key_exists('two_factor_enabled', $data)) {
                $user->two_factor_enabled = (bool) $data['two_factor_enabled'];
            }

            $user->save();

            if (array_key_exists('roles', $data)) {
                $this->syncRoles($user, $data['roles']);
            }

            Activity::log('user.updated', [
                'subject_user_id' => $user->id,
                'module' => 'users',
                'description' => "Updated user {$user->name}",
            ]);

            return $user->load('roles');
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $name = $user->name;
            $id = $user->id;
            $user->delete();

            Activity::log('user.deleted', [
                'subject_user_id' => $id,
                'module' => 'users',
                'description' => "Deleted user {$name}",
            ]);
        });
    }

    private function syncRoles(User $user, array $roleSlugs): void
    {
        $ids = Role::whereIn('slug', $roleSlugs)->pluck('id')->all();
        $user->roles()->sync($ids);
    }
}
