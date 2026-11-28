<?php

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['claimed_at' => now()]);
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);
    AssetType::seedDefaultsForProperty($this->property->id);
    $this->fixed = AssetType::where('property_id', $this->property->id)->where('name', 'Fixed')->sole();
    $this->plant = AssetType::where('property_id', $this->property->id)->where('name', 'Plant')->sole();
    $this->as = fn () => $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id]);
});

test('a new property has its fence, gate, tank, pump, trough and vegetation types under Fixed', function () {
    $kinded = AssetType::where('property_id', $this->property->id)->whereNotNull('kind')->get();

    expect($kinded)->toHaveCount(6)
        ->and($kinded->pluck('parent_id')->unique()->all())->toBe([$this->fixed->id])
        ->and($this->fixed->parent_id)->toBeNull()
        ->and($this->plant->parent_id)->toBeNull();
});

test('a kind made again goes under Fixed, making Fixed if it has gone', function () {
    $other = Property::create(['name' => 'Next door', 'address' => '2 Test Rd']);

    $trough = AssetType::typeFor($other->id, AssetType::TROUGH);

    expect($trough->parent->name)->toBe('Fixed')
        ->and($trough->parent->property_id)->toBe($other->id)
        ->and(AssetType::typeFor($other->id, AssetType::GATE)->parent_id)->toBe($trough->parent_id);
});

test('a sub-type can be added under a category, but not under a sub-type or another property\'s category', function () {
    ($this->as)()->post(route('settings.asset-types.store'), ['name' => 'Tractor', 'parent_id' => $this->plant->id])->assertSessionHasNoErrors();
    $tractor = AssetType::where('name', 'Tractor')->sole();
    expect($tractor->parent_id)->toBe($this->plant->id);

    ($this->as)()->post(route('settings.asset-types.store'), ['name' => 'Little tractor', 'parent_id' => $tractor->id])->assertSessionHasErrors('parent_id');

    $other = Property::create(['name' => 'Next door', 'address' => '2 Test Rd']);
    $theirs = AssetType::create(['property_id' => $other->id, 'name' => 'Theirs']);
    ($this->as)()->post(route('settings.asset-types.store'), ['name' => 'Sneaky', 'parent_id' => $theirs->id])->assertSessionHasErrors('parent_id');
});

test('a type can be moved to another category or made a category, but a category with sub-types stays one', function () {
    $trough = AssetType::where('property_id', $this->property->id)->where('kind', 'trough')->sole();

    ($this->as)()->patch(route('settings.asset-types.update', $trough), ['name' => 'Trough', 'parent_id' => $this->plant->id])->assertSessionHasNoErrors();
    expect($trough->fresh()->parent_id)->toBe($this->plant->id);

    ($this->as)()->patch(route('settings.asset-types.update', $trough), ['name' => 'Trough', 'parent_id' => null]);
    expect($trough->fresh()->parent_id)->toBeNull();

    ($this->as)()->patch(route('settings.asset-types.update', $this->fixed), ['name' => 'Fixed', 'parent_id' => $this->plant->id])->assertSessionHas('error');
    ($this->as)()->patch(route('settings.asset-types.update', $this->plant), ['name' => 'Plant', 'parent_id' => $this->plant->id])->assertSessionHasErrors('parent_id');
    expect($this->fixed->fresh()->parent_id)->toBeNull();
});

test('a category with sub-types can\'t be deleted, and a deleted sub-type\'s assets move up to its category', function () {
    ($this->as)()->delete(route('settings.asset-types.destroy', $this->fixed))->assertSessionHas('error');
    expect($this->fixed->fresh())->not->toBeNull();

    $shed = AssetType::create(['property_id' => $this->property->id, 'parent_id' => $this->fixed->id, 'name' => 'Shed']);
    $asset = Asset::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Hay shed', 'asset_type_id' => $shed->id]);

    ($this->as)()->delete(route('settings.asset-types.destroy', $shed));

    expect($shed->fresh())->toBeNull()
        ->and($asset->fresh()->asset_type_id)->toBe($this->fixed->id);
});

test('the asset pages get each type\'s category', function () {
    $trough = AssetType::where('property_id', $this->property->id)->where('kind', 'trough')->sole();
    $asset = Asset::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Trough 1', 'asset_type_id' => $trough->id]);

    ($this->as)()->get(route('manage.assets'))->assertInertia(fn ($page) => $page
        ->where('assets.0.asset_type.parent.name', 'Fixed')
        ->has('assetTypes.0.parent_id'));

    ($this->as)()->get(route('assets.show', $asset))->assertInertia(fn ($page) => $page
        ->where('asset.asset_type.parent.name', 'Fixed'));
});

test('an asset can be duplicated, as "(copy)" with no location', function () {
    $trough = AssetType::where('property_id', $this->property->id)->where('kind', 'trough')->sole();
    $asset = Asset::create([
        'property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Trough 1',
        'asset_type_id' => $trough->id, 'details' => ['trough_size' => 'round_1800', 'capacity' => 1100],
        'description' => 'By the gate', 'value' => 450,
    ]);
    \App\Models\AssetLocation::create(['asset_id' => $asset->id, 'latitude' => -38.41, 'longitude' => 145.02]);

    $response = ($this->as)()->post(route('assets.duplicate', $asset));

    $copy = Asset::where('name', 'Trough 1 (copy)')->sole();
    $response->assertRedirect(route('assets.show', $copy).'?edit=1');
    expect($copy->asset_type_id)->toBe($trough->id)
        ->and($copy->details)->toEqual(['trough_size' => 'round_1800', 'capacity' => 1100])
        ->and($copy->description)->toBe('By the gate')
        ->and((float) $copy->value)->toBe(450.0)
        ->and($copy->currentLocation)->toBeNull();

    $other = Property::create(['name' => 'Next door', 'address' => '2 Test Rd']);
    $theirs = Asset::create(['property_id' => $other->id, 'created_by' => $this->user->id, 'name' => 'Theirs']);
    ($this->as)()->post(route('assets.duplicate', $theirs))->assertNotFound();
});
