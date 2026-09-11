<?php

use App\Modules\TaskManagement\Models\Task;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| The board must not offer moves the server will reject
|--------------------------------------------------------------------------
|
| The unfiltered board merges statuses across every workflow so that a task in
| any workflow still has a column to live in. That means it can render a column
| (e.g. "In review") that a given task's project does not allow. The client has
| to know which statuses each project permits, otherwise the card moves
| optimistically and then silently snaps back when the server refuses it.
|
*/

it('tells the client which statuses each project allows', function () {
    $user = TaskHelpers::admin();

    $simple = TaskHelpers::project($user);            // todo / in_progress / completed
    $software = TaskHelpers::softwareProject($user);  // + review / blocked

    Task::factory()->create(['project_id' => $simple->id]);
    Task::factory()->create(['project_id' => $software->id]);

    $this->actingAs($user)
        ->get(route('tasks.index'))
        ->assertOk()
        ->assertInertia(function ($page) use ($simple, $software) {
            $map = $page->toArray()['props']['projectStatuses'];

            expect($map[$simple->id])->toBe(['todo', 'in_progress', 'done']);
            expect($map[$software->id])->toContain('review');
            expect($map[$simple->id])->not->toContain('review');
        });
});

it('still merges every workflow status into the board columns', function () {
    $user = TaskHelpers::admin();

    $simple = TaskHelpers::project($user);
    $software = TaskHelpers::softwareProject($user);

    Task::factory()->create(['project_id' => $simple->id]);
    Task::factory()->create(['project_id' => $software->id, 'status' => 'review']);

    // A task parked in "review" must still have a column to render in.
    $this->actingAs($user)
        ->get(route('tasks.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $keys = collect($page->toArray()['props']['statuses'])->pluck('key');
            expect($keys)->toContain('review');
            expect($keys)->toContain('todo');
        });
});

it('narrows the columns to one workflow when a project is filtered', function () {
    $user = TaskHelpers::admin();
    $simple = TaskHelpers::project($user);
    TaskHelpers::softwareProject($user);

    Task::factory()->create(['project_id' => $simple->id]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['project' => $simple->slug]))
        ->assertOk()
        ->assertInertia(function ($page) {
            $keys = collect($page->toArray()['props']['statuses'])->pluck('key');
            expect($keys->all())->toBe(['todo', 'in_progress', 'done']);
        });
});

it('returns a readable error when a status is rejected by the workflow', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $task = Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'review'])
        ->assertSessionHasErrors(['status' => 'The status "review" is not part of this project\'s workflow.']);
});
