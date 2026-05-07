<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('reminder inbox requires authentication', function () {
    $this->get(route('reminders.index'))->assertRedirect(route('login'));
});

test('reminder inbox groups visible reminders by status', function () {
    Carbon::setTestNow('2026-05-07 12:00:00');

    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $user->id,
        'name' => 'Lisbon',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);

    $pastDue = $trip->reminders()->create([
        'label' => 'Past due check-in',
        'remind_at' => now()->subDay(),
        'timezone' => 'UTC',
        'delivery_channels' => ['in_app'],
    ]);
    $trip->reminders()->create([
        'label' => 'Today reminder',
        'remind_at' => now()->addHour(),
        'timezone' => 'UTC',
        'delivery_channels' => ['in_app'],
    ]);
    $trip->reminders()->create([
        'label' => 'Done reminder',
        'remind_at' => now()->subDays(2),
        'timezone' => 'UTC',
        'delivery_channels' => ['in_app'],
        'sent_at' => now(),
    ]);

    $privateTrip = Trip::create([
        'user_id' => $stranger->id,
        'name' => 'Private',
        'destination' => 'Private',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);
    $privateTrip->reminders()->create([
        'label' => 'Private reminder',
        'remind_at' => now(),
        'timezone' => 'UTC',
        'delivery_channels' => ['in_app'],
    ]);

    $this->actingAs($user)
        ->get(route('reminders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reminders/Index')
            ->has('buckets.past_due', 1)
            ->has('buckets.today', 1)
            ->has('buckets.done', 1)
            ->has('buckets.upcoming', 0)
            ->where('buckets.past_due.0.label', 'Past due check-in'),
        );

    $this->actingAs($user)
        ->post(route('reminders.done', $pastDue))
        ->assertRedirect();

    expect($pastDue->refresh()->sent_at)->not->toBeNull();
});
