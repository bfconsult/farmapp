<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\Property;
use App\Models\RecurringJob;
use App\Models\Role;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WorkSessionController extends Controller
{
    public function index(Request $request)
    {
        $currentPropertyId = session('current_property_id');
        [$dateFrom, $dateTo] = $this->parseDateRange($request);
        $showDraft = $request->has('status_draft') ? $request->boolean('status_draft') : true;
        $showFinalised = $request->has('status_finalised') ? $request->boolean('status_finalised') : true;

        $includedStatuses = [
            ...($showDraft ? [WorkSession::DRAFT] : []),
            ...($showFinalised ? [WorkSession::FINALISED, WorkSession::APPROVED] : []),
        ];

        $sessions = Auth::user()->workSessions()
            ->when($currentPropertyId, function ($query) use ($currentPropertyId) {
                $query->where('property_id', $currentPropertyId);
            })
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->when(!($showDraft && $showFinalised), fn ($query) => $query->whereIn('status', $includedStatuses))
            ->with(['farmJob', 'property', 'user'])
            ->latest('started_at')
            ->get()
            ->map(function ($session) {
                $session->duration_in_hours = $session->duration_in_hours;
                $session->billing_amount = $session->billing_amount;
                // Only a draft can still be finalised, so that's the only
                // state where a conflict is actionable - skip the query for
                // everything else.
                $session->has_conflict = $session->status === WorkSession::DRAFT
                    && $session->overlapsFinalisedSession();
                return $session;
            });

        return Inertia::render('WorkSessions/Index', [
            'sessions' => $sessions,
            // Not date-filtered - a session still running "right now" is
            // shown regardless of which historical range is selected below.
            // Deliberately not scoped to the current property - a session
            // left running on a property the user has since switched away
            // from is still theirs to stop (see stop()'s matching check).
            'activeSession' => Auth::user()->workSessions()
                ->whereNull('ended_at')
                ->with(['farmJob', 'property'])
                ->first(),
            // Property-wide, not scoped by the date/status filters below -
            // distinguishes "never logged a session here" from "none match
            // the current filter", same as FarmJobController's hasAnyJobs.
            'hasAnySessions' => Auth::user()->workSessions()
                ->when($currentPropertyId, function ($query) use ($currentPropertyId) {
                    $query->where('property_id', $currentPropertyId);
                })
                ->exists(),
            'currentDateFrom' => $dateFrom->toDateString(),
            'currentDateTo' => $dateTo->toDateString(),
            'currentStatusDraft' => $showDraft,
            'currentStatusFinalised' => $showFinalised,
        ]);
    }

    public function create()
    {
        // Note: reaching this form doesn't itself require the absence of an active
        // session — logging an already-completed past session (both start and end
        // time set) is allowed alongside one; only starting another *open* one isn't
        // (enforced in store()).
        $currentPropertyId = session('current_property_id');

        $plannedJobs = $this->bookableJobs($currentPropertyId);

        return Inertia::render('WorkSessions/Create', [
            'plannedJobs' => $plannedJobs,
            'assets' => Asset::where('property_id', $currentPropertyId)->orderBy('name')->get(),
            'billingBlockMinutes' => Auth::user()->billing_block_minutes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'nullable|string',
            'farm_job_id' => 'nullable|exists:farm_jobs,id',
            'asset_id' => 'nullable|exists:assets,id',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date|after:started_at',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // A session logged with an end time already set (e.g. forgotten past work being
        // added after the fact) isn't "active", so it doesn't conflict with one that is.
        if (empty($validated['ended_at']) && ($activeSession = $this->activeSession())) {
            return redirect()->route('work-sessions.show', $activeSession)
                ->with('error', 'You already have an active work session. Stop it before starting a new one.');
        }

        $validated['user_id'] = Auth::id();
        $validated['property_id'] = session('current_property_id');
        $validated['started_at'] = $validated['started_at']
            ?? Auth::user()->floorToBillingBlock(now());
        $validated['status'] = WorkSession::DRAFT;

        $session = WorkSession::create($validated);

        if ($session->farm_job_id) {
            $this->promoteJobToInProgress($session->farm_job_id);
        }

        // A session created with its end time already set is being logged
        // after the fact (never "in progress") - Edit is the useful next
        // stop for filling in the job/description, not the live Show view.
        // The newPastSession flash flags it as not "really" existing yet -
        // Edit.jsx uses it to delete instead of merely navigating away if
        // the user backs out without saving, so the placeholder 6am-3pm
        // entry they never asked to keep doesn't linger in their history.
        return $session->ended_at
            ? redirect()->route('work-sessions.edit', $session)->with('newPastSession', true)
            : redirect()->route('work-sessions.show', $session);
    }

    public function show(Request $request, WorkSession $workSession)
    {
        if ($workSession->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        // An auto-tracked visit hasn't had a human look at it yet - send
        // straight to Edit instead of the normal Show page until it's been
        // saved through that form once (see update() below, which sets
        // reviewed_at). Guarded on status === DRAFT too, since edit() itself
        // redirects non-draft sessions back to show() - without that guard,
        // a non-draft session that somehow never got reviewed would bounce
        // between the two forever.
        if ($workSession->source === 'auto_tracked'
            && !$workSession->reviewed_at
            && $workSession->status === WorkSession::DRAFT) {
            return redirect()->route('work-sessions.edit', $workSession);
        }

        $workSession->load(['farmJob', 'asset', 'property.zones', 'photos', 'user', 'waypoints', 'notes.photos', 'notes.createdBy', 'notes.views']);
        $workSession->has_conflict = $workSession->status === WorkSession::DRAFT
            && $workSession->overlapsFinalisedSession();
        $workSession->notes->each(fn ($note) => $note->is_unread = $note->isUnreadBy(Auth::id()));

        // Only the in-progress view offers the inline "Start Time" / "Pick a
        // Job" cards (see Show.jsx) - skip building this for every finished
        // session, which is the far more common case.
        $plannedJobs = null;
        if (!$workSession->ended_at) {
            $plannedJobs = $this->bookableJobs($workSession->property_id);
            if ($workSession->farm_job_id && !$plannedJobs->contains('id', $workSession->farm_job_id)) {
                $plannedJobs->push($workSession->farmJob);
            }
        }

        return Inertia::render('WorkSessions/Show', [
            'session' => $workSession,
            'durationInHours' => $workSession->duration_in_hours,
            'billingAmount' => $workSession->billing_amount,
            'waypoints' => $workSession->waypoints,
            'zones' => $workSession->property->zones,
            'plannedJobs' => $plannedJobs,
            'billingBlockMinutes' => Auth::user()->billing_block_minutes,
            // Reached via Manage -> Work Sessions rather than the self-service
            // Work tab - carried through finalise/stop/revert's redirects (see
            // redirectToSession() below) so "Back" keeps pointing at Manage
            // instead of defaulting back to Work.
            'from' => $this->cameFromManage($request),
        ]);
    }

    private function cameFromManage(Request $request): ?string
    {
        return $request->query('from') === 'manage' ? 'manage' : null;
    }

    public function edit(WorkSession $workSession)
    {
        if ($workSession->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        if ($workSession->status !== WorkSession::DRAFT) {
            return redirect()->route('work-sessions.show', $workSession)
                ->with('error', 'Only draft work sessions can be edited.');
        }

        $plannedJobs = $this->bookableJobs($workSession->property_id);

        // The job already linked to this session should stay selectable even
        // if it's since been closed - otherwise editing a session would
        // silently drop a perfectly valid existing link just because the
        // job moved on since the session was logged.
        if ($workSession->farm_job_id && !$plannedJobs->contains('id', $workSession->farm_job_id)) {
            $plannedJobs->push($workSession->farmJob);
        }

        return Inertia::render('WorkSessions/Edit', [
            'session' => $workSession,
            'plannedJobs' => $plannedJobs,
            'assets' => Asset::where('property_id', $workSession->property_id)->orderBy('name')->get(),
            'waypoints' => $workSession->waypoints,
            'zones' => $workSession->property->zones,
            'billingBlockMinutes' => Auth::user()->billing_block_minutes,
        ]);
    }

    public function update(Request $request, WorkSession $workSession)
    {
        if ($workSession->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        if ($workSession->status !== WorkSession::DRAFT) {
            abort(403, 'Only draft work sessions can be edited.');
        }

        $validated = $request->validate([
            'description' => 'nullable|string',
            'farm_job_id' => 'nullable|exists:farm_jobs,id',
            'asset_id' => 'nullable|exists:assets,id',
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after:started_at',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if (empty($validated['ended_at']) && ($activeSession = $this->activeSession())
            && $activeSession->isNot($workSession)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ended_at' => 'You already have another active work session. Stop it first.',
            ]);
        }

        $workSession->update([...$validated, 'reviewed_at' => $workSession->reviewed_at ?? now()]);

        if ($workSession->farm_job_id) {
            $this->promoteJobToInProgress($workSession->farm_job_id);
        }

        return redirect()->route('work-sessions.show', $workSession);
    }

    public function destroy(Request $request, WorkSession $workSession)
    {
        if ($workSession->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        $workSession->delete();

        // Unlike stop()/finalise() (back() to the same still-existing Show
        // page), the session itself is gone here - there's no page left to
        // return to, so route straight to whichever list "from" says this
        // delete came from instead. See cameFromManage().
        return $this->cameFromManage($request) === 'manage'
            ? redirect()->route('manage.work-sessions')
            : redirect()->route('work-sessions.index');
    }

    public function stop(WorkSession $workSession)
    {
        // Deliberately checks property membership, not a match against the
        // currently selected property - a session left running on a
        // property the user has since switched away from must still be
        // stoppable from wherever they currently are (see index()'s
        // activeSession, which surfaces it regardless of the current
        // selection). Still bars stopping a session on a property this
        // user has no role on at all.
        abort_unless(
            Auth::user()->properties()->where('properties.id', $workSession->property_id)->exists(),
            404
        );

        $workSession->update(['ended_at' => now()]);

        // Only ever posted from the session's own Show page, so the browser's
        // Referer (which back() reads) is always that same page - including
        // its ?from=manage marker, if it has one. See show()/cameFromManage().
        return back();
    }

    /**
     * Drops the recorded location trail once the user's done referring to it
     * - doesn't touch the session itself (times, status, etc.), just the
     * waypoint rows.
     */
    public function destroyWaypoints(WorkSession $workSession)
    {
        if ($workSession->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        $workSession->waypoints()->delete();

        return back()->with('success', 'Path deleted.');
    }

    public function finalise(WorkSession $workSession)
    {
        if ($workSession->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        if (!$workSession->ended_at) {
            abort(403, 'Stop the session before finalising it.');
        }

        if ($workSession->status !== WorkSession::DRAFT) {
            abort(403, 'Only draft work sessions can be finalised.');
        }

        if ($workSession->overlapsFinalisedSession()) {
            return back()->with('error', 'Session time conflicts with an existing session - please resolve before finalising.');
        }

        $workSession->update(['status' => WorkSession::FINALISED]);

        // Only ever posted from the session's own Show page - see stop() above.
        return back();
    }

    public function revertToDraft(Request $request, WorkSession $workSession)
    {
        if ($workSession->property_id !== (int) session('current_property_id')) {
            abort(404);
        }

        // Only a finalised session can go back to draft — once approved, it's locked in.
        if ($workSession->status !== WorkSession::FINALISED) {
            abort(403, 'Only finalised work sessions can be reverted to draft.');
        }

        $workSession->update(['status' => WorkSession::DRAFT]);

        // Unlike finalise()/stop(), this is also posted from the Manage review
        // list (not just the session's own Show page) - deliberately *navigates
        // to* Show either way, rather than back()'ing to wherever it was
        // clicked from, so there's somewhere to add a note explaining the
        // revert. Still needs from threaded through explicitly (not via
        // Referer) so the Show page that results knows which Back it came
        // from - see cameFromManage() and the two call sites in the frontend.
        $from = $this->cameFromManage($request);

        // work-sessions.show is the resource route, whose parameter is
        // {work_session} (snake_case) - unlike the explicit routes above
        // (finalise/stop/revert-to-draft itself), which all use {workSession}.
        // The single-value form below sidesteps needing to know the name at
        // all, but an associative array has to match it exactly or Laravel
        // throws a missing-parameter exception.
        return redirect()->route('work-sessions.show', $from ? ['work_session' => $workSession, 'from' => $from] : $workSession);
    }

    /**
     * Admin/manager-only visibility across every worker's sessions for the
     * property, grouped by worker then date - lets them spot a finalised
     * session that's wrong and unfinalise it (see revertToDraft() above),
     * which redirects to that session's Show page so a note explaining why
     * can be added there.
     */
    public function reviewIndex(Request $request)
    {
        $currentPropertyId = session('current_property_id');
        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $sessions = WorkSession::where('property_id', $currentPropertyId)
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->with(['farmJob', 'user', 'createdBy'])
            ->orderBy('started_at')
            ->get()
            ->map(function ($session) {
                $session->duration_in_hours = $session->duration_in_hours;
                return $session;
            });

        $workers = $sessions->groupBy('user_id')
            ->map(fn ($userSessions) => [
                'user' => $userSessions->first()->user,
                'sessions' => $userSessions->values(),
            ])
            ->sortBy(fn ($worker) => $worker['user']->name)
            ->values();

        // For the "log time for a worker" form - everyone with a role here,
        // not just people who already have sessions in this date range.
        $teamMembers = Role::where('property_id', $currentPropertyId)
            ->with('user')
            ->get()
            ->map(fn ($role) => ['id' => $role->user_id, 'name' => $role->user->name, 'type' => $role->type])
            ->sortBy('name')
            ->values();

        return Inertia::render('WorkSessions/Review', [
            'workers' => $workers,
            'teamMembers' => $teamMembers,
            'bookableJobs' => $this->propertyBookableJobs($currentPropertyId),
            'currentDateFrom' => $dateFrom->toDateString(),
            'currentDateTo' => $dateTo->toDateString(),
        ]);
    }

    /**
     * Logs a complete (already-finished) work session on behalf of someone
     * else - for a team member who may never use the app themselves, or
     * just to fix up a missed entry. Deliberately separate from store():
     * that hardcodes user_id to the actor, gates on the *actor's* own
     * active session, and floors the start time to the *actor's* billing
     * block - none of which is right here. Both start and end are always
     * required (retroactive complete entries only, never "start tracking
     * for someone else").
     */
    public function storeForWorker(Request $request)
    {
        $propertyId = (int) session('current_property_id');

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('roles', 'user_id')->where('property_id', $propertyId)],
            'farm_job_id' => ['nullable', Rule::exists('farm_jobs', 'id')->where('property_id', $propertyId)],
            'asset_id' => ['nullable', Rule::exists('assets', 'id')->where('property_id', $propertyId)],
            'description' => 'nullable|string',
            'started_at' => 'required|date',
            'ended_at' => 'required|date|after:started_at',
        ]);

        // A worker can't be in two places at once, and a session that
        // overlaps an existing one could never be finalised anyway (see
        // overlapsFinalisedSession()) - reject it now with a clear message
        // rather than creating a draft that can never go anywhere.
        if (WorkSession::overlapExistsFor($validated['user_id'], $validated['started_at'], $validated['ended_at'])) {
            throw ValidationException::withMessages([
                'started_at' => 'That time overlaps a session this person already has.',
            ]);
        }

        $session = WorkSession::create([
            ...$validated,
            'property_id' => $propertyId,
            'created_by' => Auth::id(),
            'status' => WorkSession::DRAFT,
            'source' => 'manual_on_behalf',
            // A human deliberately entered this, authoritatively - there's
            // nothing left to review the way an auto-tracked visit needs.
            'reviewed_at' => now(),
        ]);

        if ($session->farm_job_id) {
            $this->promoteJobToInProgress($session->farm_job_id);
        }

        return back()->with('success', 'Time logged.');
    }

    /**
     * All bookable jobs on the property, for the "log time for a worker"
     * form - unlike bookableJobs() (scoped to the current user's own
     * assignments for self-service clocking in), a manager/admin/approver
     * entering retroactive time is authoritative about what was worked on,
     * so assignment isn't a restriction here.
     */
    private function propertyBookableJobs(int $propertyId)
    {
        return FarmJob::where('property_id', $propertyId)
            ->where(function ($query) {
                $query->whereHas('jobStatus', fn ($q) => $q->where('can_book_time', true))
                    ->orWhereNull('job_status_id');
            })
            ->orderBy('name')
            // FarmJob always appends effective_date on serialization, which
            // falls back to created_at - selecting only id/name left that
            // null and crashed the accessor, so it has to come along too.
            ->get(['id', 'name', 'created_at']);
    }

    public function finaliseAndShare(Request $request)
    {
        $currentPropertyId = session('current_property_id');
        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        // Only sessions that are actually eligible to be finalised (stopped, still draft).
        $sessions = Auth::user()->workSessions()
            ->where('status', WorkSession::DRAFT)
            ->whereNotNull('ended_at')
            ->when($currentPropertyId, fn ($query) => $query->where('property_id', $currentPropertyId))
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->with('farmJob')
            ->latest('started_at')
            ->get()
            ->map(function ($session) {
                $session->duration_in_hours = $session->duration_in_hours;
                $session->billing_amount = $session->billing_amount;
                $session->has_conflict = $session->overlapsFinalisedSession();
                return $session;
            });

        return Inertia::render('WorkSessions/FinaliseAndShare', [
            'sessions' => $sessions,
            'currentDateFrom' => $dateFrom->toDateString(),
            'currentDateTo' => $dateTo->toDateString(),
        ]);
    }

    public function exportIndex(Request $request)
    {
        $currentPropertyId = session('current_property_id');
        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $exportable = $this->exportableSessionsQuery($currentPropertyId, $dateFrom, $dateTo)->get();

        $draftCount = Auth::user()->workSessions()
            ->where('status', WorkSession::DRAFT)
            ->whereNotNull('ended_at')
            ->when($currentPropertyId, fn ($query) => $query->where('property_id', $currentPropertyId))
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->count();

        return Inertia::render('WorkSessions/Export', [
            'currentDateFrom' => $dateFrom->toDateString(),
            'currentDateTo' => $dateTo->toDateString(),
            'draftCount' => $draftCount,
            'exportSummary' => [
                'count' => $exportable->count(),
                'hours' => round($exportable->sum('duration_in_hours'), 2),
                'billing' => round($exportable->sum('billing_amount'), 2),
            ],
        ]);
    }

    public function exportDownload(Request $request)
    {
        $currentPropertyId = session('current_property_id');
        [$dateFrom, $dateTo] = $this->parseDateRange($request);
        $rateMode = $request->rate === 'billing' ? 'billing' : 'time';

        $property = $currentPropertyId ? Property::find($currentPropertyId) : null;

        // Filtered down to only the fields actually filled in, so both the
        // PDF view and the Excel header below can just print whatever's
        // here without each needing their own blank-field handling - a
        // property with none of this set (the common case, since it's all
        // optional) ends up with an empty array and no header block at all.
        $billingDetails = collect([
            'Company/Business Name' => $property?->billing_company_name,
            'ABN' => $property?->billing_abn,
            'Address' => $property?->billing_address,
            'Phone' => $property?->billing_phone,
            'Contact' => $property?->billing_contact_name,
        ])->filter()->all();

        // Who this timesheet is *from*, if the exporting worker bills
        // through a linked Supplier (see Role::supplier()) rather than as an
        // individual - the billing_* fields are preferred (same convention
        // as $billingDetails above) but fall back to the supplier's plain
        // name/street_address/phone, since a "labour supplier" added just to
        // carry a name often never gets its dedicated billing fields filled
        // in (see SupplierController@show's "Billing via" feature).
        $exportingSupplier = Role::where('user_id', Auth::id())
            ->where('property_id', $currentPropertyId)
            ->first()?->supplier;

        $fromLine = null;
        if ($exportingSupplier) {
            $fromParts = collect([
                $exportingSupplier->billing_company_name ?: $exportingSupplier->name,
                $exportingSupplier->billing_address ?: $exportingSupplier->street_address,
                $exportingSupplier->billing_contact_name ?: ($exportingSupplier->billing_phone ?: $exportingSupplier->phone),
            ])->filter()->implode(', ');
            $fromLine = $fromParts ? "From: {$fromParts}" : null;
        }

        $sessions = $this->exportableSessionsQuery($currentPropertyId, $dateFrom, $dateTo)->get();

        // started_at/ended_at are UTC in the DB. The React UI never needs an
        // explicit conversion here - the browser's own Date/toLocaleTimeString
        // does it for free - but there's no browser for a server-rendered
        // PDF/Excel export, so it must be converted explicitly or it prints
        // raw UTC wall-clock time.
        $timezone = $request->user()->displayTimezone();

        $rows = $sessions->map(fn ($session) => [
            'date' => $session->started_at->clone()->setTimezone($timezone)->format('d/m/Y'),
            'job' => $session->farmJob?->name ?? 'Ad-hoc',
            'start' => $session->started_at->clone()->setTimezone($timezone)->format('H:i'),
            'end' => $session->ended_at?->clone()->setTimezone($timezone)->format('H:i') ?? '—',
            'duration' => $session->duration_in_hours,
            'amount' => $session->billing_amount,
        ]);

        $totalHours = round($rows->sum('duration'), 2);
        $totalBilling = round($rows->sum('amount'), 2);
        $filename = "work-sessions_{$dateFrom->toDateString()}_{$dateTo->toDateString()}";

        if ($request->format === 'pdf') {
            // Vapor never inspects Content-Type to decide whether a response
            // needs base64 encoding for API Gateway - only this header does
            // (see the Excel branch below for the failure mode when it's
            // missing). This PDF has stayed accidentally safe so far only
            // because a plain table with no embedded images/fonts happens to
            // produce a byte stream that survives as valid UTF-8 - one logo
            // added to this template would break it the same way.
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.work-sessions', [
                'rows' => $rows,
                'rateMode' => $rateMode,
                'dateFrom' => $dateFrom->toDateString(),
                'dateTo' => $dateTo->toDateString(),
                'totalHours' => $totalHours,
                'totalBilling' => $totalBilling,
                'billingDetails' => $billingDetails,
                'fromLine' => $fromLine,
            ])->download("{$filename}.pdf")->header('X-Vapor-Base64-Encode', 'True');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $rowNumber = 1;
        foreach ($billingDetails as $label => $value) {
            $sheet->setCellValue("A{$rowNumber}", "{$label}: {$value}");
            if ($label === 'Company/Business Name') {
                $sheet->getStyle("A{$rowNumber}")->getFont()->setBold(true)->setSize(14);
            }
            $rowNumber++;
        }
        if ($fromLine) {
            $sheet->setCellValue("A{$rowNumber}", $fromLine);
            $rowNumber++;
        }
        if ($billingDetails || $fromLine) {
            $rowNumber++; // blank row separating the billing header from the table
        }

        $headers = ['Date', 'Job', 'Start', 'End', 'Hours'];
        if ($rateMode === 'billing') {
            $headers[] = 'Amount (Ex GST)';
        }
        $sheet->fromArray($headers, null, "A{$rowNumber}");
        $rowNumber++;

        foreach ($rows as $row) {
            $line = [$row['date'], $row['job'], $row['start'], $row['end'], $row['duration']];
            if ($rateMode === 'billing') {
                $line[] = $row['amount'];
            }
            $sheet->fromArray($line, null, "A{$rowNumber}");
            $rowNumber++;
        }

        $totalRow = ['', 'Total', '', '', $totalHours];
        if ($rateMode === 'billing') {
            $totalRow[] = $totalBilling;
        }
        $sheet->fromArray($totalRow, null, "A{$rowNumber}");

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        // Not streamDownload(): that returns a StreamedResponse, which
        // needs to progressively write its body during the request - a
        // model Lambda can't actually support (the whole response has to
        // go back as one payload regardless), and Vapor's runtime bridge
        // for it was failing outright in production with a bare
        // {"message":"Internal server error"} from API Gateway, never even
        // reaching Laravel's own error handling. The PDF export already
        // works precisely because dompdf's download() returns a plain,
        // fully-buffered response instead. Buffering the writer's output
        // into a string first and returning it the same way fixes this.
        ob_start();
        $writer->save('php://output');
        $contents = ob_get_clean();

        // Still 502'd in production even after the buffering fix above -
        // Vapor's LambdaResponse only base64-encodes the body (required for
        // any binary payload to survive the trip through API Gateway) when
        // this exact header is present; it never sniffs Content-Type to
        // decide (see vendor/laravel/vapor-core/src/Runtime/LambdaResponse.php).
        // Without it, the xlsx's raw zip bytes get sent as if they were a
        // UTF-8 string, corrupting the Lambda response and producing a
        // content-free "Internal server error" from API Gateway rather than
        // anything Laravel's own error handling ever sees. Confirmed via a
        // production repro: identical code succeeds when invoked directly
        // (bypassing the HTTP/API Gateway round trip entirely) but 502s
        // through the real endpoint - isolating this to the response
        // encoding rather than anything in the spreadsheet generation.
        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xlsx\"",
            'X-Vapor-Base64-Encode' => 'True',
        ]);
    }

    public function finaliseAndShareStore(Request $request)
    {
        $validated = $request->validate([
            'session_ids' => 'nullable|array',
            'session_ids.*' => 'integer|exists:work_sessions,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $eligibleSessions = WorkSession::whereIn('id', $validated['session_ids'] ?? [])
            ->where('user_id', Auth::id())
            ->where('status', WorkSession::DRAFT)
            ->whereNotNull('ended_at')
            ->get();

        $updated = 0;
        $skipped = 0;

        // One at a time rather than a single mass update, so each check sees
        // the effect of the ones just finalised before it - if two selected
        // sessions overlap each other, the first one through wins and the
        // second gets caught by the same conflict check as any other.
        foreach ($eligibleSessions as $session) {
            if ($session->overlapsFinalisedSession()) {
                $skipped++;
                continue;
            }
            $session->update(['status' => WorkSession::FINALISED]);
            $updated++;
        }

        $message = $updated === 1 ? '1 session finalised.' : "{$updated} sessions finalised.";
        if ($skipped > 0) {
            $message .= ' ' . ($skipped === 1
                ? '1 session time conflicts with an existing session - please resolve before finalising.'
                : "{$skipped} sessions time-conflict with an existing session - please resolve before finalising.");
        }

        return redirect()->route('work-sessions.finalise-and-share', [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ])->with($updated === 0 && $skipped > 0 ? 'error' : 'success', $message);
    }

    private function activeSession(): ?WorkSession
    {
        return Auth::user()->workSessions()->whereNull('ended_at')->first();
    }

    /**
     * Jobs the current user can book time against: normally any assigned job
     * whose status allows it, plus — as a grace window for late entries — a
     * recurring job's most recently closed instance, until the end of the
     * following period (e.g. July's instance stays bookable through August).
     */
    private function bookableJobs($currentPropertyId)
    {
        $normal = FarmJob::whereHas('assignees', fn ($q) => $q->where('users.id', Auth::id()))
            ->when($currentPropertyId, fn ($query) => $query->where('property_id', $currentPropertyId))
            ->where(function ($query) {
                $query->whereHas('jobStatus', fn ($q) => $q->where('can_book_time', true))
                    ->orWhereNull('job_status_id');
            })
            ->get();

        $recurringInGrace = FarmJob::whereHas('assignees', fn ($q) => $q->where('users.id', Auth::id()))
            ->when($currentPropertyId, fn ($query) => $query->where('property_id', $currentPropertyId))
            ->whereNotNull('recurring_job_id')
            ->whereNotNull('period_end')
            ->with('recurringJob')
            ->get()
            ->filter(function (FarmJob $job) {
                $graceEnd = match ($job->recurringJob->interval) {
                    RecurringJob::DAILY => $job->period_end->copy()->addDay(),
                    RecurringJob::WEEKLY => $job->period_end->copy()->addWeek(),
                    RecurringJob::MONTHLY => $job->period_end->copy()->addMonthNoOverflow(),
                    RecurringJob::YEARLY => $job->period_end->copy()->addYear(),
                };

                return now()->lte($graceEnd);
            })
            ->reject(function (FarmJob $job) {
                // The grace window exists to cover the gap before a new
                // period's instance exists - once one does, this older
                // instance has been superseded and shouldn't keep showing
                // for the rest of the grace period too.
                return FarmJob::where('recurring_job_id', $job->recurring_job_id)
                    ->where('period_end', '>', $job->period_end)
                    ->exists();
            });

        return $normal->merge($recurringInGrace)->unique('id')->values();
    }

    /**
     * Switch a job out of its default status the first time time is booked
     * against it — but only from the default status, so a job someone has
     * already moved further along (or set manually) is never dragged back.
     */
    private function promoteJobToInProgress(int $farmJobId): void
    {
        $job = FarmJob::with('jobStatus')->find($farmJobId);

        if (!$job || !$job->jobStatus?->is_default) {
            return;
        }

        $inProgressStatus = JobStatus::where('property_id', $job->property_id)->where('is_in_progress_default', true)->first();

        if ($inProgressStatus) {
            $job->update(['job_status_id' => $inProgressStatus->id]);
        }
    }

    private function parseDateRange(Request $request): array
    {
        $dateFrom = $request->date_from
            ? \Carbon\Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfMonth();
        $dateTo = $request->date_to
            ? \Carbon\Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfMonth();

        return [$dateFrom, $dateTo];
    }

    /**
     * Sessions eligible for export: not draft (finalised or approved — approved
     * isn't reachable yet, but is logically included), for the current user.
     */
    private function exportableSessionsQuery($currentPropertyId, $dateFrom, $dateTo)
    {
        return Auth::user()->workSessions()
            ->whereIn('status', [WorkSession::FINALISED, WorkSession::APPROVED])
            ->when($currentPropertyId, fn ($query) => $query->where('property_id', $currentPropertyId))
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->with('farmJob')
            ->latest('started_at');
    }
}