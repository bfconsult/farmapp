<?php

namespace App\Http\Controllers;

use App\Models\DiaryShare;
use App\Models\Expense;
use App\Models\FarmJob;
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

        // Surfaced once, right after generating a link via storeDiaryShare -
        // not persisted/re-shown on a plain page reload.
        $justSharedUrl = null;
        if ($request->shared) {
            $share = DiaryShare::where('token', $request->shared)
                ->where('property_id', $currentPropertyId)
                ->first();
            $justSharedUrl = $share ? route('diary.share', $share->token) : null;
        }

        // Every role gets the full diary here - the same content as the
        // shared link (see DiaryShareController), just in-app with a date
        // filter. Sharing it stays admin/manager only (see routes/web.php).
        return Inertia::render('Reports/Index', [
            'days' => WorkSession::diaryDays($currentPropertyId, $dateFrom, $dateTo),
            'metrics' => Metric::forDiaryPeriod($currentPropertyId, $dateFrom, $dateTo),
            'completedJobs' => FarmJob::completedDuringPeriod($currentPropertyId, $dateFrom, $dateTo),
            'openJobs' => FarmJob::openAsOf($currentPropertyId, $dateTo),
            'expensesByJob' => Expense::forDiaryPeriod($currentPropertyId, $dateFrom, $dateTo),
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
            'metrics' => Metric::forDiaryPeriod($currentPropertyId, $dateFrom, $dateTo),
            'completedJobs' => FarmJob::completedDuringPeriod($currentPropertyId, $dateFrom, $dateTo),
            'openJobs' => FarmJob::openAsOf($currentPropertyId, $dateTo),
            'expensesByJob' => Expense::forDiaryPeriod($currentPropertyId, $dateFrom, $dateTo),
            'logoUrl' => asset('favicon.svg'),
            'backUrl' => route('reports.index', [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ]),
            'pdfUrl' => route('reports.diary-preview.pdf', [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ]),
        ]);
    }

    /**
     * PDF of the exact same content as previewDiary - same date range
     * resolution, so the "Download PDF" link on Diary/SharedView can just
     * carry the current date_from/date_to query params across unchanged.
     */
    public function downloadDiaryPdf(Request $request)
    {
        $currentPropertyId = session('current_property_id');
        $property = Property::findOrFail($currentPropertyId);

        $dateFrom = $request->date_from
            ? \Carbon\Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfMonth();
        $dateTo = $request->date_to
            ? \Carbon\Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfMonth();

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.diary', [
            'property' => $property->only(['name']),
            'dateFrom' => $dateFrom->toDateString(),
            'dateTo' => $dateTo->toDateString(),
            'days' => WorkSession::diaryDays($currentPropertyId, $dateFrom, $dateTo, forPdf: true),
            'metrics' => Metric::forDiaryPeriodExport($currentPropertyId, $dateFrom, $dateTo),
            'completedJobs' => FarmJob::completedDuringPeriodExport($currentPropertyId, $dateFrom, $dateTo),
            'openJobs' => FarmJob::openAsOfExport($currentPropertyId, $dateTo),
            'expensesByJob' => Expense::forDiaryPeriod($currentPropertyId, $dateFrom, $dateTo),
        ])->download("diary_{$dateFrom->toDateString()}_{$dateTo->toDateString()}.pdf");
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
