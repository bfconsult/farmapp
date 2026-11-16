<?php

use App\Models\FarmJob;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->create(['app_admin' => true]);
});

function memberOf(User $user, Property $property, string $type = Role::ADMIN): void
{
    Role::create(['user_id' => $user->id, 'property_id' => $property->id, 'type' => $type]);
}

test('a test account is deleted with its own property, its jobs and its logins', function () {
    $tester = User::factory()->create(['name' => 'Test Person']);
    $property = Property::create(['name' => 'Test Farm', 'address' => '1 Test Rd']);
    memberOf($tester, $property);
    FarmJob::create(['name' => 'Test job', 'property_id' => $property->id, 'user_id' => $tester->id]);
    $tester->createToken('phone');

    $this->actingAs($this->admin)->getJson(route('app-admin.users.delete-preview', $tester))
        ->assertOk()
        ->assertJson([
            'properties' => [['id' => $property->id, 'name' => 'Test Farm']],
            'records' => ['Jobs' => 1],
            'blocked_by' => [],
        ]);

    $this->actingAs($this->admin)
        ->delete(route('app-admin.users.destroy', $tester), ['confirm_name' => 'Test Person'])
        ->assertRedirect(route('app-admin.report'))
        ->assertSessionHas('success');

    expect(User::find($tester->id))->toBeNull()
        ->and(Property::find($property->id))->toBeNull()
        ->and(FarmJob::count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);
});

test('deleting is blocked while the user has records on a property others belong to', function () {
    $tester = User::factory()->create(['name' => 'Test Person']);
    $farm = Property::create(['name' => 'Real Farm', 'address' => '1 Test Rd']);
    memberOf($this->admin, $farm);
    memberOf($tester, $farm, Role::WORKER);
    FarmJob::create(['name' => 'Real job', 'property_id' => $farm->id, 'user_id' => $tester->id]);

    $this->actingAs($this->admin)->getJson(route('app-admin.users.delete-preview', $tester))
        ->assertJsonPath('blocked_by.0.name', 'Real Farm')
        ->assertJsonPath('blocked_by.0.records.Jobs', 1)
        ->assertJsonPath('properties', []);

    $this->actingAs($this->admin)
        ->delete(route('app-admin.users.destroy', $tester), ['confirm_name' => 'Test Person'])
        ->assertSessionHasErrors('confirm_name');

    expect(User::find($tester->id))->not->toBeNull()
        ->and(FarmJob::count())->toBe(1);
});

test('a member of a shared property with nothing on it can be deleted, leaving the property', function () {
    $tester = User::factory()->create(['name' => 'Test Person']);
    $farm = Property::create(['name' => 'Real Farm', 'address' => '1 Test Rd']);
    memberOf($this->admin, $farm);
    memberOf($tester, $farm, Role::WORKER);

    $this->actingAs($this->admin)
        ->delete(route('app-admin.users.destroy', $tester), ['confirm_name' => 'Test Person'])
        ->assertRedirect(route('app-admin.report'));

    expect(User::find($tester->id))->toBeNull()
        ->and(Property::find($farm->id))->not->toBeNull()
        ->and(Role::where('property_id', $farm->id)->count())->toBe(1);
});

test('deleting needs the user\'s name typed exactly', function () {
    $tester = User::factory()->create(['name' => 'Test Person']);

    $this->actingAs($this->admin)
        ->delete(route('app-admin.users.destroy', $tester), ['confirm_name' => 'Test'])
        ->assertSessionHasErrors('confirm_name');

    expect(User::find($tester->id))->not->toBeNull();
});

test('deactivating keeps their data, frees the email and logs them out', function () {
    $tester = User::factory()->create(['email' => 'tester@example.com']);
    $property = Property::create(['name' => 'Test Farm', 'address' => '1 Test Rd']);
    memberOf($tester, $property);
    FarmJob::create(['name' => 'Test job', 'property_id' => $property->id, 'user_id' => $tester->id]);
    $tester->createToken('phone');

    $this->actingAs($this->admin)
        ->post(route('app-admin.users.deactivate', $tester))
        ->assertRedirect(route('app-admin.report'));

    $tester->refresh();
    expect($tester->deleted)->toBeTrue()
        ->and($tester->email)->toBe('tester@example.com.deleted')
        ->and(FarmJob::count())->toBe(1)
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);
});

test('app admins can\'t remove themselves, and others can\'t remove anyone', function () {
    $this->actingAs($this->admin)->post(route('app-admin.users.deactivate', $this->admin))->assertForbidden();
    $this->actingAs($this->admin)->delete(route('app-admin.users.destroy', $this->admin), ['confirm_name' => $this->admin->name])->assertForbidden();

    $someone = User::factory()->create();
    $this->actingAs($someone)->post(route('app-admin.users.deactivate', $this->admin))->assertStatus(403);
    expect($this->admin->fresh()->deleted)->toBeFalse();
});

test('deactivated accounts are listed separately, to look at', function () {
    User::factory()->create(['name' => 'Gone Person', 'deleted' => true]);
    User::factory()->create(['name' => 'Still Here']);

    $this->actingAs($this->admin)->get(route('app-admin.report'))
        ->assertInertia(fn ($page) => $page
            ->where('deactivated', false)
            ->where('deactivatedCount', 1)
            ->where('users', fn ($users) => collect($users)->pluck('name')->doesntContain('Gone Person'))
        );

    $this->actingAs($this->admin)->get(route('app-admin.report', ['deactivated' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('deactivated', true)
            ->has('users', 1)
            ->where('users.0.name', 'Gone Person')
        );
});
