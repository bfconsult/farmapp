<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\FarmJobController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\WorkSessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ShapeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\HelpMessageController;




Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::resource('jobs', FarmJobController::class)->parameters([
    'jobs' => 'farmJob'
]);

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('help-messages/{key}', [HelpMessageController::class, 'show'])->name('help-messages.show');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('jobs', FarmJobController::class);
    Route::post('jobs/{farmJob}/photos', [PhotoController::class, 'store'])->name('photos.store');
    Route::delete('photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::get('map', function () {
        $currentPropertyId = session('current_property_id');
        
        $jobs = \Illuminate\Support\Facades\Auth::user()->farmJobs()
            ->when($currentPropertyId, function ($query) use ($currentPropertyId) {
                $query->where('property_id', $currentPropertyId);
            })
            ->with(['jobStatus'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
    
        return Inertia::render('Map', [
            'jobs' => $jobs,
        ]);
    })->name('map');
    // Must be registered before the resource() below — otherwise the literal
    // "finalise-and-share" path collides with the resource's GET /work-sessions/{workSession}.
    Route::get('work-sessions/finalise-and-share', [WorkSessionController::class, 'finaliseAndShare'])->name('work-sessions.finalise-and-share');
    Route::post('work-sessions/finalise-and-share', [WorkSessionController::class, 'finaliseAndShareStore'])->name('work-sessions.finalise-and-share.store');
    Route::get('work-sessions/export', [WorkSessionController::class, 'exportIndex'])->name('work-sessions.export');
    Route::get('work-sessions/export/download', [WorkSessionController::class, 'exportDownload'])->name('work-sessions.export.download');
    Route::resource('work-sessions', WorkSessionController::class);
    Route::post('work-sessions/{workSession}/stop', [WorkSessionController::class, 'stop'])->name('work-sessions.stop');
    Route::post('work-sessions/{workSession}/photos', [PhotoController::class, 'storeForSession'])->name('photos.store-session');
});

// added JMB
Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');

// Priorities
Route::post('settings/priorities', [SettingsController::class, 'storePriority'])->name('settings.priorities.store');
Route::patch('settings/priorities/{priority}', [SettingsController::class, 'updatePriority'])->name('settings.priorities.update');
Route::delete('settings/priorities/{priority}', [SettingsController::class, 'destroyPriority'])->name('settings.priorities.destroy');

// Job Types
Route::post('settings/job-types', [SettingsController::class, 'storeJobType'])->name('settings.job-types.store');
Route::patch('settings/job-types/{jobType}', [SettingsController::class, 'updateJobType'])->name('settings.job-types.update');
Route::delete('settings/job-types/{jobType}', [SettingsController::class, 'destroyJobType'])->name('settings.job-types.destroy');

// Job Statuses
Route::post('settings/job-statuses', [SettingsController::class, 'storeJobStatus'])->name('settings.job-statuses.store');
Route::patch('settings/job-statuses/{jobStatus}', [SettingsController::class, 'updateJobStatus'])->name('settings.job-statuses.update');
Route::delete('settings/job-statuses/{jobStatus}', [SettingsController::class, 'destroyJobStatus'])->name('settings.job-statuses.destroy');

// Admin only routes
Route::middleware(['auth', 'property.role:admin'])->group(function () {
    Route::resource('properties', PropertyController::class)->only(['edit', 'update', 'destroy']);
});

// Admin and Manager routes
Route::middleware(['auth', 'property.role:admin,manager'])->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('settings/priorities', [SettingsController::class, 'storePriority'])->name('settings.priorities.store');
    Route::patch('settings/priorities/{priority}', [SettingsController::class, 'updatePriority'])->name('settings.priorities.update');
    Route::delete('settings/priorities/{priority}', [SettingsController::class, 'destroyPriority'])->name('settings.priorities.destroy');
    Route::post('settings/job-types', [SettingsController::class, 'storeJobType'])->name('settings.job-types.store');
    Route::patch('settings/job-types/{jobType}', [SettingsController::class, 'updateJobType'])->name('settings.job-types.update');
    Route::delete('settings/job-types/{jobType}', [SettingsController::class, 'destroyJobType'])->name('settings.job-types.destroy');
    Route::post('settings/job-statuses', [SettingsController::class, 'storeJobStatus'])->name('settings.job-statuses.store');
    Route::patch('settings/job-statuses/{jobStatus}', [SettingsController::class, 'updateJobStatus'])->name('settings.job-statuses.update');
    Route::delete('settings/job-statuses/{jobStatus}', [SettingsController::class, 'destroyJobStatus'])->name('settings.job-statuses.destroy');
    Route::patch('settings/billing-block', [SettingsController::class, 'updateBillingBlock'])->name('settings.billing-block.update');
});

    // All authenticated users with a property
    Route::middleware(['auth'])->group(function () {
    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('properties/create', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::resource('jobs', FarmJobController::class)->parameters(['jobs' => 'farmJob']);
    Route::resource('work-sessions', WorkSessionController::class);
    Route::post('work-sessions/{workSession}/stop', [WorkSessionController::class, 'stop'])->name('work-sessions.stop');
    Route::post('work-sessions/{workSession}/finalise', [WorkSessionController::class, 'finalise'])->name('work-sessions.finalise');
    Route::post('work-sessions/{workSession}/revert-to-draft', [WorkSessionController::class, 'revertToDraft'])->name('work-sessions.revert-to-draft');
    Route::post('jobs/{farmJob}/photos', [PhotoController::class, 'store'])->name('photos.store');
    Route::post('work-sessions/{workSession}/photos', [PhotoController::class, 'storeForSession'])->name('photos.store-session');
    Route::delete('photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::post('select-property', function (\Illuminate\Http\Request $request) {
        $request->validate(['property_id' => 'required|exists:properties,id']);
        session(['current_property_id' => $request->property_id]);
        return back();
    })->name('property.select');
    Route::get('map', function () {
        $user = \Illuminate\Support\Facades\Auth::user();
        $currentPropertyId = session('current_property_id');

        $currentProperty = $currentPropertyId
            ? \App\Models\Property::with('shape')->find($currentPropertyId)
            : null;

        $jobs = $user->farmJobs()
            ->when($currentPropertyId, fn($q) => $q->where('property_id', $currentPropertyId))
            ->with(['jobStatus'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $currentRole = $currentProperty
            ? $user->roleOn($currentProperty)
            : null;

        return Inertia::render('Map', [
            'jobs' => $jobs,
            'shape' => $currentProperty?->shape,
            'currentRole' => $currentRole,
        ]);
    })->name('map');
});

// Invitation accept flow (no auth required to view, but process requires auth)
Route::get('invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
Route::post('invitations/{token}', [InvitationController::class, 'process'])->name('invitations.process');

// Admin and Manager can manage the property boundary
Route::middleware(['auth', 'property.role:admin,manager'])->group(function () {
    Route::get('properties/{property}/shape/edit', [ShapeController::class, 'edit'])->name('shape.edit');
    Route::put('properties/{property}/shape', [ShapeController::class, 'update'])->name('shape.update');
    Route::delete('properties/{property}/shape', [ShapeController::class, 'destroy'])->name('shape.destroy');
});

// Admin and Manager can manage invitations
Route::middleware(['auth', 'property.role:admin,manager'])->group(function () {
    Route::get('team', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('team/invite', [InvitationController::class, 'store'])->name('invitations.store');
    Route::patch('team/roles/{role}', [InvitationController::class, 'updateRole'])->name('invitations.update-role');
    Route::patch('team/roles/{role}/rate', [InvitationController::class, 'updateMemberRate'])->name('invitations.update-member-rate');
    Route::delete('team/roles/{role}', [InvitationController::class, 'destroyRole'])->name('invitations.destroy-role');
    Route::delete('team/invitations/{invitation}', [InvitationController::class, 'destroyInvitation'])->name('invitations.destroy-invitation');
});

require __DIR__.'/auth.php';
