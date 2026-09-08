<?php

use App\Models\Livestock;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createLivestockWithAdmin(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $livestock = Livestock::create(['property_id' => $property->id, 'created_by' => $admin->id, 'tag_number' => 'NLIS001', 'status' => 'active']);

    return [$admin, $property, $livestock];
}

test('an admin can upload a photo for an animal', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$admin, $property, $livestock] = createLivestockWithAdmin();

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('photos.store-livestock', $livestock->id), [
            'photos' => [UploadedFile::fake()->image('ear-tag.jpg')],
        ])
        ->assertRedirect();

    $livestock->refresh();
    expect($livestock->photos)->toHaveCount(1);
    Storage::disk('public')->assertExists($livestock->photos->first()->file);
});

test('a worker can upload a photo for an animal', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [, $property, $livestock] = createLivestockWithAdmin();
    $worker = User::factory()->create();
    Role::create(['user_id' => $worker->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    $this->actingAs($worker)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('photos.store-livestock', $livestock->id), [
            'photos' => [UploadedFile::fake()->image('ear-tag.jpg')],
        ])
        ->assertRedirect();

    expect($livestock->fresh()->photos)->toHaveCount(1);
});

test('an approver cannot upload a photo for an animal', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [, $property, $livestock] = createLivestockWithAdmin();
    $approver = User::factory()->create();
    Role::create(['user_id' => $approver->id, 'property_id' => $property->id, 'type' => Role::APPROVER]);

    $this->actingAs($approver)
        ->withSession(['current_property_id' => $property->id])
        ->post(route('photos.store-livestock', $livestock->id), [
            'photos' => [UploadedFile::fake()->image('ear-tag.jpg')],
        ])
        ->assertForbidden();

    expect($livestock->fresh()->photos)->toHaveCount(0);
});

test('deleting an animal photo removes it from storage', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$admin, $property, $livestock] = createLivestockWithAdmin();
    $path = Storage::disk('public')->putFile('photos', UploadedFile::fake()->image('ear-tag.jpg'));
    $photo = $livestock->photos()->create(['file' => $path, 'time_taken' => now()]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->delete(route('photos.destroy', $photo->id))
        ->assertRedirect();

    expect(\App\Models\Photo::find($photo->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('the livestock show page includes its photos', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    [$admin, $property, $livestock] = createLivestockWithAdmin();
    $path = Storage::disk('public')->putFile('photos', UploadedFile::fake()->image('ear-tag.jpg'));
    $livestock->photos()->create(['file' => $path, 'time_taken' => now()]);

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('livestock.show', $livestock->id))
        ->assertInertia(fn ($page) => $page
            ->has('livestock.photos', 1));
});
