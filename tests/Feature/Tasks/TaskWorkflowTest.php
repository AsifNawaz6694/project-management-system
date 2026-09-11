<?php

use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskLink;
use App\Modules\TaskManagement\Models\TaskStatusHistory;
use App\Modules\Teams\Models\Team;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| Configurable statuses and transition rules
|--------------------------------------------------------------------------
*/

it('exposes the project workflow statuses rather than a hardcoded set', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['project' => $project->slug]))
        ->assertOk()
        // The software workflow is the full dev → QA → review → deployment pipeline.
        ->assertInertia(fn ($page) => $page->has('statuses', 7));
});

it('accepts a custom status defined by the workflow', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'in_progress']);

    // "pushed_for_qa" exists only in this workflow, not in the hardcoded set.
    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'pushed_for_qa'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('pushed_for_qa');
});

it('rejects a status that is not part of the workflow', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);   // simple workflow: todo/in_progress/completed
    $task = Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'review'])
        ->assertSessionHasErrors('status');

    expect($task->fresh()->status)->toBe('todo');
});

it('enforces transition rules where the workflow defines them', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    // todo -> completed is not an edge in the software workflow.
    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertSessionHasErrors('status');

    expect($task->fresh()->status)->toBe('todo');

    // todo -> in_progress is.
    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'in_progress'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('in_progress');
});

it('allows unrestricted movement when a workflow defines no transitions', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('done');
    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('records status history with the time spent in the previous status', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $task = Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->patch(route('tasks.status', $task), ['status' => 'in_progress']);
    $this->actingAs($user)->patch(route('tasks.status', $task), ['status' => 'done']);

    $history = TaskStatusHistory::query()->where('task_id', $task->id)->orderBy('id')->get();

    expect($history)->toHaveCount(2);
    expect($history[0]->from_status)->toBe('todo');
    expect($history[0]->to_status)->toBe('in_progress');
    expect($history[1]->to_status)->toBe('done');
});

/*
|--------------------------------------------------------------------------
| Dependencies
|--------------------------------------------------------------------------
*/

it('creates the inverse edge when two tasks are linked', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $a = Task::factory()->create(['project_id' => $project->id]);
    $b = Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(route('tasks.links.store', $a), ['target_task_id' => $b->id, 'type' => 'blocks'])
        ->assertRedirect();

    expect(TaskLink::query()->where([
        'source_task_id' => $a->id, 'target_task_id' => $b->id, 'type' => 'blocks',
    ])->exists())->toBeTrue();

    // The mirrored relationship is written automatically.
    expect(TaskLink::query()->where([
        'source_task_id' => $b->id, 'target_task_id' => $a->id, 'type' => 'blocked_by',
    ])->exists())->toBeTrue();
});

it('refuses a circular blocking dependency', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $a = Task::factory()->create(['project_id' => $project->id]);
    $b = Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('tasks.links.store', $a), [
        'target_task_id' => $b->id, 'type' => 'blocked_by',
    ])->assertRedirect();

    // b blocked_by a would close the loop.
    $this->actingAs($user)->post(route('tasks.links.store', $b), [
        'target_task_id' => $a->id, 'type' => 'blocked_by',
    ])->assertSessionHasErrors('target_task_id');
});

it('refuses to link a task to itself', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $a = Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('tasks.links.store', $a), [
        'target_task_id' => $a->id, 'type' => 'relates_to',
    ])->assertSessionHasErrors('target_task_id');
});

it('blocks completion while an open blocker remains, and allows it once cleared', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $task = Task::factory()->create(['project_id' => $project->id]);
    $blocker = Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('tasks.links.store', $task), [
        'target_task_id' => $blocker->id, 'type' => 'blocked_by',
    ])->assertRedirect();

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertSessionHasErrors('status');

    expect($task->fresh()->status)->toBe('todo');

    // Clear the blocker, then the task can close.
    $this->actingAs($user)->patch(route('tasks.status', $blocker), ['status' => 'done']);

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('done');
});

/*
|--------------------------------------------------------------------------
| Notifications — the gaps the audit found
|--------------------------------------------------------------------------
*/

it('notifies followers when a task status changes', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);
    $task->watchers()->attach($assignee->id);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect(Notification::query()
        ->where('user_id', $assignee->id)
        ->where('type', 'task.status-changed')
        ->exists())->toBeTrue();
});

it('notifies followers when someone comments', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->post(route('tasks.comments.store', $task), ['body' => 'Any progress?']);

    expect(Notification::query()
        ->where('user_id', $assignee->id)
        ->where('type', 'task.commented')
        ->exists())->toBeTrue();
});

it('does not notify the person who performed the action', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $owner->id,
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect(Notification::query()->where('user_id', $owner->id)->exists())->toBeFalse();
});

it('notifies the owner of a blocked task when its blocker is completed', function () {
    $owner = TaskHelpers::admin();
    $waiting = TaskHelpers::userWith(['tasks.view']);

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $waiting->id]);
    $blocker = Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($owner)->post(route('tasks.links.store', $task), [
        'target_task_id' => $blocker->id, 'type' => 'blocked_by',
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $blocker), ['status' => 'done']);

    expect(Notification::query()
        ->where('user_id', $waiting->id)
        ->where('type', 'task.unblocked')
        ->exists())->toBeTrue();
});

it('notifies a team when work is routed to it', function () {
    $owner = TaskHelpers::admin();
    $member = TaskHelpers::userWith(['tasks.view']);

    $team = Team::query()->create(['name' => 'Delivery', 'color' => 'blue']);
    $team->members()->attach($member->id);

    $project = TaskHelpers::project($owner);

    $this->actingAs($owner)->post(route('tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Team work',
        'priority' => 'medium',
        'team_id' => $team->id,
    ])->assertRedirect();

    expect(Notification::query()
        ->where('user_id', $member->id)
        ->where('type', 'task.assigned-team')
        ->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Bulk operations
|--------------------------------------------------------------------------
*/

it('applies a bulk status change to every permitted task', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $tasks = Task::factory()->count(5)->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('tasks.bulk'), [
        'ids' => $tasks->pluck('id')->all(),
        'action' => 'status',
        'status' => 'in_progress',
    ])->assertRedirect();

    expect(Task::query()->whereIn('id', $tasks->pluck('id'))->where('status', 'in_progress')->count())->toBe(5);
});

it('skips tasks the user may not change during a bulk action', function () {
    $actor = TaskHelpers::userWith(['tasks.view', 'tasks.update', 'tasks.bulk-edit']);
    $other = TaskHelpers::admin();

    $mine = TaskHelpers::project($actor);
    $theirs = TaskHelpers::project($other);

    $allowed = Task::factory()->create(['project_id' => $mine->id, 'created_by_id' => $actor->id]);
    $denied = Task::factory()->create(['project_id' => $theirs->id]);

    $this->actingAs($actor)->post(route('tasks.bulk'), [
        'ids' => [$allowed->id, $denied->id],
        'action' => 'priority',
        'priority' => 'critical',
    ])->assertRedirect();

    expect($allowed->fresh()->priority)->toBe('critical');
    expect($denied->fresh()->priority)->not->toBe('critical');
});

it('requires the bulk-edit permission', function () {
    $user = TaskHelpers::userWith(['tasks.view', 'tasks.update']);
    $project = TaskHelpers::project($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $user->id]);

    $this->actingAs($user)->post(route('tasks.bulk'), [
        'ids' => [$task->id],
        'action' => 'priority',
        'priority' => 'high',
    ])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Task keys, labels and watchers
|--------------------------------------------------------------------------
*/

it('assigns a sequential human-readable key per project', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    foreach (['First', 'Second', 'Third'] as $title) {
        $this->actingAs($user)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'title' => $title,
            'priority' => 'medium',
        ])->assertRedirect();
    }

    $numbers = Task::query()->where('project_id', $project->id)->orderBy('id')->pluck('number')->all();

    expect($numbers)->toBe([1, 2, 3]);
    expect(Task::query()->where('title', 'Second')->first()->key_label)
        ->toBe($project->fresh()->key.'-2');
});

it('finds a task by its human-readable key', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    $this->actingAs($user)->post(route('tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Findable task',
        'priority' => 'medium',
    ]);

    $key = $project->fresh()->key.'-1';

    $this->actingAs($user)
        ->get(route('tasks.index', ['search' => $key, 'view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 1));
});

it('attaches labels and filters by them', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    $this->actingAs($user)->post(route('tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Labelled task',
        'priority' => 'medium',
        'labels' => ['Urgent'],
    ])->assertRedirect();

    Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['label' => 'urgent', 'view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 1));
});

it('toggles watching a task', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $task = Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->post(route('tasks.watch', $task))->assertRedirect();
    expect($task->watchers()->whereKey($user->id)->exists())->toBeTrue();

    $this->actingAs($user)->post(route('tasks.watch', $task))->assertRedirect();
    expect($task->watchers()->whereKey($user->id)->exists())->toBeFalse();
});

it('lets an author edit their own comment but not someone elses', function () {
    $author = TaskHelpers::admin();
    $other = TaskHelpers::admin();

    $project = TaskHelpers::project($author);
    $task = Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($author)->post(route('tasks.comments.store', $task), ['body' => 'Original']);
    $comment = $task->comments()->first();

    $this->actingAs($author)
        ->patch(route('tasks.comments.update', [$task, $comment]), ['body' => 'Edited'])
        ->assertRedirect();

    expect($comment->fresh()->body)->toBe('Edited');
    expect($comment->fresh()->edited_at)->not->toBeNull();

    $this->actingAs($other)
        ->patch(route('tasks.comments.update', [$task, $comment]), ['body' => 'Hijack'])
        ->assertForbidden();
});
