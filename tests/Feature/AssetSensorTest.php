<?php

use App\Mail\TankLow;
use App\Models\Asset;
use App\Models\AssetLocation;
use App\Models\AssetType;
use App\Models\Device;
use App\Models\Property;
use App\Models\Role;
use App\Models\SensorReading;
use App\Models\User;
use App\Models\Zone;
use App\Services\Lorawan\TankActivity;

beforeEach(function () {
    $this->user = User::factory()->create(['claimed_at' => now()]);
    $this->user->forceFill(['app_admin' => true])->save();
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);
    foreach (['gate', 'tank', 'pump', 'trough', 'vegetation'] as $kind) {
        $this->{$kind.'Type'} = AssetType::typeFor($this->property->id, $kind);
    }
    $this->as = fn () => $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id]);

    $this->tank = Asset::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Top tank', 'asset_type_id' => $this->tankType->id]);
    AssetLocation::create(['asset_id' => $this->tank->id, 'latitude' => -38.412, 'longitude' => 145.022]);
    // 2000 mm when empty, 400 mm when full.
    $this->sensor = Device::create([
        'property_id' => $this->property->id,
        'name' => 'DDS75 #1',
        'model' => 'dragino_dds75',
        'kind' => 'tank',
        'dev_eui' => 'a840418f4362f80e',
        'latitude' => -38.5,
        'longitude' => 145.5,
        'config' => ['settings' => ['empty_distance' => 2000, 'full_distance' => 400, 'capacity' => 10000]],
    ]);
});

function fitSensor($test, Device $device, $assetId, array $settings = [])
{
    return ($test->as)()->put(route('manage.sensors.update', $device), [
        'name' => $device->name,
        'kind' => $device->kind,
        'asset_id' => $assetId,
        'latitude' => $device->latitude,
        'longitude' => $device->longitude,
        'settings' => [...($device->config['settings'] ?? []), ...$settings],
    ]);
}

test('a new property gets a type of each kind, and none of them can be deleted', function () {
    $other = Property::create(['name' => 'Next door', 'address' => '2 Test Rd']);
    AssetType::seedDefaultsForProperty($other->id);

    expect(AssetType::where('property_id', $other->id)->whereNotNull('kind')->pluck('name', 'kind')->all())
        ->toBe(['fence' => 'Fence', 'gate' => 'Gate', 'tank' => 'Water tank', 'pump' => 'Pump', 'trough' => 'Trough', 'vegetation' => 'Vegetation']);

    ($this->as)()->delete(route('settings.asset-types.destroy', $this->vegetationType))->assertSessionHas('error');
    expect($this->vegetationType->fresh())->not->toBeNull();
});

test('an asset keeps the details of its kind only', function () {
    ($this->as)()->patch(route('assets.update', $this->tank), [
        'name' => 'Top tank',
        'asset_type_id' => $this->tankType->id,
        'details' => ['capacity' => '22500', 'fence_type' => 'electric', 'vegetation_type' => 'tree'],
    ])->assertSessionHasNoErrors();
    expect($this->tank->fresh()->details)->toBe(['capacity' => 22500]);

    ($this->as)()->patch(route('assets.update', $this->tank), [
        'name' => 'Gums',
        'asset_type_id' => $this->vegetationType->id,
        'details' => ['capacity' => '22500', 'vegetation_type' => 'shrubs'],
    ])->assertSessionHasNoErrors();
    expect($this->tank->fresh()->details)->toBe(['vegetation_type' => 'shrubs']);

    ($this->as)()->patch(route('assets.update', $this->tank), ['name' => 'Gums', 'details' => ['vegetation_type' => 'cactus']])
        ->assertSessionHasErrors('details.vegetation_type');
});

test('a sensor is fitted to an asset on its own property, and its tank takes the capacity', function () {
    fitSensor($this, $this->sensor, $this->tank->id)->assertSessionHasNoErrors();

    expect($this->sensor->fresh()->asset_id)->toBe($this->tank->id)
        // The tank had no capacity, so it takes the sensor's.
        ->and($this->tank->fresh()->details)->toBe(['capacity' => 10000]);

    // From then on the tank's capacity is the one used.
    $this->tank->update(['details' => ['capacity' => 20000]]);
    expect(TankActivity::level($this->sensor->fresh(), 1200))->toBe(['percent' => 50.0, 'litres' => 10000]);

    // Not to an asset of another property.
    $other = Property::create(['name' => 'Next door', 'address' => '2 Test Rd']);
    $theirs = Asset::create(['property_id' => $other->id, 'created_by' => $this->user->id, 'name' => 'Their tank']);
    fitSensor($this, $this->sensor, $theirs->id)->assertSessionHasErrors('asset_id');

    // Unfitted.
    fitSensor($this, $this->sensor, '')->assertSessionHasNoErrors();
    expect($this->sensor->fresh()->asset_id)->toBeNull();
});

test('alerts call a fitted sensor by its asset name', function () {
    $this->sensor->update(['asset_id' => $this->tank->id]);
    $mail = new TankLow($this->sensor->fresh(), ['percent' => 12, 'litres' => 1200]);

    expect($mail->envelope()->subject)->toBe('Top tank is down to 12%')
        ->and($this->sensor->fresh()->displayName())->toBe('Top tank');
});

test('the asset page shows what its sensor says', function () {
    $this->sensor->update(['asset_id' => $this->tank->id]);
    SensorReading::create(['device_id' => $this->sensor->id, 'metric' => 'distance', 'value' => 1200, 'recorded_at' => now()]);

    ($this->as)()->get(route('assets.show', $this->tank))->assertInertia(fn ($page) => $page
        ->where('sensors.0.name', 'DDS75 #1')
        ->where('sensors.0.status.percent', 50)
        ->where('sensors.0.status.litres', 5000)
        ->where('sensors.0.capacity', 10000)
    );
});

test('the farm view draws gate, tank and pump assets with their sensors, and vegetation', function () {
    Zone::create(['property_id' => $this->property->id, 'name' => 'TCL', 'coordinates' => [[-38.41, 145.02], [-38.41, 145.03], [-38.42, 145.03]]]);
    $this->sensor->update(['asset_id' => $this->tank->id]);
    SensorReading::create(['device_id' => $this->sensor->id, 'metric' => 'distance', 'value' => 1200, 'recorded_at' => now()]);
    $gate = Asset::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Front gate', 'asset_type_id' => $this->gateType->id]);
    AssetLocation::create(['asset_id' => $gate->id, 'latitude' => -38.41, 'longitude' => 145.025]);
    // A gate sensor fitted to nothing is still drawn, where it is.
    Device::create(['property_id' => $this->property->id, 'name' => 'Back gate', 'model' => 'dragino_lds02', 'kind' => 'gate', 'dev_eui' => 'a84041000000aaaa', 'latitude' => -38.415, 'longitude' => 145.025]);
    $gums = Asset::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Gums', 'asset_type_id' => $this->vegetationType->id, 'details' => ['vegetation_type' => 'tree']]);
    AssetLocation::create(['asset_id' => $gums->id, 'shape' => [[-38.411, 145.021], [-38.411, 145.022], [-38.412, 145.022]]]);

    ($this->as)()->get(route('farm-view'))->assertInertia(fn ($page) => $page
        ->has('props', 3)
        ->where('props.0.name', 'Front gate')
        ->where('props.0.monitored', false)
        ->where('props.1.name', 'Top tank')
        ->where('props.1.latitude', -38.412)
        ->where('props.1.percent', 50)
        ->where('props.1.url', route('assets.show', $this->tank))
        ->where('props.2.name', 'Back gate')
        ->where('props.2.monitored', true)
        ->where('vegetation', [[
            'id' => $gums->id,
            'name' => 'Gums',
            'vegetation_type' => 'tree',
            'point' => null,
            'shape' => [[-38.411, 145.021], [-38.411, 145.022], [-38.412, 145.022]],
            'line' => null,
        ]])
    );
});

test('the map shows a fitted sensor where its asset is, by its name', function () {
    $this->sensor->update(['asset_id' => $this->tank->id]);

    ($this->as)()->get(route('map'))->assertInertia(fn ($page) => $page
        ->where('sensors.0.name', 'Top tank')
        ->where('sensors.0.asset_id', $this->tank->id)
        ->where('sensors.0.latitude', -38.412)
        ->where('sensors.0.longitude', 145.022)
    );
});

test('a trough records its size and capacity, and a level sensor fitted to it uses them', function () {
    $trough = Asset::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Hay trough']);
    ($this->as)()->patch(route('assets.update', $trough), [
        'name' => 'Hay trough',
        'asset_type_id' => $this->troughType->id,
        'details' => ['trough_size' => 'rect_2400', 'capacity' => '400'],
    ])->assertSessionHasNoErrors();
    expect($trough->fresh()->details)->toEqual(['trough_size' => 'rect_2400', 'capacity' => 400]);

    ($this->as)()->patch(route('assets.update', $trough), ['name' => 'Hay trough', 'details' => ['trough_size' => 'bathtub']])
        ->assertSessionHasErrors('details.trough_size');

    // A tank (level) sensor can be fitted to it, and its capacity is the trough's.
    fitSensor($this, $this->sensor, $trough->id)->assertSessionHasNoErrors();
    expect(TankActivity::level($this->sensor->fresh(), 1200))->toBe(['percent' => 50.0, 'litres' => 200]);
});

test('the farm view draws troughs at their size', function () {
    Zone::create(['property_id' => $this->property->id, 'name' => 'TCL', 'coordinates' => [[-38.41, 145.02], [-38.41, 145.03], [-38.42, 145.03]]]);
    $this->tank->delete();
    $trough = Asset::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Hay trough', 'asset_type_id' => $this->troughType->id, 'details' => ['trough_size' => 'round_1800']]);
    AssetLocation::create(['asset_id' => $trough->id, 'latitude' => -38.415, 'longitude' => 145.025]);

    ($this->as)()->get(route('farm-view'))->assertInertia(fn ($page) => $page
        ->where('props.0.name', 'Hay trough')
        ->where('props.0.kind', 'trough')
        ->where('props.0.monitored', false)
        ->where('props.0.trough.shape', 'round')
        ->where('props.0.trough.length', 1.8)
    );
});
