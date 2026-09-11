<?php

namespace Database\Seeders;

use App\Modules\UserManagement\Models\PermissionScheme;
use App\Modules\UserManagement\Models\PermissionSchemeGrant as Grant;
use App\Modules\UserManagement\Services\ProjectPermissionResolver;
use Illuminate\Database\Seeder;

/**
 * Ships two schemes.
 *
 * "Open" is the default and grants everything to everyone who already holds the
 * permission on their workspace role — i.e. it reproduces the behaviour the
 * workspace had before schemes existed, so adopting them is a deliberate act.
 *
 * "Delivery lead" is the worked example: everyday work stays open, but the
 * destructive and administrative actions are reserved for the project owner,
 * its leads, and workspace managers.
 */
class PermissionSchemeSeeder extends Seeder
{
    public function run(): void
    {
        $this->open();
        $this->deliveryLead();
    }

    private function open(): void
    {
        $scheme = PermissionScheme::query()->updateOrCreate(
            ['name' => 'Open'],
            [
                'description' => 'Everyone who holds the permission on their role may use it in the project.',
                'is_default' => true,
                'is_system' => true,
            ],
        );

        $this->sync($scheme, array_fill_keys(
            ProjectPermissionResolver::GOVERNED,
            [[Grant::TYPE_EVERYONE, null]],
        ));
    }

    private function deliveryLead(): void
    {
        $scheme = PermissionScheme::query()->updateOrCreate(
            ['name' => 'Delivery lead'],
            [
                'description' => 'Day-to-day work is open to the team; destructive and administrative actions are reserved for the project owner, leads and workspace managers.',
                'is_default' => false,
                'is_system' => true,
            ],
        );

        $restricted = [
            [Grant::TYPE_PROJECT_OWNER, null],
            [Grant::TYPE_PROJECT_ROLE, 'lead'],
            [Grant::TYPE_WORKSPACE_ROLE, 'manager'],
        ];

        $team = [[Grant::TYPE_ANY_MEMBER, null], [Grant::TYPE_ASSIGNEE, null], [Grant::TYPE_REPORTER, null]];

        $this->sync($scheme, [
            'projects.update' => $restricted,
            'projects.delete' => [[Grant::TYPE_PROJECT_OWNER, null], [Grant::TYPE_WORKSPACE_ROLE, 'manager']],
            'projects.manage-members' => $restricted,
            'tasks.delete' => $restricted,
            'tasks.archive' => $restricted,
            'tasks.bulk-edit' => $restricted,
            'tasks.assign' => $restricted,
            'tasks.view' => $team,
            'tasks.create' => $team,
            'tasks.update' => $team,
            'tasks.update-status' => $team,
            'tasks.log-time' => $team,
            'tasks.link' => $team,
            'reports.export' => $restricted,
        ]);
    }

    /**
     * @param  array<string, array<int, array{0: string, 1: string|null}>>  $map
     */
    private function sync(PermissionScheme $scheme, array $map): void
    {
        $scheme->grants()->delete();

        $rows = [];
        $now = now();

        foreach ($map as $permission => $grants) {
            foreach ($grants as [$type, $value]) {
                $rows[] = [
                    'permission_scheme_id' => $scheme->id,
                    'permission' => $permission,
                    'grant_type' => $type,
                    'grant_value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        Grant::query()->insert($rows);
        ProjectPermissionResolver::flushCache();
    }
}
