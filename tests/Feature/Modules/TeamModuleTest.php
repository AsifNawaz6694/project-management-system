<?php

use App\Modules\Teams\Models\Team;
use App\Modules\UserManagement\Models\Activity;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

it('lists teams for someone who may view them', function () {
    $user = TaskHelpers::userWith(['teams.view']);
    Team::query()->create(['name' => 'Platform', 'slug' => 'platform']);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('teams'));
});

it('refuses the team list without the permission', function () {
    $user = TaskHelpers::userWith([]);

    $this->actingAs($user)->get(route('teams.index'))->assertForbidden();
});

it('creates a team and makes the lead a member', function () {
    $admin = TaskHelpers::admin();
    $lead = TaskHelpers::userWith(['teams.view']);
    $member = TaskHelpers::userWith(['teams.view']);

    $this->actingAs($admin)->post(route('teams.store'), [
        'name' => 'Platform',
        'description' => 'Runs the plumbing',
        'color' => 'blue',
        'lead_id' => $lead->id,
        'member_ids' => [$member->id],
    ])->assertRedirect();

    $team = Team::query()->where('name', 'Platform')->firstOrFail();

    expect($team->members()->pluck('users.id')->all())->toEqualCanonicalizing([$lead->id, $member->id]);
    expect($team->members()->where('users.id', $lead->id)->first()->pivot->role)->toBe('lead');
});

it('adds the lead even when they are left off the member list', function () {
    $admin = TaskHelpers::admin();
    $lead = TaskHelpers::userWith(['teams.view']);

    $this->actingAs($admin)->post(route('teams.store'), [
        'name' => 'Solo',
        'lead_id' => $lead->id,
        'member_ids' => [],
    ])->assertRedirect();

    expect(Team::query()->where('name', 'Solo')->firstOrFail()->members()->count())->toBe(1);
});

it('rejects a team with no name', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('teams.store'), ['name' => ''])->assertSessionHasErrors('name');
});

it('rejects an unknown colour', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->post(route('teams.store'), ['name' => 'Odd', 'color' => 'chartreuse'])
        ->assertSessionHasErrors('color');
});

it('rejects a lead who does not exist', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)
        ->post(route('teams.store'), ['name' => 'Ghost led', 'lead_id' => 999999])
        ->assertSessionHasErrors('lead_id');
});

it('refuses team creation without the manage permission', function () {
    $user = TaskHelpers::userWith(['teams.view']);

    $this->actingAs($user)->post(route('teams.store'), ['name' => 'Nope'])->assertForbidden();
    expect(Team::query()->where('name', 'Nope')->exists())->toBeFalse();
});

it('records the creation in the activity log', function () {
    $admin = TaskHelpers::admin();

    $this->actingAs($admin)->post(route('teams.store'), ['name' => 'Audited'])->assertRedirect();

    expect(Activity::query()->where('action', 'team.created')->where('module', 'teams')->count())->toBe(1);
});

it('shows a team', function () {
    $user = TaskHelpers::userWith(['teams.view']);
    $team = Team::query()->create(['name' => 'Platform', 'slug' => 'platform']);

    $this->actingAs($user)->get(route('teams.show', $team))->assertOk();
});

it('updates a team', function () {
    $admin = TaskHelpers::admin();
    $team = Team::query()->create(['name' => 'Old name', 'slug' => 'old-name']);

    $this->actingAs($admin)
        ->patch(route('teams.update', $team), ['name' => 'New name', 'member_ids' => []])
        ->assertRedirect();

    expect($team->fresh()->name)->toBe('New name');
});

it('deletes a team', function () {
    $admin = TaskHelpers::admin();
    $team = Team::query()->create(['name' => 'Doomed', 'slug' => 'doomed']);

    $this->actingAs($admin)->delete(route('teams.destroy', $team))->assertRedirect();

    expect(Team::query()->whereKey($team->id)->exists())->toBeFalse();
});
