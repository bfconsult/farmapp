import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    SensorMonthCalendar,
    SensorPageHeader,
    SensorStatusCard,
    formatDayHeading,
    formatDuration,
    formatTime,
    initialDay,
} from '@/Components/SensorMonth';

// Hours in a calendar cell: minutes under an hour, else one decimal.
function formatHours(seconds) {
    if (!seconds) {
        return '0';
    }
    if (seconds < 3600) {
        return `${Math.max(1, Math.round(seconds / 60))}m`;
    }

    return `${(seconds / 3600).toFixed(1)}h`;
}

function Run({ run, day }) {
    // Times on another day are dated, e.g. a run from before midnight.
    const at = (datetime) => {
        const date = new Date(datetime);
        const sameDay = date.toLocaleDateString('en-CA') === day;

        return sameDay ? formatTime(datetime) : date.toLocaleString([], { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
    };

    return (
        <div className="px-4 py-2.5">
            <div className="flex items-center justify-between gap-2 text-sm">
                <span className="text-gray-900">
                    {at(run.started_at)}
                    <span className="text-gray-400"> → </span>
                    {run.ended_at ? at(run.ended_at) : <span className="text-blue-700 font-medium">running now</span>}
                </span>
                <span className="text-gray-500 flex-shrink-0">{formatDuration(run.seconds)}</span>
            </div>
            <p className="text-xs text-gray-500">Average {run.average_current} A</p>
        </div>
    );
}

export default function Pump({ device, status, month, today, days, runs, firstDate, threshold, warnAfterMinutes }) {
    const [selected, setSelected] = useState(initialDay(month, today, days));

    // Running past its warning limit (0 = no limit).
    const runningMinutes = status?.running && status.since ? (Date.now() - new Date(status.since).getTime()) / 60000 : 0;
    const runningLong = warnAfterMinutes > 0 && runningMinutes > warnAfterMinutes;

    const total = Object.values(days).reduce((sum, seconds) => sum + seconds, 0);
    const dayRuns = runs.filter((run) => run.dates.includes(selected)).reverse();

    return (
        <AuthenticatedLayout title={device.name}>
            <Head title={device.name} />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <SensorPageHeader device={device} />

                <SensorStatusCard
                    device={device}
                    state={status && (status.running
                        ? { key: 'running', label: runningLong ? `Running ${formatDuration(runningMinutes * 60)}` : 'Running', tone: runningLong ? 'attention' : 'active' }
                        : { key: 'stopped', label: 'Stopped' })}
                    since={status?.since}
                    today={today}
                    middle={{ label: 'Current', value: status ? `${status.current} A` : '—' }}
                />

                <SensorMonthCalendar
                    device={device}
                    month={month}
                    values={days}
                    today={today}
                    firstDate={firstDate}
                    selected={selected}
                    onSelect={setSelected}
                    formatValue={formatHours}
                    summary={total === 0 ? 'Didn\'t run this month' : `Ran ${formatDuration(total)} this month`}
                />

                {selected && (
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900 mb-2">
                            {formatDayHeading(selected)}
                            <span className="font-normal text-gray-500">
                                {' · '}{days[selected] ? `ran ${formatDuration(days[selected])}` : 'didn\'t run'}
                            </span>
                        </h2>
                        {dayRuns.length === 0 ? (
                            <p className="text-sm text-gray-500">No runs this day.</p>
                        ) : (
                            <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                                {dayRuns.map((run) => (
                                    <Run key={run.started_at} run={run} day={selected} />
                                ))}
                            </div>
                        )}
                    </div>
                )}

                <p className="text-xs text-gray-400">
                    Running means drawing more than {threshold} A
                    {warnAfterMinutes > 0 && `, with a warning after ${warnAfterMinutes} min`} - change these under Edit.
                    Start and stop times are as accurate as the sensor's reporting interval.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
