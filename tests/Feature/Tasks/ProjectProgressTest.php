<?php

use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Services\ProjectProgressService;
use App\Modules\TaskManagement\Models\Task;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

it('derives progress from tasks when a project has no milestones', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    Task::factory()->count(3)->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'status' => 'todo']);
    $done = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'status' => 'todo']);

    $this->actingAs($admin)->patch(route('tasks.status', $done), ['status' => 'done']);

    // 1 of 4 finished.
    expect($project->fresh()->progress)->toBe(25);
});

it('moves progress as work is completed', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $a = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'status' => 'todo']);
    $b = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'status' => 'todo']);

    $this->actingAs($admin)->patch(route('tasks.status', $a), ['status' => 'done']);
    expect($project->fresh()->progress)->toBe(50);

    $this->actingAs($admin)->patch(route('tasks.status', $b), ['status' => 'done']);
    expect($project->fresh()->progress)->toBe(100);

    // And back down when work is reopened.
    $this->actingAs($admin)->patch(route('tasks.status', $b), ['status' => 'todo']);
    expect($project->fresh()->progress)->toBe(50);
});

it('prefers milestones over tasks when both exist', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    Milestone::query()->create(['project_id' => $project->id, 'title' => 'Alpha', 'completed_at' => now()]);
    Milestone::query()->create(['project_id' => $project->id, 'title' => 'Beta']);

    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'status' => 'todo']);
    $this->actingAs($admin)->patch(route('tasks.status', $task), ['status' => 'done']);

    // Tasks say 100%; the milestones say 50% and they win.
    expect($project->fresh()->progress)->toBe(50);
});

it('leaves a manually entered figure alone when there is nothing to derive from', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $project->forceFill(['progress' => 42])->save();

    app(ProjectProgressService::class)->recalculate($project);

    expect($project->fresh()->progress)->toBe(42);
});

it('ignores archived work and subtasks', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $parent = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'status' => 'done']);
    Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'status' => 'todo', 'parent_task_id' => $parent->id,
    ]);
    Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'status' => 'todo', 'archived_at' => now(),
    ]);

    // Only the parent counts, and it is done.
    expect(app(ProjectProgressService::class)->recalculate($project->fresh()))->toBe(100);
});
