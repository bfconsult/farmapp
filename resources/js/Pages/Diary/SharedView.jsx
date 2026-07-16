import { Head, Link } from '@inertiajs/react';
import DiaryDays from '@/Components/DiaryDays';

function formatRangeHeading(dateStr) {
    return new Date(`${dateStr}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function SharedView({ property, dateFrom, dateTo, days, logoUrl, backUrl }) {
    return (
        <>
            <Head title={`${property.name} — Activity Diary`} />

            <div className="min-h-screen bg-gray-100 flex justify-center p-4">
                <div className="max-w-lg w-full space-y-4 mt-8">
                    {/* Only set when this is the in-app preview (see
                        ReportController::previewDiary) - absent on the real
                        public share link, which has no app to return to. */}
                    {backUrl && (
                        <Link
                            href={backUrl}
                            className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                        >
                            ← Back to Reports
                        </Link>
                    )}

                    <div className="flex items-center justify-center gap-2 text-gray-400 text-sm mb-2">
                        <img src={logoUrl} className="w-5 h-5" alt="" />
                        <span>FieldWerkz</span>
                    </div>

                    <div className="text-center mb-4">
                        <h1 className="text-xl font-semibold text-gray-900">{property.name}</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Activity diary · {formatRangeHeading(dateFrom)} – {formatRangeHeading(dateTo)}
                        </p>
                    </div>

                    <DiaryDays days={days} />

                    <p className="text-center text-xs text-gray-400 pt-2 pb-8">
                        Shared view — read-only.
                    </p>
                </div>
            </div>
        </>
    );
}
