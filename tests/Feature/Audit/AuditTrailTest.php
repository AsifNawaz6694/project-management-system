<?php

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Services\ProjectService;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\TaskService;
use App\Modules\Teams\Models\Team;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

function tasks(): TaskService
{
    return app(TaskService::class);
}

function projects(): ProjectService
{
    return app(ProjectService::class);
}

/**
 * Every activity recorded against one task, oldest first.
 */
function trail(Task $task): array
{
    return Activity::query()
        ->forSubject(Activity::SUBJECT_TASK, $task->id)
        ->orderBy('created_at')
        ->orderBy('id')
        ->pluck('action')
        ->all();
}

function projectTrail(Project $project): array
{
    return Activity::query()
        ->forSubject(Activity::SUBJECT_PROJECT, $project->id)
        ->orderBy('created_at')
        ->orderBy('id')
        ->pluck('action')
        ->all();
}

/*
|--------------------------------------------------------------------------
| Completeness — every meaningful action reaches the trail
|--------------------------------------------------------------------------
*/

it('records creation with its actor, subject and time', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Ship it'], $admin);

    $entry = Activity::query()->forSubject(Activity::SUBJECT_TASK, $task->id)->sole();

    expect($entry->action)->toBe('task.created')
        ->and($entry->user_id)->toBe($admin->id)
        ->and($entry->subject_type)->toBe('task')
        ->and($entry->subject_id)->toBe($task->id)
        ->and($entry->created_at)->not->toBeNull()
        ->and($entry->properties['project_id'])->toBe($project->id);
});

it('records an update as a structured before and after', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Before', 'priority' => 'low'], $admin);

    tasks()->update($task, ['title' => 'After', 'priority' => 'high'], $admin);

    $entry = Activity::query()->where('action', 'task.updated')->sole();

    expect($entry->properties['changes'])->toBe([
        'title' => ['Before', 'After'],
        'priority' => ['low', 'high'],
    ]);
});

it('records due date and priority changes with both values', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create([
        'project_id' => $project->id, 'title' => 'Dated', 'due_date' => '2026-01-01', 'priority' => 'low',
    ], $admin);

    tasks()->update($task, ['due_date' => '2026-02-01', 'priority' => 'critical'], $admin);

    $changes = Activity::query()->where('action', 'task.updated')->sole()->properties['changes'];

    expect($changes['due_date'])->toBe(['2026-01-01', '2026-02-01'])
        ->and($changes['priority'])->toBe(['low', 'critical']);
});

it('records an assignment as a before and after pair', function () {
    $admin = TaskHelpers::admin();
    $other = TaskHelpers::userWith([]);
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Unowned'], $admin);

    tasks()->update($task, ['assignee_id' => $other->id], $admin);

    $changes = Activity::query()->where('action', 'task.updated')->sole()->properties['changes'];

    expect($changes['assignee_id'])->toBe([null, $other->id]);
});

it('names completion and reopening rather than calling both a status change', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Cycle'], $admin);

    tasks()->changeStatus($task, 'done', null, $admin);
    tasks()->changeStatus($task->fresh(), 'in_progress', null, $admin);

    expect(trail($task))->toBe(['task.created', 'task.completed', 'task.reopened']);
});

it('records an ordinary move as a status change', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Moving'], $admin);

    tasks()->changeStatus($task, 'in_progress', null, $admin);

    $entry = Activity::query()->where('action', 'task.status-changed')->sole();

    expect($entry->properties['from'])->toBe('todo')
        ->and($entry->properties['to'])->toBe('in_progress');
});

it('records archiving, restoring and deletion', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Lifecycle'], $admin);

    tasks()->archive($task, $admin);
    tasks()->unarchive($task, $admin);
    tasks()->delete($task);

    expect(trail($task))->toBe(['task.created', 'task.archived', 'task.unarchived', 'task.deleted']);
});

it('records a comment against the task it was left on', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Discussed'], $admin);

    $this->actingAs($admin)
        ->post(route('tasks.comments.store', $task), ['body' => 'Looks right to me'])
        ->assertRedirect();

    expect(trail($task))->toContain('task.commented');
});

it('records subtask creation against the subtask itself', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $parent = tasks()->create(['project_id' => $project->id, 'title' => 'Parent'], $admin);

    tasks()->update($parent, ['subtasks' => [['title' => 'Child one']]], $admin);

    $child = Task::query()->where('parent_task_id', $parent->id)->sole();

    expect(trail($child))->toContain('task.created');
});

it('records project creation, stage changes and completion', function () {
    $admin = TaskHelpers::admin();

    $project = projects()->create(['title' => 'Delivery', 'status' => 'planning'], $admin);
    projects()->update($project, ['status' => 'active']);
    projects()->update($project->fresh(), ['status' => 'completed']);
    projects()->update($project->fresh(), ['status' => 'active']);

    expect(projectTrail($project))->toBe([
        'project.created',
        'project.status-changed',
        'project.completed',
        'project.reopened',
    ]);
});

it('records milestone completion and reopening on the project', function () {
    $admin = TaskHelpers::admin();
    $project = projects()->create([
        'title' => 'With milestones',
        'milestones' => [['title' => 'Beta']],
    ], $admin);

    $milestone = $project->milestones()->sole();

    projects()->toggleMilestone($milestone);
    projects()->toggleMilestone($milestone->fresh());

    expect(projectTrail($project))
        ->toContain('milestone.completed')
        ->toContain('milestone.reopened');
});

it('records who joined and left a project', function () {
    $admin = TaskHelpers::admin();
    $joiner = TaskHelpers::userWith([]);
    $project = projects()->create(['title' => 'Team shuffle'], $admin);

    projects()->update($project, ['member_ids' => [$joiner->id]]);

    $entry = Activity::query()->where('action', 'project.members-changed')->latest('id')->first();

    expect($entry->properties['changes']['added'])->toContain($joiner->id);
});

/*
|--------------------------------------------------------------------------
| Ordering — deterministic, even inside one second
|--------------------------------------------------------------------------
*/

it('orders a timeline deterministically when rows share a timestamp', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Busy'], $admin);

    // One frozen instant, so created_at cannot break the tie on its own.
    $this->travelTo(now()->startOfSecond());
    tasks()->update($task, ['title' => 'Busier'], $admin);
    tasks()->changeStatus($task->fresh(), 'in_progress', null, $admin);
    tasks()->update($task->fresh(), ['priority' => 'high'], $admin);

    $stamps = Activity::query()->forSubject(Activity::SUBJECT_TASK, $task->id)->pluck('created_at')->unique();
    expect($stamps->count())->toBeLessThan(4); // at least some rows collide

    $newestFirst = Activity::query()
        ->forSubject(Activity::SUBJECT_TASK, $task->id)
        ->chronological()
        ->pluck('id')
        ->all();

    expect($newestFirst)->toBe(collect($newestFirst)->sortDesc()->values()->all());
});

it('returns the same order every time it is asked', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Stable'], $admin);

    $this->travelTo(now()->startOfSecond());
    foreach (['a', 'b', 'c', 'd'] as $title) {
        tasks()->update($task->fresh(), ['title' => $title], $admin);
    }

    $first = Activity::query()->forSubject(Activity::SUBJECT_TASK, $task->id)->chronological()->pluck('id')->all();
    $second = Activity::query()->forSubject(Activity::SUBJECT_TASK, $task->id)->chronological()->pluck('id')->all();

    expect($first)->toBe($second);
});

/*
|--------------------------------------------------------------------------
| No bloat — nothing meaningless, nothing duplicated
|--------------------------------------------------------------------------
*/

it('writes nothing when a task update changes nothing', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Same', 'priority' => 'low'], $admin);

    $before = Activity::query()->count();

    tasks()->update($task, ['title' => 'Same', 'priority' => 'low'], $admin);

    expect(Activity::query()->count())->toBe($before);
});

it('writes nothing when a project update changes nothing', function () {
    $admin = TaskHelpers::admin();
    $project = projects()->create(['title' => 'Untouched', 'priority' => 'medium'], $admin);

    $before = Activity::query()->count();

    projects()->update($project, ['title' => 'Untouched', 'priority' => 'medium']);

    expect(Activity::query()->count())->toBe($before);
});

it('writes nothing when a project membership sync changes nobody', function () {
    $admin = TaskHelpers::admin();
    $member = TaskHelpers::userWith([]);
    $project = projects()->create(['title' => 'Steady', 'member_ids' => [$member->id]], $admin);

    $before = Activity::query()->where('action', 'project.members-changed')->count();

    projects()->update($project, ['member_ids' => [$member->id]]);

    expect(Activity::query()->where('action', 'project.members-changed')->count())->toBe($before);
});

it('does not report a stage change twice', function () {
    $admin = TaskHelpers::admin();
    $project = projects()->create(['title' => 'One voice', 'status' => 'planning'], $admin);

    projects()->update($project, ['status' => 'active', 'title' => 'One voice renamed']);

    $updated = Activity::query()->where('action', 'project.updated')->sole();

    // The rename is in the diff; the stage change has its own row instead.
    expect($updated->properties['changes'])->toHaveKey('title')
        ->and($updated->properties['changes'])->not->toHaveKey('status')
        ->and(Activity::query()->where('action', 'project.status-changed')->count())->toBe(1);
});

it('keeps one subject out of another subject timeline', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $mine = tasks()->create(['project_id' => $project->id, 'title' => 'Mine'], $admin);
    $theirs = tasks()->create(['project_id' => $project->id, 'title' => 'Theirs'], $admin);

    tasks()->update($theirs, ['title' => 'Theirs renamed'], $admin);

    expect(trail($mine))->toBe(['task.created']);
});

it('records a team edit as a structured diff and stays quiet otherwise', function () {
    $admin = TaskHelpers::admin();
    $team = Team::query()->create(['name' => 'Platform', 'slug' => 'platform', 'color' => 'blue']);

    // A save that changes nothing.
    $this->actingAs($admin)
        ->patch(route('teams.update', $team), ['name' => 'Platform'])
        ->assertRedirect();

    expect(Activity::query()->where('action', 'team.updated')->count())->toBe(0);

    $this->actingAs($admin)
        ->patch(route('teams.update', $team), ['name' => 'Platform Core'])
        ->assertRedirect();

    $entry = Activity::query()->where('action', 'team.updated')->sole();

    expect($entry->subject_type)->toBe('team')
        ->and($entry->subject_id)->toBe($team->id)
        ->and($entry->properties['changes']['name'])->toBe(['Platform', 'Platform Core']);
});

/*
|--------------------------------------------------------------------------
| Immutability
|--------------------------------------------------------------------------
*/

it('refuses to modify an audit record', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    tasks()->create(['project_id' => $project->id, 'title' => 'Fixed'], $admin);

    $entry = Activity::query()->sole();

    expect(fn () => $entry->update(['description' => 'rewritten']))
        ->toThrow(RuntimeException::class);

    expect($entry->fresh()->description)->not->toBe('rewritten');
});

it('refuses to delete an audit record', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    tasks()->create(['project_id' => $project->id, 'title' => 'Permanent'], $admin);

    $entry = Activity::query()->sole();

    expect(fn () => $entry->delete())->toThrow(RuntimeException::class);
    expect(Activity::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('exposes no route that edits or removes an activity', function () {
    $routes = collect(app('router')->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->filter(fn (string $uri) => str_contains($uri, 'activit'));

    expect($routes->values()->all())->toBe(['activity']);
});

/*
|--------------------------------------------------------------------------
| Transaction safety
|--------------------------------------------------------------------------
*/

it('leaves no audit row behind when the surrounding transaction rolls back', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $before = Activity::query()->count();

    try {
        DB::transaction(function () use ($project, $admin) {
            tasks()->create(['project_id' => $project->id, 'title' => 'Doomed'], $admin);

            throw new RuntimeException('abandon');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(Activity::query()->count())->toBe($before)
        ->and(Task::query()->where('title', 'Doomed')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Performance
|--------------------------------------------------------------------------
*/

it('reads a task timeline from the subject index rather than scanning', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Explained'], $admin);

    $plan = DB::select(
        'EXPLAIN SELECT * FROM activities WHERE subject_type = ? AND subject_id = ? ORDER BY created_at DESC, id DESC LIMIT 40',
        [Activity::SUBJECT_TASK, $task->id],
    )[0];

    expect($plan->key)->toBe('activities_subject_idx')
        ->and($plan->type)->not->toBe('ALL')
        // The index supplies the order too, so there is nothing left to sort.
        ->and((string) $plan->Extra)->not->toContain('filesort');
});

it('pages a timeline without repeating or skipping a row', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Paged'], $admin);

    $this->travelTo(now()->startOfSecond());
    foreach (range(1, 12) as $i) {
        tasks()->update($task->fresh(), ['title' => "Paged {$i}"], $admin);
    }

    $page = fn (int $n) => Activity::query()
        ->forSubject(Activity::SUBJECT_TASK, $task->id)
        ->chronological()
        ->forPage($n, 5)
        ->pluck('id')
        ->all();

    $seen = [...$page(1), ...$page(2), ...$page(3)];

    expect($seen)->toHaveCount(13)
        ->and(array_unique($seen))->toHaveCount(13);
});

it('keeps the whole task page to a bounded number of queries', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = tasks()->create(['project_id' => $project->id, 'title' => 'Counted'], $admin);

    foreach (range(1, 10) as $i) {
        tasks()->update($task->fresh(), ['title' => "Counted {$i}"], $admin);
    }

    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('tasks.show', $task))->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // The trail is one query plus one eager load of its actors, however many
    // entries it holds — never one query per entry.
    expect($count)->toBeLessThan(60);
});
