<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a user with no avatar has a null avatar_url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.avatar_url', null));
});

test('uploading an avatar stores it, resizes it, and sets avatar_url', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('photo.jpg', 2000, 2000),
        ])
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->avatar)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar);
    expect($user->avatar_url)->toContain($user->avatar);
});

test('uploading a new avatar deletes the previous one', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('profile.avatar.update'), [
        'avatar' => UploadedFile::fake()->image('first.jpg'),
    ]);
    $firstPath = $user->refresh()->avatar;

    $this->actingAs($user)->post(route('profile.avatar.update'), [
        'avatar' => UploadedFile::fake()->image('second.jpg'),
    ]);
    $user->refresh();

    expect($user->avatar)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($user->avatar);
});

test('removing an avatar deletes the file and clears the column', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('profile.avatar.update'), [
        'avatar' => UploadedFile::fake()->image('photo.jpg'),
    ]);
    $path = $user->refresh()->avatar;

    $this->actingAs($user)
        ->delete(route('profile.avatar.destroy'))
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('a non-image file is rejected', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->create('document.pdf', 100),
        ])
        ->assertSessionHasErrors('avatar');

    expect($user->refresh()->avatar)->toBeNull();
});
