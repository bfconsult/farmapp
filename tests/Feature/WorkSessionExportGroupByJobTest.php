<?php

use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

function downloadGroupedExport($user, $property, array $overrides = [])
{
    return test()->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('work-sessions.export.download', array_merge([
            'format' => 'excel',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'group_by_job' => 1,
        ], $overrides)));
}

function loadExportSheet($response)
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($tmpFile, $response->getContent());
    $sheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($tmpFile)->getActiveSheet();
    unlink($tmpFile);

    return $sheet;
}

test('grouping by job produces a bold subtotal row per job, jobs alphabetical, ad-hoc last', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $zebra = FarmJob::create(['name' => 'Zebra fencing', 'property_id' => $property->id, 'user_id' => $user->id]);
    $apple = FarmJob::create(['name' => 'Apple orchard', 'property_id' => $property->id, 'user_id' => $user->id]);

    // Out of date order on purpose, and interleaved across jobs, to prove
    // both the grouping and the within-group date sort.
    WorkSession::create(['property_id' => $property->id, 'user_id' => $user->id, 'farm_job_id' => $zebra->id, 'started_at' => '2026-06-10 01:00:00', 'ended_at' => '2026-06-10 03:00:00', 'status' => WorkSession::FINALISED]);
    WorkSession::create(['property_id' => $property->id, 'user_id' => $user->id, 'started_at' => '2026-06-05 01:00:00', 'ended_at' => '2026-06-05 02:00:00', 'status' => WorkSession::FINALISED]); // ad-hoc
    WorkSession::create(['property_id' => $property->id, 'user_id' => $user->id, 'farm_job_id' => $apple->id, 'started_at' => '2026-06-15 01:00:00', 'ended_at' => '2026-06-15 05:00:00', 'status' => WorkSession::FINALISED]);
    WorkSession::create(['property_id' => $property->id, 'user_id' => $user->id, 'farm_job_id' => $zebra->id, 'started_at' => '2026-06-01 01:00:00', 'ended_at' => '2026-06-01 02:00:00', 'status' => WorkSession::FINALISED]);

    $response = downloadGroupedExport($user, $property);
    $response->assertOk();
    $sheet = loadExportSheet($response);

    // Row 1: headers. Then Apple's subtotal + 1 detail row, then Zebra's
    // subtotal + 2 detail rows (earliest first), then Ad-hoc's subtotal +
    // its 1 row, then the grand total.
    expect($sheet->getCell('B2')->getValue())->toBe('Apple orchard');
    expect($sheet->getCell('E2')->getValue())->toEqual(4.0);
    expect($sheet->getStyle('B2')->getFont()->getBold())->toBeTrue();
    expect($sheet->getCell('B3')->getValue())->toBe('Apple orchard');
    expect($sheet->getCell('A3')->getValue())->toBe('15/06/2026');

    expect($sheet->getCell('B4')->getValue())->toBe('Zebra fencing');
    expect($sheet->getCell('E4')->getValue())->toEqual(3.0);
    expect($sheet->getStyle('B4')->getFont()->getBold())->toBeTrue();
    expect($sheet->getCell('A5')->getValue())->toBe('01/06/2026');
    expect($sheet->getCell('A6')->getValue())->toBe('10/06/2026');

    expect($sheet->getCell('B7')->getValue())->toBe('Ad-hoc');
    expect($sheet->getCell('E7')->getValue())->toEqual(1.0);
    expect($sheet->getCell('A8')->getValue())->toBe('05/06/2026');

    expect($sheet->getCell('B9')->getValue())->toBe('Total');
    expect($sheet->getCell('E9')->getValue())->toEqual(8.0);
});

test('two distinct jobs sharing a name get separate subtotals, not merged', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $first = FarmJob::create(['name' => 'Mow the lawn', 'property_id' => $property->id, 'user_id' => $user->id]);
    $second = FarmJob::create(['name' => 'Mow the lawn', 'property_id' => $property->id, 'user_id' => $user->id]);

    WorkSession::create(['property_id' => $property->id, 'user_id' => $user->id, 'farm_job_id' => $first->id, 'started_at' => '2026-06-01 01:00:00', 'ended_at' => '2026-06-01 02:00:00', 'status' => WorkSession::FINALISED]);
    WorkSession::create(['property_id' => $property->id, 'user_id' => $user->id, 'farm_job_id' => $second->id, 'started_at' => '2026-06-02 01:00:00', 'ended_at' => '2026-06-02 03:00:00', 'status' => WorkSession::FINALISED]);

    $response = downloadGroupedExport($user, $property);
    $sheet = loadExportSheet($response);

    // Two separate subtotal rows (1h and 2h) - not one merged 3h row. Which
    // of the two same-named jobs sorts first isn't specified, so just
    // confirm both subtotals exist rather than assuming an order.
    expect($sheet->getCell('B2')->getValue())->toBe('Mow the lawn');
    expect($sheet->getCell('B4')->getValue())->toBe('Mow the lawn');
    expect([$sheet->getCell('E2')->getValue(), $sheet->getCell('E4')->getValue()])
        ->toEqualCanonicalizing([1.0, 2.0]);
});

test('grouping includes the billing subtotal column when billing rate is requested', function () {
    $user = User::factory()->create(['hourly_rate' => 50]);
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $job = FarmJob::create(['name' => 'Fencing', 'property_id' => $property->id, 'user_id' => $user->id]);

    WorkSession::create(['property_id' => $property->id, 'user_id' => $user->id, 'farm_job_id' => $job->id, 'started_at' => '2026-06-01 01:00:00', 'ended_at' => '2026-06-01 03:00:00', 'status' => WorkSession::FINALISED]);

    $response = downloadGroupedExport($user, $property, ['rate' => 'billing']);
    $sheet = loadExportSheet($response);

    expect($sheet->getCell('F1')->getValue())->toBe('Amount (Ex GST)');
    expect($sheet->getCell('B2')->getValue())->toBe('Fencing');
    expect($sheet->getCell('F2')->getValue())->toEqual(100.0);
});

test('leaving group_by_job off keeps the flat, ungrouped layout', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $job = FarmJob::create(['name' => 'Fencing', 'property_id' => $property->id, 'user_id' => $user->id]);

    WorkSession::create(['property_id' => $property->id, 'user_id' => $user->id, 'farm_job_id' => $job->id, 'started_at' => '2026-06-01 01:00:00', 'ended_at' => '2026-06-01 02:00:00', 'status' => WorkSession::FINALISED]);

    $response = downloadGroupedExport($user, $property, ['group_by_job' => 0]);
    $sheet = loadExportSheet($response);

    // No subtotal row - straight from the header into the one detail row.
    expect($sheet->getCell('B2')->getValue())->toBe('Fencing');
    expect($sheet->getCell('A2')->getValue())->toBe('01/06/2026');
    expect($sheet->getCell('B3')->getValue())->toBe('Total');
});
