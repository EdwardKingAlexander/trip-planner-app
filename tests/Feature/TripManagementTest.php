<?php

use App\Models\Trip;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can create a trip with generated trip days', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('trips.store'), [
        'name' => 'Anniversary in Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
        'summary' => 'Food, views, and slow mornings.',
        'cover_theme' => 'coastal',
    ]);

    $trip = Trip::first();

    $response->assertRedirect(route('trips.show', $trip));
    expect($trip)->not->toBeNull()
        ->and($trip->fresh()->days)->toHaveCount(3);
});

test('trip owners can share trips with an editor', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Oahu',
        'destination' => 'Honolulu, Hawaii',
        'starts_on' => '2026-07-01',
        'ends_on' => '2026-07-08',
        'status' => 'planned',
    ]);

    $this->actingAs($owner)->post(route('trips.collaborators.store', $trip), [
        'email' => $editor->email,
        'role' => 'editor',
    ])->assertRedirect();

    expect($trip->fresh()->collaborators)->toHaveCount(1);

    $this->actingAs($editor)
        ->get(route('trips.show', $trip), ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->assertOk();
});

test('non collaborators cannot view another users trip', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Paris',
        'destination' => 'Paris, France',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-05',
        'status' => 'planned',
    ]);

    $this->actingAs($stranger)->get(route('trips.show', $trip))->assertForbidden();
});

test('editors can add reservations with local date time details', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Tokyo',
        'destination' => 'Tokyo, Japan',
        'starts_on' => '2026-10-03',
        'ends_on' => '2026-10-12',
        'status' => 'planned',
    ]);
    $trip->collaborators()->create([
        'user_id' => $editor->id,
        'email' => $editor->email,
        'role' => 'editor',
        'accepted_at' => now(),
    ]);

    $this->actingAs($editor)->post(route('trips.reservations.store', $trip), [
        'type' => 'flight',
        'title' => 'Outbound flight',
        'provider_name' => 'United',
        'booking_reference' => 'ABC123',
        'status' => 'confirmed',
        'starts_at' => '2026-10-03T08:30',
        'starts_timezone' => 'America/Denver',
        'ends_at' => '2026-10-04T14:10',
        'ends_timezone' => 'Asia/Tokyo',
        'airline' => 'United',
        'flight_number' => 'UA143',
        'departure_airport' => 'DEN',
        'arrival_airport' => 'HND',
    ])->assertRedirect();

    $reservation = $trip->reservations()->first();

    expect($reservation)->not->toBeNull()
        ->and($reservation->starts_timezone)->toBe('America/Denver')
        ->and($reservation->ends_timezone)->toBe('Asia/Tokyo')
        ->and($reservation->flightSegments)->toHaveCount(1);
});

test('trip owners can add itinerary reservations packing items and tasks', function () {
    $owner = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Seattle Weekend',
        'destination' => 'Seattle, Washington',
        'starts_on' => '2026-11-06',
        'ends_on' => '2026-11-08',
        'status' => 'planned',
    ]);
    $trip->syncDays();

    $this->actingAs($owner)->post(route('trips.itinerary-items.store', $trip), [
        'trip_day_id' => $trip->days()->first()->id,
        'type' => 'activity',
        'title' => 'Pike Place Market',
        'location_name' => 'Downtown Seattle',
        'starts_at' => '2026-11-06T10:00',
        'ends_at' => '2026-11-06T12:00',
        'timezone' => 'America/Los_Angeles',
        'status' => 'planned',
    ])->assertRedirect();

    $this->actingAs($owner)->post(route('trips.reservations.store', $trip), [
        'type' => 'lodging',
        'title' => 'Waterfront hotel',
        'provider_name' => 'Harbor House',
        'booking_reference' => 'SEA123',
        'status' => 'confirmed',
        'starts_at' => '2026-11-06T15:00',
        'starts_timezone' => 'America/Los_Angeles',
        'ends_at' => '2026-11-08T11:00',
        'ends_timezone' => 'America/Los_Angeles',
        'property_name' => 'Harbor House',
        'room_type' => 'King',
    ])->assertRedirect();

    $this->actingAs($owner)->post(route('trips.packing-items.store', $trip), [
        'traveler_name' => 'Shared',
        'category' => 'weather',
        'label' => 'Rain jacket',
        'quantity' => 2,
    ])->assertRedirect();

    $this->actingAs($owner)->post(route('trips.tasks.store', $trip), [
        'title' => 'Download boarding passes',
        'description' => 'Save offline copies before leaving.',
        'due_at' => '2026-11-05T18:00',
        'priority' => 'high',
    ])->assertRedirect();

    expect($trip->itineraryItems()->where('title', 'Pike Place Market')->exists())->toBeTrue()
        ->and($trip->reservations()->where('title', 'Waterfront hotel')->exists())->toBeTrue()
        ->and($trip->packingItems()->where('label', 'Rain jacket')->exists())->toBeTrue()
        ->and($trip->tasks()->where('title', 'Download boarding passes')->exists())->toBeTrue();
});

test('dated tasks are exposed on the matching itinerary day', function () {
    $owner = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Seattle Weekend',
        'destination' => 'Seattle, Washington',
        'starts_on' => '2026-11-06',
        'ends_on' => '2026-11-08',
        'status' => 'planned',
    ]);
    $trip->syncDays();

    $trip->tasks()->create([
        'title' => 'Download boarding passes',
        'description' => 'Save offline copies before leaving.',
        'due_at' => '2026-11-06 18:00:00',
        'priority' => 'high',
    ]);

    $this->actingAs($owner)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Trips/Show')
            ->where('trip.days.0.tasks.0.title', 'Download boarding passes')
            ->where('trip.days.0.tasks.0.priority', 'high')
            ->where('trip.days.1.tasks', []),
        );
});

test('editors can update itinerary reservations budget and planning notes', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Tokyo',
        'destination' => 'Tokyo, Japan',
        'starts_on' => '2026-10-03',
        'ends_on' => '2026-10-12',
        'status' => 'planned',
    ]);
    $trip->syncDays();
    $trip->collaborators()->create([
        'user_id' => $editor->id,
        'email' => $editor->email,
        'role' => 'editor',
        'accepted_at' => now(),
    ]);

    $itineraryItem = $trip->itineraryItems()->create([
        'trip_day_id' => $trip->days()->first()->id,
        'type' => 'activity',
        'title' => 'Museum',
        'timezone' => 'Asia/Tokyo',
        'status' => 'planned',
        'sort_order' => 0,
    ]);
    $reservation = $trip->reservations()->create([
        'type' => 'flight',
        'title' => 'Outbound',
        'status' => 'reserved',
        'starts_timezone' => 'America/Denver',
        'ends_timezone' => 'Asia/Tokyo',
    ]);
    $cost = $trip->costs()->create([
        'category' => 'lodging',
        'label' => 'Hotel',
        'currency' => 'USD',
    ]);
    $packingItem = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Jacket',
        'quantity' => 1,
        'sort_order' => 0,
    ]);
    $task = $trip->tasks()->create([
        'title' => 'Check passports',
        'priority' => 'normal',
    ]);
    $document = $trip->documents()->create([
        'title' => 'Passport scan',
        'document_type' => 'id',
    ]);
    $reminder = $trip->reminders()->create([
        'label' => 'Check in',
        'remind_at' => '2026-10-02 08:00:00',
        'timezone' => 'America/Denver',
        'delivery_channels' => ['in_app'],
    ]);

    $this->actingAs($editor)->patch(route('trips.itinerary-items.update', [$trip, $itineraryItem]), [
        'trip_day_id' => $trip->days()->first()->id,
        'type' => 'dining',
        'title' => 'Sushi dinner',
        'description' => 'Ask for the counter seats.',
        'location_name' => 'Ginza',
        'starts_at' => '2026-10-05T19:00',
        'ends_at' => '2026-10-05T21:00',
        'timezone' => 'Asia/Tokyo',
        'is_all_day' => false,
        'status' => 'booked',
    ])->assertRedirect();

    $this->actingAs($editor)->patch(route('trips.reservations.update', [$trip, $reservation]), [
        'type' => 'lodging',
        'title' => 'Shinjuku hotel',
        'provider_name' => 'Hotel Century',
        'booking_reference' => 'HOTEL123',
        'status' => 'confirmed',
        'starts_at' => '2026-10-04T15:00',
        'starts_timezone' => 'Asia/Tokyo',
        'ends_at' => '2026-10-12T11:00',
        'ends_timezone' => 'Asia/Tokyo',
        'location_name' => 'Shinjuku',
        'address' => '1-1 Shinjuku',
        'contact_phone' => '555-0101',
        'contact_email' => 'frontdesk@example.com',
        'notes' => 'Request a high floor.',
        'property_name' => 'Hotel Century',
        'room_type' => 'King',
    ])->assertRedirect();

    $this->actingAs($editor)->patch(route('trips.costs.update', [$trip, $cost]), [
        'category' => 'lodging',
        'label' => 'Hotel deposit',
        'planned_amount' => '400.00',
        'actual_amount' => '425.50',
        'currency' => 'USD',
        'notes' => 'Includes breakfast.',
    ])->assertRedirect();

    $this->actingAs($editor)->patch(route('trips.packing-items.update', [$trip, $packingItem]), [
        'traveler_name' => 'Taylor',
        'category' => 'weather',
        'label' => 'Rain jacket',
        'quantity' => 2,
        'is_packed' => true,
        'notes' => 'Pack in carry-on.',
    ])->assertRedirect();

    $this->actingAs($editor)->patch(route('trips.tasks.update', [$trip, $task]), [
        'title' => 'Check passport expiration',
        'description' => 'Confirm both passports are valid for six months.',
        'due_at' => '2026-09-15T09:00',
        'completed_at' => '2026-09-14T12:00',
        'priority' => 'high',
    ])->assertRedirect();

    $this->actingAs($editor)->patch(route('trips.documents.update', [$trip, $document]), [
        'title' => 'Passport scan backup',
        'document_type' => 'identity',
        'expires_on' => '2030-01-01',
        'notes' => 'Keep offline copy too.',
    ])->assertRedirect();

    $this->actingAs($editor)->patch(route('trips.reminders.update', [$trip, $reminder]), [
        'label' => 'Online check in',
        'remind_at' => '2026-10-02T09:00',
        'timezone' => 'America/Denver',
        'notes' => 'Use airline app.',
    ])->assertRedirect();

    expect($itineraryItem->fresh()->description)->toBe('Ask for the counter seats.')
        ->and($reservation->fresh()->notes)->toBe('Request a high floor.')
        ->and($reservation->fresh()->lodgingStay->room_type)->toBe('King')
        ->and($reservation->fresh()->flightSegments)->toHaveCount(0)
        ->and($cost->fresh()->notes)->toBe('Includes breakfast.')
        ->and($packingItem->fresh()->notes)->toBe('Pack in carry-on.')
        ->and($packingItem->fresh()->is_packed)->toBeTrue()
        ->and($task->fresh()->description)->toBe('Confirm both passports are valid for six months.')
        ->and($document->fresh()->notes)->toBe('Keep offline copy too.')
        ->and($reminder->fresh()->notes)->toBe('Use airline app.');
});

test('owners can delete trip planning entries', function () {
    $owner = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);
    $trip->syncDays();

    $itineraryItem = $trip->itineraryItems()->create([
        'trip_day_id' => $trip->days()->first()->id,
        'type' => 'activity',
        'title' => 'Walking tour',
        'timezone' => 'Europe/Lisbon',
        'status' => 'planned',
        'sort_order' => 0,
    ]);
    $reservation = $trip->reservations()->create([
        'type' => 'lodging',
        'title' => 'Alfama hotel',
        'status' => 'confirmed',
        'starts_timezone' => 'Europe/Lisbon',
        'ends_timezone' => 'Europe/Lisbon',
    ]);
    $cost = $trip->costs()->create([
        'category' => 'lodging',
        'label' => 'Hotel deposit',
        'currency' => 'USD',
    ]);
    $packingItem = $trip->packingItems()->create([
        'category' => 'clothes',
        'label' => 'Light jacket',
        'quantity' => 1,
        'sort_order' => 0,
    ]);
    $task = $trip->tasks()->create([
        'title' => 'Check passport',
        'priority' => 'normal',
    ]);
    $document = $trip->documents()->create([
        'title' => 'Passport copy',
        'document_type' => 'identity',
    ]);
    $reminder = $trip->reminders()->create([
        'label' => 'Check in',
        'remind_at' => '2026-09-09 09:00:00',
        'timezone' => 'America/Denver',
        'delivery_channels' => ['in_app'],
    ]);

    $this->actingAs($owner)->delete(route('trips.itinerary-items.destroy', [$trip, $itineraryItem]))->assertRedirect();
    $this->actingAs($owner)->delete(route('trips.reservations.destroy', [$trip, $reservation]))->assertRedirect();
    $this->actingAs($owner)->delete(route('trips.costs.destroy', [$trip, $cost]))->assertRedirect();
    $this->actingAs($owner)->delete(route('trips.packing-items.destroy', [$trip, $packingItem]))->assertRedirect();
    $this->actingAs($owner)->delete(route('trips.tasks.destroy', [$trip, $task]))->assertRedirect();
    $this->actingAs($owner)->delete(route('trips.documents.destroy', [$trip, $document]))->assertRedirect();
    $this->actingAs($owner)->delete(route('trips.reminders.destroy', [$trip, $reminder]))->assertRedirect();

    $this->assertModelMissing($itineraryItem);
    $this->assertModelMissing($reservation);
    $this->assertModelMissing($cost);
    $this->assertModelMissing($packingItem);
    $this->assertModelMissing($task);
    $this->assertModelMissing($document);
    $this->assertModelMissing($reminder);

    expect($trip->activityEvents()->where('event_type', 'itinerary.deleted')->exists())->toBeTrue()
        ->and($trip->activityEvents()->where('event_type', 'reservation.deleted')->exists())->toBeTrue()
        ->and($trip->activityEvents()->where('event_type', 'cost.deleted')->exists())->toBeTrue()
        ->and($trip->activityEvents()->where('event_type', 'packing.deleted')->exists())->toBeTrue()
        ->and($trip->activityEvents()->where('event_type', 'task.deleted')->exists())->toBeTrue()
        ->and($trip->activityEvents()->where('event_type', 'document.deleted')->exists())->toBeTrue()
        ->and($trip->activityEvents()->where('event_type', 'reminder.deleted')->exists())->toBeTrue();
});

test('editors can delete trip planning entries but viewers cannot', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $viewer = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Tokyo',
        'destination' => 'Tokyo, Japan',
        'starts_on' => '2026-10-03',
        'ends_on' => '2026-10-12',
        'status' => 'planned',
    ]);
    $trip->collaborators()->create([
        'user_id' => $editor->id,
        'email' => $editor->email,
        'role' => 'editor',
        'accepted_at' => now(),
    ]);
    $trip->collaborators()->create([
        'user_id' => $viewer->id,
        'email' => $viewer->email,
        'role' => 'viewer',
        'accepted_at' => now(),
    ]);

    $editorTask = $trip->tasks()->create([
        'title' => 'Book train',
        'priority' => 'normal',
    ]);
    $viewerTask = $trip->tasks()->create([
        'title' => 'Should remain',
        'priority' => 'normal',
    ]);

    $this->actingAs($editor)->delete(route('trips.tasks.destroy', [$trip, $editorTask]))->assertRedirect();
    $this->assertModelMissing($editorTask);

    $this->actingAs($viewer)->delete(route('trips.tasks.destroy', [$trip, $viewerTask]))->assertForbidden();
    $this->assertModelExists($viewerTask);
});

test('viewers cannot update trip planning entries', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);
    $cost = $trip->costs()->create([
        'category' => 'food',
        'label' => 'Dinner',
        'currency' => 'USD',
    ]);
    $trip->collaborators()->create([
        'user_id' => $viewer->id,
        'email' => $viewer->email,
        'role' => 'viewer',
        'accepted_at' => now(),
    ]);

    $this->actingAs($viewer)->patch(route('trips.costs.update', [$trip, $cost]), [
        'category' => 'food',
        'label' => 'Dinner updated',
        'planned_amount' => '80.00',
        'actual_amount' => null,
        'currency' => 'USD',
        'notes' => 'Should not save.',
    ])->assertForbidden();

    expect($cost->fresh()->label)->toBe('Dinner');
});
