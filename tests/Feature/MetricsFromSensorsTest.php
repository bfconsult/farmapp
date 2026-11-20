<?php

use App\Models\Device;
use App\Models\LorawanEvent;
use App\Models\Metric;
use App\Models\MetricMeasurement;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Services\Lorawan\UplinkProcessor;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Australia/Adelaide', 'claimed_at' => now()]);
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);
    // 2000 mm when empty, 400 mm when full, 10,000 L: each 16 mm is 100 L.
    $this->tank = Device::create([
        'property_id' => $this->property->id,
        'name' => 'House tank',
        'model' => 'dragino_dds75',
        'kind' => 'tank',
        'dev_eui' => 'a840418f4362f80e',
        'config' => ['settings' => ['empty_distance' => 2000, 'full_distance' => 400, 'capacity' => 10000, 'warn_below_percent' => 0]],
    ]);
    $this->metric = Metric::create([
        'property_id' => $this->property->id,
        'created_by' => $this->user->id,
        'name' => 'House tank volume',
        'reporting_period' => Metric::DAILY,
        'answer_type' => Metric::NUMBER,
        'device_id' => $this->tank->id,
        'is_active' => true,
    ]);
    $this->nov19 = $this->metric->createMeasurement(Carbon::parse('2026-11-19'));
    $this->nov20 = $this->metric->createMeasurement(Carbon::parse('2026-11-20'));
    $this->fCnt = 0;

    // In UTC like the server - see GateActivityTest.
    Carbon::setTestNow(Carbon::parse('2026-11-20 12:00', 'Australia/Adelaide')->utc());
});

afterEach(fn () => Carbon::setTestNow());

function tankReading($test, string $at, int $distance): void
{
    app(UplinkProcessor::class)->process(LorawanEvent::create([
        'property_id' => $test->property->id,
        'event' => 'uplink',
        'dev_eui' => 'a840418f4362f80e',
        'body' => [
            'data' => '0EC4'.sprintf('%04X', $distance).'000CCC01',
            'fCnt' => ++$test->fCnt,
            'fPort' => 2,
            'devEUI' => 'a840418f4362f80e',
            'rxInfo' => [['rssi' => -51, 'loRaSNR' => 12]],
            'timestamp' => Carbon::parse($at, 'Australia/Adelaide')->getTimestamp(),
            'data_encode' => 'hexstring',
        ],
    ]));
}

test('a finished period is filled with the sensor\'s last reading in it, in litres', function () {
    tankReading($this, '2026-11-19 08:00', 720);   // 8,000 L
    tankReading($this, '2026-11-19 23:40', 880);   // 7,000 L - the day's last
    // 12:30am the next day where the property is (still the 19th in UTC).
    tankReading($this, '2026-11-20 00:30', 1200);

    $this->artisan('metrics:fill-from-sensors')->assertSuccessful();

    $nov19 = $this->nov19->fresh();
    expect($nov19->status)->toBe(MetricMeasurement::COMPLETE)
        ->and((float) $nov19->value_number)->toBe(7000.0)
        ->and($nov19->source)->toBe(MetricMeasurement::SENSOR)
        ->and($nov19->completed_by)->toBeNull()
        ->and($nov19->completed_at->equalTo(Carbon::parse('2026-11-19 23:40', 'Australia/Adelaide')))->toBeTrue();

    // Today isn't over yet.
    expect($this->nov20->fresh()->status)->toBe(MetricMeasurement::INCOMPLETE);
});

test('a period with no readings, or a tank with no capacity, is left to fill in by hand', function () {
    $this->artisan('metrics:fill-from-sensors');
    expect($this->nov19->fresh()->status)->toBe(MetricMeasurement::INCOMPLETE);

    $this->tank->update(['config' => ['settings' => ['empty_distance' => 2000, 'full_distance' => 400]]]);
    tankReading($this, '2026-11-19 08:00', 720);
    $this->artisan('metrics:fill-from-sensors');
    expect($this->nov19->fresh()->status)->toBe(MetricMeasurement::INCOMPLETE);
});

test('a measurement someone has changed is never filled over', function () {
    tankReading($this, '2026-11-19 08:00', 720);

    // Opened for a re-measure but not saved yet: still theirs.
    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->patch(route('metric-measurements.update', $this->nov19), ['status' => 'incomplete']);
    $this->artisan('metrics:fill-from-sensors');
    expect($this->nov19->fresh())->status->toBe(MetricMeasurement::INCOMPLETE)->source->toBe(MetricMeasurement::MANUAL);

    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->patch(route('metric-measurements.update', $this->nov19), ['status' => 'complete', 'value_number' => 7500]);
    $this->artisan('metrics:fill-from-sensors');
    expect((float) $this->nov19->fresh()->value_number)->toBe(7500.0);
});

test('reports show a linked metric\'s latest filled value, not today\'s open one', function () {
    tankReading($this, '2026-11-19 08:00', 720);
    $this->artisan('metrics:fill-from-sensors');

    $metric = Metric::forDiaryPeriod($this->property->id, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-20'))->first();
    expect($metric->latestMeasurement->id)->toBe($this->nov19->id);

    // A by-hand metric still shows its current period, filled or not.
    $this->metric->update(['device_id' => null]);
    $metric = Metric::forDiaryPeriod($this->property->id, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-20'))->first();
    expect($metric->latestMeasurement->id)->toBe($this->nov20->id);
});

test('only a number metric can be linked, and only to this property\'s sensors that can record one', function () {
    $save = fn (array $values) => $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->patch(route('metrics.update', $this->metric), [
            'name' => 'House tank volume', 'reporting_period' => 'daily', 'answer_type' => 'number', ...$values,
        ]);

    $gate = Device::create(['property_id' => $this->property->id, 'name' => 'Gate', 'model' => 'dragino_lds02', 'kind' => 'gate', 'dev_eui' => 'a84041000000aaaa']);
    $otherProperty = Property::create(['name' => 'Elsewhere', 'address' => '2 Test Rd']);
    $otherTank = Device::create(['property_id' => $otherProperty->id, 'name' => 'Their tank', 'model' => 'dragino_dds75', 'kind' => 'tank', 'dev_eui' => 'a84041000000bbbb']);

    $save(['device_id' => $gate->id])->assertSessionHasErrors('device_id');
    $save(['device_id' => $otherTank->id])->assertSessionHasErrors('device_id');
    $save(['device_id' => $this->tank->id, 'answer_type' => 'text'])->assertSessionHasErrors('device_id');

    $save(['device_id' => null])->assertSessionHasNoErrors();
    expect($this->metric->fresh()->device_id)->toBeNull();
    $save(['device_id' => $this->tank->id])->assertSessionHasNoErrors();
    expect($this->metric->fresh()->device_id)->toBe($this->tank->id);

    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->get(route('metrics.index'))
        ->assertInertia(fn ($page) => $page->where('sensors', [['id' => $this->tank->id, 'name' => 'House tank', 'records' => 'litres in the tank']]));
});

test('removing the sensor makes the metric a by-hand one again', function () {
    $this->tank->delete();
    expect($this->metric->fresh()->device_id)->toBeNull();
});
