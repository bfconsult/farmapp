import { formatDate } from '@/dateInput';

function formatDayHeading(dateStr) {
    return formatDate(dateStr, { weekday: 'long', month: 'long', year: 'numeric' });
}

function formatTime(datetime) {
    if (!datetime) return '—';
    return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/**
 * Day-by-day, blog-style rendering of WorkSession::diaryDays() output -
 * shared between the public diary share (Diary/SharedView) and the in-app
 * approver diary (Reports/Diary), so the two stay visually consistent.
 */
export default function DiaryDays({ days }) {
    if (days.length === 0) {
        return (
            <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500 text-sm">
                No finalised activity recorded in this period.
            </div>
        );
    }

    return (
        <>
            {days.map((day) => (
                <div key={day.date}>
                    <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-2 mt-6">
                        {formatDayHeading(day.date)}
                    </h2>
                    <div className="space-y-3">
                        {day.entries.map((entry) => (
                            <div key={entry.id} className="bg-white rounded-lg shadow p-4">
                                <div className="flex items-baseline justify-between gap-2 mb-1">
                                    <p className="font-medium text-gray-900">{entry.label}</p>
                                    <p className="text-xs text-gray-500 flex-shrink-0">
                                        {formatTime(entry.started_at)} – {formatTime(entry.ended_at)}
                                        {entry.duration_in_hours ? ` (${entry.duration_in_hours}h)` : ''}
                                    </p>
                                </div>
                                <p className="text-sm text-gray-500 mb-2">{entry.user_name}</p>
                                {entry.description && (
                                    <p className="text-sm text-gray-700 whitespace-pre-line mb-3">
                                        {entry.description}
                                    </p>
                                )}
                                {entry.photos.length > 0 && (
                                    <div className="grid grid-cols-3 gap-2">
                                        {entry.photos.map((photo) => (
                                            <img
                                                key={photo.id}
                                                src={photo.url}
                                                className="w-full h-24 object-cover rounded-lg"
                                                alt=""
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </>
    );
}
