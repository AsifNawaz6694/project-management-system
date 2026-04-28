<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            ProjectSeeder::class,
            TaskSeeder::class,
            ExpenseSeeder::class,
            CommunicationSeeder::class,
            NotificationSeeder::class,
            ActivitySeeder::class,
        ]);
    }
}
