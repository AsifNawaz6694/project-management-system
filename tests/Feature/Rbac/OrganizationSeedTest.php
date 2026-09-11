<?php

use App\Models\User;
use App\Modules\Teams\Models\Team;
use App\Modules\UserManagement\Models\Department;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Services\PermissionRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * The acceptance criteria from RBAC.txt §20, expressed as tests.
 *
 * These lock the *shape* of the organisation — who exists, what they hold, and
 * crucially what they do not hold — so a future edit to the permission sets
 * cannot silently hand a team member the keys to user administration.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function seededUser(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail()->fresh(['roles.permissions', 'directPermissions', 'teams', 'department']);
}

it('seeds the three departments and four delivery teams', function () {
    expect(Department::query()->pluck('slug')->sort()->values()->all())
        ->toBe(['dtt', 'hr', 'it']);

    expect(Team::query()->pluck('slug')->sort()->values()->all())
        ->toBe(['backend', 'mobile', 'qa', 'ui']);
});

it('keeps the role list short: super admin, manager, employee', function () {
    expect(Role::query()->orderBy('id')->pluck('slug')->all())
        ->toBe([Role::ADMIN, Role::MANAGER, Role::EMPLOYEE]);

    expect(Role::query()->where('slug', Role::ADMIN)->value('name'))->toBe('Super Admin');
});

it('seeds exactly the roster and nothing else', function () {
    expect(User::query()->count())->toBe(19);

    // Everyone belongs to a department and holds exactly one workspace role.
    User::query()->with('roles')->get()->each(function (User $user) {
        expect($user->department_id)->not->toBeNull()
            ->and($user->roles)->toHaveCount(1)
            ->and($user->status)->toBe('active');
    });
});

it('gives the super admin unrestricted access', function () {
    $asif = seededUser('asif@bargoventures.com');

    expect($asif->isAdmin())->toBeTrue()
        ->and($asif->permissionSlugs())->toHaveCount(Permission::query()->count())
        ->and(count(PermissionRegistry::flat()))->toBe(Permission::query()->count());

    // Every registered permission resolves true, including ones nobody else has.
    foreach (PermissionRegistry::flat() as $permission) {
        expect($asif->hasPermission($permission['slug']))->toBeTrue();
    }
});

it('lets the DTT managers run delivery but not security configuration', function () {
    foreach (['saad@bargoventures.com', 'marcin@bargoventures.com'] as $email) {
        $manager = seededUser($email);

        expect($manager->isManager())->toBeTrue()
            ->and($manager->isAdmin())->toBeFalse();

        // Work management across the whole organisation.
        foreach (['projects.view-all', 'projects.create', 'projects.delete', 'projects.manage-members',
            'tasks.view-all', 'tasks.assign', 'tasks.delete', 'tasks.bulk-edit',
            'teams.manage', 'reports.view-all', 'reports.export'] as $slug) {
            expect($manager->hasPermission($slug))->toBeTrue("manager should hold {$slug}");
        }

        // Security-critical surface stays with the Super Admin (RBAC §8), and
        // so does everything the sidebar files under People & Goals and
        // Administration — read access included.
        foreach (['users.view', 'users.create', 'users.update', 'users.delete', 'users.assign-roles',
            'roles.view', 'roles.update', 'roles.create', 'permission-schemes.view', 'permission-schemes.manage',
            'departments.view', 'departments.create', 'departments.delete', 'settings.view', 'settings.update',
            'workflows.view', 'workflows.manage', 'automations.view', 'automations.manage',
            'meetings.view', 'meetings.manage', 'okrs.view', 'okrs.manage',
            'feedback.view', 'feedback.manage'] as $slug) {
            expect($manager->hasPermission($slug))->toBeFalse("manager must not hold {$slug}");
        }
    }
});

it('makes Adnan a team lead rather than a workspace manager', function () {
    $adnan = seededUser('adnan@bargoventures.com');

    expect($adnan->isEmployee())->toBeTrue()
        ->and($adnan->isManager())->toBeFalse()
        ->and($adnan->isAdmin())->toBeFalse();

    // He can manage work…
    foreach (['tasks.create', 'tasks.update', 'tasks.assign', 'tasks.bulk-edit', 'tasks.link'] as $slug) {
        expect($adnan->hasPermission($slug))->toBeTrue("Adnan should hold {$slug}");
    }

    // …but his reach is scoped by the teams he leads, not by a blanket grant,
    // and the Super Admin surfaces stay out of the bundle entirely.
    foreach (['tasks.view-all', 'projects.view-all', 'tasks.delete', 'teams.manage',
        'users.view', 'users.create', 'roles.update', 'workflows.view',
        'meetings.view', 'okrs.view', 'feedback.view'] as $slug) {
        expect($adnan->hasPermission($slug))->toBeFalse("Adnan must not hold {$slug}");
    }

    $led = Team::query()->whereIn('id', $adnan->ledTeamIds())->pluck('slug')->sort()->values()->all();
    expect($led)->toBe(['backend', 'ui']);
});

it('leaves HR and IT staff as plain employees', function () {
    foreach ([
        'farhan@bargoventures.com' => 'HR',
        'waqas@bargoventures.com' => 'IT',
        'shelbayah@bargoventures.com' => 'IT',
    ] as $email => $department) {
        $user = seededUser($email);

        expect($user->department->name)->toBe($department)
            ->and($user->isEmployee())->toBeTrue()
            ->and($user->directPermissions)->toHaveCount(0);

        foreach (['tasks.assign', 'tasks.delete', 'tasks.view-all', 'projects.view-all',
            'projects.create', 'users.view', 'users.create', 'roles.view',
            'departments.update', 'teams.manage'] as $slug) {
            expect($user->hasPermission($slug))->toBeFalse("{$email} must not hold {$slug}");
        }
    }
});

it('gives regular team members only their own work', function () {
    $member = seededUser('talha.kayani@bargoventures.com');

    expect($member->isEmployee())->toBeTrue()
        ->and($member->teams->pluck('slug')->all())->toBe(['backend'])
        ->and($member->ledTeamIds())->toBe([]);

    // Can do their job.
    foreach (['tasks.view', 'tasks.create', 'tasks.update-status', 'tasks.log-time', 'projects.view'] as $slug) {
        expect($member->hasPermission($slug))->toBeTrue("member should hold {$slug}");
    }

    // Cannot reach past it (RBAC §11).
    foreach (['tasks.view-all', 'tasks.assign', 'tasks.delete', 'projects.view-all',
        'users.view', 'roles.view', 'departments.view', 'permission-schemes.view',
        'workflows.view', 'meetings.view', 'okrs.view', 'feedback.view'] as $slug) {
        expect($member->hasPermission($slug))->toBeFalse("member must not hold {$slug}");
    }
});

it('reserves People & Goals and administration for the Super Admin', function () {
    $reserved = PermissionRegistry::superAdminOnly();

    expect($reserved)->not->toBeEmpty();

    // No other role carries any of them…
    foreach (Role::query()->where('slug', '!=', Role::ADMIN)->with('permissions')->get() as $role) {
        $held = array_intersect($role->permissions->pluck('slug')->all(), $reserved);
        expect($held)->toBe([], "{$role->slug} must hold none of the reserved permissions");
    }

    // …nor does any responsibility bundle hand one out directly.
    foreach (User::query()->with(['roles', 'directPermissions'])->get() as $user) {
        if ($user->isAdmin()) {
            continue;
        }

        $held = array_intersect($user->permissionSlugs(), $reserved);
        expect($held)->toBe([], "{$user->email} must hold none of the reserved permissions");
    }

    // The Super Admin still holds every one of them.
    $asif = seededUser('asif@bargoventures.com');
    foreach ($reserved as $slug) {
        expect($asif->hasPermission($slug))->toBeTrue("super admin should hold {$slug}");
    }
});

it('is idempotent and never resets an existing password', function () {
    $before = User::query()->where('email', 'saad@bargoventures.com')->firstOrFail();

    // Simulate the account having been used since the first deploy.
    $before->forceFill(['password' => Hash::make('a-password-they-chose')])->save();

    $counts = fn () => [
        'users' => User::query()->count(),
        'roles' => Role::query()->count(),
        'permissions' => Permission::query()->count(),
        'departments' => Department::query()->count(),
        'teams' => Team::query()->count(),
        'grants' => DB::table('permission_user')->count(),
        'memberships' => DB::table('team_user')->count(),
    ];

    $first = $counts();

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect($counts())->toBe($first);

    $after = User::query()->where('email', 'saad@bargoventures.com')->firstOrFail();
    expect(Hash::check('a-password-they-chose', $after->password))->toBeTrue();
});
