<?php

use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskStatusHistory;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Services\WorkflowService;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| The QA / deployment pipeline
|--------------------------------------------------------------------------
*/

it('exposes the full delivery pipeline as board columns', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['project' => $project->slug]))
        ->assertOk()
        ->assertInertia(function ($page) {
            $keys = collect($page->toArray()['props']['statuses'])->pluck('key')->all();

            expect($keys)->toBe([
                'todo', 'in_progress', 'pushed_for_qa', 'qa_in_progress',
                'review', 'ready_for_deployment', 'deployed',
            ]);
        });
});

it('walks a task through the whole pipeline', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    $steps = [
        ['in_progress', null],
        ['pushed_for_qa', null],
        ['qa_in_progress', null],
        // QA passing routes to Review, and demands a verdict.
        ['review', 'Regression suite green; smoke-tested checkout on staging.'],
        ['ready_for_deployment', null],
        ['deployed', null],
    ];

    foreach ($steps as [$status, $reason]) {
        $payload = ['status' => $status];
        if ($reason) {
            $payload['reason'] = $reason;
        }

        $this->actingAs($user)
            ->patch(route('tasks.status', $task), $payload)
            ->assertRedirect();

        expect($task->fresh()->status)->toBe($status);
    }

    // "deployed" is the done state, so completion is recorded.
    expect($task->fresh()->completed_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| QA must record a verdict reason
|--------------------------------------------------------------------------
*/

it('refuses a QA pass to Review without a reason', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'qa_in_progress']);

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'review'])
        ->assertSessionHasErrors('reason');

    expect($task->fresh()->status)->toBe('qa_in_progress');
});

it('refuses a QA failure back to development without a reason', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'qa_in_progress']);

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'in_progress', 'reason' => '   '])
        ->assertSessionHasErrors('reason');

    expect($task->fresh()->status)->toBe('qa_in_progress');
});

it('stores the QA verdict against the transition that produced it', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'qa_in_progress']);

    $reason = 'Checkout throws a 500 when the cart is empty. Steps: 1) empty cart 2) submit.';

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'in_progress', 'reason' => $reason])
        ->assertRedirect();

    $history = TaskStatusHistory::query()
        ->where('task_id', $task->id)
        ->where('to_status', 'in_progress')
        ->first();

    expect($history)->not->toBeNull();
    expect($history->note)->toBe($reason);
    expect($history->from_status)->toBe('qa_in_progress');
});

it('surfaces the QA reason on the task detail page', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'qa_in_progress']);

    $this->actingAs($user)->patch(route('tasks.status', $task), [
        'status' => 'in_progress',
        'reason' => 'Login redirect loops on Safari.',
    ]);

    $this->actingAs($user)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(function ($page) {
            $notes = collect($page->toArray()['props']['statusHistory'])->pluck('note')->filter();
            expect($notes)->toContain('Login redirect loops on Safari.');
        });
});

it('does not demand a reason for ordinary moves', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'pushed_for_qa']);

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'qa_in_progress'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($task->fresh()->status)->toBe('qa_in_progress');
});

/*
|--------------------------------------------------------------------------
| Pipeline ordering is enforced
|--------------------------------------------------------------------------
*/

it('refuses to skip QA on the way to deployment', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'in_progress']);

    $this->actingAs($user)
        ->patch(route('tasks.status', $task), ['status' => 'deployed'])
        ->assertSessionHasErrors('status');

    expect($task->fresh()->status)->toBe('in_progress');
});

/*
|--------------------------------------------------------------------------
| The client is told which moves need a reason
|--------------------------------------------------------------------------
*/

it('flags which transitions require a reason', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'qa_in_progress']);

    $this->actingAs($user)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(function ($page) {
            $options = collect($page->toArray()['props']['transitionOptions'])->keyBy('key');

            // From QA, both verdicts demand a reason.
            expect($options['review']['requires_comment'])->toBeTrue();
            expect($options['review']['comment_label'])->toContain('QA passed');
            expect($options['in_progress']['requires_comment'])->toBeTrue();
            expect($options['in_progress']['comment_label'])->toContain('QA failed');
        });
});

it('does not demand a reason for an ordinary pipeline step', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::softwareProject($user);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    $svc = app(WorkflowService::class);
    $workflow = Workflow::with(['statuses', 'transitions'])->where('name', 'Software delivery')->firstOrFail();

    expect($svc->requiredCommentLabel($workflow, 'todo', 'in_progress'))->toBeNull();
    expect($svc->requiredCommentLabel($workflow, 'in_progress', 'pushed_for_qa'))->toBeNull();
    // Only the QA and review verdicts demand one.
    expect($svc->requiredCommentLabel($workflow, 'qa_in_progress', 'review'))->not->toBeNull();
    expect($svc->requiredCommentLabel($workflow, 'review', 'in_progress'))->not->toBeNull();
});
