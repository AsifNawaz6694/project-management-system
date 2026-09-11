<?php

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStatus;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

it('hides the workflow admin from users without permission', function () {
    $user = TaskHelpers::userWith(['tasks.view']);

    $this->actingAs($user)->get(route('workflows.index'))->assertForbidden();
});

it('lets a viewer list workflows but not edit them', function () {
    $viewer = TaskHelpers::userWith(['workflows.view']);
    $workflow = Workflow::query()->where('is_default', true)->firstOrFail();
    TaskHelpers::openWorkflow();

    $this->actingAs($viewer)
        ->get(route('workflows.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.manage', false)->has('workflows', 2));

    $this->actingAs($viewer)->get(route('workflows.edit', $workflow))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Creating and editing
|--------------------------------------------------------------------------
*/

it('creates a workflow with a usable starting set of stages', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->post(route('workflows.store'), ['name' => 'Marketing campaign'])
        ->assertRedirect();

    $workflow = Workflow::query()->where('name', 'Marketing campaign')->firstOrFail();

    expect($workflow->statuses()->count())->toBe(3);
    expect($workflow->statuses()->where('is_initial', true)->count())->toBe(1);
    expect($workflow->statuses()->where('category', 'done')->count())->toBe(1);
});

it('adds a stage with a generated key', function () {
    $admin = TaskHelpers::admin();
    $workflow = TaskHelpers::openWorkflow();

    $this->actingAs($admin)
        ->post(route('workflows.statuses.store', $workflow), [
            'name' => 'Waiting on client',
            'category' => 'in_progress',
            'color' => 'amber',
        ])
        ->assertRedirect();

    $status = $workflow->statuses()->where('name', 'Waiting on client')->first();

    expect($status)->not->toBeNull();
    expect($status->key)->toBe('waiting_on_client');
});

it('renames a stage without changing its key', function () {
    $admin = TaskHelpers::admin();
    $workflow = TaskHelpers::openWorkflow();
    $status = $workflow->statuses()->where('key', 'in_progress')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('workflows.statuses.update', [$workflow, $status]), [
            'name' => 'Doing',
            'category' => 'in_progress',
            'color' => 'sky',
        ])
        ->assertRedirect();

    $status->refresh();

    expect($status->name)->toBe('Doing');
    // The key is what tasks store, so it must survive a rename.
    expect($status->key)->toBe('in_progress');
});

it('moves the starting flag to exactly one stage', function () {
    $admin = TaskHelpers::admin();
    $workflow = TaskHelpers::openWorkflow();
    $target = $workflow->statuses()->where('key', 'in_progress')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('workflows.statuses.update', [$workflow, $target]), [
            'name' => $target->name,
            'category' => $target->category,
            'is_initial' => true,
        ])
        ->assertRedirect();

    expect($workflow->statuses()->where('is_initial', true)->count())->toBe(1);
    expect($target->fresh()->is_initial)->toBeTrue();
});

it('reorders stages', function () {
    $admin = TaskHelpers::admin();
    $workflow = TaskHelpers::openWorkflow();
    $ids = $workflow->statuses()->orderBy('position')->pluck('id')->all();

    $this->actingAs($admin)
        ->post(route('workflows.statuses.reorder', $workflow), ['ids' => array_reverse($ids)])
        ->assertRedirect();

    expect($workflow->statuses()->orderBy('position')->pluck('id')->all())->toBe(array_reverse($ids));
});

/*
|--------------------------------------------------------------------------
| Guards that protect live work
|--------------------------------------------------------------------------
*/

it('refuses to delete a stage that tasks are sitting in', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $workflow = TaskHelpers::openWorkflow();
    $status = $workflow->statuses()->where('key', 'todo')->firstOrFail();

    Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => 'todo']);

    $this->actingAs($admin)
        ->delete(route('workflows.statuses.destroy', [$workflow, $status]))
        ->assertSessionHasErrors('status');

    expect(WorkflowStatus::query()->whereKey($status->id)->exists())->toBeTrue();
});

it('deletes an unused stage and keeps a starting point', function () {
    $admin = TaskHelpers::admin();
    $workflow = TaskHelpers::openWorkflow();
    $initial = $workflow->statuses()->where('is_initial', true)->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('workflows.statuses.destroy', [$workflow, $initial]))
        ->assertRedirect();

    expect(WorkflowStatus::query()->whereKey($initial->id)->exists())->toBeFalse();
    // The flag is handed to another stage rather than lost.
    expect($workflow->statuses()->where('is_initial', true)->count())->toBe(1);
});

it('refuses to delete the default workflow', function () {
    $admin = TaskHelpers::admin();
    $workflow = Workflow::query()->where('is_default', true)->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('workflows.destroy', $workflow))
        ->assertSessionHasErrors('workflow');

    expect(Workflow::query()->whereKey($workflow->id)->exists())->toBeTrue();
});

it('refuses to delete a workflow still used by a project', function () {
    $admin = TaskHelpers::admin();
    $workflow = TaskHelpers::openWorkflow();
    TaskHelpers::project($admin);   // now points at that workflow

    $this->actingAs($admin)
        ->delete(route('workflows.destroy', $workflow))
        ->assertSessionHasErrors('workflow');
});

/*
|--------------------------------------------------------------------------
| Transition rules
|--------------------------------------------------------------------------
*/

it('replaces the transition set and takes effect immediately', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);  // Simple: unrestricted
    $workflow = TaskHelpers::openWorkflow();
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    // Unrestricted to begin with.
    $this->actingAs($admin)
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertRedirect();

    // Reset via the query builder: the in-memory model still holds the old
    // values, so $task->update() here would be a no-op.
    Task::query()->whereKey($task->id)->update(['status' => 'todo', 'completed_at' => null]);
    $task->refresh();
    expect($task->status)->toBe('todo');

    // Now restrict it to todo -> in_progress only.
    $this->actingAs($admin)
        ->put(route('workflows.transitions.update', $workflow), [
            'transitions' => [
                ['from' => 'todo', 'to' => 'in_progress', 'requires_comment' => false],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertSessionHasErrors('status');

    expect($task->fresh()->status)->toBe('todo');
});

it('configures a reason requirement through the editor', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $workflow = TaskHelpers::openWorkflow();
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    $this->actingAs($admin)
        ->put(route('workflows.transitions.update', $workflow), [
            'transitions' => [
                [
                    'from' => 'todo',
                    'to' => 'done',
                    'requires_comment' => true,
                    'comment_label' => 'Why are you closing this?',
                ],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertSessionHasErrors(['reason' => 'Why are you closing this?']);

    $this->actingAs($admin)
        ->patch(route('tasks.status', $task), ['status' => 'done', 'reason' => 'Duplicate of WEB-3.'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('done');
});

it('clears all rules when an empty set is saved', function () {
    $admin = TaskHelpers::admin();
    $workflow = Workflow::query()->where('name', 'Software delivery')->firstOrFail();

    expect($workflow->transitions()->count())->toBeGreaterThan(0);

    $this->actingAs($admin)
        ->put(route('workflows.transitions.update', $workflow), ['transitions' => []])
        ->assertRedirect();

    expect($workflow->transitions()->count())->toBe(0);
});

it('ignores a self-referencing or unknown transition', function () {
    $admin = TaskHelpers::admin();
    $workflow = TaskHelpers::openWorkflow();

    $this->actingAs($admin)
        ->put(route('workflows.transitions.update', $workflow), [
            'transitions' => [
                ['from' => 'todo', 'to' => 'todo'],            // self
                ['from' => 'todo', 'to' => 'does_not_exist'],  // unknown target
                ['from' => 'todo', 'to' => 'in_progress'],     // valid
            ],
        ])
        ->assertRedirect();

    expect($workflow->transitions()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Project assignment
|--------------------------------------------------------------------------
*/

it('assigns projects to a workflow from the editor', function () {
    $admin = TaskHelpers::admin();
    $a = TaskHelpers::project($admin);
    $b = TaskHelpers::project($admin);
    $workflow = Workflow::query()->where('name', 'Software delivery')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('workflows.projects.assign', $workflow), ['project_ids' => [$a->id, $b->id]])
        ->assertRedirect();

    expect(Project::query()->whereKey($a->id)->value('workflow_id'))->toBe($workflow->id);
    expect(Project::query()->whereKey($b->id)->value('workflow_id'))->toBe($workflow->id);
});

it('reports stage usage so the editor can warn before deleting', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $workflow = TaskHelpers::openWorkflow();

    Task::factory()->count(3)->create(['project_id' => $project->id, 'status' => 'todo']);

    $this->actingAs($admin)
        ->get(route('workflows.edit', $workflow))
        ->assertOk()
        ->assertInertia(function ($page) {
            $statuses = collect($page->toArray()['props']['statuses'])->keyBy('key');
            expect($statuses['todo']['task_count'])->toBe(3);
            expect($statuses['done']['task_count'])->toBe(0);
        });
});
