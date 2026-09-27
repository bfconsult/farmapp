<?php

use App\Models\Asset;
use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;

function createPropertyAdminAndAsset(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $asset = Asset::create(['property_id' => $property->id, 'created_by' => $admin->id, 'name' => 'Tractor']);

    return [$admin, $property, $asset];
}

test('the edit page is given the property\'s assets and the job\'s current asset id', function () {
    [$admin, $property, $asset] = createPropertyAdminAndAsset();
    $job = FarmJob::create(['name' => 'Service the tractor', 'property_id' => $property->id, 'user_id' => $admin->id, 'asset_id' => $asset->id]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('jobs.edit', $job))
        ->assertInertia(fn ($page) => $page
            ->component('Jobs/Edit')
            ->where('job.asset_id', $asset->id)
            ->where('assets.0.id', $asset->id)
        );
});

test('editing a job to link an asset attaches it', function () {
    [$admin, $property, $asset] = createPropertyAdminAndAsset();
    $job = FarmJob::create(['name' => 'Service the tractor', 'property_id' => $property->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('jobs.update', $job), [
            'name' => 'Service the tractor',
            'asset_id' => $asset->id,
        ])
        ->assertSessionHasNoErrors();

    expect($job->fresh()->asset_id)->toBe($asset->id);
});

test('editing a job to remove its asset link clears it', function () {
    [$admin, $property, $asset] = createPropertyAdminAndAsset();
    $job = FarmJob::create(['name' => 'Service the tractor', 'property_id' => $property->id, 'user_id' => $admin->id, 'asset_id' => $asset->id]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('jobs.update', $job), [
            'name' => 'Service the tractor',
            'asset_id' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($job->fresh()->asset_id)->toBeNull();
});

test('an asset belonging to a different property is rejected', function () {
    [$admin, $property] = createPropertyAdminAndAsset();
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    $otherAsset = Asset::create(['property_id' => $otherProperty->id, 'created_by' => $admin->id, 'name' => 'Someone Else\'s Tractor']);
    $job = FarmJob::create(['name' => 'Service the tractor', 'property_id' => $property->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->patch(route('jobs.update', $job), [
            'name' => 'Service the tractor',
            'asset_id' => $otherAsset->id,
        ])
        ->assertSessionHasErrors('asset_id');

    expect($job->fresh()->asset_id)->toBeNull();
});
