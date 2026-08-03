<?php

use App\Models\User;

test('a valid timezone cookie updates the logged-in user', function () {
    $user = User::factory()->create(['timezone' => null]);

    $this->actingAs($user)
        ->withUnencryptedCookie('timezone', 'Australia/Perth')
        ->get('/jobs');

    expect($user->fresh()->timezone)->toBe('Australia/Perth');
});

test('a bogus timezone cookie value is ignored', function () {
    $user = User::factory()->create(['timezone' => null]);

    $this->actingAs($user)
        ->withUnencryptedCookie('timezone', 'Not/A_Real_Zone')
        ->get('/jobs');

    expect($user->fresh()->timezone)->toBeNull();
});
