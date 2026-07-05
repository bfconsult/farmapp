<?php

namespace App\Http\Controllers;

use App\Models\WorkSession;
use Illuminate\Http\Request;
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
                    ])->values(),
                ];
            })
            ->sortBy(fn ($worker) => $worker['user']->name)
            ->values();

        return Inertia::render('Reports/Index', [
            'workers' => $workers,
            'grandTotal' => [
                'hours' => round($sessions->sum('duration_in_hours'), 2),
                'billing' => round($sessions->sum('billing_amount'), 2),
            ],
            'currentDateFrom' => $dateFrom->toDateString(),
            'currentDateTo' => $dateTo->toDateString(),
        ]);
    }
}
