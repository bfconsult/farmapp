import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { signalLabel, timeAgo } from '@/Utils/sensorFormat';

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function pad(number) {
    return String(number).padStart(2, '0');
}

function shiftMonth(month, by) {
    const [year, mon] = month.split('-').map(Number);
    const date = new Date(year, mon - 1 + by, 1);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

// A 'YYYY-MM-DD' day as a local date, for labels only.
function dayDate(day) {
    const [year, mon, date] = day.split('-').map(Number);

    return new Date(year, mon - 1, date);
}

function formatTime(datetime) {
    return new Date(datetime).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function formatDuration(seconds) {
    if (seconds < 60) {
        return 'under a minute';
    }
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `${minutes} min`;
    }

    return `${Math.floor(minutes / 60)} h ${minutes % 60} min`;
}

// When the current state began: just the time if it was today.
function formatSince(datetime, today) {
    const date = new Date(datetime);
    const day = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    if (day === today) {
        return formatTime(datetime);
    }

    return date.toLocaleString([], { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
}

// Busier days darker, relative to the month's busiest.
function shade(count, max) {
    if (!count) {
        return 'bg-white text-gray-400';
    }
    const level = count / max;
    if (level > 0.75) {
        return 'bg-green-600 text-white';
    }
    if (level > 0.5) {
        return 'bg-green-400 text-white';
    }
    if (level > 0.25) {
        return 'bg-green-200 text-green-900';
    }

    return 'bg-green-100 text-green-900';
}

function Calendar({ month, days, today, firstDate, selected, onSelect }) {
    const [year, mon] = month.split('-').map(Number);
    const daysInMonth = new Date(year, mon, 0).getDate();
    const offset = (new Date(year, mon - 1, 1).getDay() + 6) % 7;
    const max = Math.max(1, ...Object.values(days));

    const cells = [];
    for (let i = 0; i < offset; i++) {
        cells.push(<div key={`blank-${i}`} />);
    }
    for (let date = 1; date <= daysInMonth; date++) {
        const day = `${month}-${pad(date)}`;
        // Before the sensor was installed, or still to come: nothing to count.
        const hasData = firstDate !== null && day >= firstDate && day <= today;
        const count = days[day] ?? 0;

        cells.push(
            <button
                key={day}
                type="button"
                disabled={!hasData}
                onClick={() => onSelect(day)}
                className={`aspect-square rounded-md flex flex-col items-center justify-center border ${
                    hasData ? shade(count, max) : 'bg-gray-50 text-gray-300 border-transparent'
                } ${selected === day ? 'ring-2 ring-green-700 ring-offset-1' : ''} ${
                    day === today ? 'border-green-700' : 'border-gray-100'
                }`}
            >
                <span className="text-[10px] leading-none opacity-75">{date}</span>
                <span className="text-sm font-semibold leading-tight">{hasData ? count : ''}</span>
            </button>
        );
    }

    return (
        <div className="grid grid-cols-7 gap-1">
            {WEEKDAYS.map((weekday) => (
                <div key={weekday} className="text-center text-[11px] text-gray-500 pb-1">{weekday}</div>
            ))}
            {cells}
        </div>
    );
}

function Opening({ opening }) {
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
                    {opening.closed_at ? formatTime(opening.closed_at) : <span className="text-gray-500">close not received</span>}
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

export default function Show({ device, status, month, today, days, openings, firstDate }) {
    const isCurrentMonth = today.startsWith(month);
    const activeDays = Object.keys(days).filter((day) => days[day] > 0).sort();
    const [selected, setSelected] = useState(isCurrentMonth ? today : (activeDays[activeDays.length - 1] ?? null));

    // The open uplink of an opening still in progress; it shows as open now.
    const isOpenNow = (opening) => status?.open && !opening.closed_at && opening === openings[openings.length - 1];

    const total = Object.values(days).reduce((sum, count) => sum + count, 0);
    const busiest = activeDays.reduce((best, day) => (best === null || days[day] > days[best] ? day : best), null);
    const dayOpenings = openings.filter((opening) => opening.date === selected).reverse();

    const goToMonth = (target) => {
        router.get(route('manage.sensors.show', device.id), { month: target }, { preserveScroll: true });
    };
    const canGoBack = firstDate !== null && shiftMonth(month, -1) >= firstDate.slice(0, 7);

    const signal = signalLabel(device.last_rssi, device.last_snr);

    return (
        <AuthenticatedLayout title={device.name}>
            <Head title={device.name} />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <div className="flex items-center justify-between">
                    <BackLink href={route('manage.sensors.index')}>Sensors</BackLink>
                    <div className="flex items-center gap-4 text-sm">
                        <Link href={route('manage.sensors.trace', device.id)} className="text-gray-500">
                            Trace
                        </Link>
                        <Link href={route('manage.sensors.edit', device.id)} className="text-green-600 font-medium">
                            Edit
                        </Link>
                    </div>
                </div>

                <h1 className="text-lg font-semibold text-gray-900">{device.name}</h1>

                <div className="bg-white rounded-lg shadow p-4">
                    {status ? (
                        <div className="flex items-center gap-3">
                            <span
                                className={`px-3 py-1 rounded-full text-sm font-semibold ${
                                    status.open ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700'
                                }`}
                            >
                                {status.open ? 'Open' : 'Closed'}
                            </span>
                            {status.since && (
                                <span className="text-sm text-gray-600">since {formatSince(status.since, today)}</span>
                            )}
                        </div>
                    ) : (
                        <p className="text-sm text-gray-500">Nothing received from this sensor yet.</p>
                    )}
                    <div className="mt-3 pt-3 border-t border-gray-100 grid grid-cols-3 gap-2 text-center">
                        <div>
                            <p className="text-xs text-gray-500">Last seen</p>
                            <p className="text-sm text-gray-900">{timeAgo(device.last_seen_at)}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Battery</p>
                            <p className="text-sm text-gray-900">{device.battery !== null ? `${device.battery.toFixed(2)} V` : '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">Signal</p>
                            <p className="text-sm text-gray-900">{signal ? signal.split(' (')[0] : '—'}</p>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-lg shadow p-4">
                    <div className="flex items-center justify-between mb-1">
                        <button
                            type="button"
                            onClick={() => goToMonth(shiftMonth(month, -1))}
                            disabled={!canGoBack}
                            className="px-2 text-lg text-gray-600 disabled:text-gray-200"
                            aria-label="Previous month"
                        >
                            ‹
                        </button>
                        <h2 className="text-sm font-semibold text-gray-900">
                            {dayDate(`${month}-01`).toLocaleDateString([], { month: 'long', year: 'numeric' })}
                        </h2>
                        <button
                            type="button"
                            onClick={() => goToMonth(shiftMonth(month, 1))}
                            disabled={isCurrentMonth}
                            className="px-2 text-lg text-gray-600 disabled:text-gray-200"
                            aria-label="Next month"
                        >
                            ›
                        </button>
                    </div>
                    <p className="text-xs text-gray-500 text-center mb-3">
                        {total === 0
                            ? 'No openings this month'
                            : `Opened ${total} time${total === 1 ? '' : 's'}` +
                              (busiest ? ` · busiest ${dayDate(busiest).toLocaleDateString([], { weekday: 'short', day: 'numeric' })} (${days[busiest]})` : '')}
                    </p>

                    <Calendar
                        month={month}
                        days={days}
                        today={today}
                        firstDate={firstDate}
                        selected={selected}
                        onSelect={setSelected}
                    />
                </div>

                {selected && (
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900 mb-2">
                            {dayDate(selected).toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' })}
                            <span className="font-normal text-gray-500">
                                {' · '}opened {days[selected] ?? 0} time{(days[selected] ?? 0) === 1 ? '' : 's'}
                            </span>
                        </h2>
                        {dayOpenings.length === 0 ? (
                            <p className="text-sm text-gray-500">No openings this day.</p>
                        ) : (
                            <div className="bg-white rounded-lg shadow divide-y divide-gray-100 overflow-hidden">
                                {dayOpenings.map((opening, index) => (
                                    isOpenNow(opening) ? (
                                        <div key={index} className="px-4 py-2.5 flex items-center justify-between text-sm">
                                            <span className="text-gray-900">
                                                {formatTime(opening.opened_at)}
                                                <span className="text-gray-400"> → </span>
                                                <span className="text-amber-700 font-medium">open now</span>
                                            </span>
                                        </div>
                                    ) : (
                                        <Opening key={index} opening={opening} />
                                    )
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
