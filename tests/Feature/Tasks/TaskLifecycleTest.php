<?php

use App\Modules\TaskManagement\Models\Task;
use Tests\Feature\Tasks\TaskHelpers;

/**
 * Taking work off the board is a manager's call.
 *
 * Archiving, deleting and restoring are gated on the *role*, not only on the
 * permission: a responsibility bundle or a direct grant can carry
 * `tasks.delete`, and none of those should imply the authority to remove
 * somebody else's task. Super Admin passes through TaskPolicy::before().
 */
beforeEach(function () {
    TaskHelpers::bootstrap();
});

function lifecycleTask(): Task
{
    $owner = TaskHelpers::admin();

    return Task::factory()->create([
        'project_id' => TaskHelpers::project($owner)->id,
        'created_by_id' => $owner->id,
        'status' => 'todo',
    ]);
}

it('refuses archive, delete and the trash to an employee holding the permissions', function () {
    $task = lifecycleTask();

    // Everything the permission system can give them, short of the role.
    $user = TaskHelpers::userWith([
        'tasks.view', 'tasks.view-all', 'tasks.update', 'tasks.delete', 'tasks.archive',
    ]);

    $this->actingAs($user)->post(route('tasks.archive', $task))->assertForbidden();
    $this->actingAs($user)->post(route('tasks.unarchive', $task))->assertForbidden();
    $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertForbidden();
    $this->actingAs($user)->get(route('tasks.trash'))->assertForbidden();

    expect($task->fresh())->not->toBeNull()
        ->and($task->fresh()->archived_at)->toBeNull();
});

it('lets a manager archive, delete and restore', function () {
    $task = lifecycleTask();

    $manager = TaskHelpers::managerWith([
        'tasks.view', 'tasks.view-all', 'tasks.update', 'tasks.delete', 'tasks.archive',
    ]);

    $this->actingAs($manager)->post(route('tasks.archive', $task))->assertRedirect();
    expect($task->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($manager)->post(route('tasks.unarchive', $task))->assertRedirect();
    expect($task->fresh()->archived_at)->toBeNull();

    $this->actingAs($manager)->delete(route('tasks.destroy', $task))->assertRedirect();
    expect(Task::query()->find($task->id))->toBeNull();

    $this->actingAs($manager)->get(route('tasks.trash'))->assertOk();

    $this->actingAs($manager)->post(route('tasks.restore', $task->id))->assertRedirect();
    expect(Task::query()->find($task->id))->not->toBeNull();
});

it('lets the super admin through', function () {
    $task = lifecycleTask();
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('tasks.archive', $task))->assertRedirect();
    $this->actingAs($admin)->delete(route('tasks.destroy', $task))->assertRedirect();
    $this->actingAs($admin)->get(route('tasks.trash'))->assertOk();
});

it('refuses bulk archive and bulk delete to an employee', function () {
    $task = lifecycleTask();

    $user = TaskHelpers::userWith([
        'tasks.view', 'tasks.view-all', 'tasks.update', 'tasks.bulk-edit', 'tasks.delete', 'tasks.archive',
    ]);

    // The bulk endpoint re-authorises every task, so an unauthorised batch
    // comes back as an error rather than a silent partial success.
    $this->actingAs($user)
        ->post(route('tasks.bulk'), ['ids' => [$task->id], 'action' => 'delete'])
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->post(route('tasks.bulk'), ['ids' => [$task->id], 'action' => 'archive'])
        ->assertSessionHas('error');

    expect(Task::query()->find($task->id))->not->toBeNull()
        ->and($task->fresh()->archived_at)->toBeNull();

    // The same batch, run by a manager, goes through.
    $manager = TaskHelpers::managerWith(['tasks.view', 'tasks.view-all', 'tasks.bulk-edit', 'tasks.archive']);

    $this->actingAs($manager)
        ->post(route('tasks.bulk'), ['ids' => [$task->id], 'action' => 'archive'])
        ->assertSessionHas('status');

    expect($task->fresh()->archived_at)->not->toBeNull();
});

it('hides the lifecycle controls from the task list', function () {
    lifecycleTask();

    $employee = TaskHelpers::userWith(['tasks.view', 'tasks.view-all', 'tasks.delete']);
    $manager = TaskHelpers::managerWith(['tasks.view', 'tasks.view-all', 'tasks.delete']);

    $this->actingAs($employee)
        ->get(route('tasks.index'))
        ->assertInertia(fn ($page) => $page->where('can.lifecycle', false));

    $this->actingAs($manager)
        ->get(route('tasks.index'))
        ->assertInertia(fn ($page) => $page->where('can.lifecycle', true));
});
