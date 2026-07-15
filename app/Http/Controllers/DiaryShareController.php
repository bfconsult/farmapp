<?php

namespace App\Http\Controllers;

use App\Models\DiaryShare;
use App\Models\WorkSession;
use Inertia\Inertia;

class DiaryShareController extends Controller
{
    /**
     * Public, unauthenticated day-by-day activity diary for a property over
     * a fixed date range - generated from Reports (ReportController::
     * storeDiaryShare) so a manager can send it to an approver who may not
     * have (or want) an account of their own yet. Read-only: no way to act
     * on it from this route, only to view it.
     */
    public function show(string $token)
    {
        $share = DiaryShare::where('token', $token)->with('property')->firstOrFail();

        $days = WorkSession::diaryDays(
            $share->property_id,
            $share->date_from->startOfDay(),
            $share->date_to->endOfDay(),
        );

        return Inertia::render('Diary/SharedView', [
            'property' => $share->property->only(['name']),
            'dateFrom' => $share->date_from->toDateString(),
            'dateTo' => $share->date_to->toDateString(),
            'days' => $days,
            // Not a plain "/favicon.svg" path - Vapor only serves favicon.ico
            // and robots.txt directly from the app root; everything else in
            // public/ needs asset(), which redirects to the CDN-backed URL.
            'logoUrl' => asset('favicon.svg'),
        ]);
    }
}
