<?php

namespace App\Http\Controllers;

use App\Models\DiaryShare;
use App\Models\Metric;
use App\Models\Property;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $currentPropertyId = session('current_property_id');

        $dateFrom = $request->date_from
            ? \Carbon\Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfMonth();
        $dateTo = $request->date_to
            ? \Carbon\Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfMonth();

        // Approvers see the same day-by-day diary they'd get via a shared
        // link (see DiaryShareController), just in-app with a date filter -
        // not the admin/manager ledger view, which also carries billing
        // figures an approver-only role shouldn't see.
        $userRole = Auth::user()->roleOn(Property::find($currentPropertyId));
        if ($userRole === 'approver') {
            return Inertia::render('Reports/Diary', [
                'days' => WorkSession::diaryDays($currentPropertyId, $dateFrom, $dateTo),
                'currentDateFrom' => $dateFrom->toDateString(),
                'currentDateTo' => $dateTo->toDateString(),
                'metrics' => Metric::where('property_id', $currentPropertyId)
                    ->with('latestMeasurement.photos')
                    ->orderBy('name')
                    ->get(),
            ]);
        }

        $sessions = WorkSession::where('property_id', $currentPropertyId)
            ->whereIn('status', [WorkSession::FINALISED, WorkSession::APPROVED])
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->with(['farmJob', 'user'])
            ->latest('started_at')
            ->get();

        $workers = $sessions
            ->groupBy('user_id')
            ->map(function ($userSessions) {
                return [
                    'user' => $userSessions->first()->user,
                    'totalHours' => round($userSessions->sum('duration_in_hours'), 2),
                    'totalBilling' => round($userSessions->sum('billing_amount'), 2),
                    'sessions' => $userSessions->map(fn ($session) => [
                        'id' => $session->id,
                        'farm_job' => $session->farmJob,
                        'started_at' => $session->started_at,
                        'ended_at' => $session->ended_at,
                        'duration_in_hours' => $session->duration_in_hours,
                        'billing_amount' => $session->billing_amount,
                        'status' => $session->status,
                        'source' => $session->source,
                    ])->values(),
                ];
            })
            ->sortBy(fn ($worker) => $worker['user']->name)
            ->values();

        // Surfaced once, right after generating a link via storeDiaryShare -
        // not persisted/re-shown on a plain page reload.
        $justSharedUrl = null;
        if ($request->shared) {
            $share = DiaryShare::where('token', $request->shared)
                ->where('property_id', $currentPropertyId)
                ->first();
            $justSharedUrl = $share ? route('diary.share', $share->token) : null;
        }

        return Inertia::render('Reports/Index', [
            'workers' => $workers,
            'grandTotal' => [
                'hours' => round($sessions->sum('duration_in_hours'), 2),
                'billing' => round($sessions->sum('billing_amount'), 2),
            ],
            'currentDateFrom' => $dateFrom->toDateString(),
            'currentDateTo' => $dateTo->toDateString(),
            'justSharedUrl' => $justSharedUrl,
        ]);
    }

    /**
     * Renders the exact same page a recipient would see (Diary/SharedView),
     * for the current property and a given date range, without creating a
     * DiaryShare record - lets an admin/manager sanity-check the content
     * before committing to storeDiaryShare below. Auth-protected, unlike the
     * real share link, since it's not meant to be sent to anyone.
     */
    public function previewDiary(Request $request)
    {
        $currentPropertyId = session('current_property_id');

        $dateFrom = $request->date_from
            ? \Carbon\Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfMonth();
        $dateTo = $request->date_to
            ? \Carbon\Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfMonth();

        $property = Property::findOrFail($currentPropertyId);

        return Inertia::render('Diary/SharedView', [
            'property' => $property->only(['name']),
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'days' => WorkSession::diaryDays($currentPropertyId, $dateFrom, $dateTo),
            'metrics' => Metric::where('property_id', $currentPropertyId)
                ->with('latestMeasurement.photos')
                ->orderBy('name')
                ->get(),
            'logoUrl' => asset('favicon.svg'),
            'backUrl' => route('reports.index', [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ]),
        ]);
    }

    /**
     * Generates a public, unauthenticated link (see DiaryShareController)
     * showing this property's finalised/approved activity for the given
     * date range, day by day - for sharing with an approver who may not
     * have (or want) an account yet.
     */
    public function storeDiaryShare(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $share = DiaryShare::create([
            'property_id' => session('current_property_id'),
            'created_by' => Auth::id(),
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
        ]);

        return redirect()->route('reports.index', [
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'shared' => $share->token,
        ]);
    }
}
