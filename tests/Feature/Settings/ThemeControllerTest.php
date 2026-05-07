<?php

use App\Models\User;

test('persists a valid theme for the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('appearance.edit'))
        ->patch(route('settings.theme.update'), [
            'theme' => 'sunset',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('appearance.edit'));

    expect($user->refresh()->theme)->toBe('sunset');
});

test('rejects an unknown theme', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('appearance.edit'))
        ->patch(route('settings.theme.update'), [
            'theme' => 'unknown',
        ])
        ->assertSessionHasErrors('theme')
        ->assertRedirect(route('appearance.edit'));

    expect($user->refresh()->theme)->toBe('coastal');
});

test('requires authentication to update theme', function () {
    $this->patch(route('settings.theme.update'), [
        'theme' => 'forest',
    ])->assertRedirect(route('login'));
});
