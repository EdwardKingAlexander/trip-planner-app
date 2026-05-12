<?php

use App\Models\Trip;
use App\Models\User;

function makeTaskToggleTrip(User $owner, ?User $editor = null): Trip
{
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);

    if ($editor !== null) {
        $trip->collaborators()->create([
            'user_id' => $editor->id,
            'email' => $editor->email,
            'role' => 'editor',
            'accepted_at' => now(),
        ]);
    }

    return $trip;
}

test('trip owners can complete a task from the inline checkbox', function () {
    $owner = User::factory()->create();
    $trip = makeTaskToggleTrip($owner);
    $task = $trip->tasks()->create([
        'title' => 'Download boarding passes',
        'priority' => 'normal',
    ]);

    $this->actingAs($owner)
        ->patch(route('trips.tasks.toggle-completion', [$trip, $task]), ['completed' => true])
        ->assertRedirect();

    $task->refresh();

    expect($task->completed_at)->not->toBeNull()
        ->and($task->updated_by_user_id)->toBe($owner->id)
        ->and($trip->activityEvents()->where('event_type', 'task.completed')->where('summary', 'completed task: Download boarding passes')->exists())->toBeTrue();
});

test('editors can reopen a completed task from the inline checkbox', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = makeTaskToggleTrip($owner, $editor);
    $task = $trip->tasks()->create([
        'title' => 'Download boarding passes',
        'priority' => 'normal',
        'completed_at' => now(),
    ]);

    $this->actingAs($editor)
        ->patch(route('trips.tasks.toggle-completion', [$trip, $task]), ['completed' => false])
        ->assertRedirect();

    $task->refresh();

    expect($task->completed_at)->toBeNull()
        ->and($task->updated_by_user_id)->toBe($editor->id)
        ->and($trip->activityEvents()->where('event_type', 'task.reopened')->where('summary', 'reopened task: Download boarding passes')->exists())->toBeTrue();
});

test('task completion toggle rejects invalid values', function () {
    $owner = User::factory()->create();
    $trip = makeTaskToggleTrip($owner);
    $task = $trip->tasks()->create([
        'title' => 'Download boarding passes',
        'priority' => 'normal',
    ]);

    $this->actingAs($owner)
        ->patchJson(route('trips.tasks.toggle-completion', [$trip, $task]), ['completed' => 'maybe'])
        ->assertUnprocessable();
});

test('task completion toggle rejects viewers and strangers', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = makeTaskToggleTrip($owner);
    $trip->collaborators()->create([
        'user_id' => $viewer->id,
        'email' => $viewer->email,
        'role' => 'viewer',
        'accepted_at' => now(),
    ]);
    $task = $trip->tasks()->create([
        'title' => 'Download boarding passes',
        'priority' => 'normal',
    ]);

    $this->actingAs($viewer)->patch(route('trips.tasks.toggle-completion', [$trip, $task]), ['completed' => true])->assertForbidden();
    $this->actingAs($stranger)->patch(route('trips.tasks.toggle-completion', [$trip, $task]), ['completed' => true])->assertForbidden();

    expect($task->fresh()->completed_at)->toBeNull();
});

test('task completion toggle returns not found for a task from another trip', function () {
    $owner = User::factory()->create();
    $trip = makeTaskToggleTrip($owner);
    $otherTrip = makeTaskToggleTrip($owner);
    $task = $otherTrip->tasks()->create([
        'title' => 'Download boarding passes',
        'priority' => 'normal',
    ]);

    $this->actingAs($owner)
        ->patch(route('trips.tasks.toggle-completion', [$trip, $task]), ['completed' => true])
        ->assertNotFound();
});
