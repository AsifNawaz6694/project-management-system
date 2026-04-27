<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\UserManagement\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::all()->keyBy('slug');

        $defaults = [
            [
                'name' => 'Talha Kayani',
                'email' => 'admin@raqtan.com',
                'password' => '123456789',
                'job_title' => 'Workspace Owner',
                'department' => 'Leadership',
                'role' => Role::ADMIN,
            ],
            [
                'name' => 'Sarah Mansoor',
                'email' => 'manager@raqtan.com',
                'password' => '123456789',
                'job_title' => 'Engineering Manager',
                'department' => 'Engineering',
                'role' => Role::MANAGER,
            ],
            [
                'name' => 'Hamza Ali',
                'email' => 'employee@raqtan.com',
                'password' => '123456789',
                'job_title' => 'Software Engineer',
                'department' => 'Engineering',
                'role' => Role::EMPLOYEE,
            ],
        ];

        foreach ($defaults as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                    'job_title' => $data['job_title'],
                    'department' => $data['department'],
                    'status' => 'active',
                    'two_factor_enabled' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->roles()->sync([$roles[$data['role']]->id]);
        }

        $sampleEmployees = [
            ['name' => 'Yara Saleh', 'email' => 'yara@raqtan.com', 'job_title' => 'Product Designer', 'department' => 'Design'],
            ['name' => 'Omar Khalid', 'email' => 'omar@raqtan.com', 'job_title' => 'Backend Engineer', 'department' => 'Engineering'],
            ['name' => 'Layla Hassan', 'email' => 'layla@raqtan.com', 'job_title' => 'QA Engineer', 'department' => 'Engineering'],
            ['name' => 'Faisal Noor', 'email' => 'faisal@raqtan.com', 'job_title' => 'Data Analyst', 'department' => 'Operations'],
            ['name' => 'Mona Idris', 'email' => 'mona@raqtan.com', 'job_title' => 'Project Coordinator', 'department' => 'Operations', 'role' => Role::MANAGER],
            ['name' => 'Bilal Tariq', 'email' => 'bilal@raqtan.com', 'job_title' => 'Frontend Engineer', 'department' => 'Engineering'],
            ['name' => 'Aisha Rauf', 'email' => 'aisha@raqtan.com', 'job_title' => 'Customer Success', 'department' => 'Success'],
            ['name' => 'Zayd Mahmoud', 'email' => 'zayd@raqtan.com', 'job_title' => 'DevOps Engineer', 'department' => 'Engineering'],
        ];

        foreach ($sampleEmployees as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('123456789'),
                    'job_title' => $data['job_title'],
                    'department' => $data['department'],
                    'status' => 'active',
                    'two_factor_enabled' => false,
                    'email_verified_at' => now(),
                ],
            );

            $user->roles()->sync([$roles[$data['role'] ?? Role::EMPLOYEE]->id]);
        }
    }
}
