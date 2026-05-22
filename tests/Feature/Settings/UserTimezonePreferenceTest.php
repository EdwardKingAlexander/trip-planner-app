<?php

use App\Models\User;

test('shared inertia payload exposes the default app timezone before a preference is saved', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('appearance.edit'));

    $timezone = $response->viewData('page')['props']['auth']['timezone'];

    expect($timezone['value'])->toBe(config('app.timezone'))
        ->and($timezone['default'])->toBe(config('app.timezone'))
        ->and($timezone['is_default'])->toBeTrue();
});

test('shared inertia payload exposes the saved app timezone', function () {
    $user = User::factory()->create();
    $user->travelPreference()->create(['home_timezone' => 'America/Denver']);

    $response = $this->actingAs($user)->get(route('appearance.edit'));

    $timezone = $response->viewData('page')['props']['auth']['timezone'];

    expect($timezone['value'])->toBe('America/Denver')
        ->and($timezone['default'])->toBe(config('app.timezone'))
        ->and($timezone['is_default'])->toBeFalse();
});

test('users can update only their app timezone from the account dropdown', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('appearance.edit'))
        ->patch(route('settings.timezone.update'), [
            'timezone' => 'Asia/Tokyo',
        ])
        ->assertRedirect(route('appearance.edit'))
        ->assertSessionHasNoErrors();

    expect($user->travelPreference()->first())
        ->home_timezone->toBe('Asia/Tokyo')
        ->default_currency->toBe('USD');
});

test('app timezone update rejects invalid shorthand identifiers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('appearance.edit'))
        ->patch(route('settings.timezone.update'), [
            'timezone' => 'PST',
        ])
        ->assertRedirect(route('appearance.edit'))
        ->assertSessionHasErrors('timezone');

    expect($user->travelPreference()->exists())->toBeFalse();
});
