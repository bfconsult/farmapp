<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

/**
 * Vapor's LambdaResponse only base64-encodes a response body (required for
 * any binary payload to survive the trip through API Gateway) when this
 * exact header is present - it never sniffs Content-Type to decide (see
 * vendor/laravel/vapor-core/src/Runtime/LambdaResponse.php). Without it,
 * the Excel export's raw zip bytes reached production fine locally/in
 * tinker but 502'd with a content-free "Internal server error" through the
 * real HTTP endpoint, since Laravel's own error handling never even saw a
 * failure - confirmed via a production repro before this header was added.
 */
test('the Excel export response is marked for Vapor to base64-encode', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'started_at' => '2026-06-15 22:00:00',
        'ended_at' => '2026-06-16 00:00:00',
        'status' => WorkSession::FINALISED,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.export.download', [
            'format' => 'excel',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

    $response->assertOk();
    $response->assertHeader('X-Vapor-Base64-Encode', 'True');
});

test('the PDF export response is also marked for Vapor to base64-encode', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'started_at' => '2026-06-15 22:00:00',
        'ended_at' => '2026-06-16 00:00:00',
        'status' => WorkSession::FINALISED,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.export.download', [
            'format' => 'pdf',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]));

    $response->assertOk();
    $response->assertHeader('X-Vapor-Base64-Encode', 'True');
});
