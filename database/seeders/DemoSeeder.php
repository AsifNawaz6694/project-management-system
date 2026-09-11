<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Sample content for local development and demos — projects, tasks, threads,
 * notifications and activity, hung off the real roster seeded by
 * OrganizationSeeder.
 *
 * Deliberately excluded from DatabaseSeeder so a production deployment gets an
 * empty workspace. Run it explicitly:
 *
 *   php artisan db:seed --class=Database\Seeders\DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProjectSeeder::class,
            TaskSeeder::class,
            CommunicationSeeder::class,
            NotificationSeeder::class,
            ActivitySeeder::class,
        ]);
    }
}
