<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production seed: everything a fresh deployment needs and nothing it does not.
 *
 * The order matters — permissions before roles that reference them, departments
 * and teams before the people assigned to them.
 *
 * Every seeder here is idempotent, so `php artisan db:seed` may be re-run after
 * a release to pick up new permissions without disturbing live data.
 *
 * Sample projects, tasks, comments and notifications are NOT part of this run.
 * They live in DemoSeeder: `php artisan db:seed --class=Database\Seeders\DemoSeeder`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DepartmentSeeder::class,
            TeamSeeder::class,
            OrganizationSeeder::class,
            WorkflowSeeder::class,
            PermissionSchemeSeeder::class,
        ]);
    }
}
