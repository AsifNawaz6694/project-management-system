<?php

use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\TaskManagement\Models\Task;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| Unread state survives, and clears only when actually read
|--------------------------------------------------------------------------
*/

it('shows the unread count on every page after signing in', function () {
    $owner = TaskHelpers::admin();
    $watcher = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $watcher->id,
    ]);

    // Three separate things happen to work the watcher is involved in.
    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);
    $this->actingAs($owner)->post(route('tasks.comments.store', $task), ['body' => 'Any update?']);

    $other = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);
    $this->actingAs($owner)->patch(route('tasks.update', $other), ['assignee_id' => $watcher->id]);

    // The shared Inertia payload carries the badge on any page.
    $this->actingAs($watcher)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('notifications.unread', 3));
});

it('keeps a notification unread until it is read', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    // Visiting an unrelated page must not clear it.
    $this->actingAs($assignee)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('notifications.unread', 1));

    $notification = Notification::query()->where('user_id', $assignee->id)->firstOrFail();

    $this->actingAs($assignee)->patch(route('notifications.read', $notification))->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($assignee)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('notifications.unread', 0));
});

it('does not resurface a notification once read', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);
    $this->actingAs($assignee)->patch(route('notifications.read-all'));

    expect(app(NotificationService::class)->unreadCount($assignee->fresh()))->toBe(0);

    // A later event creates a new unread row rather than reopening the read one.
    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'done']);

    expect(app(NotificationService::class)->unreadCount($assignee->fresh()))->toBe(1);
    expect(Notification::query()->where('user_id', $assignee->id)->whereNotNull('read_at')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Repeated events collapse instead of flooding the badge
|--------------------------------------------------------------------------
*/

it('collapses repeated events about the same task into one row', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    foreach (['One', 'Two', 'Three', 'Four'] as $body) {
        $this->actingAs($owner)->post(route('tasks.comments.store', $task), ['body' => $body]);
    }

    $rows = Notification::query()
        ->where('user_id', $assignee->id)
        ->where('type', 'task.commented')
        ->get();

    // One row, four events — the badge counts things needing attention.
    expect($rows)->toHaveCount(1);
    expect($rows->first()->event_count)->toBe(4);
    expect(app(NotificationService::class)->unreadCount($assignee->fresh()))->toBe(1);
});

it('does not collapse events about different tasks', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    foreach (range(1, 3) as $i) {
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by_id' => $owner->id,
            'assignee_id' => $assignee->id,
        ]);
        $this->actingAs($owner)->post(route('tasks.comments.store', $task), ['body' => "Comment {$i}"]);
    }

    expect(Notification::query()->where('user_id', $assignee->id)->where('type', 'task.commented')->count())->toBe(3);
});

it('starts a fresh row once the previous one has been read', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->post(route('tasks.comments.store', $task), ['body' => 'First']);
    $this->actingAs($assignee)->patch(route('notifications.read-all'));
    $this->actingAs($owner)->post(route('tasks.comments.store', $task), ['body' => 'Second']);

    // The read row is left alone; the new event gets its own unread row.
    expect(Notification::query()->where('user_id', $assignee->id)->where('type', 'task.commented')->count())->toBe(2);
    expect(app(NotificationService::class)->unreadCount($assignee->fresh()))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Opening the thing counts as reading its notifications
|--------------------------------------------------------------------------
*/

it('clears a task notification when the user opens that task', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);
    $this->actingAs($owner)->post(route('tasks.comments.store', $task), ['body' => 'Look at this']);

    expect(app(NotificationService::class)->unreadCount($assignee->fresh()))->toBeGreaterThan(0);

    $this->actingAs($assignee)->get(route('tasks.show', $task))->assertOk();

    expect(app(NotificationService::class)->unreadCount($assignee->fresh()))->toBe(0);
});

it('leaves notifications about other tasks alone when one is opened', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $opened = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'assignee_id' => $assignee->id]);
    $untouched = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'assignee_id' => $assignee->id]);

    $this->actingAs($owner)->post(route('tasks.comments.store', $opened), ['body' => 'A']);
    $this->actingAs($owner)->post(route('tasks.comments.store', $untouched), ['body' => 'B']);

    $this->actingAs($assignee)->get(route('tasks.show', $opened))->assertOk();

    expect(app(NotificationService::class)->unreadCount($assignee->fresh()))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Privacy and the notification centre
|--------------------------------------------------------------------------
*/

it('never leaks another user notifications', function () {
    $owner = TaskHelpers::admin();
    $mine = TaskHelpers::userWith(['tasks.view']);
    $theirs = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'assignee_id' => $theirs->id]);
    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    $foreign = Notification::query()->where('user_id', $theirs->id)->firstOrFail();

    $this->actingAs($mine)->patch(route('notifications.read', $foreign))->assertForbidden();
    $this->actingAs($mine)->delete(route('notifications.destroy', $foreign))->assertForbidden();

    expect($foreign->fresh()->read_at)->toBeNull();
});

it('filters the notification centre to unread and by group', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'assignee_id' => $user->id]);
    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'system.notice',
        'group' => Notification::GROUP_SYSTEM,
        'title' => 'Already read',
        'read_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 2)->where('stats.unread', 1));

    $this->actingAs($user)
        ->get(route('notifications.index', ['unread' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1));

    $this->actingAs($user)
        ->get(route('notifications.index', ['group' => 'system']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1));
});

it('reports unread totals per group for the bell', function () {
    $owner = TaskHelpers::admin();
    $user = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id, 'assignee_id' => $user->id]);
    $this->actingAs($owner)->patch(route('tasks.status', $task), ['status' => 'in_progress']);

    $payload = $this->actingAs($user)->getJson(route('notifications.dropdown'))->assertOk()->json();

    expect($payload['unread'])->toBe(1);
    expect($payload['by_group'])->toHaveKey(Notification::GROUP_TASKS);
});

it('prunes long-read notifications but keeps unread ones', function () {
    $user = TaskHelpers::userWith(['tasks.view']);

    Notification::query()->create([
        'user_id' => $user->id, 'type' => 'x', 'group' => 'system',
        'title' => 'Old and read', 'read_at' => now()->subDays(90),
    ]);
    Notification::query()->create([
        'user_id' => $user->id, 'type' => 'y', 'group' => 'system',
        'title' => 'Old but unread',
    ]);

    app(NotificationService::class)->pruneRead(60);

    expect(Notification::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(Notification::query()->where('user_id', $user->id)->first()->title)->toBe('Old but unread');
});
