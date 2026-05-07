<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('appearance page shares the resolved theme', function () {
    $user = User::factory()->create([
        'theme' => 'midnight',
    ]);

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Appearance')
            ->where('theme', 'midnight'),
        );
});

test('appearance page uses the theme cookie for guests before authentication', function () {
    $this->withUnencryptedCookie('theme', 'forest')
        ->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('theme', 'forest'),
        );
});
