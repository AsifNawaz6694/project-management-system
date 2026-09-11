<?php

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\Teams\Models\Team;
use App\Modules\Workflow\Models\Workflow;
use Database\Seeders\DatabaseSeeder;

/**
 * Row-level scoping, exercised over HTTP.
 *
 * RBAC §15 and §16 are the point of this file: a permission is only real if the
 * backend refuses the request. Every negative case here changes an id in the URL
 * rather than clicking a hidden button, and expects the server to say no.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->workflow = Workflow::query()->where('is_default', true)->firstOrFail();
});

function person(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

function projectFor(User $owner, Workflow $workflow, string $title = 'Unrelated initiative'): Project
{
    return Project::factory()->create([
        'title' => $title,
        'owner_id' => $owner->id,
        'workflow_id' => $workflow->id,
    ]);
}

function taskIn(Project $project, array $attributes = []): Task
{
    return Task::factory()->create(array_merge([
        'project_id' => $project->id,
        'status' => 'todo',
        'created_by_id' => $project->owner_id,
    ], $attributes));
}

it('hides an unrelated project from a regular employee, over HTTP', function () {
    $manager = person('saad@bargoventures.com');
    $employee = person('farhan@bargoventures.com');

    $project = projectFor($manager, $this->workflow);

    $this->actingAs($employee)
        ->get(route('projects.show', $project))
        ->assertForbidden();

    $this->actingAs($employee)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertDontSee('Unrelated initiative');
});

it('hides an unrelated task from a regular employee, over HTTP', function () {
    $manager = person('saad@bargoventures.com');
    $employee = person('farhan@bargoventures.com');

    $task = taskIn(projectFor($manager, $this->workflow), ['title' => 'Confidential work']);

    $this->actingAs($employee)
        ->get(route('tasks.show', $task))
        ->assertForbidden();
});

it('refuses to let a regular employee reassign someone else’s task', function () {
    $manager = person('saad@bargoventures.com');
    $employee = person('farhan@bargoventures.com');
    $victim = person('yousuf@bargoventures.com');

    $task = taskIn(projectFor($manager, $this->workflow), ['assignee_id' => $victim->id]);

    $this->actingAs($employee)
        ->patch(route('tasks.update', $task), ['assignee_id' => $employee->id])
        ->assertForbidden();

    expect($task->fresh()->assignee_id)->toBe($victim->id);
});

it('lets an employee see and progress the task assigned to them', function () {
    $manager = person('saad@bargoventures.com');
    $employee = person('yousuf@bargoventures.com');

    $task = taskIn(projectFor($manager, $this->workflow), ['assignee_id' => $employee->id]);

    $this->actingAs($employee)->get(route('tasks.show', $task))->assertOk();

    $this->actingAs($employee)
        ->patch(route('tasks.status', $task), ['status' => 'in_progress'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('in_progress');
});

it('gives a DTT manager the whole organisation', function () {
    $manager = person('saad@bargoventures.com');
    $other = person('marcin@bargoventures.com');

    $task = taskIn(projectFor($other, $this->workflow));

    $this->actingAs($manager)->get(route('tasks.show', $task))->assertOk();
    $this->actingAs($manager)->get(route('projects.show', $task->project))->assertOk();
});

it('extends Adnan’s reach over Backend and UI work but no further', function () {
    $adnan = person('adnan@bargoventures.com');
    $manager = person('saad@bargoventures.com');

    $project = projectFor($manager, $this->workflow);

    $backend = Team::query()->where('slug', 'backend')->value('id');
    $qa = Team::query()->where('slug', 'qa')->value('id');

    $backendTask = taskIn($project, ['team_id' => $backend, 'title' => 'Backend work']);
    $qaTask = taskIn(projectFor($manager, $this->workflow, 'QA only'), ['team_id' => $qa, 'title' => 'QA work']);

    $this->actingAs($adnan)->get(route('tasks.show', $backendTask))->assertOk();
    $this->actingAs($adnan)->get(route('tasks.show', $qaTask))->assertForbidden();

    // The project carrying Backend work becomes visible; the QA-only one does not.
    $visible = Project::query()->visibleTo($adnan)->pluck('title')->all();
    expect($visible)->toContain($project->title)->not->toContain('QA only');
});

it('reaches Adnan’s team members even when a task carries no team', function () {
    $adnan = person('adnan@bargoventures.com');
    $manager = person('saad@bargoventures.com');
    $backendDev = person('usman@bargoventures.com');
    $qaTester = person('ashar@bargoventures.com');

    $project = projectFor($manager, $this->workflow);

    $his = taskIn($project, ['assignee_id' => $backendDev->id]);
    $notHis = taskIn(projectFor($manager, $this->workflow, 'QA only'), ['assignee_id' => $qaTester->id]);

    $this->actingAs($adnan)->get(route('tasks.show', $his))->assertOk();
    $this->actingAs($adnan)->get(route('tasks.show', $notHis))->assertForbidden();
});

it('keeps administration and People & Goals away from everyone but the super admin', function () {
    // The whole administration surface — the directory included — and the
    // People & Goals screens belong to the Super Admin alone. The sidebar
    // hides both sections for everyone else; these are the routes behind them.
    $reserved = [
        ['get', route('users.index')],
        ['get', route('users.create')],
        ['get', route('roles.index')],
        ['get', route('permission-schemes.index')],
        ['get', route('workflows.index')],
        ['get', route('automations.index')],
        ['get', route('activity.index')],
        ['get', route('meetings.index')],
        ['get', route('okrs.index')],
        ['get', route('feedback.cycles.index')],
    ];

    foreach (['saad@bargoventures.com', 'adnan@bargoventures.com', 'farhan@bargoventures.com'] as $email) {
        foreach ($reserved as [$method, $url]) {
            $this->actingAs(person($email))->{$method}($url)->assertForbidden();
        }
    }

    foreach ($reserved as [$method, $url]) {
        $this->actingAs(person('asif@bargoventures.com'))->{$method}($url)->assertSuccessful();
    }
});

it('refuses a direct permission grant from someone who may edit users but not assign', function () {
    // Managers hold neither users.update nor users.assign-permissions, so the
    // escalation path is closed at the request layer, not the form.
    $manager = person('saad@bargoventures.com');
    $target = person('yousuf@bargoventures.com');

    $this->actingAs($manager)
        ->patch(route('users.update', $target), [
            'name' => $target->name,
            'permissions' => ['users.create'],
        ])
        ->assertForbidden();

    expect($target->fresh()->hasPermission('users.create'))->toBeFalse();
});
