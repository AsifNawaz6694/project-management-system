<?php

use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskComment;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| F1 — subtasks must survive a parent edit
|--------------------------------------------------------------------------
*/

it('keeps subtask identity when the parent task is updated', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    $this->actingAs($user)->post(route('tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Parent task',
        'priority' => 'medium',
        'subtasks' => [
            ['title' => 'First subtask'],
            ['title' => 'Second subtask'],
        ],
    ])->assertRedirect();

    $parent = Task::query()->where('title', 'Parent task')->firstOrFail();
    $originalIds = $parent->subtasks()->orderBy('position')->pluck('id')->all();

    expect($originalIds)->toHaveCount(2);

    // Edit the parent, resubmitting the same subtasks with their ids.
    $this->actingAs($user)->patch(route('tasks.update', $parent), [
        'title' => 'Parent task renamed',
        'subtasks' => [
            ['id' => $originalIds[0], 'title' => 'First subtask renamed'],
            ['id' => $originalIds[1], 'title' => 'Second subtask'],
        ],
    ])->assertRedirect();

    $afterIds = $parent->subtasks()->orderBy('position')->pluck('id')->all();

    // Same rows, updated in place — not destroyed and recreated.
    expect($afterIds)->toBe($originalIds);
    expect(Task::query()->find($originalIds[0])->title)->toBe('First subtask renamed');
    // And nothing was soft-deleted behind our back.
    expect(Task::onlyTrashed()->whereIn('id', $originalIds)->count())->toBe(0);
});

it('deletes only the subtasks actually removed by the client', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $parent = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $user->id]);

    $a = Task::factory()->create(['project_id' => $project->id, 'parent_task_id' => $parent->id, 'title' => 'Keep me']);
    $b = Task::factory()->create(['project_id' => $project->id, 'parent_task_id' => $parent->id, 'title' => 'Drop me']);

    $this->actingAs($user)->patch(route('tasks.update', $parent), [
        'subtasks' => [['id' => $a->id, 'title' => 'Keep me']],
    ])->assertRedirect();

    expect(Task::query()->find($a->id))->not->toBeNull();
    expect(Task::query()->find($b->id))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| F2 — nullable fields must be clearable
|--------------------------------------------------------------------------
*/

it('can clear a description and a due date', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $user->id,
        'description' => 'Some description',
        'due_date' => now()->addWeek()->toDateString(),
    ]);

    $this->actingAs($user)->patch(route('tasks.update', $task), [
        'description' => null,
        'due_date' => null,
    ])->assertRedirect();

    $task->refresh();

    expect($task->description)->toBeNull();
    expect($task->due_date)->toBeNull();
});

it('does not wipe the title when it is omitted from the payload', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $user->id,
        'title' => 'Original title',
    ]);

    $this->actingAs($user)->patch(route('tasks.update', $task), ['priority' => 'high'])->assertRedirect();

    expect($task->fresh()->title)->toBe('Original title');
});

/*
|--------------------------------------------------------------------------
| F5 — status must be filterable server-side
|--------------------------------------------------------------------------
*/

it('filters the task list by status', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    Task::factory()->count(3)->create(['project_id' => $project->id, 'status' => 'todo']);
    Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => 'done', 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['status' => ['done'], 'view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 2));
});

/*
|--------------------------------------------------------------------------
| F4 — the list must paginate
|--------------------------------------------------------------------------
*/

it('paginates the list view instead of returning every task', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    Task::factory()->count(60)->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks', 50)
            ->where('pagination.total', 60)
            ->where('pagination.last_page', 2));
});

/*
|--------------------------------------------------------------------------
| F11 — replies must belong to their task
|--------------------------------------------------------------------------
*/

it('rejects a reply whose parent comment belongs to another task', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    $taskA = Task::factory()->create(['project_id' => $project->id]);
    $taskB = Task::factory()->create(['project_id' => $project->id]);

    $commentOnA = TaskComment::query()->create([
        'task_id' => $taskA->id,
        'user_id' => $user->id,
        'body' => 'On task A',
    ]);

    $this->actingAs($user)->post(route('tasks.comments.store', $taskB), [
        'body' => 'Sneaky reply',
        'parent_id' => $commentOnA->id,
    ])->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| F12 — soft-deleted tasks must be reachable and restorable
|--------------------------------------------------------------------------
*/

it('lists and restores soft-deleted tasks', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $user->id]);

    $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertRedirect();
    expect(Task::query()->find($task->id))->toBeNull();

    $this->actingAs($user)->get(route('tasks.trash'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 1));

    $this->actingAs($user)->post(route('tasks.restore', $task->id))->assertRedirect();

    expect(Task::query()->find($task->id))->not->toBeNull();
});

it('archives and unarchives a task without deleting it', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $user->id]);

    $this->actingAs($user)->post(route('tasks.archive', $task))->assertRedirect();
    expect($task->fresh()->archived_at)->not->toBeNull();

    // Archived work drops out of the default list.
    $this->actingAs($user)->get(route('tasks.index', ['view' => 'list']))
        ->assertInertia(fn ($page) => $page->has('tasks', 0));

    $this->actingAs($user)->post(route('tasks.unarchive', $task))->assertRedirect();
    expect($task->fresh()->archived_at)->toBeNull();
});
