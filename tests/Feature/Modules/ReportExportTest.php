<?php

use App\Modules\Reporting\Services\CumulativeFlowService;
use App\Modules\TaskManagement\Models\Task;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| CSV export
|--------------------------------------------------------------------------
*/

it('streams a csv of the current selection', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'title' => 'Exportable work', 'priority' => 'high',
    ]);

    $response = $this->actingAs($admin)->get(route('reports.export.tasks'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();

    expect($body)->toContain('Exportable work');
    expect($body)->toContain('Key,Title,Project');
});

it('starts the csv with a byte order mark so excel reads it as utf-8', function () {
    $admin = TaskHelpers::admin();

    $body = $this->actingAs($admin)->get(route('reports.export.tasks'))->streamedContent();

    expect(substr($body, 0, 3))->toBe("\xEF\xBB\xBF");
});

it('honours the filters on the export', function () {
    $admin = TaskHelpers::admin();
    $mine = TaskHelpers::project($admin);
    $other = TaskHelpers::project($admin);

    Task::factory()->create(['project_id' => $mine->id, 'created_by_id' => $admin->id, 'title' => 'Included work']);
    Task::factory()->create(['project_id' => $other->id, 'created_by_id' => $admin->id, 'title' => 'Excluded work']);

    $body = $this->actingAs($admin)
        ->get(route('reports.export.tasks', ['project' => [$mine->slug]]))
        ->streamedContent();

    expect($body)->toContain('Included work');
    expect($body)->not->toContain('Excluded work');
});

it('never exports a task outside the caller visibility', function () {
    $owner = TaskHelpers::admin();
    $outsider = TaskHelpers::userWith(['tasks.view', 'reports.view', 'reports.export']);
    $project = TaskHelpers::project($owner);

    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'title' => 'Private work']);

    $body = $this->actingAs($outsider)->get(route('reports.export.tasks'))->streamedContent();

    expect($body)->not->toContain('Private work');
});

it('refuses the export without the export permission', function () {
    $user = TaskHelpers::userWith(['reports.view']);

    $this->actingAs($user)->get(route('reports.export.tasks'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Printable report
|--------------------------------------------------------------------------
*/

it('builds a printable report with a summary', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'status' => 'done', 'story_points' => 3,
    ]);
    Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'status' => 'todo', 'story_points' => 2, 'due_date' => now()->subDays(2)->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('reports.print'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.summary.total', 2)
            ->where('report.summary.done', 1)
            ->where('report.summary.overdue', 1)
            ->where('report.summary.points', 5));
});

it('refuses the printable report without the export permission', function () {
    $user = TaskHelpers::userWith(['reports.view']);

    $this->actingAs($user)->get(route('reports.print'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Cumulative flow
|--------------------------------------------------------------------------
*/

it('renders a flow band for every day in the window', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->get(route('reports.flow', ['days' => 14]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('days', 14)->has('flow.series', 14));
});

it('falls back to thirty days for a nonsense window', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->get(route('reports.flow', ['days' => 9999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('days', 30));
});

it('counts a task in the stage it is sitting in today', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'status' => 'todo']);

    $this->actingAs($admin)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    $flow = app(CumulativeFlowService::class)->build($admin->fresh(), 7);
    $today = collect($flow['series'])->last();

    expect($today['in_progress'])->toBe(1);
    expect($today['todo'] ?? 0)->toBe(0);
});

it('does not count a task on days before it existed', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'status' => 'todo']);

    $flow = app(CumulativeFlowService::class)->build($admin->fresh(), 7);
    $stages = collect($flow['stages'])->pluck('key');

    $firstDayTotal = $stages->sum(fn ($key) => (int) ($flow['series'][0][$key] ?? 0));
    $lastDayTotal = $stages->sum(fn ($key) => (int) (collect($flow['series'])->last()[$key] ?? 0));

    expect($firstDayTotal)->toBe(0);
    expect($lastDayTotal)->toBe(1);
});

it('keeps the flow chart inside the caller visibility', function () {
    $owner = TaskHelpers::admin();
    $outsider = TaskHelpers::userWith(['tasks.view', 'reports.view']);
    $project = TaskHelpers::project($owner);

    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'status' => 'todo']);

    $flow = app(CumulativeFlowService::class)->build($outsider, 7);
    $stages = collect($flow['stages'])->pluck('key');
    $total = $stages->sum(fn ($key) => (int) (collect($flow['series'])->last()[$key] ?? 0));

    expect($total)->toBe(0);
});

it('needs the report view permission', function () {
    $user = TaskHelpers::userWith([]);

    $this->actingAs($user)->get(route('reports.flow'))->assertForbidden();
});
