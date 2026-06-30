<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\FarmJobController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\WorkSessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\InvitationController;




Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::resource('jobs', FarmJobController::class)->parameters([
    'jobs' => 'farmJob'
]);

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
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
    Route::resource('properties', PropertyController::class)->except(['index', 'show']);
});

// Admin and Manager routes
Route::middleware(['auth', 'property.role:admin,manager'])->group(function () {
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
});

    // All authenticated users with a property
    Route::middleware(['auth'])->group(function () {
    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::resource('jobs', FarmJobController::class)->parameters(['jobs' => 'farmJob']);
    Route::resource('work-sessions', WorkSessionController::class);
    Route::post('work-sessions/{workSession}/stop', [WorkSessionController::class, 'stop'])->name('work-sessions.stop');
    Route::post('jobs/{farmJob}/photos', [PhotoController::class, 'store'])->name('photos.store');
    Route::post('work-sessions/{workSession}/photos', [PhotoController::class, 'storeForSession'])->name('photos.store-session');
    Route::delete('photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::post('select-property', function (\Illuminate\Http\Request $request) {
        $request->validate(['property_id' => 'required|exists:properties,id']);
        session(['current_property_id' => $request->property_id]);
        return back();
    })->name('property.select');
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
        return Inertia::render('Map', ['jobs' => $jobs]);
    })->name('map');
});

Route::middleware('auth')->group(function () {
    Route::resource('properties', PropertyController::class);

    Route::post('select-property', function (\Illuminate\Http\Request $request) {
        $request->validate(['property_id' => 'required|exists:properties,id']);
        session(['current_property_id' => $request->property_id]);
        return back();
    })->name('property.select');

});

// Invitation accept flow (no auth required to view, but process requires auth)
Route::get('invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
Route::post('invitations/{token}', [InvitationController::class, 'process'])->name('invitations.process');

// Admin and Manager can manage invitations
Route::middleware(['auth', 'property.role:admin,manager'])->group(function () {
    Route::get('team', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('team/invite', [InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('team/roles/{role}', [InvitationController::class, 'destroyRole'])->name('invitations.destroy-role');
    Route::delete('team/invitations/{invitation}', [InvitationController::class, 'destroyInvitation'])->name('invitations.destroy-invitation');
});

require __DIR__.'/auth.php';
