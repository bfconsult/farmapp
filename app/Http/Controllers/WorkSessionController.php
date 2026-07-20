<?php

namespace App\Http\Controllers;

use App\Models\FarmJob;
use App\Models\JobStatus;
use App\Models\RecurringJob;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                return $session;
            });

        return Inertia::render('WorkSessions/Index', [
            'sessions' => $sessions,
            // Not date-filtered - a session still running "right now" is
            // shown regardless of which historical range is selected below.
            'activeSession' => Auth::user()->workSessions()
                ->whereNull('ended_at')
                ->with('farmJob')
                ->first(),
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
            'billingBlockMinutes' => Auth::user()->billing_block_minutes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'nullable|string',
            'farm_job_id' => 'nullable|exists:farm_jobs,id',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date|after:started_at',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // A session logged with an end time already set (e.g. forgotten past work being
        // added after the fact) isn't "active", so it doesn't conflict with one that is.
        if (!$validated['ended_at'] && ($activeSession = $this->activeSession())) {
            return redirect()->route('work-sessions.show', $activeSession)
                ->with('error', 'You already have an active work session. Stop it before starting a new one.');
        }

        // If the user set a custom start/end time (rather than leaving it to
        // default to "now"), they're logging work from somewhere other than the
        // job site right now — don't pop the camera asking for an on-site photo.
        $timeWasAltered = $validated['started_at'] || $validated['ended_at'];

        $validated['user_id'] = Auth::id();
        $validated['property_id'] = session('current_property_id');
        $validated['started_at'] = $validated['started_at']
            ?? Auth::user()->floorToBillingBlock(now());
        $validated['status'] = WorkSession::DRAFT;

        $session = WorkSession::create($validated);

        if ($session->farm_job_id) {
            $this->promoteJobToInProgress($session->farm_job_id);
        }

        $redirect = redirect()->route('work-sessions.show', $session);

        return $timeWasAltered ? $redirect : $redirect->with('addPhoto', true);
    }

    public function show(WorkSession $workSession)
    {
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

        $workSession->load(['farmJob', 'property', 'photos', 'user', 'waypoints']);

        return Inertia::render('WorkSessions/Show', [
            'session' => $workSession,
            'durationInHours' => $workSession->duration_in_hours,
            'billingAmount' => $workSession->billing_amount,
            'waypoints' => $workSession->waypoints,
        ]);
    }

    public function edit(WorkSession $workSession)
    {
        if ($workSession->status !== WorkSession::DRAFT) {
            return redirect()->route('work-sessions.show', $workSession)
                ->with('error', 'Only draft work sessions can be edited.');
        }

        $plannedJobs = FarmJob::whereHas('assignees', fn ($q) => $q->where('users.id', Auth::id()))
            ->where('property_id', $workSession->property_id)
            ->get();

        return Inertia::render('WorkSessions/Edit', [
            'session' => $workSession,
            'plannedJobs' => $plannedJobs,
            'waypoints' => $workSession->waypoints,
            'billingBlockMinutes' => Auth::user()->billing_block_minutes,
        ]);
    }

    public function update(Request $request, WorkSession $workSession)
    {
        if ($workSession->status !== WorkSession::DRAFT) {
            abort(403, 'Only draft work sessions can be edited.');
        }

        $validated = $request->validate([
            'description' => 'nullable|string',
            'farm_job_id' => 'nullable|exists:farm_jobs,id',
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after:started_at',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if (!$validated['ended_at'] && ($activeSession = $this->activeSession())
            && $activeSession->isNot($workSession)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ended_at' => 'You already have another active work session. Stop it first.',
            ]);
        }

        $workSession->update([...$validated, 'reviewed_at' => $workSession->reviewed_at ?? now()]);

        return redirect()->route('work-sessions.show', $workSession);
    }

    public function destroy(WorkSession $workSession)
    {
        $workSession->delete();

        return redirect()->route('work-sessions.index');
    }

    public function stop(WorkSession $workSession)
    {
        $workSession->update(['ended_at' => now()]);

        return redirect()->route('work-sessions.show', $workSession);
    }

    public function finalise(WorkSession $workSession)
    {
        if (!$workSession->ended_at) {
            abort(403, 'Stop the session before finalising it.');
        }

        if ($workSession->status !== WorkSession::DRAFT) {
            abort(403, 'Only draft work sessions can be finalised.');
        }

        $workSession->update(['status' => WorkSession::FINALISED]);

        return redirect()->route('work-sessions.show', $workSession);
    }

    public function revertToDraft(WorkSession $workSession)
    {
        // Only a finalised session can go back to draft — once approved, it's locked in.
        if ($workSession->status !== WorkSession::FINALISED) {
            abort(403, 'Only finalised work sessions can be reverted to draft.');
        }

        $workSession->update(['status' => WorkSession::DRAFT]);

        return redirect()->route('work-sessions.show', $workSession);
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

        $sessions = $this->exportableSessionsQuery($currentPropertyId, $dateFrom, $dateTo)->get();

        $rows = $sessions->map(fn ($session) => [
            'date' => $session->started_at->format('d/m/Y'),
            'job' => $session->farmJob?->name ?? 'Ad-hoc',
            'start' => $session->started_at->format('H:i'),
            'end' => $session->ended_at?->format('H:i') ?? '—',
            'duration' => $session->duration_in_hours,
            'amount' => $session->billing_amount,
        ]);

        $totalHours = round($rows->sum('duration'), 2);
        $totalBilling = round($rows->sum('amount'), 2);
        $filename = "work-sessions_{$dateFrom->toDateString()}_{$dateTo->toDateString()}";

        if ($request->format === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.work-sessions', [
                'rows' => $rows,
                'rateMode' => $rateMode,
                'dateFrom' => $dateFrom->toDateString(),
                'dateTo' => $dateTo->toDateString(),
                'totalHours' => $totalHours,
                'totalBilling' => $totalBilling,
            ])->download("{$filename}.pdf");
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Date', 'Job', 'Start', 'End', 'Hours'];
        if ($rateMode === 'billing') {
            $headers[] = 'Amount (Ex GST)';
        }
        $sheet->fromArray($headers, null, 'A1');

        $rowNumber = 2;
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

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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

        $updated = WorkSession::whereIn('id', $validated['session_ids'] ?? [])
            ->where('user_id', Auth::id())
            ->where('status', WorkSession::DRAFT)
            ->whereNotNull('ended_at')
            ->update(['status' => WorkSession::FINALISED]);

        return redirect()->route('work-sessions.finalise-and-share', [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ])->with('success', $updated === 1 ? '1 session finalised.' : "{$updated} sessions finalised.");
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

        $inProgressStatus = JobStatus::where('is_in_progress_default', true)->first();

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