import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    SensorMonthCalendar,
    SensorPageHeader,
    SensorStatusCard,
    dayDate,
    formatDayHeading,
    formatDuration,
    formatTime,
    initialDay,
} from '@/Components/SensorMonth';

function Opening({ opening, isOpenNow }) {
    if (!opening.opened_at) {
        return (
            <div className="px-4 py-2.5 text-sm text-gray-500">
                {opening.missed} more opening{opening.missed === 1 ? '' : 's'} counted by the sensor, times not received
            </div>
        );
    }

    return (
        <div className="px-4 py-2.5">
            <div className="flex items-center justify-between gap-2 text-sm">
                <span className="text-gray-900">
                    {formatTime(opening.opened_at)}
                    <span className="text-gray-400"> → </span>
                    {isOpenNow ? (
                        <span className="text-amber-700 font-medium">open now</span>
                    ) : opening.closed_at ? (
                        formatTime(opening.closed_at)
                    ) : (
                        <span className="text-gray-500">close not received</span>
                    )}
                </span>
                <span className="text-gray-500 flex-shrink-0">
                    {opening.seconds !== null ? formatDuration(opening.seconds) : ''}
                </span>
            </div>
            {opening.missed > 0 && (
                <p className="text-xs text-gray-500">
                    +{opening.missed} more opening{opening.missed === 1 ? '' : 's'} counted by the sensor, times not received
                </p>
            )}
        </div>
    );
}

export default function Gate({ device, status, month, today, days, openings, firstDate }) {
    const [selected, setSelected] = useState(initialDay(month, today, days));

    // The last opening, with no close yet, while the gate reads open.
    const openNow = status?.open ? openings[openings.length - 1] : null;

    const total = Object.values(days).reduce((sum, count) => sum + count, 0);
    const busiest = Object.keys(days).reduce((best, day) => (days[day] > (days[best] ?? 0) ? day : best), null);
    const dayOpenings = openings.filter((opening) => opening.date === selected).reverse();

    return (
        <AuthenticatedLayout title={device.name}>
            <Head title={device.name} />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <SensorPageHeader device={device} />

                <SensorStatusCard
                    device={device}
                    state={status && (status.open
                        ? { key: 'open', label: 'Open', tone: 'attention' }
                        : { key: 'closed', label: 'Closed' })}
                    since={status?.since}
                    today={today}
                    middle={{ label: 'Battery', value: device.battery !== null ? `${device.battery.toFixed(2)} V` : '—' }}
                />

                <SensorMonthCalendar
                    device={device}
                    month={month}
                    values={days}
                    today={today}
                    firstDate={firstDate}
                    selected={selected}
                    onSelect={setSelected}
                    formatValue={(count) => count}
                    summary={total === 0
                        ? 'No openings this month'
                        : `Opened ${total} time${total === 1 ? '' : 's'}` +
                          (busiest ? ` · busiest ${dayDate(busiest).toLocaleDateString([], { weekday: 'short', day: 'numeric' })} (${days[busiest]})` : '')}
                />

                {selected && (
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900 mb-2">
                            {formatDayHeading(selected)}
                            <span className="font-normal text-gray-500">
                                {' · '}opened {days[selected] ?? 0} time{(days[selected] ?? 0) === 1 ? '' : 's'}
                            </span>
                        </h2>
                        {dayOpenings.length === 0 ? (
                            <p className="text-sm text-gray-500">No openings this day.</p>
                        ) : (
                            <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                                {dayOpenings.map((opening, index) => (
                                    <Opening
                                        key={index}
                                        opening={opening}
                                        isOpenNow={opening === openNow && !opening.closed_at}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
