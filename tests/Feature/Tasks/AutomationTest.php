<?php

use App\Modules\Automation\Models\AutomationAction as Action;
use App\Modules\Automation\Models\AutomationCondition as Condition;
use App\Modules\Automation\Models\AutomationRule;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskComment;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/**
 * Builds a rule with its conditions and actions in one call.
 *
 * @param  array<int, array{0: string, 1: string, 2: string|null}>  $conditions
 * @param  array<int, array{0: string, 1: array<string, mixed>}>  $actions
 */
function rule(string $trigger, array $conditions = [], array $actions = [], array $attributes = []): AutomationRule
{
    $rule = AutomationRule::query()->create($attributes + [
        'name' => 'Rule '.uniqid(),
        'trigger' => $trigger,
        'is_active' => true,
    ]);

    foreach ($conditions as $i => [$field, $operator, $value]) {
        $rule->conditions()->create(compact('field', 'operator', 'value') + ['position' => $i]);
    }

    foreach ($actions as $i => [$type, $config]) {
        $rule->actions()->create(['type' => $type, 'config' => $config, 'position' => $i]);
    }

    return $rule->fresh(['conditions', 'actions']);
}

/*
|--------------------------------------------------------------------------
| Triggers
|--------------------------------------------------------------------------
*/

it('runs a rule when a task is created', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);

    rule(AutomationRule::TRIGGER_CREATED, [], [[Action::SET_PRIORITY, ['priority' => 'critical']]]);

    $this->actingAs($owner)->post(route('tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Something urgent',
        'priority' => 'low',
    ]);

    expect(Task::query()->where('title', 'Something urgent')->value('priority'))->toBe('critical');
});

it('runs a rule when a task changes stage', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    rule(
        AutomationRule::TRIGGER_STATUS_CHANGED,
        [[Condition::FIELD_TO_STATUS, 'equals', 'in_progress']],
        [[Action::ADD_LABEL, ['label' => 'started']]],
    );

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect($task->fresh()->labels->pluck('name')->all())->toContain('started');
});

it('runs a rule when a task is commented on', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    rule(
        AutomationRule::TRIGGER_COMMENTED,
        [[Condition::FIELD_COMMENT, 'contains', 'blocked']],
        [[Action::SET_PRIORITY, ['priority' => 'high']]],
    );

    $this->actingAs($owner)->post(route('tasks.comments.store', $task), ['body' => 'We are blocked on infra']);

    expect($task->fresh()->priority)->toBe('high');
});

it('leaves other triggers alone', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'priority' => 'low']);

    rule(AutomationRule::TRIGGER_CREATED, [], [[Action::SET_PRIORITY, ['priority' => 'critical']]]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect($task->fresh()->priority)->toBe('low');
});

/*
|--------------------------------------------------------------------------
| Scope and activation
|--------------------------------------------------------------------------
*/

it('only runs a project-scoped rule on that project', function () {
    $owner = TaskHelpers::admin();
    $mine = TaskHelpers::project($owner);
    $other = TaskHelpers::project($owner);

    rule(
        AutomationRule::TRIGGER_STATUS_CHANGED,
        [],
        [[Action::SET_PRIORITY, ['priority' => 'critical']]],
        ['project_id' => $mine->id],
    );

    $inScope = Task::factory()->create(['project_id' => $mine->id, 'created_by_id' => $owner->id, 'priority' => 'low']);
    $outOfScope = Task::factory()->create(['project_id' => $other->id, 'created_by_id' => $owner->id, 'priority' => 'low']);

    $this->actingAs($owner)->patch(route('tasks.status', $inScope), ['status' => 'in_progress']);
    $this->actingAs($owner)->patch(route('tasks.status', $outOfScope), ['status' => 'in_progress']);

    expect($inScope->fresh()->priority)->toBe('critical');
    expect($outOfScope->fresh()->priority)->toBe('low');
});

it('does not run a paused rule', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'priority' => 'low']);

    rule(AutomationRule::TRIGGER_STATUS_CHANGED, [], [[Action::SET_PRIORITY, ['priority' => 'critical']]], ['is_active' => false]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect($task->fresh()->priority)->toBe('low');
});

/*
|--------------------------------------------------------------------------
| Conditions
|--------------------------------------------------------------------------
*/

it('skips a rule whose condition does not hold', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'priority' => 'low']);

    rule(
        AutomationRule::TRIGGER_STATUS_CHANGED,
        [[Condition::FIELD_TO_STATUS, 'equals', 'done']],
        [[Action::SET_PRIORITY, ['priority' => 'critical']]],
    );

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect($task->fresh()->priority)->toBe('low');
});

it('requires every condition to hold', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'priority' => 'low',
        'assignee_id' => null,
    ]);

    rule(
        AutomationRule::TRIGGER_STATUS_CHANGED,
        [
            [Condition::FIELD_TO_STATUS, 'equals', 'in_progress'],
            [Condition::FIELD_ASSIGNEE, 'is_not_empty', null], // fails — nobody assigned
        ],
        [[Action::SET_PRIORITY, ['priority' => 'critical']]],
    );

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect($task->fresh()->priority)->toBe('low');
});

it('matches a list with the in operator', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'priority' => 'low']);

    rule(
        AutomationRule::TRIGGER_STATUS_CHANGED,
        [[Condition::FIELD_TO_STATUS, 'in', json_encode(['done', 'in_progress'])]],
        [[Action::SET_PRIORITY, ['priority' => 'critical']]],
    );

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect($task->fresh()->priority)->toBe('critical');
});

it('reads the stage a task moved away from', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'status' => 'in_progress', 'priority' => 'low']);

    rule(
        AutomationRule::TRIGGER_STATUS_CHANGED,
        [[Condition::FIELD_FROM_STATUS, 'equals', 'in_progress']],
        [[Action::SET_PRIORITY, ['priority' => 'critical']]],
    );

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'done']);

    expect($task->fresh()->priority)->toBe('critical');
});

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

it('assigns to the reporter', function () {
    $owner = TaskHelpers::admin();
    $reporter = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $reporter->id, 'assignee_id' => null]);

    rule(AutomationRule::TRIGGER_STATUS_CHANGED, [], [[Action::ASSIGN, ['target' => Action::TARGET_REPORTER]]]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect($task->fresh()->assignee_id)->toBe($reporter->id);
});

it('posts a comment with the task placeholders filled in', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'title' => 'Ship the thing']);

    rule(
        AutomationRule::TRIGGER_STATUS_CHANGED,
        [],
        [[Action::ADD_COMMENT, ['body' => '{{task.title}} moved on.']]],
    );

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    $comment = TaskComment::query()->where('task_id', $task->id)->first();

    expect($comment)->not->toBeNull();
    expect($comment->body)->toBe('Ship the thing moved on.');
    expect($comment->user_id)->toBeNull(); // authored by the rule, not a person
});

it('sends a notification to a named person', function () {
    $owner = TaskHelpers::admin();
    $watcher = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    rule(
        AutomationRule::TRIGGER_STATUS_CHANGED,
        [],
        [[Action::NOTIFY, ['user_id' => (string) $watcher->id, 'title' => 'Have a look', 'body' => '{{task.key}}']]],
    );

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect(Notification::query()->where('user_id', $watcher->id)->where('type', 'automation.notify')->count())->toBe(1);
});

it('sets a due date relative to now', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'due_date' => null]);

    rule(AutomationRule::TRIGGER_STATUS_CHANGED, [], [[Action::SET_DUE_DATE, ['days' => '3']]]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect($task->fresh()->due_date->toDateString())->toBe(now()->addDays(3)->toDateString());
});

it('runs several actions in order', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'priority' => 'low']);

    rule(AutomationRule::TRIGGER_STATUS_CHANGED, [], [
        [Action::SET_PRIORITY, ['priority' => 'high']],
        [Action::ADD_LABEL, ['label' => 'escalated']],
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    $fresh = $task->fresh();

    expect($fresh->priority)->toBe('high');
    expect($fresh->labels->pluck('name')->all())->toContain('escalated');
});

/*
|--------------------------------------------------------------------------
| Safety
|--------------------------------------------------------------------------
*/

it('does not loop when a rule triggers itself', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'status' => 'todo']);

    // Moving to in_progress moves it to done, which fires the trigger again.
    rule(AutomationRule::TRIGGER_STATUS_CHANGED, [], [[Action::SET_STATUS, ['status' => 'done']]]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect($task->fresh()->status)->toBe('done');
    // One run per task per chain — not an unbounded cascade.
    expect(AutomationRun::query()->count())->toBeLessThanOrEqual(2);
});

it('records a failed run instead of breaking the request', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'status' => 'todo']);

    // "deployed" is not reachable from "in_progress" on the delivery workflow.
    $r = rule(AutomationRule::TRIGGER_STATUS_CHANGED, [], [[Action::SET_STATUS, ['status' => 'deployed']]]);

    $this->actingAs($owner)
        ->patch(route('tasks.status', $task), ['status' => 'in_progress'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('in_progress');
    expect(AutomationRun::query()->where('automation_rule_id', $r->id)->where('status', AutomationRun::STATUS_FAILED)->count())->toBe(1);
});

it('records a successful run', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    $r = rule(AutomationRule::TRIGGER_STATUS_CHANGED, [], [[Action::SET_PRIORITY, ['priority' => 'high']]]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect(AutomationRun::query()->where('automation_rule_id', $r->id)->where('status', AutomationRun::STATUS_SUCCESS)->count())->toBe(1);
    expect($r->fresh()->run_count)->toBe(1);
});

it('can be switched off entirely for a block of work', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'priority' => 'low']);

    rule(AutomationRule::TRIGGER_STATUS_CHANGED, [], [[Action::SET_PRIORITY, ['priority' => 'critical']]]);

    AutomationEngine::withoutRules(function () use ($owner, $task) {
        $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);
    });

    expect($task->fresh()->priority)->toBe('low');
});

/*
|--------------------------------------------------------------------------
| Admin screens
|--------------------------------------------------------------------------
*/

it('lists rules for a viewer without offering management', function () {
    $user = TaskHelpers::userWith(['automations.view']);
    rule(AutomationRule::TRIGGER_CREATED);

    $this->actingAs($user)
        ->get(route('automations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.manage', false)->has('rules', 1));
});

it('refuses the editor without the manage permission', function () {
    $user = TaskHelpers::userWith(['automations.view']);
    $r = rule(AutomationRule::TRIGGER_CREATED);

    $this->actingAs($user)->get(route('automations.edit', $r))->assertForbidden();
});

it('creates a rule switched off so it cannot fire half-built', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->post(route('automations.store'), ['name' => 'Escalate blockers', 'trigger' => AutomationRule::TRIGGER_COMMENTED])
        ->assertRedirect();

    $created = AutomationRule::query()->where('name', 'Escalate blockers')->firstOrFail();

    expect($created->is_active)->toBeFalse();
});

it('rejects an unknown trigger', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->post(route('automations.store'), ['name' => 'Nope', 'trigger' => 'task.exploded'])
        ->assertSessionHasErrors('trigger');
});

it('replaces the logic on save and drops a valueless comparison', function () {
    $admin = TaskHelpers::admin();
    $r = rule(AutomationRule::TRIGGER_CREATED, [[Condition::FIELD_PRIORITY, 'equals', 'low']], [[Action::SET_PRIORITY, ['priority' => 'high']]]);

    $this->actingAs($admin)
        ->put(route('automations.logic.update', $r), [
            'conditions' => [
                ['field' => Condition::FIELD_STATUS, 'operator' => 'equals', 'value' => 'todo'],
                ['field' => Condition::FIELD_TITLE, 'operator' => 'contains', 'value' => ''], // no value — dropped
                ['field' => Condition::FIELD_ASSIGNEE, 'operator' => 'is_empty', 'value' => null],
            ],
            'actions' => [['type' => Action::ADD_LABEL, 'config' => ['label' => 'triage']]],
        ])
        ->assertRedirect();

    $fresh = $r->fresh(['conditions', 'actions']);

    expect($fresh->conditions)->toHaveCount(2);
    expect($fresh->actions)->toHaveCount(1);
    expect($fresh->actions->first()->config['label'])->toBe('triage');
});

it('rejects an unknown condition field', function () {
    $admin = TaskHelpers::admin();
    $r = rule(AutomationRule::TRIGGER_CREATED);

    $this->actingAs($admin)
        ->put(route('automations.logic.update', $r), [
            'conditions' => [['field' => 'moon_phase', 'operator' => 'equals', 'value' => 'full']],
            'actions' => [],
        ])
        ->assertSessionHasErrors('conditions.0.field');
});

it('toggles a rule on and off', function () {
    $admin = TaskHelpers::admin();
    $r = rule(AutomationRule::TRIGGER_CREATED, [], [], ['is_active' => false]);

    $this->actingAs($admin)->post(route('automations.toggle', $r))->assertRedirect();
    expect($r->fresh()->is_active)->toBeTrue();

    $this->actingAs($admin)->post(route('automations.toggle', $r))->assertRedirect();
    expect($r->fresh()->is_active)->toBeFalse();
});

it('deletes a rule and its logic', function () {
    $admin = TaskHelpers::admin();
    $r = rule(AutomationRule::TRIGGER_CREATED, [[Condition::FIELD_PRIORITY, 'equals', 'low']], [[Action::SET_PRIORITY, ['priority' => 'high']]]);

    $this->actingAs($admin)->delete(route('automations.destroy', $r))->assertRedirect();

    expect(AutomationRule::query()->whereKey($r->id)->exists())->toBeFalse();
    expect(Condition::query()->where('automation_rule_id', $r->id)->count())->toBe(0);
    expect(Action::query()->where('automation_rule_id', $r->id)->count())->toBe(0);
});
