<?php

use App\Modules\TaskManagement\Models\Task;
use App\Modules\Teams\Models\Team;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowAssignment;
use App\Modules\Workflow\Services\WorkflowService;
use Tests\Feature\Tasks\TaskHelpers;

/**
 * Chains of task stages assigned to a person or a team.
 *
 * The project's workflow stays the canonical pipeline; an assigned chain only
 * subtracts from it. The first test pins the shipped behaviour — no
 * assignments, everybody sees the same stages — because that is the state the
 * feature has to preserve until somebody deliberately narrows a person.
 */
beforeEach(function () {
    TaskHelpers::bootstrap();
});

/**
 * A chain naming exactly these status keys.
 *
 * @param  array<int, string>  $keys
 */
function chain(string $name, array $keys): Workflow
{
    $workflow = Workflow::query()->create([
        'name' => $name,
        'description' => 'Test chain',
        'is_default' => false,
        'is_system' => false,
    ]);

    foreach ($keys as $i => $key) {
        $workflow->statuses()->create([
            'key' => $key,
            'name' => ucfirst(str_replace('_', ' ', $key)),
            'category' => $key === 'done' ? 'done' : ($i === 0 ? 'todo' : 'in_progress'),
            'color' => 'slate',
            'position' => $i + 1,
            'is_initial' => $i === 0,
        ]);
    }

    return $workflow->fresh('statuses');
}

function putOnChain(Workflow $workflow, string $type, int $id): void
{
    WorkflowAssignment::query()->updateOrCreate(
        ['assignable_type' => $type, 'assignable_id' => $id],
        ['workflow_id' => $workflow->id],
    );

    WorkflowService::flushCache();
}

/*
|--------------------------------------------------------------------------
| Resolution
|--------------------------------------------------------------------------
*/

it('offers every stage of the project workflow while nobody is narrowed', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    expect(WorkflowAssignment::query()->count())->toBe(0);

    $options = app(WorkflowService::class)->transitionOptions($task->fresh(), $user);

    expect(collect($options)->pluck('key')->sort()->values()->all())->toBe(['done', 'in_progress']);
});

it('narrows the stages offered to a person on a chain', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.view', 'tasks.update-status']);
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'status' => 'todo',
        'assignee_id' => $user->id,
    ]);

    putOnChain(chain('No closing', ['todo', 'in_progress']), WorkflowAssignment::TYPE_USER, $user->id);

    $options = app(WorkflowService::class)->transitionOptions($task->fresh(), $user->fresh());

    expect(collect($options)->pluck('key')->all())->toBe(['in_progress']);

    // The owner, who is on no chain, still sees the full set.
    expect(collect(app(WorkflowService::class)->transitionOptions($task->fresh(), $owner))->pluck('key')->sort()->values()->all())
        ->toBe(['done', 'in_progress']);
});

it('refuses a move into a stage the chain leaves out', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.view', 'tasks.update-status']);
    $project = TaskHelpers::project($owner);
    $project->members()->attach([$user->id => ['role' => 'member']]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'status' => 'todo',
        'assignee_id' => $user->id,
    ]);

    putOnChain(chain('No closing', ['todo', 'in_progress']), WorkflowAssignment::TYPE_USER, $user->id);

    // Allowed: in the chain.
    $this->actingAs($user->fresh())
        ->patch(route('tasks.status', $task), ['status' => 'in_progress'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('in_progress');

    // Refused: outside it. The board would not offer this, but the write path
    // is what actually has to hold.
    $this->actingAs($user->fresh())
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertSessionHasErrors('status');

    expect($task->fresh()->status)->toBe('in_progress');
});

it('applies a team chain to its members', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.view', 'tasks.update-status']);
    $team = Team::query()->create(['name' => 'Chain squad '.uniqid(), 'color' => 'blue']);
    $team->members()->attach([$user->id => ['role' => 'member']]);

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    putOnChain(chain('Team lane', ['todo', 'in_progress']), WorkflowAssignment::TYPE_TEAM, $team->id);

    $options = app(WorkflowService::class)->transitionOptions($task->fresh(), $user->fresh());

    expect(collect($options)->pluck('key')->all())->toBe(['in_progress']);
});

it('prefers a person’s own chain over their team’s', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.view', 'tasks.update-status']);
    $team = Team::query()->create(['name' => 'Chain squad '.uniqid(), 'color' => 'blue']);
    $team->members()->attach([$user->id => ['role' => 'member']]);

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    putOnChain(chain('Team lane', ['todo']), WorkflowAssignment::TYPE_TEAM, $team->id);
    putOnChain(chain('Personal lane', ['todo', 'done']), WorkflowAssignment::TYPE_USER, $user->id);

    $options = app(WorkflowService::class)->transitionOptions($task->fresh(), $user->fresh());

    expect(collect($options)->pluck('key')->all())->toBe(['done']);
});

it('cannot introduce a stage the project workflow does not have', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.view', 'tasks.update-status']);
    $project = TaskHelpers::project($owner);   // todo / in_progress / done
    $project->members()->attach([$user->id => ['role' => 'member']]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'status' => 'todo',
        'assignee_id' => $user->id,
    ]);

    // The chain names a stage the project has never heard of.
    putOnChain(chain('Invented', ['todo', 'shipped_to_mars']), WorkflowAssignment::TYPE_USER, $user->id);

    $options = app(WorkflowService::class)->transitionOptions($task->fresh(), $user->fresh());

    expect($options)->toBe([]);

    $this->actingAs($user->fresh())
        ->patch(route('tasks.status', $task), ['status' => 'shipped_to_mars'])
        ->assertSessionHasErrors('status');

    expect($task->fresh()->status)->toBe('todo');
});

it('takes effect on the next request, without a cache flush of its own', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.view', 'tasks.update-status']);
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    $narrow = chain('No closing', ['todo', 'in_progress']);

    // Saving the assignment through the model is what an admin's request does;
    // the model event has to drop the memoised resolution.
    app(WorkflowService::class)->chainFor($user);   // warm the cache

    WorkflowAssignment::query()->create([
        'workflow_id' => $narrow->id,
        'assignable_type' => WorkflowAssignment::TYPE_USER,
        'assignable_id' => $user->id,
    ]);

    expect(collect(app(WorkflowService::class)->transitionOptions($task->fresh(), $user->fresh()))->pluck('key')->all())
        ->toBe(['in_progress']);
});

/*
|--------------------------------------------------------------------------
| Managing the assignments
|--------------------------------------------------------------------------
*/

it('lets an admin put people and teams on a chain', function () {
    $admin = TaskHelpers::admin();
    $person = TaskHelpers::userWith(['tasks.view']);
    $team = Team::query()->create(['name' => 'Chain squad '.uniqid(), 'color' => 'blue']);

    $workflow = chain('QA lane', ['todo', 'in_progress']);

    $this->actingAs($admin)
        ->post(route('workflows.people.assign', $workflow), ['user_ids' => [$person->id]])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('workflows.teams.assign', $workflow), ['team_ids' => [$team->id]])
        ->assertRedirect();

    expect($workflow->assignedUserIds())->toBe([$person->id])
        ->and($workflow->assignedTeamIds())->toBe([$team->id]);
});

it('releases anyone dropped from the submitted list', function () {
    $admin = TaskHelpers::admin();
    $stays = TaskHelpers::userWith(['tasks.view']);
    $goes = TaskHelpers::userWith(['tasks.view']);

    $workflow = chain('QA lane', ['todo']);

    $this->actingAs($admin)->post(route('workflows.people.assign', $workflow), ['user_ids' => [$stays->id, $goes->id]]);
    expect($workflow->assignedUserIds())->toHaveCount(2);

    $this->actingAs($admin)->post(route('workflows.people.assign', $workflow), ['user_ids' => [$stays->id]]);

    expect($workflow->assignedUserIds())->toBe([$stays->id]);

    // Dropped, not reassigned: they follow the project's chain again.
    WorkflowService::flushCache();
    expect(app(WorkflowService::class)->chainFor($goes->fresh()))->toBeNull();
});

it('moves a subject rather than letting them follow two chains', function () {
    $admin = TaskHelpers::admin();
    $person = TaskHelpers::userWith(['tasks.view']);

    $first = chain('First lane', ['todo']);
    $second = chain('Second lane', ['todo', 'done']);

    $this->actingAs($admin)->post(route('workflows.people.assign', $first), ['user_ids' => [$person->id]]);
    $this->actingAs($admin)->post(route('workflows.people.assign', $second), ['user_ids' => [$person->id]]);

    expect($first->assignedUserIds())->toBe([])
        ->and($second->assignedUserIds())->toBe([$person->id])
        ->and(WorkflowAssignment::query()->where('assignable_id', $person->id)->count())->toBe(1);
});

it('refuses assignment to someone without workflows.manage', function () {
    $user = TaskHelpers::userWith(['workflows.view']);
    $workflow = chain('QA lane', ['todo']);

    $this->actingAs($user)
        ->post(route('workflows.people.assign', $workflow), ['user_ids' => [$user->id]])
        ->assertForbidden();

    expect($workflow->assignedUserIds())->toBe([]);
});

it('shows the editor who follows this chain and who is on another', function () {
    $admin = TaskHelpers::admin();
    $person = TaskHelpers::userWith(['tasks.view']);

    $mine = chain('Mine', ['todo']);
    $theirs = chain('Theirs', ['todo']);

    putOnChain($theirs, WorkflowAssignment::TYPE_USER, $person->id);

    $this->actingAs($admin)
        ->get(route('workflows.edit', $mine))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('people')
            ->has('teams')
            ->where('people', fn ($people) => collect($people)
                ->firstWhere('id', $person->id)['other_chain'] === 'Theirs'));
});
