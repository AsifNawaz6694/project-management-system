<?php

use App\Modules\Communication\Models\ProjectComment;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| Listing and visibility
|--------------------------------------------------------------------------
*/

it('lists projects', function () {
    $admin = TaskHelpers::admin();
    TaskHelpers::project($admin);

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});

it('hides projects a member is not on', function () {
    $owner = TaskHelpers::admin();
    $outsider = TaskHelpers::userWith(['projects.view']);
    TaskHelpers::project($owner);

    $this->actingAs($outsider)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 0));
});

it('shows a project to a member', function () {
    $owner = TaskHelpers::admin();
    $member = TaskHelpers::userWith(['projects.view']);
    $project = TaskHelpers::project($owner);
    $project->members()->attach([$member->id => ['role' => 'member']]);

    $this->actingAs($member)->get(route('projects.show', $project->slug))->assertOk();
});

it('refuses a project to someone outside its scope', function () {
    $owner = TaskHelpers::admin();
    $outsider = TaskHelpers::userWith(['projects.view']);
    $project = TaskHelpers::project($owner);

    $this->actingAs($outsider)->get(route('projects.show', $project->slug))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Creating and editing
|--------------------------------------------------------------------------
*/

it('creates a project with members and milestones', function () {
    $admin = TaskHelpers::admin();
    $member = TaskHelpers::userWith(['projects.view']);

    $this->actingAs($admin)->post(route('projects.store'), [
        'title' => 'Client rollout',
        'status' => 'active',
        'priority' => 'high',
        'member_ids' => [$member->id],
        'milestones' => [
            ['title' => 'Kickoff', 'due_date' => now()->addWeek()->toDateString()],
        ],
    ])->assertRedirect();

    $project = Project::query()->where('title', 'Client rollout')->firstOrFail();

    expect($project->slug)->toBe('client-rollout');
    expect($project->key)->not->toBeNull();
    expect($project->members()->pluck('users.id')->all())->toContain($member->id);
    expect(Milestone::query()->where('project_id', $project->id)->count())->toBe(1);
});

it('gives a project a default workflow', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('projects.store'), [
        'title' => 'Needs a board',
        'status' => 'planning',
        'priority' => 'medium',
    ])->assertRedirect();

    expect(Project::query()->where('title', 'Needs a board')->value('workflow_id'))->not->toBeNull();
});

it('makes a unique slug when two projects share a title', function () {
    $admin = TaskHelpers::admin();

    foreach (['Same name', 'Same name'] as $title) {
        $this->actingAs($admin)->post(route('projects.store'), [
            'title' => $title, 'status' => 'planning', 'priority' => 'medium',
        ]);
    }

    expect(Project::query()->where('title', 'Same name')->pluck('slug')->all())
        ->toEqualCanonicalizing(['same-name', 'same-name-2']);
});

it('rejects an end date before the start date', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('projects.store'), [
        'title' => 'Backwards',
        'status' => 'planning',
        'priority' => 'medium',
        'start_date' => '2026-05-01',
        'end_date' => '2026-04-01',
    ])->assertSessionHasErrors('end_date');
});

it('rejects an unknown status', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->post(route('projects.store'), ['title' => 'Odd', 'status' => 'vibing', 'priority' => 'medium'])
        ->assertSessionHasErrors('status');
});

it('refuses project creation without the permission', function () {
    $user = TaskHelpers::userWith(['projects.view']);

    $this->actingAs($user)
        ->post(route('projects.store'), ['title' => 'Nope', 'status' => 'planning', 'priority' => 'low'])
        ->assertForbidden();
});

it('updates a project', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $this->actingAs($admin)->patch(route('projects.update', $project->slug), [
        'title' => 'Renamed',
        'status' => 'on_hold',
        'priority' => 'critical',
    ])->assertRedirect();

    $fresh = $project->fresh();

    expect($fresh->title)->toBe('Renamed');
    expect($fresh->status)->toBe('on_hold');
    // Renaming re-slugs, which is what the show route binds on.
    expect($fresh->slug)->toBe('renamed');
});

it('soft-deletes a project', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $this->actingAs($admin)->delete(route('projects.destroy', $project->slug))->assertRedirect();

    expect(Project::query()->whereKey($project->id)->exists())->toBeFalse();
    expect(Project::withTrashed()->whereKey($project->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Milestones
|--------------------------------------------------------------------------
*/

it('toggles a milestone and moves the project percentage', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $a = Milestone::query()->create(['project_id' => $project->id, 'title' => 'One', 'position' => 1]);
    Milestone::query()->create(['project_id' => $project->id, 'title' => 'Two', 'position' => 2]);

    $this->actingAs($admin)->patch(route('projects.milestones.toggle', [$project->slug, $a]))->assertRedirect();

    expect($a->fresh()->completed_at)->not->toBeNull();
    expect($project->fresh()->progress)->toBe(50);

    // And back down when it is reopened.
    $this->actingAs($admin)->patch(route('projects.milestones.toggle', [$project->slug, $a]));

    expect($project->fresh()->progress)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Comments
|--------------------------------------------------------------------------
*/

it('posts a comment on a project', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $this->actingAs($admin)
        ->post(route('projects.comments.store', $project->slug), ['body' => 'Looking good'])
        ->assertRedirect();

    expect(ProjectComment::query()->where('project_id', $project->id)->value('body'))->toBe('Looking good');
});

it('rejects an empty comment', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $this->actingAs($admin)
        ->post(route('projects.comments.store', $project->slug), ['body' => ''])
        ->assertSessionHasErrors('body');
});

it('lets an author delete their own project comment', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $comment = ProjectComment::query()->create([
        'project_id' => $project->id,
        'user_id' => $admin->id,
        'body' => 'Mine',
    ]);

    $this->actingAs($admin)
        ->delete(route('projects.comments.destroy', [$project->slug, $comment]))
        ->assertRedirect();

    expect(ProjectComment::query()->whereKey($comment->id)->exists())->toBeFalse();
});
