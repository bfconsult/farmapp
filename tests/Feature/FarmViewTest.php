<?php

use App\Models\Device;
use App\Models\Livestock;
use App\Models\LivestockType;
use App\Models\Mob;
use App\Models\MobZoneHistory;
use App\Models\Property;
use App\Models\PropertyTerrain;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create(['claimed_at' => now()]);
    $this->user->forceFill(['app_admin' => true])->save();
    $this->property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $this->user->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);
});

function farmView($test, ?User $user = null)
{
    return $test->actingAs($user ?? $test->user)
        ->withSession(['current_property_id' => $test->property->id])
        ->get(route('farm-view'));
}

test('it\'s only for app admins while it\'s a prototype', function () {
    $other = User::factory()->create(['claimed_at' => now()]);
    Role::create(['user_id' => $other->id, 'property_id' => $this->property->id, 'type' => Role::ADMIN]);

    farmView($this, $other)->assertForbidden();
    farmView($this)->assertOk();
});

test('it has the paddocks, each mob in its paddock with its head count, and the placed sensors', function () {
    $paddock = Zone::create(['property_id' => $this->property->id, 'name' => 'TCL', 'coordinates' => [[-38.41, 145.02], [-38.41, 145.03], [-38.42, 145.03]]]);
    $sheep = LivestockType::create(['property_id' => $this->property->id, 'name' => 'Sheep']);
    $mob = Mob::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Ewes']);
    MobZoneHistory::create(['mob_id' => $mob->id, 'zone_id' => $paddock->id, 'moved_at' => now(), 'created_by' => $this->user->id]);
    foreach (['active', 'active', 'sold'] as $i => $status) {
        Livestock::create(['property_id' => $this->property->id, 'mob_id' => $mob->id, 'livestock_type_id' => $sheep->id, 'created_by' => $this->user->id, 'tag_number' => "T{$i}", 'status' => $status]);
    }
    // Not in a paddock: nowhere to draw it.
    Mob::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Unplaced']);

    Device::create(['property_id' => $this->property->id, 'name' => 'Gate', 'model' => 'dragino_lds02', 'kind' => 'gate', 'dev_eui' => 'a84041000000aaaa', 'latitude' => -38.415, 'longitude' => 145.025]);
    Device::create(['property_id' => $this->property->id, 'name' => 'Not placed', 'model' => 'dragino_lds02', 'kind' => 'gate', 'dev_eui' => 'a84041000000bbbb']);

    farmView($this)->assertInertia(fn ($page) => $page
        ->component('FarmView')
        ->has('zones', 1)
        ->where('zones.0.name', 'TCL')
        ->where('mobs', [['id' => $mob->id, 'name' => 'Ewes', 'zone_id' => $paddock->id, 'count' => 2, 'type' => 'Sheep', 'sprites' => [['sprite' => 'sheep_white', 'count' => 2]]]])
        ->has('props', 1)
        ->where('props.0.name', 'Gate')
        ->where('props.0.kind', 'gate')
        ->where('props.0.monitored', true)
    );
});

test('which way round the property is shown is saved for it', function () {
    farmView($this)->assertInertia(fn ($page) => $page->where('rotation', 0));

    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->put(route('farm-view.rotation'), ['rotation' => 400])
        ->assertSessionHasErrors('rotation');
    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->put(route('farm-view.rotation'), ['rotation' => 135])
        ->assertSessionHasNoErrors();

    farmView($this)->assertInertia(fn ($page) => $page->where('rotation', 135));
});

// A Terrarium tile that's this many metres high all over.
function terrariumTile(float $metres): string
{
    $value = (int) round(($metres + 32768) * 256);
    $image = imagecreatetruecolor(256, 256);
    imagefill($image, 0, 0, imagecolorallocate($image, ($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF));
    ob_start();
    imagepng($image);

    return ob_get_clean();
}

test('the heights of the ground are fetched for the land and saved', function () {
    Http::fake(['s3.amazonaws.com/elevation-tiles-prod/*' => Http::response(terrariumTile(123.5), 200, ['Content-Type' => 'image/png'])]);
    Zone::create(['property_id' => $this->property->id, 'name' => 'TCL', 'coordinates' => [[-38.41, 145.02], [-38.41, 145.03], [-38.42, 145.03]]]);

    farmView($this)->assertInertia(fn ($page) => $page->where('terrain', null));

    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->post(route('farm-view.terrain'))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('error');

    $terrain = PropertyTerrain::where('property_id', $this->property->id)->firstOrFail();
    expect($terrain->north)->toBeGreaterThan(-38.41)
        ->and($terrain->south)->toBeLessThan(-38.42)
        ->and($terrain->heights)->toHaveCount($terrain->columns * $terrain->rows)
        ->and(array_unique($terrain->heights))->toBe([123.5]);

    farmView($this)->assertInertia(fn ($page) => $page
        ->where('terrain.columns', $terrain->columns)
        ->where('terrainStale', false));

    // A paddock drawn past the heights' edge needs them fetched again.
    Zone::create(['property_id' => $this->property->id, 'name' => 'Far', 'coordinates' => [[-38.50, 145.02], [-38.50, 145.03], [-38.51, 145.03]]]);
    farmView($this)->assertInertia(fn ($page) => $page->where('terrainStale', true));
});

test('a failed fetch of the heights says so', function () {
    Http::fake(['*' => Http::response('', 503)]);
    Zone::create(['property_id' => $this->property->id, 'name' => 'TCL', 'coordinates' => [[-38.41, 145.02], [-38.41, 145.03], [-38.42, 145.03]]]);

    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->post(route('farm-view.terrain'))
        ->assertSessionHas('error');

    expect(PropertyTerrain::count())->toBe(0);
});

test("each mob says how many of its animals are drawn as each sprite: the animal's own, else the mob's, else by type", function () {
    $paddock = Zone::create(['property_id' => $this->property->id, 'name' => 'TCL', 'coordinates' => [[-38.41, 145.02], [-38.41, 145.03], [-38.42, 145.03]]]);
    $cattle = LivestockType::create(['property_id' => $this->property->id, 'name' => 'Cattle']);
    $mob = Mob::create(['property_id' => $this->property->id, 'created_by' => $this->user->id, 'name' => 'Steers']);
    MobZoneHistory::create(['mob_id' => $mob->id, 'zone_id' => $paddock->id, 'created_by' => $this->user->id]);
    foreach (range(1, 3) as $i) {
        Livestock::create(['property_id' => $this->property->id, 'mob_id' => $mob->id, 'livestock_type_id' => $cattle->id, 'created_by' => $this->user->id, 'tag_number' => "C{$i}"]);
    }
    $odd = Livestock::where('tag_number', 'C3')->sole();

    // Picked by type: cattle are black cows.
    farmView($this)->assertInertia(fn ($page) => $page->where('mobs.0.sprites', [['sprite' => 'cow_black', 'count' => 3]]));

    // Set on the mob, then one animal changed on its own.
    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->patch(route('mobs.update', $mob), ['name' => 'Steers', 'sprite' => 'sheep_white'])->assertSessionHasNoErrors();
    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->patch(route('livestock.update', $odd), ['tag_number' => 'C3', 'mob_id' => $mob->id, 'sprite' => 'cow_black'])->assertSessionHasNoErrors();

    farmView($this)->assertInertia(fn ($page) => $page->where('mobs.0.sprites', [
        ['sprite' => 'sheep_white', 'count' => 2],
        ['sprite' => 'cow_black', 'count' => 1],
    ]));

    $this->actingAs($this->user)->withSession(['current_property_id' => $this->property->id])
        ->patch(route('mobs.update', $mob), ['name' => 'Steers', 'sprite' => 'unicorn'])->assertSessionHasErrors('sprite');
});
