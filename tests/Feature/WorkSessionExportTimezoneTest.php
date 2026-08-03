<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

test('exported work session times are converted to the user\'s timezone, not left as raw UTC', function () {
    $user = User::factory()->create(['timezone' => 'Australia/Perth']); // UTC+8, no DST
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'started_at' => '2026-06-15 22:00:00', // UTC -> 06:00 next day in Perth
        'ended_at' => '2026-06-16 00:00:00',    // UTC -> 08:00 in Perth
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

    $tmpFile = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($tmpFile, $response->streamedContent());

    $sheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($tmpFile)->getActiveSheet();
    unlink($tmpFile);

    expect($sheet->getCell('A2')->getValue())->toBe('16/06/2026');
    expect($sheet->getCell('C2')->getValue())->toBe('06:00');
    expect($sheet->getCell('D2')->getValue())->toBe('08:00');
});

test('a user with no captured timezone falls back to the app default instead of erroring', function () {
    $user = User::factory()->create(['timezone' => null]);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    WorkSession::create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'started_at' => '2026-06-15 22:00:00',
        'ended_at' => '2026-06-16 00:00:00',
        'status' => WorkSession::FINALISED,
    ]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.export.download', [
            'format' => 'pdf',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
        ]))
        ->assertOk();
});
