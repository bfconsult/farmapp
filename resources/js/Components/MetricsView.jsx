import { Link } from '@inertiajs/react';

const REPORTING_PERIOD_ORDER = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];

const REPORTING_PERIOD_LABELS = {
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
};

const STATUS_LABELS = {
    incomplete: 'Incomplete',
    complete: 'Complete',
};

const STATUS_COLORS = {
    incomplete: 'bg-gray-100 text-gray-600',
    complete: 'bg-green-100 text-green-700',
};

function formatMeasurementValue(measurement) {
    // Data isn't final until the measurement is marked complete, so don't
    // show a possibly-in-progress value as if it were the recorded figure.
    if (measurement.status !== 'complete') {
        return 'Not measured';
    }
    if (measurement.answer_type === 'number') {
        return measurement.value_number ?? 'No value recorded';
    }
    return measurement.value_text || 'No value recorded';
}

function ViewSection({ period, metrics, showHistoryLinks, showStatusBadge, showPhotos }) {
    return (
        <div className="bg-white rounded-lg shadow overflow-hidden">
            <div className="px-4 py-2 bg-gray-50 border-b border-gray-100">
                <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                    {REPORTING_PERIOD_LABELS[period]}
                </p>
            </div>
            <div className="divide-y divide-gray-100">
                {metrics.map((metric) => {
                    const photos = showPhotos ? metric.latest_measurement.photos ?? [] : [];

                    return (
                        <div key={metric.id} className="px-4 py-3">
                            <div className="flex items-center justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="text-sm text-gray-900">{metric.name}</p>
                                    <p className="text-xs text-gray-500 mt-1">
                                        {formatMeasurementValue(metric.latest_measurement)}
                                    </p>
                                    {showHistoryLinks && (
                                        <Link href={route('metrics.history', metric.id)} className="text-xs text-green-600 mt-1 inline-block">
                                            History
                                        </Link>
                                    )}
                                </div>
                                {showStatusBadge && (
                                    <span className={`text-xs px-2 py-1 rounded-full font-medium flex-shrink-0 ${STATUS_COLORS[metric.latest_measurement.status]}`}>
                                        {STATUS_LABELS[metric.latest_measurement.status]}
                                    </span>
                                )}
                            </div>
                            {photos.length > 0 && (
                                <div className="grid grid-cols-3 gap-2 mt-3">
                                    {photos.map((photo) => (
                                        <img key={photo.id} src={photo.url} className="w-full h-20 object-cover rounded-lg" />
                                    ))}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

/**
 * The most recent measurement for each metric, grouped by reporting period -
 * used by the Metrics page's View tab, the approver Diary page, and the
 * (shared/preview) Diary/SharedView page, so it stays in sync in one place.
 * showHistoryLinks is off by default on the public share link, since History
 * requires a login the recipient may not have. showStatusBadge is off in the
 * Diary contexts, where the value itself ("Not measured" when incomplete)
 * already conveys status, making the badge redundant. showPhotos is off by
 * default (the Metrics page keeps its compact list); Diary contexts turn it
 * on and must eager-load latestMeasurement.photos for it to have anything
 * to show.
 */
export default function MetricsView({ metrics, showHistoryLinks = true, showStatusBadge = true, showPhotos = false }) {
    const viewGroups = REPORTING_PERIOD_ORDER
        .map((period) => ({
            period,
            metrics: metrics.filter((m) => m.reporting_period === period && m.latest_measurement),
        }))
        .filter((group) => group.metrics.length > 0);

    if (viewGroups.length === 0) {
        return (
            <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                No metrics set up yet.
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {viewGroups.map((group) => (
                <ViewSection key={group.period} period={group.period} metrics={group.metrics} showHistoryLinks={showHistoryLinks} showStatusBadge={showStatusBadge} showPhotos={showPhotos} />
            ))}
        </div>
    );
}
