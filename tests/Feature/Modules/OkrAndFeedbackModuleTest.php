<?php

use App\Modules\Feedback\Models\FeedbackCycle;
use App\Modules\Feedback\Models\FeedbackRequest;
use App\Modules\Okrs\Models\KeyResult;
use App\Modules\Okrs\Models\Objective;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

function objectivePayload(array $overrides = []): array
{
    return $overrides + [
        'title' => 'Grow retention',
        'period' => 'Q2 2026',
        'starts_at' => '2026-04-01',
        'ends_at' => '2026-06-30',
        'status' => 'active',
        'visibility' => 'company',
    ];
}

/*
|--------------------------------------------------------------------------
| OKRs
|--------------------------------------------------------------------------
*/

it('lists objectives', function () {
    $user = TaskHelpers::userWith(['okrs.view']);

    $this->actingAs($user)
        ->get(route('okrs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('objectives'));
});

it('refuses the objective list without the permission', function () {
    $user = TaskHelpers::userWith([]);

    $this->actingAs($user)->get(route('okrs.index'))->assertForbidden();
});

it('creates an objective with key results', function () {
    $user = TaskHelpers::userWith(['okrs.view', 'okrs.create']);

    $this->actingAs($user)->post(route('okrs.store'), objectivePayload([
        'key_results' => [
            ['title' => 'Lift NPS', 'metric_type' => 'number', 'start_value' => 30, 'target_value' => 50],
        ],
    ]))->assertRedirect();

    $objective = Objective::query()->where('title', 'Grow retention')->firstOrFail();

    expect($objective->keyResults()->count())->toBe(1);
    expect($objective->keyResults()->value('target_value'))->toEqual(50.0);
});

it('rejects an objective ending before it starts', function () {
    $user = TaskHelpers::userWith(['okrs.view', 'okrs.create']);

    $this->actingAs($user)
        ->post(route('okrs.store'), objectivePayload(['starts_at' => '2026-06-30', 'ends_at' => '2026-04-01']))
        ->assertSessionHasErrors('ends_at');
});

it('rejects an unknown visibility', function () {
    $user = TaskHelpers::userWith(['okrs.view', 'okrs.create']);

    $this->actingAs($user)
        ->post(route('okrs.store'), objectivePayload(['visibility' => 'cosmic']))
        ->assertSessionHasErrors('visibility');
});

it('refuses objective creation without the permission', function () {
    $user = TaskHelpers::userWith(['okrs.view']);

    $this->actingAs($user)->post(route('okrs.store'), objectivePayload())->assertForbidden();
});

it('records a key result update and moves the objective progress', function () {
    $user = TaskHelpers::userWith(['okrs.view', 'okrs.create', 'okrs.update']);

    $this->actingAs($user)->post(route('okrs.store'), objectivePayload([
        'key_results' => [
            ['title' => 'Lift NPS', 'metric_type' => 'number', 'start_value' => 0, 'target_value' => 100, 'current_value' => 0],
        ],
    ]))->assertRedirect();

    $objective = Objective::query()->where('title', 'Grow retention')->firstOrFail();
    $keyResult = $objective->keyResults()->firstOrFail();

    $this->actingAs($user)->post(route('okrs.key-results.updates', [$objective, $keyResult]), [
        'value' => 50,
        'confidence' => 'on_track',
        'note' => 'Halfway',
    ])->assertRedirect();

    expect((float) $keyResult->fresh()->current_value)->toEqual(50.0);
    expect($objective->fresh()->progress)->toBeGreaterThan(0);
});

it('hides a private objective from someone else', function () {
    $owner = TaskHelpers::userWith(['okrs.view', 'okrs.create']);
    $other = TaskHelpers::userWith(['okrs.view']);

    $this->actingAs($owner)
        ->post(route('okrs.store'), objectivePayload(['visibility' => 'private']))
        ->assertRedirect();

    $objective = Objective::query()->where('title', 'Grow retention')->firstOrFail();

    $this->actingAs($other)->get(route('okrs.show', $objective))->assertForbidden();
    $this->actingAs($owner)->get(route('okrs.show', $objective))->assertOk();
});

it('deletes an objective', function () {
    $user = TaskHelpers::admin();

    $this->actingAs($user)->post(route('okrs.store'), objectivePayload())->assertRedirect();
    $objective = Objective::query()->where('title', 'Grow retention')->firstOrFail();

    $this->actingAs($user)->delete(route('okrs.destroy', $objective))->assertRedirect();

    expect(Objective::query()->whereKey($objective->id)->exists())->toBeFalse();
    expect(KeyResult::query()->where('objective_id', $objective->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Feedback
|--------------------------------------------------------------------------
*/

function cyclePayload(array $overrides = []): array
{
    return $overrides + [
        'name' => 'H1 peer review',
        'kind' => 'peer',
        'starts_at' => '2026-04-01',
        'ends_at' => '2026-04-30',
        'status' => 'draft',
    ];
}

it('lists feedback cycles', function () {
    $user = TaskHelpers::userWith(['feedback.view']);

    $this->actingAs($user)
        ->get(route('feedback.cycles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('cycles'));
});

it('creates a cycle with questions and review pairs', function () {
    $manager = TaskHelpers::userWith(['feedback.view', 'feedback.manage']);
    $subject = TaskHelpers::userWith(['feedback.view']);
    $reviewer = TaskHelpers::userWith(['feedback.view', 'feedback.give']);

    $this->actingAs($manager)->post(route('feedback.cycles.store'), cyclePayload([
        'questions' => [['body' => 'What went well?', 'kind' => 'text', 'required' => true]],
        'pairs' => [['subject_user_id' => $subject->id, 'reviewer_id' => $reviewer->id]],
    ]))->assertRedirect();

    $cycle = FeedbackCycle::query()->where('name', 'H1 peer review')->firstOrFail();

    expect($cycle->questions()->count())->toBe(1);
    expect($cycle->requests()->count())->toBe(1);
});

it('refuses a reviewer who is also the subject', function () {
    $manager = TaskHelpers::userWith(['feedback.view', 'feedback.manage']);
    $person = TaskHelpers::userWith(['feedback.view']);

    $this->actingAs($manager)->post(route('feedback.cycles.store'), cyclePayload([
        'pairs' => [['subject_user_id' => $person->id, 'reviewer_id' => $person->id]],
    ]))->assertSessionHasErrors('pairs.0.reviewer_id');
});

it('rejects an unknown cycle kind', function () {
    $manager = TaskHelpers::userWith(['feedback.view', 'feedback.manage']);

    $this->actingAs($manager)
        ->post(route('feedback.cycles.store'), cyclePayload(['kind' => 'telepathic']))
        ->assertSessionHasErrors('kind');
});

it('refuses cycle creation without the manage permission', function () {
    $user = TaskHelpers::userWith(['feedback.view']);

    $this->actingAs($user)->post(route('feedback.cycles.store'), cyclePayload())->assertForbidden();
});

it('activates and closes a cycle', function () {
    $manager = TaskHelpers::admin();

    $this->actingAs($manager)->post(route('feedback.cycles.store'), cyclePayload())->assertRedirect();
    $cycle = FeedbackCycle::query()->where('name', 'H1 peer review')->firstOrFail();

    $this->actingAs($manager)->post(route('feedback.cycles.activate', $cycle))->assertRedirect();
    expect($cycle->fresh()->status)->toBe('active');

    $this->actingAs($manager)->post(route('feedback.cycles.close', $cycle))->assertRedirect();
    expect($cycle->fresh()->status)->toBe('closed');
});

it('lets an invited reviewer decline', function () {
    $manager = TaskHelpers::admin();
    $subject = TaskHelpers::userWith(['feedback.view']);
    $reviewer = TaskHelpers::userWith(['feedback.view', 'feedback.give']);

    $this->actingAs($manager)->post(route('feedback.cycles.store'), cyclePayload([
        'status' => 'active',
        'pairs' => [['subject_user_id' => $subject->id, 'reviewer_id' => $reviewer->id]],
    ]))->assertRedirect();

    $request = FeedbackRequest::query()->where('reviewer_id', $reviewer->id)->firstOrFail();

    $this->actingAs($reviewer)->post(route('feedback.requests.decline', $request))->assertRedirect();

    expect($request->fresh()->status)->toBe('declined');
});

it('keeps one reviewer out of another reviewer request', function () {
    $manager = TaskHelpers::admin();
    $subject = TaskHelpers::userWith(['feedback.view']);
    $reviewer = TaskHelpers::userWith(['feedback.view', 'feedback.give']);
    $stranger = TaskHelpers::userWith(['feedback.view', 'feedback.give']);

    $this->actingAs($manager)->post(route('feedback.cycles.store'), cyclePayload([
        'status' => 'active',
        'pairs' => [['subject_user_id' => $subject->id, 'reviewer_id' => $reviewer->id]],
    ]))->assertRedirect();

    $request = FeedbackRequest::query()->where('reviewer_id', $reviewer->id)->firstOrFail();

    $this->actingAs($stranger)->get(route('feedback.requests.show', $request))->assertForbidden();
});
