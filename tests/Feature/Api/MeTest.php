<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('an authenticated request to /api/me returns the token owner', function () {
    $user = User::factory()->create(['name' => 'Wez Jones', 'email' => 'wez1139@gmail.com']);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/me');

    $response->assertOk()->assertJson([
        'id' => $user->id,
        'name' => 'Wez Jones',
        'email' => 'wez1139@gmail.com',
    ]);
});

test('an unauthenticated request to /api/me is rejected', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});
