<?php

use App\Modules\NotificationCenter\Jobs\SendNotificationDigests;
use App\Modules\NotificationCenter\Mail\NotificationMail;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Models\NotificationPreference;
use App\Modules\NotificationCenter\Services\NotificationDelivery;
use App\Modules\TaskManagement\Models\Task;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
    Mail::fake();
});

/*
|--------------------------------------------------------------------------
| Email delivery
|--------------------------------------------------------------------------
*/

it('emails a notification immediately by default', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    Mail::assertQueued(NotificationMail::class, fn ($mail) => $mail->hasTo($assignee->email) && ! $mail->isDigest);
});

it('marks a notification as emailed so a digest cannot resend it', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect(Notification::query()->where('user_id', $assignee->id)->first()->emailed_at)->not->toBeNull();
});

it('sends no email when the user turns email off', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $assignee->forceFill(['email_digest' => 'off'])->save();

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    Mail::assertNothingOutgoing();
    // The in-app record still exists — only the channel was silenced.
    expect(Notification::query()->where('user_id', $assignee->id)->count())->toBe(1);
});

it('holds email back for a user on daily digest', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $assignee->forceFill(['email_digest' => 'daily'])->save();

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    Mail::assertNothingOutgoing();

    // ...until the digest runs.
    (new SendNotificationDigests)->handle(app(NotificationDelivery::class));

    Mail::assertQueued(NotificationMail::class, fn ($mail) => $mail->hasTo($assignee->email) && $mail->isDigest);
});

it('does not include already-read items in a digest', function () {
    $user = TaskHelpers::userWith(['tasks.view']);
    $user->forceFill(['email_digest' => 'daily'])->save();

    Notification::query()->create([
        'user_id' => $user->id, 'type' => 'x', 'group' => Notification::GROUP_TASKS,
        'title' => 'Already read', 'read_at' => now(),
    ]);

    (new SendNotificationDigests)->handle(app(NotificationDelivery::class));

    Mail::assertNothingOutgoing();
});

/*
|--------------------------------------------------------------------------
| Per-group preferences
|--------------------------------------------------------------------------
*/

it('respects a muted group for email while keeping the in-app record', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);

    NotificationPreference::query()->create([
        'user_id' => $assignee->id,
        'group' => Notification::GROUP_TASKS,
        'in_app' => true,
        'email' => false,
    ]);

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    Mail::assertNothingOutgoing();
    expect(Notification::query()->where('user_id', $assignee->id)->count())->toBe(1);
});

it('creates no record at all when a group is muted in-app', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);

    NotificationPreference::query()->create([
        'user_id' => $assignee->id,
        'group' => Notification::GROUP_TASKS,
        'in_app' => false,
        'email' => false,
    ]);

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    expect(Notification::query()->where('user_id', $assignee->id)->count())->toBe(0);
    Mail::assertNothingOutgoing();
});

it('defaults a group with no stored preference to on', function () {
    $user = TaskHelpers::userWith(['tasks.view']);
    $delivery = app(NotificationDelivery::class);

    expect($delivery->wantsInApp($user, Notification::GROUP_TASKS))->toBeTrue();
    expect($delivery->wantsEmail($user, Notification::GROUP_TASKS))->toBeTrue();
    // "system" is deliberately quiet by email unless opted in.
    expect($delivery->wantsEmail($user, Notification::GROUP_SYSTEM))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The settings screen
|--------------------------------------------------------------------------
*/

it('shows every group on the preferences screen', function () {
    $user = TaskHelpers::userWith(['tasks.view']);

    $this->actingAs($user)
        ->get(route('notifications.preferences'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('preferences', count(Notification::GROUPS))
            ->where('digest', 'immediate'));
});

it('saves preference changes', function () {
    $user = TaskHelpers::userWith(['tasks.view']);

    $this->actingAs($user)->patch(route('notifications.preferences.update'), [
        'digest' => 'daily',
        'preferences' => [
            ['group' => Notification::GROUP_TASKS, 'in_app' => true, 'email' => false],
            ['group' => Notification::GROUP_MENTIONS, 'in_app' => false, 'email' => true],
        ],
    ])->assertRedirect();

    expect($user->fresh()->email_digest)->toBe('daily');

    $delivery = app(NotificationDelivery::class);
    $fresh = $user->fresh();

    expect($delivery->wantsEmail($fresh, Notification::GROUP_TASKS))->toBeFalse();
    expect($delivery->wantsInApp($fresh, Notification::GROUP_MENTIONS))->toBeFalse();
});

it('rejects an unknown digest mode', function () {
    $user = TaskHelpers::userWith(['tasks.view']);

    $this->actingAs($user)->patch(route('notifications.preferences.update'), [
        'digest' => 'hourly',
        'preferences' => [],
    ])->assertSessionHasErrors('digest');
});

it('ignores an unknown group when saving', function () {
    $user = TaskHelpers::userWith(['tasks.view']);

    $this->actingAs($user)->patch(route('notifications.preferences.update'), [
        'digest' => 'immediate',
        'preferences' => [['group' => 'not_a_group', 'in_app' => false, 'email' => false]],
    ])->assertRedirect();

    expect(NotificationPreference::query()->where('user_id', $user->id)->count())->toBe(0);
});
