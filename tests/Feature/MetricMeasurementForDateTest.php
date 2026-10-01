<?php

use App\Models\Metric;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;

function createPropertyAdminAndMonthlyMetric(): array
{
    $admin = User::factory()->create();
    $property = Property::create(['name' => 'Valle Pacis', 'address' => '1 Test Rd']);
    Role::create(['user_id' => $admin->id, 'property_id' => $property->id, 'type' => Role::ADMIN]);
    $metric = Metric::create([
        'property_id' => $property->id, 'created_by' => $admin->id, 'name' => 'Tractor hours',
        'reporting_period' => Metric::MONTHLY, 'answer_type' => Metric::NUMBER, 'is_active' => true,
    ]);

    return [$admin, $property, $metric];
}

test('picking a date inside a past period redirects to that measurement', function () {
    [$admin, $property, $metric] = createPropertyAdminAndMonthlyMetric();
    $september = $metric->createMeasurement(\Carbon\Carbon::parse('2026-09-01'));
    $october = $metric->createMeasurement(\Carbon\Carbon::parse('2026-10-01'));

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('metrics.measurement-for-date', ['metric' => $metric->id, 'date' => '2026-09-15']))
        ->assertRedirect(route('metric-measurements.show', $september));
});

test('a date with no matching measurement creates one for that calendar month', function () {
    [$admin, $property, $metric] = createPropertyAdminAndMonthlyMetric();
    $metric->createMeasurement(\Carbon\Carbon::parse('2026-10-01'));

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('metrics.measurement-for-date', ['metric' => $metric->id, 'date' => '2026-08-15']));

    $created = $metric->measurements()->where('period_start', '2026-08-01')->first();
    expect($created)->not->toBeNull();
    expect($created->period_end->toDateString())->toBe('2026-08-31');
    expect($created->status)->toBe('incomplete');
});

test('a future date is rejected', function () {
    [$admin, $property, $metric] = createPropertyAdminAndMonthlyMetric();
    $metric->createMeasurement(\Carbon\Carbon::parse('2026-10-01'));

    $this->actingAs($admin)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('metrics.measurement-for-date', ['metric' => $metric->id, 'date' => '2027-01-01']))
        ->assertSessionHasErrors('date');
});

test('a metric belonging to a different property 404s', function () {
    [$admin, $property, $metric] = createPropertyAdminAndMonthlyMetric();
    $otherProperty = Property::create(['name' => 'Other Farm', 'address' => '2 Test Rd']);
    $otherAdmin = User::factory()->create();
    Role::create(['user_id' => $otherAdmin->id, 'property_id' => $otherProperty->id, 'type' => Role::ADMIN]);

    $this->actingAs($otherAdmin)
        ->withSession(['current_property_id' => $otherProperty->id])
        ->get(route('metrics.measurement-for-date', ['metric' => $metric->id, 'date' => '2026-09-15']))
        ->assertNotFound();
});

test('an approver cannot use this - read only role', function () {
    [$admin, $property, $metric] = createPropertyAdminAndMonthlyMetric();
    $approver = User::factory()->create();
    Role::create(['user_id' => $approver->id, 'property_id' => $property->id, 'type' => Role::APPROVER]);

    $this->actingAs($approver)
        ->withSession(['current_property_id' => $property->id])
        ->get(route('metrics.measurement-for-date', ['metric' => $metric->id, 'date' => '2026-09-15']))
        ->assertForbidden();
});
