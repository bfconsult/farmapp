import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import WaypointTrail from '@/Components/WaypointTrail';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    toLocalInputValue,
    fromLocalInputValue,
    splitLocalValue,
    joinLocalValue,
    billingBlockLabel,
    timeOptionsForBlock,
    floorToBillingBlock,
    ceilToBillingBlock,
} from '@/dateInput';

export default function Edit({ session, plannedJobs, waypoints, billingBlockMinutes }) {
    // The exact recorded moment (e.g. an auto-tracked visit's GPS timestamp)
    // never needs to be shown or selectable - the start suggests the block
    // boundary before it, the end the one after, so the dropdown only ever
    // offers clean block-aligned times either way.
    const startedLocal = splitLocalValue(
        toLocalInputValue(floorToBillingBlock(new Date(session.started_at), billingBlockMinutes).toISOString())
    );
    const endedLocal = session.ended_at
        ? splitLocalValue(
              toLocalInputValue(ceilToBillingBlock(new Date(session.ended_at), billingBlockMinutes).toISOString())
          )
        : { date: '', time: '' };

    const { data, setData, patch, processing, errors, transform } = useForm({
        description: session.description ?? '',
        farm_job_id: session.farm_job_id ?? '',
        started_date: startedLocal.date,
        started_time: startedLocal.time,
        ended_date: endedLocal.date,
        ended_time: endedLocal.time,
    });

    const baseTimeOptions = useMemo(() => timeOptionsForBlock(billingBlockMinutes), [billingBlockMinutes]);
    const startedTimeOptions = baseTimeOptions;
    const endedTimeOptions = useMemo(() => {
        // An active session has no end time yet - keep that choice available
        // rather than forcing a real time onto it just by opening the form.
        return endedLocal.time ? baseTimeOptions : [{ value: '', label: '— Still active —' }, ...baseTimeOptions];
    }, [baseTimeOptions, endedLocal.time]);

    const submit = (e) => {
        e.preventDefault();
        transform((data) => ({
            ...data,
            started_at: fromLocalInputValue(joinLocalValue(data.started_date, data.started_time)),
            ended_at: fromLocalInputValue(joinLocalValue(data.ended_date, data.ended_time)),
        }));
        patch(route('work-sessions.update', session.id));
    };

    // Only the special "hasn't been reviewed yet" screen, not a permanent
    // label - once reviewed_at is set (see WorkSessionController::update()),
    // this is a normal edit screen even though source stays 'auto_tracked'
    // forever as a historical fact about how the session was recorded.
    const isAutoTracked = session.source === 'auto_tracked' && !session.reviewed_at;

    return (
        <AuthenticatedLayout>
            <Head title={isAutoTracked ? 'Auto Tracked Visit' : 'Edit Work Session'} />

            <div className="max-w-lg mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <Link href={route('work-sessions.show', session.id)} className="text-green-600 text-sm">
                        ← Back
                    </Link>
                    <h1 className="text-xl font-semibold text-gray-900">
                        {isAutoTracked ? 'Auto Tracked Visit' : 'Edit Session'}
                    </h1>
                </div>

                <WaypointTrail waypoints={waypoints} />

                <form onSubmit={submit} className="space-y-4">
                    <div className="bg-white rounded-lg shadow p-4 space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Started At
                                {billingBlockMinutes && (
                                    <span className="text-gray-400 font-normal"> (in steps of {billingBlockLabel(billingBlockMinutes)})</span>
                                )}
                            </label>
                            <div className="grid grid-cols-2 gap-2">
                                <input
                                    type="date"
                                    value={data.started_date}
                                    onChange={(e) => setData('started_date', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                />
                                <select
                                    value={data.started_time}
                                    onChange={(e) => setData('started_time', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                >
                                    {startedTimeOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </div>
                            {errors.started_at && <p className="mt-1 text-sm text-red-600">{errors.started_at}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Ended At
                                {billingBlockMinutes && (
                                    <span className="text-gray-400 font-normal"> (in steps of {billingBlockLabel(billingBlockMinutes)})</span>
                                )}
                            </label>
                            <div className="grid grid-cols-2 gap-2">
                                <input
                                    type="date"
                                    value={data.ended_date}
                                    onChange={(e) => setData('ended_date', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                />
                                <select
                                    value={data.ended_time}
                                    onChange={(e) => setData('ended_time', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                >
                                    {endedTimeOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </div>
                            {errors.ended_at && <p className="mt-1 text-sm text-red-600">{errors.ended_at}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Link to Planned Job (optional)</label>
                            <select
                                value={data.farm_job_id}
                                onChange={(e) => setData('farm_job_id', e.target.value)}
                                className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                            >
                                <option value="">Ad hoc work (no planned job)</option>
                                {plannedJobs.map((job) => (
                                    <option key={job.id} value={job.id}>{job.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                            />
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <Link
                            href={route('work-sessions.show', session.id)}
                            className="flex-1 py-4 border border-gray-300 text-gray-700 rounded-lg text-base text-center"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex-1 py-4 bg-green-600 text-white rounded-lg text-base font-medium disabled:opacity-50"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}