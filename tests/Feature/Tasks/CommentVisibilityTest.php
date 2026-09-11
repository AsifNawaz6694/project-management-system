<?php

use App\Models\User;
use App\Modules\Communication\Services\MentionParser;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\TaskManagement\Models\CommentRevision;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskComment;
use App\Modules\Teams\Models\Team;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| Internal notes
|--------------------------------------------------------------------------
*/

it('marks a comment internal when the author can edit the task', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('tasks.comments.store', $task), ['body' => 'Internal note', 'is_internal' => true])
        ->assertRedirect();

    expect(TaskComment::query()->where('task_id', $task->id)->value('is_internal'))->toBeTrue();
});

it('refuses to make a note internal for someone who cannot edit the task', function () {
    $owner = TaskHelpers::admin();
    $commenter = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);
    $project->members()->attach([$commenter->id => ['role' => 'member']]);

    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    $this->actingAs($commenter)
        ->post(route('tasks.comments.store', $task), ['body' => 'Trying to hide this', 'is_internal' => true])
        ->assertRedirect();

    // Posted, but as an ordinary comment.
    expect(TaskComment::query()->where('task_id', $task->id)->value('is_internal'))->toBeFalse();
});

it('hides an internal note from someone who cannot edit the task', function () {
    $owner = TaskHelpers::admin();
    $viewer = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);
    $project->members()->attach([$viewer->id => ['role' => 'member']]);

    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    TaskComment::query()->create([
        'task_id' => $task->id, 'user_id' => $owner->id,
        'body' => 'Client is unhappy', 'is_internal' => true,
    ]);
    TaskComment::query()->create([
        'task_id' => $task->id, 'user_id' => $owner->id,
        'body' => 'Public update', 'is_internal' => false,
    ]);

    $this->actingAs($viewer)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('comments', 1)->where('comments.0.body', 'Public update'));
});

it('shows an internal note to someone who can edit the task', function () {
    $owner = TaskHelpers::admin();
    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    TaskComment::query()->create([
        'task_id' => $task->id, 'user_id' => $owner->id,
        'body' => 'Client is unhappy', 'is_internal' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('comments', 1)->where('comments.0.is_internal', true));
});

it('does not notify mentions from an internal note', function () {
    $owner = TaskHelpers::admin();
    $mentioned = TaskHelpers::userWith(['tasks.view']);
    $mentioned->forceFill(['name' => 'Dana Scully'])->save();

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    $this->actingAs($owner)->post(route('tasks.comments.store', $task), [
        'body' => 'Quietly: @Dana is on this',
        'is_internal' => true,
    ])->assertRedirect();

    expect(Notification::query()->where('user_id', $mentioned->id)->where('type', 'comment.mention')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Edit history
|--------------------------------------------------------------------------
*/

it('keeps the previous text when a comment is edited', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);

    $comment = TaskComment::query()->create([
        'task_id' => $task->id, 'user_id' => $admin->id, 'body' => 'First wording',
    ]);

    $this->actingAs($admin)
        ->patch(route('tasks.comments.update', [$task, $comment]), ['body' => 'Second wording'])
        ->assertRedirect();

    expect($comment->fresh()->body)->toBe('Second wording');
    expect(CommentRevision::query()->where('task_comment_id', $comment->id)->value('body'))->toBe('First wording');
});

it('records one revision per edit', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);

    $comment = TaskComment::query()->create([
        'task_id' => $task->id, 'user_id' => $admin->id, 'body' => 'v1',
    ]);

    foreach (['v2', 'v3'] as $body) {
        $this->actingAs($admin)->patch(route('tasks.comments.update', [$task, $comment]), ['body' => $body]);
    }

    expect(CommentRevision::query()->where('task_comment_id', $comment->id)->count())->toBe(2);
    expect($comment->fresh()->body)->toBe('v3');
});

it('takes the history with it when a comment is deleted', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);

    $comment = TaskComment::query()->create([
        'task_id' => $task->id, 'user_id' => $admin->id, 'body' => 'v1',
    ]);

    $this->actingAs($admin)->patch(route('tasks.comments.update', [$task, $comment]), ['body' => 'v2']);
    $this->actingAs($admin)->delete(route('tasks.comments.destroy', [$task, $comment]));

    expect(CommentRevision::query()->where('task_comment_id', $comment->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Mentions
|--------------------------------------------------------------------------
*/

it('resolves a mention by the start of a name', function () {
    $user = User::factory()->create(['name' => 'Dana Scully', 'status' => 'active']);

    expect(MentionParser::extract('nice work @Dana'))->toContain($user->id);
});

it('resolves a mention by the local part of an email', function () {
    $user = User::factory()->create(['name' => 'Fox Mulder', 'email' => 'fox.mulder@raqtan.test', 'status' => 'active']);

    expect(MentionParser::extract('over to @fox.mulder'))->toContain($user->id);
});

it('ignores a token too short to mean anybody', function () {
    User::factory()->create(['name' => 'Aaron Adams', 'status' => 'active']);
    User::factory()->create(['name' => 'Alice Ashe', 'status' => 'active']);

    // "@a" used to notify up to ten arbitrary people.
    expect(MentionParser::extract('see @a'))->toBe([]);
});

it('mentions everyone in a team by its slug', function () {
    $team = Team::query()->create(['name' => 'Platform squad', 'slug' => 'platform-squad']);
    $a = TaskHelpers::userWith(['tasks.view']);
    $b = TaskHelpers::userWith(['tasks.view']);
    $team->members()->attach([$a->id => ['role' => 'member'], $b->id => ['role' => 'member']]);

    $ids = MentionParser::extract('heads up @platform-squad');

    expect($ids)->toContain($a->id);
    expect($ids)->toContain($b->id);
});

it('reports which teams a body mentions', function () {
    $team = Team::query()->create(['name' => 'Platform squad', 'slug' => 'platform-squad']);

    expect(MentionParser::extractTeams('ping @platform-squad'))->toBe([$team->id]);
    expect(MentionParser::extractTeams('nobody here'))->toBe([]);
});

it('notifies a whole team mentioned in a comment', function () {
    $owner = TaskHelpers::admin();
    $member = TaskHelpers::userWith(['tasks.view']);

    $team = Team::query()->create(['name' => 'Platform squad', 'slug' => 'platform-squad']);
    $team->members()->attach([$member->id => ['role' => 'member']]);

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('tasks.comments.store', $task), ['body' => 'Need eyes from @platform-squad'])
        ->assertRedirect();

    expect(Notification::query()->where('user_id', $member->id)->where('type', 'comment.mention')->count())->toBe(1);
});

it('treats a wildcard in a mention token as a literal', function () {
    User::factory()->create(['name' => 'Percent Person', 'status' => 'active']);

    // The token is filtered to safe characters, so this can never match everyone.
    expect(MentionParser::extract('hello @%%%'))->toBe([]);
});
