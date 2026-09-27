import { formatNumber } from '@/numberFormat';

/**
 * The two Jobs sections appended to a Diary report - Completed Jobs (one
 * card per job finished in the period, all-time hours/expenses/photos) and
 * Open Jobs (a compact row per still-open job, totals as of the period end,
 * no photos). Used identically by Reports/Diary.jsx and Diary/SharedView.jsx,
 * the same way MetricsView.jsx is shared across both - see FarmJob::
 * completedDuringPeriod()/openAsOf() for how the data is built.
 */
export default function JobsView({ completedJobs = [], openJobs = [] }) {
    if (completedJobs.length === 0 && openJobs.length === 0) {
        return null;
    }

    return (
        <div className="space-y-4">
            {completedJobs.length > 0 && (
                <div>
                    <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">
                        Completed Jobs
                    </h2>
                    <div className="space-y-3">
                        {completedJobs.map((job) => (
                            <div key={job.id} className="bg-white rounded-lg shadow p-4">
                                <p className="text-sm text-gray-900">{job.name}</p>
                                {job.description && (
                                    <p className="text-xs text-gray-500 mt-1 whitespace-pre-line">{job.description}</p>
                                )}
                                {job.photos && job.photos.length > 0 && (
                                    <div className="grid grid-cols-3 gap-2 mt-3">
                                        {job.photos.map((photo) => (
                                            <img key={photo.id} src={photo.url} className="w-full h-20 object-cover rounded-lg" />
                                        ))}
                                    </div>
                                )}
                                <div className="flex justify-between text-xs text-gray-500 mt-3 pt-3 border-t border-gray-100">
                                    <span>{formatNumber(job.total_hours)}h booked</span>
                                    <span>${formatNumber(job.total_expenses)} expenses</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {openJobs.length > 0 && (
                <div>
                    <h2 className="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">
                        Open Jobs
                    </h2>
                    <div className="bg-white rounded-lg shadow overflow-hidden divide-y divide-gray-100">
                        {openJobs.map((job) => (
                            <div key={job.id} className="flex items-center justify-between gap-2 px-4 py-3">
                                <span className="text-sm text-gray-900 min-w-0 truncate">{job.name}</span>
                                <span className="text-xs text-gray-500 flex-shrink-0">
                                    {formatNumber(job.total_hours)}h &middot; ${formatNumber(job.total_expenses)}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
