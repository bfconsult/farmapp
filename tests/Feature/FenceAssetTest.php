<?php

use App\Console\Commands\AssetShapesToLines;
use App\Models\Asset;
use App\Models\AssetLocation;
use App\Models\AssetType;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;

beforeEach(function () {
    $this->user = User::factory()->create(['claimed_at' => now()]);
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);
    $this->fenceType = AssetType::create(['property_id' => $this->property->id, 'name' => 'Fence', 'kind' => 'fence']);
    $this->fixed = AssetType::create(['property_id' => $this->property->id, 'name' => 'Fixed']);
    $this->fence = Asset::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Creek fence']);
    $this->as = fn () => $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id]);
});

test('an asset can be located by a line', function () {
    ($this->as)()->put(route('assets.update-location', $this->fence), ['line' => [[-38.41, 145.02], [-38.411, 145.021], [-38.412, 145.021]]])
        ->assertSessionHasNoErrors();

    $location = $this->fence->fresh()->currentLocation;
    expect($location->line)->toBe([[-38.41, 145.02], [-38.411, 145.021], [-38.412, 145.021]])
        ->and($location->shape)->toBeNull()
        ->and($location->latitude)->toBeNull();

    // One point isn't a line.
    ($this->as)()->put(route('assets.update-location', $this->fence), ['line' => [[-38.41, 145.02]]])
        ->assertSessionHasErrors('line');
});

test('an asset of the fence type records what type of fence it is and its wires', function () {
    ($this->as)()->patch(route('assets.update', $this->fence), [
        'name' => 'Creek fence',
        'asset_type_id' => $this->fenceType->id,
        'details' => ['fence_type' => 'electric', 'wires' => '3'],
    ])->assertSessionHasNoErrors();
    expect($this->fence->fresh()->details)->toBe(['fence_type' => 'electric', 'wires' => 3]);

    // The asset page's edit form doesn't send the type: still a fence.
    ($this->as)()->patch(route('assets.update', $this->fence), ['name' => 'Creek fence', 'details' => ['fence_type' => 'netting']]);
    expect($this->fence->fresh()->details)->toBe(['fence_type' => 'netting']);

    // Not a fence any more: its fence details go too.
    ($this->as)()->patch(route('assets.update', $this->fence), ['name' => 'Creek fence', 'asset_type_id' => $this->fixed->id, 'details' => ['fence_type' => 'electric']]);
    expect($this->fence->fresh()->details)->toBeNull();

    ($this->as)()->patch(route('assets.update', $this->fence), ['name' => 'Creek fence', 'asset_type_id' => $this->fenceType->id, 'details' => ['fence_type' => 'moat']])
        ->assertSessionHasErrors('details.fence_type');
});

test('the fence type can be renamed but not deleted', function () {
    ($this->as)()->delete(route('settings.asset-types.destroy', $this->fenceType))->assertSessionHas('error');
    expect($this->fenceType->fresh())->not->toBeNull();

    ($this->as)()->delete(route('settings.asset-types.destroy', $this->fixed));
    expect($this->fixed->fresh())->toBeNull();
});

test('a new property gets a fence type', function () {
    $other = Property::create(['name' => 'Next door', 'address' => '2 Test Rd']);
    AssetType::seedDefaultsForProperty($other->id);

    $type = AssetType::where('property_id', $other->id)->where('kind', 'fence')->sole();
    expect($type->name)->toBe('Fence')
        ->and(AssetType::fenceTypeFor($other->id)->id)->toBe($type->id);
});

test('a fence is drawn in the farm view where its line is', function () {
    $this->user->forceFill(['app_admin' => true])->save();
    Zone::create(['property_id' => $this->property->id, 'name' => 'TCL', 'coordinates' => [[-38.41, 145.02], [-38.41, 145.03], [-38.42, 145.03]]]);
    $this->fence->update(['asset_type_id' => $this->fenceType->id, 'details' => ['fence_type' => 'netting']]);
    AssetLocation::create(['asset_id' => $this->fence->id, 'line' => [[-38.41, 145.02], [-38.42, 145.03]]]);
    // A line that isn't a fence isn't drawn as one.
    $pipe = Asset::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Pipe']);
    AssetLocation::create(['asset_id' => $pipe->id, 'line' => [[-38.41, 145.02], [-38.42, 145.02]]]);

    ($this->as)()->get(route('farm-view'))->assertInertia(fn ($page) => $page
        ->where('fences', [['id' => $this->fence->id, 'name' => 'Creek fence', 'fence_type' => 'netting', 'wires' => null, 'line' => [[-38.41, 145.02], [-38.42, 145.03]]]])
    );

    // ...and the asset page has the other lines to draw up to.
    ($this->as)()->get(route('assets.show', $this->fence))->assertInertia(fn ($page) => $page
        ->where('otherLines.0.name', 'Pipe')
        // ...and the types, so its edit form can make an asset a fence.
        ->where('assetTypes.0.name', 'Fence')
        ->where('assetTypes.0.kind', 'fence')
    );
});

test('an out-and-back shape reads as the line it was drawn along', function () {
    $a = [-38.41, 145.02];
    $b = [-38.411, 145.021];
    $c = [-38.412, 145.021];

    // Out A-B-C and back C-B to A.
    expect(AssetShapesToLines::outAndBack([$a, $b, $c, $b]))->toBe([$a, $b, $c]);
    // Starting in the middle, with the far end doubled and slightly off.
    // (Either way along it is the same fence.)
    expect(AssetShapesToLines::outAndBack([$b, $c, [-38.41201, 145.02101], $b, $a]))->toBeIn([[$a, $b, $c], [$c, $b, $a]]);
    // A real shape isn't one.
    expect(AssetShapesToLines::outAndBack([$a, $b, $c, [-38.411, 145.019]]))->toBeNull();
});

test('the command converts out-and-back fences to lines only when told to', function () {
    AssetLocation::create(['asset_id' => $this->fence->id, 'shape' => [[-38.41, 145.02], [-38.411, 145.021], [-38.41, 145.02000001]]]);
    $shed = Asset::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Shed']);
    AssetLocation::create(['asset_id' => $shed->id, 'shape' => [[-38.41, 145.02], [-38.41, 145.0201], [-38.4101, 145.0201], [-38.4101, 145.02]]]);

    $this->artisan('assets:shapes-to-lines')->assertSuccessful();
    expect($this->fence->fresh()->currentLocation->line)->toBeNull();

    $this->artisan('assets:shapes-to-lines --apply')->assertSuccessful();
    $fence = $this->fence->fresh();
    expect($fence->currentLocation->line)->toBe([[-38.41, 145.02], [-38.411, 145.021]])
        ->and($fence->asset_type_id)->toBe($this->fenceType->id)
        ->and($fence->locations()->count())->toBe(2)
        ->and($shed->fresh()->currentLocation->shape)->not->toBeNull()
        ->and($shed->fresh()->asset_type_id)->toBeNull();
});

test('the boundary and zones editor has the fences, to draw paddocks up to', function () {
    $this->fence->update(['asset_type_id' => $this->fenceType->id]);
    AssetLocation::create(['asset_id' => $this->fence->id, 'line' => [[-38.41, 145.02], [-38.42, 145.03]]]);

    ($this->as)()->get(route('shape.edit', $this->property))->assertInertia(fn ($page) => $page
        ->component('Properties/Shape')
        ->where('fences', [['id' => $this->fence->id, 'name' => 'Creek fence', 'line' => [[-38.41, 145.02], [-38.42, 145.03]]]])
    );
});
