<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@raqtan.com')->first();
        $manager = User::where('email', 'manager@raqtan.com')->first();
        $employee = User::where('email', 'employee@raqtan.com')->first();

        if (! $admin || ! $manager || ! $employee) {
            return;
        }

        $entries = [
            ['user_id' => $admin->id, 'subject_user_id' => $manager->id, 'action' => 'user.created', 'module' => 'users', 'description' => 'Invited Sarah Mansoor as Manager'],
            ['user_id' => $admin->id, 'subject_user_id' => $employee->id, 'action' => 'user.created', 'module' => 'users', 'description' => 'Invited Hamza Ali as Employee'],
            ['user_id' => $manager->id, 'subject_user_id' => $manager->id, 'action' => 'auth.login', 'module' => 'auth', 'description' => 'Signed in'],
            ['user_id' => $employee->id, 'subject_user_id' => $employee->id, 'action' => 'auth.login', 'module' => 'auth', 'description' => 'Signed in'],
            ['user_id' => $admin->id, 'subject_user_id' => $admin->id, 'action' => 'role.permissions-updated', 'module' => 'roles', 'description' => 'Adjusted Manager permissions'],
            ['user_id' => $manager->id, 'subject_user_id' => $employee->id, 'action' => 'task.assigned', 'module' => 'tasks', 'description' => 'Assigned 3 tasks for the launch sprint'],
        ];

        foreach ($entries as $i => $entry) {
            Activity::create(array_merge($entry, [
                'created_at' => now()->subDays($i)->subHours(random_int(0, 8)),
                'updated_at' => now()->subDays($i),
                'ip_address' => '127.0.0.1',
            ]));
        }
    }
}
