<?php

namespace App\Http\Controllers;

use App\Models\FarmJob;
use App\Models\Priority;
use App\Models\JobType;
use App\Models\JobStatus;
use App\Models\Property;
use App\Models\RecurringJob;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FarmJobController extends Controller
{
    public function index(Request $request)
    {
        if (Auth::user()->properties()->doesntExist()) {
            return redirect()->route('properties.create');
        }

        $currentPropertyId = session('current_property_id');

        // An approver reviews everyone's work without being added to the
        // team on individual jobs - see everything on the property instead
        // of only jobs they're personally assigned to.
        $isApprover = $currentPropertyId
            && Auth::user()->roleOn(Property::find($currentPropertyId)) === Role::APPROVER;
        $scopeToAssignee = fn ($query) => $isApprover
            ? $query
            : $query->whereHas('assignees', fn ($q) => $q->where('users.id', Auth::id()));

        $dateFrom = $request->date_from
            ? \Carbon\Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfMonth();
        $dateTo = $request->date_to
            ? \Carbon\Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfMonth();

        // Status ids: explicit selection from the Filters panel, or (on first
        // load, when nothing has been chosen yet) the statuses flagged as active.
        // Note: an empty `statuses` array is dropped entirely by query-string
        // serialization, so it can't be distinguished from "absent" on its own —
        // `date_from` is always sent by the Filters panel and never empty, so we
        // use its presence as the "the user has touched the filters" signal.
        if ($request->has('date_from')) {
            $statusIds = collect($request->input('statuses', []))->map(fn ($id) => (int) $id)->all();
        } else {
            $statusIds = JobStatus::where('can_book_time', true)->pluck('id')->all();
        }

        $query = $scopeToAssignee(FarmJob::query())
            ->when($currentPropertyId, function ($query) use ($currentPropertyId) {
                $query->where('property_id', $currentPropertyId);
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with(['priority', 'jobType', 'jobStatus', 'property'])
            ->whereIn('job_status_id', $statusIds);

        // Status counts, scoped to the date range so they reflect what the
        // checkboxes would currently show, but not to the status selection itself.
        $countableJobs = $scopeToAssignee(FarmJob::query())
            ->when($currentPropertyId, function ($query) use ($currentPropertyId) {
                $query->where('property_id', $currentPropertyId);
            })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with('jobStatus')
            ->get();

        $counts = $countableJobs->groupBy('job_status_id')->map->count();

        // Ordering
        $order = $request->order ?? 'latest';
        match($order) {
            'priority' => $query->join('priorities', 'farm_jobs.priority_id', '=', 'priorities.id')
                               ->orderBy('priorities.order', 'desc')
                               ->select('farm_jobs.*'),
            'status' => $query->join('job_statuses', 'farm_jobs.job_status_id', '=', 'job_statuses.id')
                             ->orderBy('job_statuses.order')
                             ->select('farm_jobs.*'),
            default => $query->latest(),
        };

        $jobs = $query->get();

        // Calendar view - independent of the list's filters/date range, since
        // it's a month-by-month browse rather than a filtered list. A job
        // shows on its scheduled_date, or created_at if it has none.
        $calendarMonth = $request->input('calendar_month') ?? now()->format('Y-m');
        $calendarMonthStart = \Carbon\Carbon::parse($calendarMonth . '-01')->startOfMonth();
        $calendarMonthEnd = $calendarMonthStart->copy()->endOfMonth();

        $calendarJobs = $scopeToAssignee(FarmJob::query())
            ->when($currentPropertyId, function ($query) use ($currentPropertyId) {
                $query->where('property_id', $currentPropertyId);
            })
            ->where(function ($query) use ($calendarMonthStart, $calendarMonthEnd) {
                $query->whereBetween('scheduled_date', [$calendarMonthStart, $calendarMonthEnd])
                    ->orWhere(function ($query) use ($calendarMonthStart, $calendarMonthEnd) {
                        $query->whereNull('scheduled_date')
                            ->whereBetween('created_at', [$calendarMonthStart, $calendarMonthEnd]);
                    });
            })
            ->with(['priority', 'jobType', 'jobStatus'])
            ->get();

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'counts' => $counts,
            'currentStatusIds' => $statusIds,
            'currentOrder' => $order,
            'currentDateFrom' => $dateFrom->toDateString(),
            'currentDateTo' => $dateTo->toDateString(),
            'jobStatuses' => JobStatus::orderBy('order')->get(),
            'calendarJobs' => $calendarJobs,
            'calendarMonth' => $calendarMonth,
        ]);
    }

    public function create()
    {
        $currentProperty = Auth::user()->properties()
            ->find(session('current_property_id'));
    
        if (!$currentProperty) {
            return redirect()->route('properties.index')
                ->with('error', 'Please select a property first.');
        }
    
        return Inertia::render('Jobs/Create', [
            'priorities' => Priority::orderBy('order')->get(),
            'jobTypes' => JobType::orderBy('name')->get(),
            'jobStatuses' => JobStatus::orderBy('order')->get(),
            'currentProperty' => $currentProperty,
        ]);
    }

   
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_hours' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'priority_id' => 'nullable|exists:priorities,id',
            'job_type_id' => 'nullable|exists:job_types,id',
            'job_status_id' => 'nullable|exists:job_statuses,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'intent' => 'nullable|in:camera,plan',
            'repeats' => 'boolean',
            'interval' => 'required_if:repeats,true|in:' . implode(',', RecurringJob::INTERVALS),
            'starts_on' => 'required_if:repeats,true|date',
            'scheduled_date' => 'nullable|date',
        ]);

        $intent = $validated['intent'] ?? 'camera';
        $repeats = $validated['repeats'] ?? false;
        unset($validated['intent'], $validated['repeats']);

        // A repeating job's first instance is created immediately, in the
        // same action, rather than waiting for the scheduler to pick it up.
        if ($repeats) {
            $recurringJob = RecurringJob::create([
                'property_id' => session('current_property_id'),
                'created_by' => Auth::id(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'job_type_id' => $validated['job_type_id'] ?? null,
                'priority_id' => $validated['priority_id'] ?? null,
                'estimated_hours' => $validated['estimated_hours'] ?? null,
                'budget' => $validated['budget'] ?? null,
                'hourly_rate' => $validated['hourly_rate'] ?? null,
                'interval' => $validated['interval'],
                'starts_on' => $validated['starts_on'],
                'is_active' => true,
            ]);

            $farmJob = $recurringJob->createInstance($recurringJob->starts_on);

            return redirect()->route('jobs.edit', $farmJob);
        }

        $validated['user_id'] = Auth::id();
        $validated['property_id'] = session('current_property_id');
        $validated['job_status_id'] = $validated['job_status_id'] ?? JobStatus::where('is_default', true)->value('id');

        $farmJob = FarmJob::create($validated);

        $teamUserIds = Role::where('property_id', $farmJob->property_id)->pluck('user_id');
        $farmJob->assignees()->attach($teamUserIds);

        if ($intent === 'plan') {
            return redirect()->route('jobs.edit', $farmJob);
        }

        return redirect()->route('jobs.show', $farmJob)->with('addPhoto', true);
    }

    public function show(FarmJob $farmJob)
    {
        $farmJob->load(['priority', 'jobType', 'jobStatus', 'property', 'photos']);

        return Inertia::render('Jobs/Show', [
            'job' => $farmJob,
        ]);
    }

    /**
     * A job's share link. If the viewer is logged in and normally allowed to
     * see this job (assigned to it), send them to the real page instead -
     * this route is only meant to stand in for people who can't see it.
     */
    public function share(string $token)
    {
        $farmJob = FarmJob::where('share_token', $token)->firstOrFail();

        if ($farmJob->isVisibleTo(Auth::user())) {
            return redirect()->route('jobs.show', $farmJob);
        }

        $farmJob->load(['priority', 'jobType', 'jobStatus', 'property', 'photos']);

        // Curated on purpose - this is a public, unauthenticated route, so
        // internal figures like budget/hourly_rate/location never leave the
        // server, not just hidden in the UI.
        return Inertia::render('Jobs/SharedView', [
            'job' => [
                'name' => $farmJob->name,
                'description' => $farmJob->description,
                'scheduled_date' => $farmJob->scheduled_date,
                'priority' => $farmJob->priority?->only(['name']),
                'job_type' => $farmJob->jobType?->only(['name']),
                'job_status' => $farmJob->jobStatus?->only(['name']),
                'property' => $farmJob->property?->only(['name']),
                'photos' => $farmJob->photos->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => $photo->url,
                ]),
            ],
        ]);
    }

    public function edit(FarmJob $farmJob)
    {
        $farmJob->load('assignees');

        return Inertia::render('Jobs/Edit', [
            'job' => $farmJob,
            'priorities' => Priority::orderBy('order')->get(),
            'jobStatuses' => JobStatus::orderBy('order')->get(),
            'jobTypes' => JobType::orderBy('name')->get(),
            'properties' => Auth::user()->properties()->get(),
            'teamRoles' => Property::find($farmJob->property_id)->roles()->with('user')->get(),
        ]);
    }

    public function update(Request $request, FarmJob $farmJob)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_hours' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'priority_id' => 'nullable|exists:priorities,id',
            'job_type_id' => 'nullable|exists:job_types,id',
            'job_status_id' => 'nullable|exists:job_statuses,id',
            'property_id' => 'required|exists:properties,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
            'repeats' => 'boolean',
            'interval' => 'required_if:repeats,true|in:' . implode(',', RecurringJob::INTERVALS),
            'starts_on' => 'required_if:repeats,true|date',
            'scheduled_date' => 'nullable|date',
        ]);

        $assigneeIds = $validated['assignee_ids'] ?? [];
        $repeats = $validated['repeats'] ?? false;
        $interval = $validated['interval'] ?? null;
        $startsOn = $validated['starts_on'] ?? null;
        unset($validated['assignee_ids'], $validated['repeats'], $validated['interval'], $validated['starts_on']);

        $validated['job_status_id'] = $validated['job_status_id'] ?? JobStatus::where('is_default', true)->value('id');

        // Turn this existing job into the first instance of a new recurring
        // template — only possible if it isn't already part of one.
        if ($repeats && !$farmJob->recurring_job_id) {
            $recurringJob = RecurringJob::create([
                'property_id' => $validated['property_id'],
                'created_by' => Auth::id(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'job_type_id' => $validated['job_type_id'] ?? null,
                'priority_id' => $validated['priority_id'] ?? null,
                'estimated_hours' => $validated['estimated_hours'] ?? null,
                'budget' => $validated['budget'] ?? null,
                'hourly_rate' => $validated['hourly_rate'] ?? null,
                'interval' => $interval,
                'starts_on' => $startsOn,
                'is_active' => true,
            ]);

            $periodStart = \Carbon\Carbon::parse($startsOn);
            $validated['recurring_job_id'] = $recurringJob->id;
            $validated['period_start'] = $periodStart;
            $validated['period_end'] = $recurringJob->periodEndFor($periodStart);
        }

        $farmJob->update($validated);
        $farmJob->assignees()->sync($assigneeIds);

        return redirect()->route('jobs.show', $farmJob);
    }

    public function destroy(Request $request, FarmJob $farmJob)
    {
        // Deleting the template stops future instances being generated, but
        // (via nullOnDelete) leaves any other instances it already created
        // intact - same as RecurringJobController::destroy().
        if ($farmJob->recurring_job_id && $request->boolean('delete_recurring')) {
            $farmJob->recurringJob?->delete();
        }

        $farmJob->delete();

        return redirect()->route('jobs.index');
    }

    /**
     * Downloadable .ics calendar event for a job's scheduled date — works
     * with Apple Calendar, Outlook and Google Calendar without needing any
     * account integration, since it's just a standard file the OS/browser
     * hands off to whatever calendar app is installed.
     */
    public function calendar(FarmJob $farmJob)
    {
        if (!$farmJob->scheduled_date) {
            abort(404);
        }

        $start = $farmJob->scheduled_date->format('Ymd');
        $end = $farmJob->scheduled_date->copy()->addDay()->format('Ymd');
        $stamp = now()->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//FarmTask//EN',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            "UID:farm-job-{$farmJob->id}@farmtask.be",
            "DTSTAMP:{$stamp}",
            "DTSTART;VALUE=DATE:{$start}",
            "DTEND;VALUE=DATE:{$end}",
            'SUMMARY:' . $this->icsEscape($farmJob->name),
        ];

        if ($farmJob->description) {
            $lines[] = 'DESCRIPTION:' . $this->icsEscape($farmJob->description);
        }

        $lines = array_merge($lines, [
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'DESCRIPTION:Reminder',
            'TRIGGER:-P1D',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        $ics = implode("\r\n", $lines) . "\r\n";

        return response($ics, 200, [
            // Deliberately not "attachment" - forcing a download makes mobile
            // browsers treat this as a generic file instead of handing it off
            // to the calendar app directly, which is the whole point of
            // serving text/calendar.
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="' . Str::slug($farmJob->name) . '.ics"',
        ]);
    }

    private function icsEscape(string $text): string
    {
        return str_replace(["\\", ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], $text);
    }
}