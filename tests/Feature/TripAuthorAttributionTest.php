<?php

use App\Models\Trip;
use App\Models\User;

test('first_name accessor returns the first whitespace token of the user name', function () {
    expect(User::factory()->make(['name' => 'Edward King Alexander'])->first_name)->toBe('Edward')
        ->and(User::factory()->make(['name' => 'Edward'])->first_name)->toBe('Edward')
        ->and(User::factory()->make(['name' => '  Edward  Alexander'])->first_name)->toBe('Edward')
        ->and(User::factory()->make(['name' => ''])->first_name)->toBe('');
});

test('creating a shared component records the acting user as creator and editor', function () {
    $owner = User::factory()->create(['name' => 'Owen Owner']);
    $editor = User::factory()->create(['name' => 'Eddie Editor']);
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);
    $trip->collaborators()->create([
        'user_id' => $editor->id,
        'email' => $editor->email,
        'role' => 'editor',
        'accepted_at' => now(),
    ]);

    $this->actingAs($editor)->post(route('trips.tasks.store', $trip), [
        'title' => 'Book transit cards',
        'priority' => 'normal',
    ])->assertRedirect();

    $task = $trip->tasks()->first();

    expect($task->created_by_user_id)->toBe($editor->id)
        ->and($task->updated_by_user_id)->toBe($editor->id);
});

test('updating a shared component reassigns updated_by but preserves created_by', function () {
    $owner = User::factory()->create(['name' => 'Owen Owner']);
    $editor = User::factory()->create(['name' => 'Eddie Editor']);
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);
    $trip->collaborators()->create([
        'user_id' => $editor->id,
        'email' => $editor->email,
        'role' => 'editor',
        'accepted_at' => now(),
    ]);

    $this->actingAs($owner)->post(route('trips.costs.store', $trip), [
        'category' => 'food',
        'label' => 'Dinner',
        'planned_amount' => '60',
        'actual_amount' => null,
        'currency' => 'USD',
    ])->assertRedirect();

    $cost = $trip->costs()->first();

    expect($cost->created_by_user_id)->toBe($owner->id)
        ->and($cost->updated_by_user_id)->toBe($owner->id);

    $this->actingAs($editor)->patch(route('trips.costs.update', [$trip, $cost]), [
        'category' => 'food',
        'label' => 'Dinner adjusted',
        'planned_amount' => '70',
        'actual_amount' => '75',
        'currency' => 'USD',
    ])->assertRedirect();

    $cost->refresh();

    expect($cost->created_by_user_id)->toBe($owner->id)
        ->and($cost->updated_by_user_id)->toBe($editor->id);
});

test('shared trip detail payload exposes the editor first name on each component', function () {
    $owner = User::factory()->create(['name' => 'Owen Owner']);
    $editor = User::factory()->create(['name' => 'Eddie Editor']);
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);
    $trip->collaborators()->create([
        'user_id' => $editor->id,
        'email' => $editor->email,
        'role' => 'editor',
        'accepted_at' => now(),
    ]);

    $this->actingAs($editor)->post(route('trips.tasks.store', $trip), [
        'title' => 'Book transit cards',
        'priority' => 'normal',
    ])->assertRedirect();

    $response = $this->actingAs($owner)->get(route('trips.show', $trip));

    $tasks = $response->viewData('page')['props']['trip']['tasks'];

    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]['last_edited_by'])->toBe('Eddie');
});
