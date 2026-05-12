<?php

use App\Models\Trip;
use App\Models\User;

function makePackingAttributionTrip(User $owner, ?User $editor = null): Trip
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

test('packing items can be assigned only to linked trip participants', function () {
    $owner = User::factory()->create(['name' => 'Alex Owner']);
    $editor = User::factory()->create(['name' => 'Sam Editor']);
    $stranger = User::factory()->create(['name' => 'Pat Stranger']);
    $trip = makePackingAttributionTrip($owner, $editor);

    $this->actingAs($owner)->post(route('trips.packing-items.store', $trip), [
        'traveler_name' => '',
        'assigned_to_user_id' => $editor->id,
        'category' => 'toiletries',
        'label' => 'Sunscreen',
        'quantity' => 1,
        'notes' => null,
    ])->assertRedirect();

    expect($trip->packingItems()->first()->assigned_to_user_id)->toBe($editor->id);

    $this->actingAs($owner)->post(route('trips.packing-items.store', $trip), [
        'traveler_name' => '',
        'assigned_to_user_id' => $stranger->id,
        'category' => 'toiletries',
        'label' => 'Adapters',
        'quantity' => 1,
        'notes' => null,
    ])->assertSessionHasErrors('assigned_to_user_id');
});

test('trip payload exposes packing attribution and participant summaries', function () {
    $owner = User::factory()->create(['name' => 'Alex Owner']);
    $editor = User::factory()->create(['name' => 'Sam Editor']);
    $trip = makePackingAttributionTrip($owner, $editor);

    $this->actingAs($owner)->post(route('trips.packing-items.store', $trip), [
        'traveler_name' => 'Travel pack',
        'assigned_to_user_id' => $editor->id,
        'category' => 'toiletries',
        'label' => 'Sunscreen',
        'quantity' => 2,
        'notes' => 'SPF 50',
    ])->assertRedirect();

    $response = $this->actingAs($owner)->get(route('trips.show', $trip));
    $tripPayload = $response->viewData('page')['props']['trip'];
    $item = $tripPayload['packing_items'][0];

    expect($tripPayload['participants'])->toHaveCount(2)
        ->and($tripPayload['participants'][0])->toMatchArray([
            'id' => $owner->id,
            'name' => 'Alex Owner',
            'first_name' => 'Alex',
            'initials' => 'A',
            'role' => 'owner',
        ])
        ->and($tripPayload['participants'][1])->toMatchArray([
            'id' => $editor->id,
            'name' => 'Sam Editor',
            'first_name' => 'Sam',
            'initials' => 'S',
            'role' => 'editor',
        ])
        ->and($item['assigned_to_user_id'])->toBe($editor->id)
        ->and($item['traveler_name'])->toBe('Travel pack')
        ->and($item['added_by'])->toMatchArray([
            'id' => $owner->id,
            'first_name' => 'Alex',
            'initials' => 'A',
        ])
        ->and($item['assigned_to'])->toMatchArray([
            'id' => $editor->id,
            'first_name' => 'Sam',
            'initials' => 'S',
        ]);
});

test('free text traveler remains the fallback when no account is assigned', function () {
    $owner = User::factory()->create(['name' => 'Alex Owner']);
    $trip = makePackingAttributionTrip($owner);

    $this->actingAs($owner)->post(route('trips.packing-items.store', $trip), [
        'traveler_name' => 'Mom',
        'assigned_to_user_id' => null,
        'category' => 'general',
        'label' => 'Medication list',
        'quantity' => 1,
        'notes' => null,
    ])->assertRedirect();

    $response = $this->actingAs($owner)->get(route('trips.show', $trip));
    $item = $response->viewData('page')['props']['trip']['packing_items'][0];

    expect($item['assigned_to_user_id'])->toBeNull()
        ->and($item['assigned_to'])->toBeNull()
        ->and($item['traveler_name'])->toBe('Mom');
});
