<?php

use App\Models\Property;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkSession;

test('a property with billing details set prints them at the top of the Excel export', function () {
    $user = User::factory()->create();
    $property = Property::create([
        'name' => 'Valle Pacis',
        'address' => '1 Test Rd',
        'billing_company_name' => 'Valle Pacis Pty Ltd',
        'billing_abn' => '12 345 678 901',
        'billing_address' => '1 Test Rd, Testville',
        'billing_phone' => '0400 000 000',
        'billing_contact_name' => 'Jane Smith',
    ]);
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

    $tmpFile = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($tmpFile, $response->getContent());
    $sheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($tmpFile)->getActiveSheet();
    unlink($tmpFile);

    expect($sheet->getCell('A1')->getValue())->toBe('Company/Business Name: Valle Pacis Pty Ltd');
    expect($sheet->getCell('A2')->getValue())->toBe('ABN: 12 345 678 901');
    expect($sheet->getCell('A3')->getValue())->toBe('Address: 1 Test Rd, Testville');
    expect($sheet->getCell('A4')->getValue())->toBe('Phone: 0400 000 000');
    expect($sheet->getCell('A5')->getValue())->toBe('Contact: Jane Smith');
    // Row 6 is the blank separator, so the table header lands on row 7.
    expect($sheet->getCell('A7')->getValue())->toBe('Date');
    expect($sheet->getCell('A8')->getValue())->toBe('15/06/2026');
});

test('a property with no billing details set omits the header block entirely', function () {
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

    $tmpFile = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($tmpFile, $response->getContent());
    $sheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($tmpFile)->getActiveSheet();
    unlink($tmpFile);

    expect($sheet->getCell('A1')->getValue())->toBe('Date');
    expect($sheet->getCell('A2')->getValue())->toBe('15/06/2026');
});

test('a worker linked to a supplier prints a From line under the client billing header', function () {
    $user = User::factory()->create();
    $property = Property::create([
        'name' => 'Valle Pacis',
        'address' => '1 Test Rd',
        'billing_company_name' => 'Valle Pacis Pty Ltd',
    ]);
    $supplier = Supplier::create([
        'property_id' => $property->id,
        'name' => 'BFC',
        'billing_company_name' => 'Billson Fisher Consulting',
        'billing_address' => '10 Rochester Street',
        'billing_contact_name' => 'James Billson',
    ]);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN, 'supplier_id' => $supplier->id]);

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

    $tmpFile = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($tmpFile, $response->getContent());
    $sheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($tmpFile)->getActiveSheet();
    unlink($tmpFile);

    expect($sheet->getCell('A1')->getValue())->toBe('Company/Business Name: Valle Pacis Pty Ltd');
    expect($sheet->getCell('A2')->getValue())->toBe('From: Billson Fisher Consulting, 10 Rochester Street, James Billson');
    // Row 3 is the blank separator, so the table header lands on row 4.
    expect($sheet->getCell('A4')->getValue())->toBe('Date');
});

test('a supplier with no billing fields filled in falls back to its plain name/address/phone', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $supplier = Supplier::create([
        'property_id' => $property->id,
        'name' => 'Dolittle Family Trust',
        'street_address' => '1 Station St, Yarra Glen',
        'phone' => '0404888777',
    ]);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::WORKER, 'supplier_id' => $supplier->id]);

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

    $tmpFile = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($tmpFile, $response->getContent());
    $sheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($tmpFile)->getActiveSheet();
    unlink($tmpFile);

    // No client billing header (property has none), so the From line is the
    // very first row.
    expect($sheet->getCell('A1')->getValue())->toBe('From: Dolittle Family Trust, 1 Station St, Yarra Glen, 0404888777');
    expect($sheet->getCell('A3')->getValue())->toBe('Date');
});

test('a worker with no linked supplier gets no From line at all', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

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

    $tmpFile = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($tmpFile, $response->getContent());
    $sheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($tmpFile)->getActiveSheet();
    unlink($tmpFile);

    expect($sheet->getCell('A1')->getValue())->toBe('Date');
});

test('billing details are also included in the PDF export without erroring', function () {
    $user = User::factory()->create();
    $property = Property::create([
        'name' => 'Valle Pacis',
        'address' => '1 Test Rd',
        'billing_company_name' => 'Valle Pacis Pty Ltd',
        'billing_abn' => '12 345 678 901',
    ]);
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

test('a From line does not break the PDF export either', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $supplier = Supplier::create([
        'property_id' => $property->id,
        'name' => 'BFC',
        'billing_company_name' => 'Billson Fisher Consulting',
    ]);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN, 'supplier_id' => $supplier->id]);

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
