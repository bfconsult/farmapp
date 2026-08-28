<?php

use App\Models\Livestock;
use App\Models\Property;
use App\Models\User;

test('sire and dam relations resolve to the correct parent records', function () {
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $userId = User::factory()->create()->id;

    $sire = Livestock::create(['property_id' => $property->id, 'created_by' => $userId, 'tag_number' => 'S1']);
    $dam = Livestock::create(['property_id' => $property->id, 'created_by' => $userId, 'tag_number' => 'D1']);
    $calf = Livestock::create([
        'property_id' => $property->id,
        'created_by' => $userId,
        'tag_number' => 'C1',
        'sire_id' => $sire->id,
        'dam_id' => $dam->id,
    ]);

    expect($calf->sire->id)->toBe($sire->id);
    expect($calf->dam->id)->toBe($dam->id);
});

test('offspring relations and the combined helper return the right children', function () {
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $userId = User::factory()->create()->id;

    $sire = Livestock::create(['property_id' => $property->id, 'created_by' => $userId, 'tag_number' => 'S1']);
    $dam = Livestock::create(['property_id' => $property->id, 'created_by' => $userId, 'tag_number' => 'D1']);

    $calfBySire = Livestock::create(['property_id' => $property->id, 'created_by' => $userId, 'tag_number' => 'C1', 'sire_id' => $sire->id]);
    $calfByDam = Livestock::create(['property_id' => $property->id, 'created_by' => $userId, 'tag_number' => 'C2', 'dam_id' => $dam->id]);
    $calfByBoth = Livestock::create(['property_id' => $property->id, 'created_by' => $userId, 'tag_number' => 'C3', 'sire_id' => $sire->id, 'dam_id' => $dam->id]);

    expect($sire->offspringAsSire->pluck('id')->sort()->values()->all())
        ->toBe([$calfBySire->id, $calfByBoth->id]);

    expect($dam->offspringAsDam->pluck('id')->sort()->values()->all())
        ->toBe([$calfByDam->id, $calfByBoth->id]);

    expect($sire->offspring()->pluck('id')->sort()->values()->all())
        ->toBe([$calfBySire->id, $calfByBoth->id]);
});

// "An animal cannot be its own sire or dam" is validation-layer behavior
// (a request-validation closure, not something enforceable at the model
// level) - covered as an HTTP-level test in LivestockManagementTest.php
// once the controller/routes exist, not here.

test('deleting a sire or dam does not delete its offspring, just clears the reference', function () {
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $userId = User::factory()->create()->id;

    $sire = Livestock::create(['property_id' => $property->id, 'created_by' => $userId, 'tag_number' => 'S1']);
    $calf = Livestock::create(['property_id' => $property->id, 'created_by' => $userId, 'tag_number' => 'C1', 'sire_id' => $sire->id]);

    $sire->delete();

    expect(Livestock::find($calf->id))->not->toBeNull();
    expect($calf->fresh()->sire_id)->toBeNull();
});
