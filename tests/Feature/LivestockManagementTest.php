<?php

use App\Models\Livestock;
use App\Models\LivestockType;
use App\Models\Mob;
use App\Models\MobZoneHistory;
use App\Models\Note;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;

test('an admin can create a mob and move it between paddocks, building history', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $north = Zone::create(['property_id' => $property->id, 'name' => 'North Paddock', 'coordinates' => [[0, 0], [0, 1], [1, 1]]]);
    $south = Zone::create(['property_id' => $property->id, 'name' => 'South Paddock', 'coordinates' => [[0, 0], [0, 1], [1, 1]]]);

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('mobs.store'), ['name' => 'Breeding Mob', 'zone_id' => $north->id]);

    $response->assertRedirect();
    $mob = Mob::where('name', 'Breeding Mob')->firstOrFail();
    expect($mob->currentZone->zone_id)->toBe($north->id);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->put(route('mobs.update-zone', $mob), ['zone_id' => $south->id])
        ->assertRedirect();

    expect($mob->fresh()->currentZone->zone_id)->toBe($south->id);
    expect(MobZoneHistory::where('mob_id', $mob->id)->count())->toBe(2);
});

test('an admin can create an individual animal within a mob', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $mob = Mob::create(['property_id' => $property->id, 'created_by' => $user->id, 'name' => 'Breeding Mob']);
    $type = LivestockType::create(['property_id' => $property->id, 'name' => 'Cattle']);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('livestock.store'), [
            'tag_number' => 'NLIS001',
            'name' => 'Bessie',
            'sex' => 'female',
            'date_of_birth' => '2024-01-15',
            'purchase_date' => '2025-03-01',
            'livestock_type_id' => $type->id,
            'mob_id' => $mob->id,
        ])
        ->assertRedirect();

    $animal = Livestock::where('tag_number', 'NLIS001')->firstOrFail();
    expect($animal->name)->toBe('Bessie');
    expect($animal->sex)->toBe('female');
    expect($animal->date_of_birth->toDateString())->toBe('2024-01-15');
    expect($animal->purchase_date->toDateString())->toBe('2025-03-01');
    expect($animal->mob_id)->toBe($mob->id);
    expect($animal->livestock_type_id)->toBe($type->id);
    expect($animal->status)->toBe('active');
});

test('purchase and sale weight/price are recorded independently with their price type', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('livestock.store'), [
            'tag_number' => 'NLIS001',
            'purchase_weight' => 320.5,
            'purchase_price_type' => 'per_kg',
            'purchase_price' => 3.50,
        ])
        ->assertRedirect();

    $animal = Livestock::where('tag_number', 'NLIS001')->firstOrFail();
    expect((float) $animal->purchase_weight)->toBe(320.5);
    expect($animal->purchase_price_type)->toBe('per_kg');
    expect((float) $animal->purchase_price)->toBe(3.5);
    expect($animal->birth_weight)->toBeNull();
    expect($animal->sale_price)->toBeNull();

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('livestock.update', $animal), [
            'tag_number' => 'NLIS001',
            'sale_weight' => 480,
            'sale_price_type' => 'per_unit',
            'sale_price' => 950,
        ])
        ->assertRedirect();

    $fresh = $animal->fresh();
    expect((float) $fresh->sale_weight)->toBe(480.0);
    expect($fresh->sale_price_type)->toBe('per_unit');
    expect((float) $fresh->sale_price)->toBe(950.0);
});

test('an admin can bulk-add animals into a mob with auto-incrementing tag numbers', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $mob = Mob::create(['property_id' => $property->id, 'created_by' => $user->id, 'name' => 'Breeding Mob']);
    $type = LivestockType::create(['property_id' => $property->id, 'name' => 'Cattle']);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('mobs.animals.store', $mob), [
            'tag_number' => 'NLIS001',
            'count' => 3,
            'sex' => 'female',
            'date_of_birth' => '2024-01-15',
            'purchase_weight' => 28,
            'purchase_price_type' => 'per_kg',
            'purchase_price' => 4.20,
            'livestock_type_id' => $type->id,
        ])
        ->assertRedirect();

    $animals = Livestock::where('mob_id', $mob->id)->orderBy('tag_number')->get();
    expect($animals->pluck('tag_number')->all())->toBe(['NLIS001', 'NLIS002', 'NLIS003']);
    expect($animals->pluck('name')->unique()->all())->toBe([null]);
    expect($animals->every(fn ($a) => $a->sex === 'female'))->toBeTrue();
    expect($animals->every(fn ($a) => $a->livestock_type_id === $type->id))->toBeTrue();
    expect($animals->every(fn ($a) => (float) $a->purchase_weight === 28.0))->toBeTrue();
    expect($animals->every(fn ($a) => $a->purchase_price_type === 'per_kg'))->toBeTrue();
    expect($animals->every(fn ($a) => (float) $a->purchase_price === 4.2))->toBeTrue();
});

test('bulk-adding animals with a non-numeric starting tag suffixes extras instead', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $mob = Mob::create(['property_id' => $property->id, 'created_by' => $user->id, 'name' => 'Breeding Mob']);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('mobs.animals.store', $mob), ['tag_number' => 'LAMB', 'count' => 3])
        ->assertRedirect();

    expect(Livestock::where('mob_id', $mob->id)->orderBy('tag_number')->pluck('tag_number')->all())
        ->toBe(['LAMB', 'LAMB-2', 'LAMB-3']);
});

test('bulk-adding animals fails without creating any if a generated tag is already taken', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $mob = Mob::create(['property_id' => $property->id, 'created_by' => $user->id, 'name' => 'Breeding Mob']);
    Livestock::create(['property_id' => $property->id, 'created_by' => $user->id, 'tag_number' => 'NLIS002']);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('mobs.animals.store', $mob), ['tag_number' => 'NLIS001', 'count' => 3])
        ->assertSessionHasErrors('tag_number');

    expect(Livestock::where('mob_id', $mob->id)->count())->toBe(0);
});

test('a worker cannot bulk-add animals and a foreign mob cannot receive them', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    $mob = Mob::create(['property_id' => $property->id, 'created_by' => $user->id, 'name' => 'Breeding Mob']);
    $foreignMob = Mob::create(['property_id' => $otherProperty->id, 'created_by' => $user->id, 'name' => 'Foreign Mob']);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('mobs.animals.store', $mob), ['tag_number' => 'A1', 'count' => 2])
        ->assertForbidden();

    Role::where('user_id', $user->id)->update(['type' => Role::ADMIN]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('mobs.animals.store', $foreignMob), ['tag_number' => 'A1', 'count' => 2])
        ->assertNotFound();
});

test('tag numbers are unique per property but not across properties', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    Livestock::create(['property_id' => $property->id, 'created_by' => $user->id, 'tag_number' => 'NLIS001']);
    Livestock::create(['property_id' => $otherProperty->id, 'created_by' => $user->id, 'tag_number' => 'NLIS001']);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('livestock.store'), ['tag_number' => 'NLIS001'])
        ->assertSessionHasErrors('tag_number');
});

test('an animal cannot be its own sire or dam', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $animal = Livestock::create(['property_id' => $property->id, 'created_by' => $user->id, 'tag_number' => 'A1']);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('livestock.update', $animal), ['tag_number' => 'A1', 'sire_id' => $animal->id])
        ->assertSessionHasErrors('sire_id');

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('livestock.update', $animal), ['tag_number' => 'A1', 'dam_id' => $animal->id])
        ->assertSessionHasErrors('dam_id');
});

test('a mob or animal belonging to a different property cannot be viewed, edited, or deleted', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $foreignMob = Mob::create(['property_id' => $otherProperty->id, 'created_by' => $user->id, 'name' => 'Foreign Mob']);
    $foreignAnimal = Livestock::create(['property_id' => $otherProperty->id, 'created_by' => $user->id, 'tag_number' => 'F1']);

    $session = fn () => $this->actingAs($user)->withSession(['current_property_id' => $property->id]);

    $session()->get(route('mobs.show', $foreignMob))->assertNotFound();
    $session()->patch(route('mobs.update', $foreignMob), ['name' => 'Hijacked'])->assertNotFound();
    $session()->put(route('mobs.update-zone', $foreignMob), [])->assertNotFound();
    $session()->delete(route('mobs.destroy', $foreignMob))->assertNotFound();

    $session()->get(route('livestock.show', $foreignAnimal))->assertNotFound();
    $session()->patch(route('livestock.update', $foreignAnimal), ['tag_number' => 'Hijacked'])->assertNotFound();
    $session()->delete(route('livestock.destroy', $foreignAnimal))->assertNotFound();
});

test('a worker can view livestock but cannot create, edit, or delete mobs or animals', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::WORKER]);
    $mob = Mob::create(['property_id' => $property->id, 'created_by' => $user->id, 'name' => 'Breeding Mob']);
    $animal = Livestock::create(['property_id' => $property->id, 'created_by' => $user->id, 'tag_number' => 'A1']);

    $session = fn () => $this->actingAs($user)->withSession(['current_property_id' => $property->id]);

    $session()->get(route('manage.livestock'))->assertOk();
    $session()->get(route('mobs.show', $mob))->assertOk();
    $session()->get(route('livestock.show', $animal))->assertOk();

    $session()->post(route('mobs.store'), ['name' => 'New Mob'])->assertForbidden();
    $session()->patch(route('mobs.update', $mob), ['name' => 'Renamed'])->assertForbidden();
    $session()->put(route('mobs.update-zone', $mob), [])->assertForbidden();
    $session()->delete(route('mobs.destroy', $mob))->assertForbidden();
    $session()->post(route('livestock.store'), ['tag_number' => 'A2'])->assertForbidden();
    $session()->patch(route('livestock.update', $animal), ['tag_number' => 'A1'])->assertForbidden();
    $session()->delete(route('livestock.destroy', $animal))->assertForbidden();
});

test('notes can attach to both a mob and an individual animal', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $mob = Mob::create(['property_id' => $property->id, 'created_by' => $user->id, 'name' => 'Breeding Mob']);
    $animal = Livestock::create(['property_id' => $property->id, 'created_by' => $user->id, 'tag_number' => 'A1']);

    $session = fn () => $this->actingAs($user)->withSession(['current_property_id' => $property->id]);

    $session()->post(route('notes.store'), ['body' => 'Checked the mob today', 'mob_id' => $mob->id])->assertRedirect();
    $session()->post(route('notes.store'), ['body' => 'Vet visit', 'livestock_id' => $animal->id])->assertRedirect();

    expect(Note::where('mob_id', $mob->id)->count())->toBe(1);
    expect(Note::where('livestock_id', $animal->id)->count())->toBe(1);

    // Exactly-one-parent guard: neither set...
    $session()->post(route('notes.store'), ['body' => 'orphan note'])->assertStatus(422);
    // ...nor both set at once.
    $session()->post(route('notes.store'), ['body' => 'double parent', 'mob_id' => $mob->id, 'livestock_id' => $animal->id])->assertStatus(422);
});

test('deleting a mob leaves its animals in place but removes its notes and zone history', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $zone = Zone::create(['property_id' => $property->id, 'name' => 'North Paddock', 'coordinates' => [[0, 0], [0, 1], [1, 1]]]);
    $mob = Mob::create(['property_id' => $property->id, 'created_by' => $user->id, 'name' => 'Breeding Mob']);
    $mob->zoneHistory()->create(['zone_id' => $zone->id, 'created_by' => $user->id]);
    $mob->notes()->create(['property_id' => $property->id, 'created_by' => $user->id, 'body' => 'Note on the mob']);
    $animal = Livestock::create(['property_id' => $property->id, 'created_by' => $user->id, 'tag_number' => 'A1', 'mob_id' => $mob->id]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->delete(route('mobs.destroy', $mob))
        ->assertRedirect();

    expect(Livestock::find($animal->id))->not->toBeNull();
    expect($animal->fresh()->mob_id)->toBeNull();
    expect(MobZoneHistory::where('mob_id', $mob->id)->count())->toBe(0);
    expect(Note::where('mob_id', $mob->id)->count())->toBe(0);
});

test('an animal status can be changed without affecting parentage', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $sire = Livestock::create(['property_id' => $property->id, 'created_by' => $user->id, 'tag_number' => 'S1']);
    $animal = Livestock::create(['property_id' => $property->id, 'created_by' => $user->id, 'tag_number' => 'A1', 'sire_id' => $sire->id]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('livestock.update', $animal), ['tag_number' => 'A1', 'status' => 'sold'])
        ->assertRedirect();

    $fresh = $animal->fresh();
    expect($fresh->status)->toBe('sold');
    expect($fresh->sire_id)->toBe($sire->id);
});
