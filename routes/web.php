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
use App\Http\Controllers\NonWorkingZoneController;
use App\Http\Controllers\DiaryShareController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\HelpMessageController;
use App\Http\Controllers\RecurringJobController;
use App\Http\Controllers\MetricController;
use App\Http\Controllers\MetricMeasurementController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\ChecklistTemplateController;
use App\Http\Controllers\ChecklistTemplateItemController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ChecklistItemController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\MaintenanceItemController;
use App\Http\Controllers\NoteController;




Route::get('/', function () {
    return Inertia::render('Welcome');
});

// Served via a route (not a static public/ file) so the icon URLs below can
// resolve through asset(), which points at the CDN on Vapor — a plain public/
// file would 404 there, since Vapor only auto-redirects favicon.ico/robots.txt.
Route::get('manifest.webmanifest', function () {
    return response()->json([
        'name' => 'FieldWerkz',
        'short_name' => 'FieldWerkz',
        'description' => 'Farm job and work session tracking',
        'start_url' => '/work-sessions',
        'scope' => '/',
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#1A5C38',
        'theme_color' => '#1A5C38',
        'icons' => [
            ['src' => asset('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => asset('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('manifest');

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
    Route::get('jobs/{farmJob}/calendar', [FarmJobController::class, 'calendar'])->name('jobs.calendar');
    Route::post('jobs/{farmJob}/finish', [FarmJobController::class, 'finish'])->name('jobs.finish');
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

// Reports: admin/manager get the full ledger, approver gets a read-only
// day-by-day diary (see ReportController::index) - both share the same
// route/URL, just different views for different roles.
Route::middleware(['auth', 'property.role:admin,manager,approver'])->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
});

// Admin and Manager routes
Route::middleware(['auth', 'property.role:admin,manager'])->group(function () {
    Route::post('reports/diary-share', [ReportController::class, 'storeDiaryShare'])->name('reports.diary-share.store');
    Route::get('reports/diary-preview', [ReportController::class, 'previewDiary'])->name('reports.diary-preview');
    Route::get('recurring-jobs', [RecurringJobController::class, 'index'])->name('recurring-jobs.index');
    Route::patch('recurring-jobs/{recurringJob}', [RecurringJobController::class, 'update'])->name('recurring-jobs.update');
    Route::delete('recurring-jobs/{recurringJob}', [RecurringJobController::class, 'destroy'])->name('recurring-jobs.destroy');
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
    Route::post('metrics', [MetricController::class, 'store'])->name('metrics.store');
    Route::patch('metrics/{metric}', [MetricController::class, 'update'])->name('metrics.update');
    Route::delete('metrics/{metric}', [MetricController::class, 'destroy'])->name('metrics.destroy');
    Route::post('checklist-templates', [ChecklistTemplateController::class, 'store'])->name('checklist-templates.store');
    Route::patch('checklist-templates/{checklistTemplate}', [ChecklistTemplateController::class, 'update'])->name('checklist-templates.update');
    Route::delete('checklist-templates/{checklistTemplate}', [ChecklistTemplateController::class, 'destroy'])->name('checklist-templates.destroy');
    Route::post('checklist-templates/{checklistTemplate}/items', [ChecklistTemplateItemController::class, 'store'])->name('checklist-template-items.store');
    Route::patch('checklist-template-items/{checklistTemplateItem}', [ChecklistTemplateItemController::class, 'update'])->name('checklist-template-items.update');
    Route::delete('checklist-template-items/{checklistTemplateItem}', [ChecklistTemplateItemController::class, 'destroy'])->name('checklist-template-items.destroy');
    Route::post('settings/asset-types', [SettingsController::class, 'storeAssetType'])->name('settings.asset-types.store');
    Route::patch('settings/asset-types/{assetType}', [SettingsController::class, 'updateAssetType'])->name('settings.asset-types.update');
    Route::delete('settings/asset-types/{assetType}', [SettingsController::class, 'destroyAssetType'])->name('settings.asset-types.destroy');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::patch('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    Route::put('assets/{asset}/location', [AssetController::class, 'updateLocation'])->name('assets.update-location');
    Route::post('assets/{asset}/maintenance-items', [MaintenanceItemController::class, 'store'])->name('maintenance-items.store');
    Route::patch('maintenance-items/{maintenanceItem}', [MaintenanceItemController::class, 'update'])->name('maintenance-items.update');
    Route::delete('maintenance-items/{maintenanceItem}', [MaintenanceItemController::class, 'destroy'])->name('maintenance-items.destroy');
    Route::patch('notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::put('notes/{note}/location', [NoteController::class, 'updateLocation'])->name('notes.update-location');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
});

// Admin, Manager, and Worker can log measurements - approvers stay
// read-only, so they're excluded from entering/editing a measurement.
Route::middleware(['auth', 'property.role:admin,manager,worker'])->group(function () {
    Route::get('metric-measurements/{metricMeasurement}', [MetricMeasurementController::class, 'show'])->name('metric-measurements.show');
    Route::patch('metric-measurements/{metricMeasurement}', [MetricMeasurementController::class, 'update'])->name('metric-measurements.update');
    Route::post('metric-measurements/{metricMeasurement}/photos', [PhotoController::class, 'storeForMetricMeasurement'])->name('photos.store-metric-measurement');
    Route::post('checklists', [ChecklistController::class, 'store'])->name('checklists.store');
    Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');
    Route::get('checklists/{checklist}', [ChecklistController::class, 'show'])->name('checklists.show');
    Route::patch('checklist-items/{checklistItem}', [ChecklistItemController::class, 'update'])->name('checklist-items.update');
    Route::post('checklist-items/{checklistItem}/photos', [PhotoController::class, 'storeForChecklistItem'])->name('photos.store-checklist-item');
    Route::post('maintenance-items/{maintenanceItem}/convert', [MaintenanceItemController::class, 'convertToJob'])->name('maintenance-items.convert');
    Route::post('notes', [NoteController::class, 'store'])->name('notes.store');
    Route::post('notes/{note}/photos', [PhotoController::class, 'storeForNote'])->name('photos.store-note');
});

// All four roles can reach the Metrics index - it self-adjusts which tabs
// are shown (Measure/Manage are hidden for approver, who only gets View).
Route::middleware(['auth', 'property.role:admin,manager,worker,approver'])->group(function () {
    Route::get('metrics', [MetricController::class, 'index'])->name('metrics.index');
    Route::get('metrics/{metric}/history', [MetricController::class, 'history'])->name('metrics.history');
    Route::get('manage', [ManageController::class, 'index'])->name('manage.index');
    Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::get('assets/{asset}/jobs', [AssetController::class, 'jobHistory'])->name('assets.jobs');
});

    // All authenticated users with a property
    Route::middleware(['auth'])->group(function () {
    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('properties/create', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::delete('properties/{property}/leave', [PropertyController::class, 'leave'])->name('properties.leave');
    Route::resource('jobs', FarmJobController::class)->parameters(['jobs' => 'farmJob']);
    Route::put('jobs/{farmJob}/location', [FarmJobController::class, 'updateLocation'])->name('jobs.update-location');
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
    Route::get('map', function (\Illuminate\Http\Request $request) {
        $user = \Illuminate\Support\Facades\Auth::user();
        $currentPropertyId = session('current_property_id');

        $currentProperty = $currentPropertyId
            ? \App\Models\Property::with(['shape', 'zones'])->find($currentPropertyId)
            : null;

        $currentRole = $currentProperty
            ? $user->roleOn($currentProperty)
            : null;

        // Job status/age filters for the map's Filters popout - default to
        // "active jobs, all time" so a fresh visit looks like it always did.
        // Mirrors FarmJobController::index's can_book_time-based status split.
        $showActiveJobs = $request->boolean('job_status_active', true);
        $showCompletedJobs = $request->boolean('job_status_completed', false);
        $jobAge = $request->input('job_age', 'all');

        $jobStatusIds = collect()
            ->when($showActiveJobs, fn ($ids) => $ids->merge(\App\Models\JobStatus::where('can_book_time', true)->pluck('id')))
            ->when($showCompletedJobs, fn ($ids) => $ids->merge(\App\Models\JobStatus::where('can_book_time', false)->pluck('id')));

        // An approver reviews everyone's work without being added to the
        // team on individual jobs - see every job on the property instead of
        // only ones they're personally assigned to (mirrors the same
        // approver carve-out in FarmJobController::index).
        $jobs = \App\Models\FarmJob::query()
            ->when($currentRole !== \App\Models\Role::APPROVER, fn ($q) => $q->whereHas(
                'assignees',
                fn ($q2) => $q2->where('users.id', $user->id)
            ))
            ->when($currentPropertyId, fn($q) => $q->where('property_id', $currentPropertyId))
            ->whereIn('job_status_id', $jobStatusIds)
            ->when(in_array($jobAge, ['7', '30'], true), fn ($q) => $q->where('created_at', '>=', now()->subDays((int) $jobAge)))
            ->with(['jobStatus'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $assets = \App\Models\Asset::where('property_id', $currentPropertyId)
            ->with(['assetType', 'currentLocation'])
            ->get();

        $notes = \App\Models\Note::where('property_id', $currentPropertyId)
            ->whereNotNull('latitude')
            ->with(['createdBy', 'photos'])
            ->get();

        return Inertia::render('Map', [
            'jobs' => $jobs,
            'shape' => $currentProperty?->shape,
            'zones' => $currentProperty?->zones ?? [],
            'assets' => $assets,
            'notes' => $notes,
            'currentRole' => $currentRole,
            'currentJobStatusActive' => $showActiveJobs,
            'currentJobStatusCompleted' => $showCompletedJobs,
            'currentJobAge' => $jobAge,
        ]);
    })->name('map');
});

// Invitation accept flow (no auth required to view, but process requires auth)
Route::get('invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');
Route::post('invitations/{token}', [InvitationController::class, 'process'])->name('invitations.process');

// Job share link (no auth required - the controller decides whether the
// viewer sees the normal job page or a read-only share view)
Route::get('share/jobs/{token}', [FarmJobController::class, 'share'])->name('jobs.share');

// Diary share link (no auth required - a public read-only day-by-day
// activity report for an approver, see DiaryShareController)
Route::get('share/diary/{token}', [DiaryShareController::class, 'show'])->name('diary.share');

// Admin and Manager can manage the property boundary
Route::middleware(['auth', 'property.role:admin,manager'])->group(function () {
    Route::get('properties/{property}/shape/edit', [ShapeController::class, 'edit'])->name('shape.edit');
    Route::put('properties/{property}/shape', [ShapeController::class, 'update'])->name('shape.update');
    Route::delete('properties/{property}/shape', [ShapeController::class, 'destroy'])->name('shape.destroy');

    // No `non-working-zone.edit` route - it's edited on the same page as the
    // boundary (`shape.edit`), not a separate page.
    Route::put('properties/{property}/non-working-zone', [NonWorkingZoneController::class, 'update'])->name('non-working-zone.update');
    Route::delete('properties/{property}/non-working-zone', [NonWorkingZoneController::class, 'destroy'])->name('non-working-zone.destroy');

    // Paddocks/other named areas - also managed on the boundary page
    // (`shape.edit`), via its Zones tab. Unlike the boundary/non-working
    // zone, a property can have any number, so these are keyed by id.
    Route::post('properties/{property}/zones', [ZoneController::class, 'store'])->name('zones.store');
    Route::put('properties/{property}/zones/{zone}', [ZoneController::class, 'update'])->name('zones.update');
    Route::delete('properties/{property}/zones/{zone}', [ZoneController::class, 'destroy'])->name('zones.destroy');
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
