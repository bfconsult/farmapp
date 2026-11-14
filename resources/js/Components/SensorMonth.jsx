import { Link, router } from '@inertiajs/react';
import BackLink from '@/Components/BackLink';
import { SensorStateIcon } from '@/Utils/sensorIcons';
import { signalLabel, timeAgo } from '@/Utils/sensorFormat';

// The parts every sensor kind's page shares (Manage/Sensors/Gate, Pump):
// header, status card and a month calendar of one figure per day.

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function pad(number) {
    return String(number).padStart(2, '0');
}

export function shiftMonth(month, by) {
    const [year, mon] = month.split('-').map(Number);
    const date = new Date(year, mon - 1 + by, 1);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

// A 'YYYY-MM-DD' day as a local date, for labels only.
export function dayDate(day) {
    const [year, mon, date] = day.split('-').map(Number);

    return new Date(year, mon - 1, date);
}

export function formatDayHeading(day) {
    return dayDate(day).toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' });
}

export function formatTime(datetime) {
    return new Date(datetime).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

export function formatDuration(seconds) {
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
function shade(value, max) {
    if (!value) {
        return 'bg-white text-gray-400';
    }
    const level = value / max;
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

export function SensorPageHeader({ device }) {
    return (
        <>
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
        </>
    );
}

/**
 * `state` is {key, label, tone} or null before anything is reported;
 * `middle` is the centre figure under it (battery for a gate, current
 * for a pump).
 */
export function SensorStatusCard({ device, state, since, today, middle }) {
    const signal = signalLabel(device.last_rssi, device.last_snr);

    return (
        <div className="bg-white rounded-lg shadow p-4">
            {device.low_battery_since && (
                <div className="mb-3 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                    <p className="font-medium">Low battery</p>
                    <p className="text-xs">
                        Reported {formatSince(device.low_battery_since, today)}. Replace the battery soon - this
                        clears when the sensor restarts.
                    </p>
                </div>
            )}
            {state ? (
                <div className="flex items-center gap-3">
                    <SensorStateIcon kind={device.kind} state={state} size={36} />
                    <span
                        className={`px-3 py-1 rounded-full text-sm font-semibold ${
                            {
                                attention: 'bg-amber-100 text-amber-800',
                                active: 'bg-blue-100 text-blue-800',
                            }[state.tone] ?? 'bg-gray-100 text-gray-700'
                        }`}
                    >
                        {state.label}
                    </span>
                    {since && <span className="text-sm text-gray-600">since {formatSince(since, today)}</span>}
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
                    <p className="text-xs text-gray-500">{middle.label}</p>
                    <p className="text-sm text-gray-900">{middle.value}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500">Signal</p>
                    <p className="text-sm text-gray-900">{signal ? signal.split(' (')[0] : '—'}</p>
                </div>
            </div>
        </div>
    );
}

/**
 * A month of one figure per day (`values`: {'YYYY-MM-DD': number}), shaded
 * by size, with month navigation. `formatValue` turns a day's figure into
 * the text in its cell; `summary` is the line under the month name.
 */
export function SensorMonthCalendar({ device, month, values, today, firstDate, selected, onSelect, formatValue, summary }) {
    const [year, mon] = month.split('-').map(Number);
    const daysInMonth = new Date(year, mon, 0).getDate();
    const offset = (new Date(year, mon - 1, 1).getDay() + 6) % 7;
    const max = Math.max(1, ...Object.values(values));
    const isCurrentMonth = today.startsWith(month);
    const canGoBack = firstDate !== null && shiftMonth(month, -1) >= firstDate.slice(0, 7);

    const goToMonth = (target) => {
        router.get(route('manage.sensors.show', device.id), { month: target }, { preserveScroll: true });
    };

    const cells = [];
    for (let i = 0; i < offset; i++) {
        cells.push(<div key={`blank-${i}`} />);
    }
    for (let date = 1; date <= daysInMonth; date++) {
        const day = `${month}-${pad(date)}`;
        // Before the sensor was installed, or still to come: nothing to count.
        const hasData = firstDate !== null && day >= firstDate && day <= today;
        const value = values[day] ?? 0;

        cells.push(
            <button
                key={day}
                type="button"
                disabled={!hasData}
                onClick={() => onSelect(day)}
                className={`aspect-square rounded-md flex flex-col items-center justify-center border ${
                    hasData ? shade(value, max) : 'bg-gray-50 text-gray-300 border-transparent'
                } ${selected === day ? 'ring-2 ring-green-700 ring-offset-1' : ''} ${
                    day === today ? 'border-green-700' : 'border-gray-100'
                }`}
            >
                <span className="text-[10px] leading-none opacity-75">{date}</span>
                <span className="text-sm font-semibold leading-tight">{hasData ? formatValue(value) : ''}</span>
            </button>
        );
    }

    return (
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
            <p className="text-xs text-gray-500 text-center mb-3">{summary}</p>

            <div className="grid grid-cols-7 gap-1">
                {WEEKDAYS.map((weekday) => (
                    <div key={weekday} className="text-center text-[11px] text-gray-500 pb-1">{weekday}</div>
                ))}
                {cells}
            </div>
        </div>
    );
}

// The day initially selected: today in the current month, otherwise the
// month's last day with anything on it.
export function initialDay(month, today, values) {
    if (today.startsWith(month)) {
        return today;
    }
    const active = Object.keys(values).filter((day) => values[day] > 0).sort();

    return active[active.length - 1] ?? null;
}
