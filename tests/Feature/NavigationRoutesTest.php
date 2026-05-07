<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('global navigation destinations render distinct pages', function (string $routeName, string $component) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'trips' => ['trips.index', 'Trips/Index'],
    'search' => ['trips.search', 'Trips/Search'],
    'calendar' => ['calendar.index', 'calendar/Index'],
    'reminders' => ['reminders.index', 'reminders/Index'],
    'notifications' => ['notifications.index', 'notifications/Index'],
]);
