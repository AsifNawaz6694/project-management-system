<?php

use App\Models\User;
use App\Modules\MeetingManagement\Models\Meeting;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskComment;
use App\Modules\Teams\Models\Team;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/** Every hit across every section, flattened. */
function hits(array $payload): array
{
    return collect($payload['sections'] ?? [])->flatMap(fn ($s) => $s['items'])->pluck('title')->all();
}

/*
|--------------------------------------------------------------------------
| Matching
|--------------------------------------------------------------------------
*/

it('finds a task by its title', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'title' => 'Refactor the invoicing job']);

    $response = $this->actingAs($admin)->getJson(route('search.quick', ['q' => 'invoicing']));

    $response->assertOk();
    expect(hits($response->json()))->toContain('Refactor the invoicing job');
});

it('finds a task by its human-readable key', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    // Numbers are assigned by TaskService, so raise this one through the app.
    $this->actingAs($admin)->post(route('tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Keyed work',
        'priority' => 'medium',
    ])->assertRedirect();

    $task = Task::query()->where('title', 'Keyed work')->firstOrFail();

    $response = $this->actingAs($admin)->getJson(route('search.quick', ['q' => $task->key_label]));

    expect(hits($response->json()))->toContain('Keyed work');
});

it('finds a project', function () {
    $admin = TaskHelpers::admin();
    TaskHelpers::project($admin)->forceFill(['title' => 'Atlas migration'])->save();

    $response = $this->actingAs($admin)->getJson(route('search.quick', ['q' => 'Atlas']));

    expect(hits($response->json()))->toContain('Atlas migration');
});

it('finds a comment and points at its task', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);

    TaskComment::query()->create(['task_id' => $task->id, 'user_id' => $admin->id, 'body' => 'The connection pool is exhausted']);

    $response = $this->actingAs($admin)->getJson(route('search.quick', ['q' => 'connection pool']));
    $section = collect($response->json('sections'))->firstWhere('key', 'comments');

    expect($section)->not->toBeNull();
    expect($section['items'][0]['url'])->toContain((string) $task->id);
});

it('finds people and teams', function () {
    $admin = TaskHelpers::admin();
    User::factory()->create(['name' => 'Zephyrine Farooq', 'status' => 'active']);
    Team::query()->create(['name' => 'Zephyr squad', 'slug' => 'zephyr-squad']);

    $response = $this->actingAs($admin)->getJson(route('search.quick', ['q' => 'Zephyr']));
    $titles = hits($response->json());

    expect($titles)->toContain('Zephyrine Farooq');
    expect($titles)->toContain('Zephyr squad');
});

it('finds a meeting', function () {
    $admin = TaskHelpers::admin();

    Meeting::query()->create([
        'title' => 'Quarterly planning offsite',
        'kind' => 'planning',
        'organizer_id' => $admin->id,
        'starts_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('search.quick', ['q' => 'offsite']));

    expect(hits($response->json()))->toContain('Quarterly planning offsite');
});

/*
|--------------------------------------------------------------------------
| Guardrails
|--------------------------------------------------------------------------
*/

it('says nothing for a one-character term', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'title' => 'A']);

    $response = $this->actingAs($admin)->getJson(route('search.quick', ['q' => 'A']));

    expect($response->json('total'))->toBe(0);
    expect($response->json('sections'))->toBe([]);
});

it('never surfaces a record the user cannot open', function () {
    $owner = TaskHelpers::admin();
    $outsider = TaskHelpers::userWith(['tasks.view', 'projects.view']);
    $project = TaskHelpers::project($owner);

    Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $owner->id,
        'title' => 'Confidential rework',
    ]);

    $response = $this->actingAs($outsider)->getJson(route('search.quick', ['q' => 'Confidential']));

    expect(hits($response->json()))->not->toContain('Confidential rework');
});

it('leaves out a section the user has no permission for', function () {
    $user = TaskHelpers::userWith(['tasks.view']);
    User::factory()->create(['name' => 'Hidden Person', 'status' => 'active']);

    $response = $this->actingAs($user)->getJson(route('search.quick', ['q' => 'Hidden']));

    // No users.view permission, so the People section is not even offered.
    expect(collect($response->json('sections'))->pluck('key')->all())->not->toContain('people');
});

it('treats a wildcard in the term as a literal', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'title' => 'Ordinary work']);

    // Unescaped, "%%" would match everything.
    $response = $this->actingAs($admin)->getJson(route('search.quick', ['q' => '%%']));

    expect(hits($response->json()))->not->toContain('Ordinary work');
});

it('requires a signed-in user', function () {
    $this->getJson(route('search.quick', ['q' => 'anything']))->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| The results page
|--------------------------------------------------------------------------
*/

it('renders the results page', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'title' => 'Findable work']);

    $this->actingAs($admin)
        ->get(route('search.index', ['q' => 'Findable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('results.term', 'Findable')->where('results.total', 1));
});

it('renders the results page with no term at all', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->get(route('search.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('results.total', 0));
});
