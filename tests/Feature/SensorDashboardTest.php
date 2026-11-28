<?php

use App\Models\Device;
use App\Models\Property;
use App\Models\Role;
use App\Models\SensorReading;
use App\Models\User;

beforeEach(function () {
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    $this->worker = User::factory()->create(['claimed_at' => now()]);
    $this->role = Role::create(['user_id' => $this->worker->id, 'property_id' => $this->property->id, 'type' => Role::WORKER]);
    $this->as = fn (?User $user = null) => $this->actingAs($user ?? $this->worker)->withSession(['current_property_id' => $this->property->id]);

    $this->tank = Device::create([
        'property_id' => $this->property->id, 'name' => 'Top tank', 'model' => 'dragino_dds75', 'kind' => 'tank', 'dev_eui' => 'a840418f4362f801',
        'config' => ['settings' => ['empty_distance' => 2000, 'full_distance' => 400, 'capacity' => 10000]],
    ]);
    $this->gate = Device::create(['property_id' => $this->property->id, 'name' => 'Front gate', 'model' => 'dragino_lds02', 'kind' => 'gate', 'dev_eui' => 'a840418f4362f802']);
    $this->pump = Device::create(['property_id' => $this->property->id, 'name' => 'Bore pump', 'model' => 'milesight_ct101', 'kind' => 'pump', 'dev_eui' => 'a840418f4362f803']);

    // Half full, falling: 60% three hours ago, 50% now.
    SensorReading::create(['device_id' => $this->tank->id, 'metric' => 'distance', 'value' => 1040, 'recorded_at' => now()->subHours(3)]);
    SensorReading::create(['device_id' => $this->tank->id, 'metric' => 'distance', 'value' => 1200, 'recorded_at' => now()->subMinutes(5)]);
    SensorReading::create(['device_id' => $this->gate->id, 'metric' => 'door_open', 'value' => 1, 'recorded_at' => now()->subMinutes(20)]);
    SensorReading::create(['device_id' => $this->pump->id, 'metric' => 'current', 'value' => 4.2, 'recorded_at' => now()->subMinutes(2)]);
});

test('everyone on the property sees a tile for each tank, gate and pump', function () {
    ($this->as)()->get(route('manage.sensor-dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Manage/SensorDashboard')
            ->where('canManage', false)
            ->has('tiles', 3)
            ->where('tiles', fn ($tiles) => collect($tiles)->pluck('kind')->sort()->values()->all() === ['gate', 'pump', 'tank']));
});

test('a tank tile has its level, litres and which way it\'s heading', function () {
    ($this->as)()->get(route('manage.sensor-dashboard'))->assertInertia(fn ($page) => $page
        ->where('tiles', function ($tiles) {
            $tank = collect($tiles)->firstWhere('kind', 'tank');

            return $tank['percent'] == 50 && $tank['litres'] === 5000 && $tank['capacity'] == 10000
                && $tank['reporting'] === true && $tank['change_per_hour'] < 0 && count($tank['levels']) === 2;
        }));
});

test('gate and pump tiles have their state and a week by day', function () {
    ($this->as)()->get(route('manage.sensor-dashboard'))->assertInertia(fn ($page) => $page
        ->where('tiles', function ($tiles) {
            $gate = collect($tiles)->firstWhere('kind', 'gate');
            $pump = collect($tiles)->firstWhere('kind', 'pump');

            return $gate['open'] === true && $gate['reporting'] === true && count($gate['openings_by_day']) === 7
                && $pump['running'] === true && $pump['current'] == 4.2 && count($pump['seconds_by_day']) === 7;
        }));
});

test('each person\'s layout is their own, in the tile sizes', function () {
    ($this->as)()->put(route('manage.sensor-dashboard.layout'), ['layout' => [
        ['i' => (string) $this->tank->id, 'x' => 0, 'y' => 0, 'w' => 2, 'h' => 2],
        // Tall: small with a calendar under it.
        ['i' => (string) $this->gate->id, 'x' => 2, 'y' => 0, 'w' => 1, 'h' => 2],
    ]])->assertSessionHasNoErrors();

    expect($this->role->fresh()->sensor_dashboard)->toEqual([
        ['i' => (string) $this->tank->id, 'x' => 0, 'y' => 0, 'w' => 2, 'h' => 2],
        ['i' => (string) $this->gate->id, 'x' => 2, 'y' => 0, 'w' => 1, 'h' => 2],
    ]);

    $manager = User::factory()->create(['claimed_at' => now()]);
    Role::create(['user_id' => $manager->id, 'property_id' => $this->property->id, 'type' => Role::MANAGER]);
    ($this->as)($manager)->get(route('manage.sensor-dashboard'))->assertInertia(fn ($page) => $page
        ->where('layout', [])
        ->where('canManage', true));

    ($this->as)()->put(route('manage.sensor-dashboard.layout'), ['layout' => [
        ['i' => 'x', 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 1],
    ]])->assertSessionHasErrors('layout.0.w');
});

test('the Manage page links to it for everyone when there are sensors with tiles', function () {
    ($this->as)()->get(route('manage.index'))->assertInertia(fn ($page) => $page->where('dashboardSensorsCount', 3));
});

test('each tile has a calendar from the Monday four weeks back to today, with the day\'s activity', function () {
    ($this->as)()->get(route('manage.sensor-dashboard'))->assertInertia(fn ($page) => $page
        ->where('tiles', function ($tiles) {
            $today = now($this->worker->displayTimezone())->toDateString();
            foreach ($tiles as $tile) {
                $first = \Illuminate\Support\Carbon::parse($tile['calendar'][0]['date']);
                if (! $first->isMonday() || end($tile['calendar'])['date'] !== $today || count($tile['calendar']) < 29) {
                    return false;
                }
            }
            $tank = collect($tiles)->firstWhere('kind', 'tank');
            $gate = collect($tiles)->firstWhere('kind', 'gate');

            // The tank's lowest level today; the gate's one opening.
            return end($tank['calendar'])['value'] == 50 && end($gate['calendar'])['value'] == 1;
        }));
});
