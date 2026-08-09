<?php

use App\Models\DiaryShare;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSession;

test('an admin can download the diary preview as a PDF', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $user->id,
        'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHours(2),
        'status' => WorkSession::FINALISED,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('reports.diary-preview.pdf'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

test('a worker cannot download the diary preview PDF', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::WORKER]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('reports.diary-preview.pdf'))
        ->assertForbidden();
});

test('the preview page carries a pdfUrl for the current date range', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $this->actingAs($user)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('reports.diary-preview', ['date_from' => '2026-06-01', 'date_to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->component('Diary/SharedView')
            ->where('pdfUrl', route('reports.diary-preview.pdf', ['date_from' => '2026-06-01', 'date_to' => '2026-06-30'])));
});

test('anyone with the public share link can download its PDF without logging in', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    WorkSession::create([
        'property_id' => $property->id, 'user_id' => $user->id,
        'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHours(2),
        'status' => WorkSession::FINALISED,
    ]);

    $share = DiaryShare::create([
        'property_id' => $property->id, 'created_by' => $user->id,
        'date_from' => now()->subMonth()->toDateString(), 'date_to' => now()->toDateString(),
    ]);

    $response = $this->get(route('diary.share.pdf', $share->token));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

test('the public share page carries its own pdfUrl and an unknown token 404s', function () {
    $user = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);

    $share = DiaryShare::create([
        'property_id' => $property->id, 'created_by' => $user->id,
        'date_from' => now()->subMonth()->toDateString(), 'date_to' => now()->toDateString(),
    ]);

    $this->get(route('diary.share', $share->token))
        ->assertInertia(fn ($page) => $page
            ->component('Diary/SharedView')
            ->where('pdfUrl', route('diary.share.pdf', $share->token)));

    $this->get(route('diary.share.pdf', 'not-a-real-token'))->assertNotFound();
});
