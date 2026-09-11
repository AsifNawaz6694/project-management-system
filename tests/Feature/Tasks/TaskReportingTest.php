<?php

use App\Modules\TaskManagement\Models\Task;
use App\Modules\Teams\Models\Team;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| F6 — the tiles and the trend must count the same thing
|--------------------------------------------------------------------------
*/

it('excludes subtasks from both the summary tiles and the trend chart', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    $parent = Task::factory()->create([
        'project_id' => $project->id,
        'created_at' => now()->subDay(),
        'completed_at' => now()->subDay(),
        'status' => 'done',
    ]);

    // A subtask completed in the same window must not inflate either number.
    Task::factory()->create([
        'project_id' => $project->id,
        'parent_task_id' => $parent->id,
        'created_at' => now()->subDay(),
        'completed_at' => now()->subDay(),
        'status' => 'done',
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['range' => 30]))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->where('overview.tasks_total', 1);
            $page->where('overview.tasks_completed', 1);

            // 'completed' here is the trend series key, not a status.
            $trendCompleted = collect($page->toArray()['props']['taskTrend'])->sum('completed');
            expect($trendCompleted)->toBe(1);
        });
});

/*
|--------------------------------------------------------------------------
| F7 — completion must come from task data, not the audit log
|--------------------------------------------------------------------------
*/

it('counts completions from task data even with no matching activity rows', function () {
    $user = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($user);

    // Created directly as completed — the old audit-log approach missed these.
    Task::factory()->count(3)->create([
        'project_id' => $project->id,
        'assignee_id' => $assignee->id,
        'status' => 'done',
        'completed_at' => now()->subHours(2),
    ]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('topPerformers', 1)
            ->where('topPerformers.0.completed_tasks', 3));
});

/*
|--------------------------------------------------------------------------
| New flow metrics
|--------------------------------------------------------------------------
*/

it('reports lead time, cycle time and an overdue rate', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    $task = Task::factory()->create(['project_id' => $project->id, 'created_at' => now()->subDays(2)]);

    $this->actingAs($user)->patch(route('tasks.status', $task), ['status' => 'in_progress']);
    $this->actingAs($user)->patch(route('tasks.status', $task), ['status' => 'done']);

    Task::factory()->overdue()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('flow.lead_time_hours')
            ->has('flow.cycle_time_hours')
            ->where('flow.overdue_rate', 100)
            ->has('aging', 4));
});

it('reports open workload per person and per team', function () {
    $user = TaskHelpers::admin();
    $worker = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($user);

    $team = Team::query()->create(['name' => 'Ops', 'color' => 'blue']);

    Task::factory()->count(3)->create([
        'project_id' => $project->id,
        'assignee_id' => $worker->id,
        'team_id' => $team->id,
        'estimate_minutes' => 120,
    ]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('workloadByUser.0.open_tasks', 3)
            ->where('workloadByUser.0.estimated_minutes', 360)
            ->where('workloadByTeam.0.open_tasks', 3));
});

it('surfaces custom workflow statuses in the status breakdown', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);

    Task::factory()->create(['project_id' => $project->id, 'status' => 'review']);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $keys = collect($page->toArray()['props']['tasksByStatus'])->pluck('key');
            expect($keys)->toContain('review');
            expect($keys)->toContain('pushed_for_qa');
        });
});
