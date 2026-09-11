<?php

namespace Database\Seeders;

use App\Modules\Teams\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Delivery teams inside DTT.
 *
 * Teams are the scope unit for a lead: leading Backend and UI is what gives
 * that person reach over Backend and UI work, rather than a workspace-wide
 * permission. Membership is assigned by OrganizationSeeder, which runs after
 * the users exist.
 */
class TeamSeeder extends Seeder
{
    public const BACKEND = 'backend';

    public const UI = 'ui';

    public const QA = 'qa';

    public const MOBILE = 'mobile';

    public function run(): void
    {
        $teams = [
            ['slug' => self::BACKEND, 'name' => 'Backend', 'color' => 'blue', 'description' => 'Server-side delivery for DTT products.'],
            ['slug' => self::UI, 'name' => 'UI', 'color' => 'violet', 'description' => 'Interface and front-end delivery for DTT products.'],
            ['slug' => self::QA, 'name' => 'QA', 'color' => 'emerald', 'description' => 'Quality assurance and release verification.'],
            ['slug' => self::MOBILE, 'name' => 'Mobile', 'color' => 'amber', 'description' => 'Mobile application delivery.'],
        ];

        foreach ($teams as $team) {
            Team::updateOrCreate(
                ['slug' => $team['slug']],
                ['name' => $team['name'], 'color' => $team['color'], 'description' => $team['description']],
            );
        }
    }
}
