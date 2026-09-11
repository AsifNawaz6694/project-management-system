<?php

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\PermissionScheme;
use App\Modules\UserManagement\Models\PermissionSchemeGrant as Grant;
use App\Modules\UserManagement\Services\ProjectPermissionResolver;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/**
 * Replaces a scheme's rules for one permission and returns the scheme.
 *
 * @param  array<int, array{0: string, 1: string|null}>  $grants
 */
function scheme(string $name, string $permission, array $grants): PermissionScheme
{
    $scheme = PermissionScheme::query()->create(['name' => $name, 'description' => null]);

    foreach ($grants as [$type, $value]) {
        $scheme->grants()->create([
            'permission' => $permission,
            'grant_type' => $type,
            'grant_value' => $value,
        ]);
    }

    ProjectPermissionResolver::flushCache();

    return $scheme;
}

function resolver(): ProjectPermissionResolver
{
    return app(ProjectPermissionResolver::class);
}

/*
|--------------------------------------------------------------------------
| The two gates
|--------------------------------------------------------------------------
*/

it('never lets a scheme hand out a permission the role does not hold', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.view']); // no tasks.delete
    $project = TaskHelpers::project($owner);

    $project->forceFill(['permission_scheme_id' => scheme('Wide open', 'tasks.delete', [
        [Grant::TYPE_EVERYONE, null],
    ])->id])->save();

    expect(resolver()->allows($user, $project, 'tasks.delete'))->toBeFalse();
});

it('lets a permission through when the scheme writes no rule for it', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.delete']);
    $project = TaskHelpers::project($owner);

    // The scheme governs tasks.archive only — tasks.delete is untouched.
    $project->forceFill(['permission_scheme_id' => scheme('Archive only', 'tasks.archive', [
        [Grant::TYPE_PROJECT_OWNER, null],
    ])->id])->save();

    expect(resolver()->allows($user, $project, 'tasks.delete'))->toBeTrue();
});

it('denies a governed permission when no grant matches', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.delete']);
    $project = TaskHelpers::project($owner);

    $project->forceFill(['permission_scheme_id' => scheme('Owner only', 'tasks.delete', [
        [Grant::TYPE_PROJECT_OWNER, null],
    ])->id])->save();

    expect(resolver()->allows($user, $project, 'tasks.delete'))->toBeFalse();
});

it('ignores schemes entirely for an admin', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $project->forceFill(['permission_scheme_id' => scheme('Nobody', 'tasks.delete', [
        [Grant::TYPE_USER, '999999'],
    ])->id])->save();

    expect(resolver()->allows($admin, $project, 'tasks.delete'))->toBeTrue();
});

it('leaves workspace-wide permissions alone', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.manage-labels']);
    $project = TaskHelpers::project($owner);

    // Not in the governed list, so a scheme can never touch it.
    expect(ProjectPermissionResolver::isGoverned('tasks.manage-labels'))->toBeFalse();
    expect(resolver()->allows($user, $project, 'tasks.manage-labels'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Grant types
|--------------------------------------------------------------------------
*/

it('matches the project owner', function () {
    $owner = TaskHelpers::userWith(['tasks.delete']);
    $project = TaskHelpers::project($owner);

    $project->forceFill(['permission_scheme_id' => scheme('Owner only', 'tasks.delete', [
        [Grant::TYPE_PROJECT_OWNER, null],
    ])->id])->save();

    expect(resolver()->allows($owner, $project, 'tasks.delete'))->toBeTrue();
});

it('matches a specific project role and not the others', function () {
    $owner = TaskHelpers::admin();
    $lead = TaskHelpers::userWith(['tasks.delete']);
    $member = TaskHelpers::userWith(['tasks.delete']);
    $project = TaskHelpers::project($owner);

    $project->members()->attach([$lead->id => ['role' => 'lead'], $member->id => ['role' => 'member']]);

    $project->forceFill(['permission_scheme_id' => scheme('Leads only', 'tasks.delete', [
        [Grant::TYPE_PROJECT_ROLE, 'lead'],
    ])->id])->save();

    expect(resolver()->allows($lead, $project, 'tasks.delete'))->toBeTrue();
    expect(resolver()->allows($member, $project, 'tasks.delete'))->toBeFalse();
});

it('matches any member regardless of their project role', function () {
    $owner = TaskHelpers::admin();
    $member = TaskHelpers::userWith(['tasks.update']);
    $outsider = TaskHelpers::userWith(['tasks.update']);
    $project = TaskHelpers::project($owner);

    $project->members()->attach([$member->id => ['role' => 'viewer']]);

    $project->forceFill(['permission_scheme_id' => scheme('Members', 'tasks.update', [
        [Grant::TYPE_ANY_MEMBER, null],
    ])->id])->save();

    expect(resolver()->allows($member, $project, 'tasks.update'))->toBeTrue();
    expect(resolver()->allows($outsider, $project, 'tasks.update'))->toBeFalse();
});

it('matches the assignee of the task being acted on', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.update']);
    $other = TaskHelpers::userWith(['tasks.update']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $project->forceFill(['permission_scheme_id' => scheme('Assignee', 'tasks.update', [
        [Grant::TYPE_ASSIGNEE, null],
    ])->id])->save();

    expect(resolver()->allows($assignee, $project, 'tasks.update', $task))->toBeTrue();
    expect(resolver()->allows($other, $project, 'tasks.update', $task))->toBeFalse();
    // With no task in hand the assignee rule cannot match anyone.
    expect(resolver()->allows($assignee, $project, 'tasks.update'))->toBeFalse();
});

it('matches the reporter who raised the task', function () {
    $reporter = TaskHelpers::userWith(['tasks.update']);
    $project = TaskHelpers::project(TaskHelpers::admin());

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $reporter->id,
        'assignee_id' => null,
    ]);

    $project->forceFill(['permission_scheme_id' => scheme('Reporter', 'tasks.update', [
        [Grant::TYPE_REPORTER, null],
    ])->id])->save();

    expect(resolver()->allows($reporter, $project, 'tasks.update', $task))->toBeTrue();
});

it('matches a named user', function () {
    $user = TaskHelpers::userWith(['tasks.delete']);
    $project = TaskHelpers::project(TaskHelpers::admin());

    $project->forceFill(['permission_scheme_id' => scheme('One person', 'tasks.delete', [
        [Grant::TYPE_USER, (string) $user->id],
    ])->id])->save();

    expect(resolver()->allows($user, $project, 'tasks.delete'))->toBeTrue();
});

it('matches a workspace role', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.delete'], 'delivery-lead');
    $project = TaskHelpers::project($owner);

    $roleSlug = $user->roles->first()->slug;

    $project->forceFill(['permission_scheme_id' => scheme('By role', 'tasks.delete', [
        [Grant::TYPE_WORKSPACE_ROLE, $roleSlug],
    ])->id])->save();

    expect(resolver()->allows($user, $project, 'tasks.delete'))->toBeTrue();
});

it('accepts a user matching any one of several grants', function () {
    $owner = TaskHelpers::admin();
    $lead = TaskHelpers::userWith(['tasks.delete']);
    $project = TaskHelpers::project($owner);

    $project->members()->attach([$lead->id => ['role' => 'lead']]);

    $project->forceFill(['permission_scheme_id' => scheme('Several', 'tasks.delete', [
        [Grant::TYPE_PROJECT_OWNER, null],
        [Grant::TYPE_PROJECT_ROLE, 'lead'],
    ])->id])->save();

    expect(resolver()->allows($lead, $project, 'tasks.delete'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Default scheme and fallback
|--------------------------------------------------------------------------
*/

it('falls back to the default scheme when a project has none', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.delete']);
    $project = TaskHelpers::project($owner);

    expect($project->permission_scheme_id)->toBeNull();

    // The seeded default is fully open, so this passes...
    expect(resolver()->allows($user, $project, 'tasks.delete'))->toBeTrue();

    // ...until the default itself is narrowed.
    PermissionScheme::query()->where('is_default', true)->each(function (PermissionScheme $s) {
        $s->grants()->where('permission', 'tasks.delete')->update(['grant_type' => Grant::TYPE_PROJECT_OWNER]);
    });
    ProjectPermissionResolver::flushCache();

    expect(resolver()->allows($user, $project, 'tasks.delete'))->toBeFalse();
});

it('ships an open default scheme so nothing is restricted out of the box', function () {
    $default = PermissionScheme::default();

    expect($default)->not->toBeNull();
    expect($default->name)->toBe('Open');
    expect($default->grants()->where('grant_type', '!=', Grant::TYPE_EVERYONE)->count())->toBe(0);
    expect($default->grants()->count())->toBe(count(ProjectPermissionResolver::GOVERNED));
});

/*
|--------------------------------------------------------------------------
| Policies read the scheme
|--------------------------------------------------------------------------
*/

it('stops a delete through the task policy when the scheme denies it', function () {
    $owner = TaskHelpers::admin();
    // A manager: deleting is a manager's call before the scheme is even asked.
    $user = TaskHelpers::managerWith(['tasks.view', 'tasks.delete']);
    $project = TaskHelpers::project($owner);
    $project->members()->attach([$user->id => ['role' => 'member']]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $user->id,
    ]);

    expect($user->can('delete', $task))->toBeTrue();

    $project->forceFill(['permission_scheme_id' => scheme('Leads only', 'tasks.delete', [
        [Grant::TYPE_PROJECT_ROLE, 'lead'],
    ])->id])->save();

    expect($user->fresh(['roles.permissions'])->can('delete', $task->fresh()))->toBeFalse();
});

it('blocks the delete endpoint for a user the scheme excludes', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.view', 'tasks.delete']);
    $project = TaskHelpers::project($owner);
    $project->members()->attach([$user->id => ['role' => 'member']]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $user->id,
    ]);

    $project->forceFill(['permission_scheme_id' => scheme('Leads only', 'tasks.delete', [
        [Grant::TYPE_PROJECT_ROLE, 'lead'],
    ])->id])->save();

    $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertForbidden();

    expect(Task::query()->whereKey($task->id)->exists())->toBeTrue();
});

it('applies a scheme edit to the very next check', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.delete']);
    $project = TaskHelpers::project($owner);

    $s = scheme('Live', 'tasks.delete', [[Grant::TYPE_PROJECT_OWNER, null]]);
    $project->forceFill(['permission_scheme_id' => $s->id])->save();

    expect(resolver()->allows($user, $project, 'tasks.delete'))->toBeFalse();

    // A model event must drop the memoised graph — no stale-cache bug.
    $s->grants()->create(['permission' => 'tasks.delete', 'grant_type' => Grant::TYPE_EVERYONE, 'grant_value' => null]);

    expect(resolver()->allows($user, $project, 'tasks.delete'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Admin screens
|--------------------------------------------------------------------------
*/

it('lists schemes for a viewer without offering management', function () {
    $user = TaskHelpers::userWith(['permission-schemes.view']);

    $this->actingAs($user)
        ->get(route('permission-schemes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.manage', false)->has('schemes', 2));
});

it('refuses the editor to a user without the manage permission', function () {
    $user = TaskHelpers::userWith(['permission-schemes.view']);
    $default = PermissionScheme::default();

    $this->actingAs($user)->get(route('permission-schemes.edit', $default))->assertForbidden();
});

it('creates a scheme as a copy of the default', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->post(route('permission-schemes.store'), ['name' => 'Client work'])
        ->assertRedirect();

    $created = PermissionScheme::query()->where('name', 'Client work')->firstOrFail();

    expect($created->grants()->count())->toBe(PermissionScheme::default()->grants()->count());
});

it('replaces the grant set on save and drops ungoverned permissions', function () {
    $admin = TaskHelpers::admin();
    $s = scheme('Editable', 'tasks.delete', [[Grant::TYPE_EVERYONE, null]]);

    $this->actingAs($admin)
        ->put(route('permission-schemes.grants.update', $s), [
            'grants' => [
                ['permission' => 'tasks.delete', 'grant_type' => Grant::TYPE_PROJECT_OWNER, 'grant_value' => null],
                ['permission' => 'tasks.delete', 'grant_type' => Grant::TYPE_PROJECT_OWNER, 'grant_value' => null], // duplicate
                ['permission' => 'tasks.manage-labels', 'grant_type' => Grant::TYPE_EVERYONE, 'grant_value' => null], // not governed
                ['permission' => 'tasks.archive', 'grant_type' => Grant::TYPE_TEAM, 'grant_value' => null], // needs a value
            ],
        ])
        ->assertRedirect();

    $rows = $s->grants()->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->permission)->toBe('tasks.delete');
    expect($rows->first()->grant_type)->toBe(Grant::TYPE_PROJECT_OWNER);
});

it('clears every rule when an empty set is saved', function () {
    $admin = TaskHelpers::admin();
    $s = scheme('Editable', 'tasks.delete', [[Grant::TYPE_PROJECT_OWNER, null]]);

    $this->actingAs($admin)
        ->put(route('permission-schemes.grants.update', $s), ['grants' => []])
        ->assertRedirect();

    expect($s->grants()->count())->toBe(0);
});

it('rejects an unknown grant type', function () {
    $admin = TaskHelpers::admin();
    $s = scheme('Editable', 'tasks.delete', [[Grant::TYPE_EVERYONE, null]]);

    $this->actingAs($admin)
        ->put(route('permission-schemes.grants.update', $s), [
            'grants' => [['permission' => 'tasks.delete', 'grant_type' => 'anyone_really', 'grant_value' => null]],
        ])
        ->assertSessionHasErrors('grants.0.grant_type');
});

it('assigns and detaches projects', function () {
    $admin = TaskHelpers::admin();
    $s = scheme('Client work', 'tasks.delete', [[Grant::TYPE_PROJECT_OWNER, null]]);

    $a = TaskHelpers::project($admin);
    $b = TaskHelpers::project($admin);

    $this->actingAs($admin)
        ->post(route('permission-schemes.projects.assign', $s), ['project_ids' => [$a->id, $b->id]])
        ->assertRedirect();

    expect(Project::query()->where('permission_scheme_id', $s->id)->count())->toBe(2);

    $this->actingAs($admin)
        ->post(route('permission-schemes.projects.assign', $s), ['project_ids' => [$a->id]])
        ->assertRedirect();

    expect($b->fresh()->permission_scheme_id)->toBeNull();
    expect($a->fresh()->permission_scheme_id)->toBe($s->id);
});

it('moves the default flag to exactly one scheme', function () {
    $admin = TaskHelpers::admin();
    $s = scheme('New default', 'tasks.delete', [[Grant::TYPE_EVERYONE, null]]);

    $this->actingAs($admin)->post(route('permission-schemes.default', $s))->assertRedirect();

    expect(PermissionScheme::query()->where('is_default', true)->count())->toBe(1);
    expect($s->fresh()->is_default)->toBeTrue();
});

it('refuses to delete the default scheme', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->delete(route('permission-schemes.destroy', PermissionScheme::default()))
        ->assertSessionHasErrors('scheme');
});

it('refuses to delete a scheme still used by a project', function () {
    $admin = TaskHelpers::admin();
    $s = scheme('In use', 'tasks.delete', [[Grant::TYPE_EVERYONE, null]]);

    $project = TaskHelpers::project($admin);
    $project->forceFill(['permission_scheme_id' => $s->id])->save();

    $this->actingAs($admin)
        ->delete(route('permission-schemes.destroy', $s))
        ->assertSessionHasErrors('scheme');

    expect(PermissionScheme::query()->whereKey($s->id)->exists())->toBeTrue();
});

it('deletes an unused scheme', function () {
    $admin = TaskHelpers::admin();
    $s = scheme('Unused', 'tasks.delete', [[Grant::TYPE_EVERYONE, null]]);

    $this->actingAs($admin)->delete(route('permission-schemes.destroy', $s))->assertRedirect();

    expect(PermissionScheme::query()->whereKey($s->id)->exists())->toBeFalse();
});
