<?php

use App\Models\User;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();

    // The three system roles the user form offers.
    foreach ([Role::ADMIN => 'Administrator', Role::MANAGER => 'Manager', Role::EMPLOYEE => 'Employee'] as $slug => $name) {
        Role::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'is_system' => true]);
    }
});

/*
|--------------------------------------------------------------------------
| Users
|--------------------------------------------------------------------------
*/

it('lists users', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data'));
});

it('refuses the user list without the permission', function () {
    $user = TaskHelpers::userWith([]);

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
});

it('creates a user with a hashed password and a role', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Dana Scully',
        'email' => 'dana@raqtan.test',
        'password' => 'a-long-enough-password',
        'roles' => [Role::EMPLOYEE],
    ])->assertRedirect();

    $created = User::query()->where('email', 'dana@raqtan.test')->firstOrFail();

    expect($created->name)->toBe('Dana Scully');
    expect($created->password)->not->toBe('a-long-enough-password');
    expect(Hash::check('a-long-enough-password', $created->password))->toBeTrue();
    expect($created->roles->pluck('slug')->all())->toBe([Role::EMPLOYEE]);
});

it('refuses a duplicate email', function () {
    $admin = TaskHelpers::admin();
    User::factory()->create(['email' => 'taken@raqtan.test']);

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Second',
        'email' => 'taken@raqtan.test',
        'password' => 'a-long-enough-password',
        'roles' => [Role::EMPLOYEE],
    ])->assertSessionHasErrors('email');
});

it('refuses a short password', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Short',
        'email' => 'short@raqtan.test',
        'password' => 'abc',
        'roles' => [Role::EMPLOYEE],
    ])->assertSessionHasErrors('password');
});

it('demands at least one role', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Roleless',
        'email' => 'roleless@raqtan.test',
        'password' => 'a-long-enough-password',
        'roles' => [],
    ])->assertSessionHasErrors('roles');
});

it('refuses user creation without the permission', function () {
    $user = TaskHelpers::userWith(['users.view']);

    $this->actingAs($user)->post(route('users.store'), [
        'name' => 'Nope',
        'email' => 'nope@raqtan.test',
        'password' => 'a-long-enough-password',
        'roles' => [Role::EMPLOYEE],
    ])->assertForbidden();
});

it('updates a user', function () {
    $admin = TaskHelpers::admin();
    $target = User::factory()->create(['name' => 'Before', 'status' => 'active']);

    $this->actingAs($admin)->patch(route('users.update', $target), [
        'name' => 'After',
        'email' => $target->email,
        'roles' => [Role::EMPLOYEE],
    ])->assertRedirect();

    expect($target->fresh()->name)->toBe('After');
});

/*
|--------------------------------------------------------------------------
| Roles and permissions
|--------------------------------------------------------------------------
*/

it('lists roles', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('roles'));
});

it('creates a custom role with permissions', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('roles.store'), [
        'name' => 'Delivery lead',
        'permissions' => ['tasks.view', 'tasks.update'],
    ])->assertRedirect();

    $role = Role::query()->where('name', 'Delivery lead')->firstOrFail();

    expect($role->permissions->pluck('slug')->all())->toEqualCanonicalizing(['tasks.view', 'tasks.update']);
    expect($role->is_system)->toBeFalse();
});

it('refuses a permission slug that does not exist', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('roles.store'), [
        'name' => 'Fictional',
        'permissions' => ['tasks.teleport'],
    ])->assertSessionHasErrors('permissions.0');
});

it('refuses a duplicate role slug', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('roles.store'), [
        'name' => 'First', 'slug' => 'delivery-lead', 'permissions' => [],
    ])->assertRedirect();

    $this->actingAs($admin)->post(route('roles.store'), [
        'name' => 'Second', 'slug' => 'delivery-lead', 'permissions' => [],
    ])->assertSessionHasErrors('slug');
});

it('replaces the permission set on a role', function () {
    $admin = TaskHelpers::admin();
    $role = Role::query()->create(['slug' => 'editors', 'name' => 'Editors', 'is_system' => false]);
    $role->permissions()->sync(Permission::query()->whereIn('slug', ['tasks.view'])->pluck('id'));

    $this->actingAs($admin)
        ->patch(route('roles.update', $role), ['permissions' => ['tasks.delete']])
        ->assertRedirect();

    expect($role->fresh()->permissions->pluck('slug')->all())->toBe(['tasks.delete']);
});

it('refuses role changes without the permission', function () {
    $user = TaskHelpers::userWith(['roles.view']);

    $this->actingAs($user)
        ->post(route('roles.store'), ['name' => 'Nope', 'permissions' => []])
        ->assertForbidden();
});

it('gives an admin every permission without listing them', function () {
    $admin = TaskHelpers::admin();

    // hasPermission short-circuits for admins — the point of the system role.
    expect($admin->hasPermission('tasks.delete'))->toBeTrue();
    expect($admin->hasPermission('a.permission.that.does.not.exist'))->toBeTrue();
});

it('grants nothing beyond its permission set to a normal role', function () {
    $user = TaskHelpers::userWith(['tasks.view']);

    expect($user->hasPermission('tasks.view'))->toBeTrue();
    expect($user->hasPermission('tasks.delete'))->toBeFalse();
});
