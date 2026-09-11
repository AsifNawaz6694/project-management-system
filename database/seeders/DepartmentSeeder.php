<?php

namespace Database\Seeders;

use App\Modules\UserManagement\Models\Department;
use Illuminate\Database\Seeder;

/**
 * The three departments the organisation is split into (RBAC §3).
 *
 * Department membership is what distinguishes an HR or IT employee from a DTT
 * one — none of them carries extra permissions.
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'slug' => Department::DTT,
                'name' => 'DTT',
                'description' => 'Digital Technology & Transformation — product, engineering, QA and mobile delivery.',
            ],
            [
                'slug' => Department::HR,
                'name' => 'HR',
                'description' => 'People operations.',
            ],
            [
                'slug' => Department::IT,
                'name' => 'IT',
                'description' => 'Internal IT and infrastructure support.',
            ],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['slug' => $department['slug']],
                ['name' => $department['name'], 'description' => $department['description']],
            );
        }
    }
}
