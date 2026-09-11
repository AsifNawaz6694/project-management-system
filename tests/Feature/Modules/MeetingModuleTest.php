<?php

use App\Modules\MeetingManagement\Models\Meeting;
use App\Modules\TaskManagement\Models\Task;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

function meetingPayload(array $overrides = []): array
{
    return $overrides + [
        'title' => 'Sprint planning',
        'kind' => 'planning',
        'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
    ];
}

it('lists meetings', function () {
    $user = TaskHelpers::userWith(['meetings.view']);

    $this->actingAs($user)
        ->get(route('meetings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('meetings'));
});

it('refuses the meeting list without the permission', function () {
    $user = TaskHelpers::userWith([]);

    $this->actingAs($user)->get(route('meetings.index'))->assertForbidden();
});

it('schedules a meeting with participants and an agenda', function () {
    $organiser = TaskHelpers::userWith(['meetings.view', 'meetings.create']);
    $attendee = TaskHelpers::userWith(['meetings.view']);

    $this->actingAs($organiser)->post(route('meetings.store'), meetingPayload([
        'participants' => [['user_id' => $attendee->id, 'role' => 'attendee']],
        'agenda_items' => [['title' => 'Review the backlog', 'time_allocation_minutes' => 20]],
    ]))->assertRedirect();

    $meeting = Meeting::query()->where('title', 'Sprint planning')->firstOrFail();

    expect($meeting->participants()->count())->toBeGreaterThanOrEqual(1);
    expect($meeting->agendaItems()->count())->toBe(1);
});

it('rejects an end time before the start', function () {
    $user = TaskHelpers::userWith(['meetings.view', 'meetings.create']);

    $this->actingAs($user)->post(route('meetings.store'), meetingPayload([
        'starts_at' => now()->addDays(2)->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
    ]))->assertSessionHasErrors('ends_at');
});

it('rejects an unknown meeting kind', function () {
    $user = TaskHelpers::userWith(['meetings.view', 'meetings.create']);

    $this->actingAs($user)
        ->post(route('meetings.store'), meetingPayload(['kind' => 'seance']))
        ->assertSessionHasErrors('kind');
});

it('refuses scheduling without the create permission', function () {
    $user = TaskHelpers::userWith(['meetings.view']);

    $this->actingAs($user)->post(route('meetings.store'), meetingPayload())->assertForbidden();
    expect(Meeting::query()->where('title', 'Sprint planning')->exists())->toBeFalse();
});

it('shows a meeting to its organiser', function () {
    $organiser = TaskHelpers::userWith(['meetings.view', 'meetings.create']);

    $this->actingAs($organiser)->post(route('meetings.store'), meetingPayload())->assertRedirect();
    $meeting = Meeting::query()->where('title', 'Sprint planning')->firstOrFail();

    $this->actingAs($organiser)->get(route('meetings.show', $meeting))->assertOk();
});

it('records an RSVP', function () {
    $organiser = TaskHelpers::userWith(['meetings.view', 'meetings.create']);
    $attendee = TaskHelpers::userWith(['meetings.view']);

    $this->actingAs($organiser)->post(route('meetings.store'), meetingPayload([
        'participants' => [['user_id' => $attendee->id]],
    ]))->assertRedirect();

    $meeting = Meeting::query()->where('title', 'Sprint planning')->firstOrFail();

    $this->actingAs($attendee)
        ->post(route('meetings.rsvp', $meeting), ['rsvp_status' => 'accepted'])
        ->assertRedirect();

    expect($meeting->participants()->where('user_id', $attendee->id)->first()->pivot->rsvp_status)->toBe('accepted');
});

it('converts an action item into a task', function () {
    $organiser = TaskHelpers::admin();
    $project = TaskHelpers::project($organiser);

    $this->actingAs($organiser)->post(route('meetings.store'), meetingPayload(['project_id' => $project->id]))->assertRedirect();
    $meeting = Meeting::query()->where('title', 'Sprint planning')->firstOrFail();

    $this->actingAs($organiser)->post(route('meetings.action-items.store', $meeting), [
        'title' => 'Chase the vendor',
        'owner_id' => $organiser->id,
    ])->assertRedirect();

    $item = $meeting->actionItems()->firstOrFail();

    $this->actingAs($organiser)
        ->post(route('meetings.action-items.convert', [$meeting, $item]), ['project_id' => $project->id])
        ->assertRedirect();

    expect(Task::query()->where('title', 'Chase the vendor')->exists())->toBeTrue();
});

it('deletes a meeting', function () {
    $organiser = TaskHelpers::admin();

    $this->actingAs($organiser)->post(route('meetings.store'), meetingPayload())->assertRedirect();
    $meeting = Meeting::query()->where('title', 'Sprint planning')->firstOrFail();

    $this->actingAs($organiser)->delete(route('meetings.destroy', $meeting))->assertRedirect();

    expect(Meeting::query()->whereKey($meeting->id)->exists())->toBeFalse();
});
