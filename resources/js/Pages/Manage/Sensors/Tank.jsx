import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    SensorMonthCalendar,
    SensorPageHeader,
    SensorStatusCard,
    formatDayHeading,
    formatTime,
    initialDay,
} from '@/Components/SensorMonth';

const litres = (value) => `${Math.round(value).toLocaleString()} L`;

// The tank drawn as a bar filled to its level.
function LevelBar({ percent, low }) {
    return (
        <div className="h-4 w-full rounded-full bg-gray-100 overflow-hidden">
            <div
                className={`h-full rounded-full ${low ? 'bg-amber-500' : 'bg-blue-500'}`}
                style={{ width: `${Math.max(0, Math.min(100, percent))}%` }}
            />
        </div>
    );
}

// A day's hourly levels on a 0-100% scale, midnight to midnight.
function DayChart({ points, day, warnBelow }) {
    const width = 300;
    const height = 100;
    const start = new Date(`${day}T00:00:00`).getTime();
    const x = (iso) => ((new Date(iso).getTime() - start) / 86400000) * width;
    const y = (percent) => height - (percent / 100) * height;
    const line = points.map(([at, percent], i) => `${i ? 'L' : 'M'}${x(at).toFixed(1)},${y(percent).toFixed(1)}`).join(' ');

    return (
        <svg viewBox={`0 -10 ${width} ${height + 24}`} className="w-full" role="img" aria-label="Level through the day">
            {[0, 50, 100].map((percent) => (
                <g key={percent}>
                    <line x1="0" x2={width} y1={y(percent)} y2={y(percent)} stroke="#e5e7eb" strokeWidth="1" />
                    <text x="2" y={y(percent) - 2} fontSize="8" fill="#9ca3af">{percent}%</text>
                </g>
            ))}
            {warnBelow > 0 && (
                <line x1="0" x2={width} y1={y(warnBelow)} y2={y(warnBelow)} stroke="#d97706" strokeWidth="1" strokeDasharray="4 3" />
            )}
            {points.length > 1 && <path d={line} fill="none" stroke="#2563eb" strokeWidth="2" strokeLinejoin="round" />}
            {points.map(([at, percent]) => (
                <circle key={at} cx={x(at)} cy={y(percent)} r="1.8" fill="#2563eb" />
            ))}
            {['12am', '6am', '12pm', '6pm'].map((label, i) => (
                <text key={label} x={(i * width) / 4 + 2} y={height + 11} fontSize="8" fill="#9ca3af">{label}</text>
            ))}
        </svg>
    );
}

export default function Tank({ device, status, month, today, days, levels, firstDate, settings }) {
    const [selected, setSelected] = useState(initialDay(month, today, days));
    const day = selected ? levels[selected] : null;
    const hasCapacity = settings.capacity > 0;

    const state = status && (!status.reporting
        ? { key: 'not_reporting', label: 'Not reporting' }
        : !status.configured
            ? { key: 'unknown', label: 'Tank size not set' }
            : status.low
                ? { key: 'low', label: `Low - ${Math.round(status.percent)}% full`, tone: 'attention' }
                : { key: 'ok', label: `${Math.round(status.percent)}% full`, tone: 'active' });

    return (
        <AuthenticatedLayout title={device.name}>
            <Head title={device.name} />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <SensorPageHeader device={device} />

                <SensorStatusCard
                    device={device}
                    state={state}
                    since={status && !status.reporting ? status.at : null}
                    today={today}
                    middle={status?.litres != null
                        ? { label: 'Water', value: litres(status.litres) }
                        : { label: 'Distance', value: status ? `${status.distance} mm` : '—' }}
                />

                {status?.configured && (
                    <div className="bg-white rounded-lg shadow p-4 space-y-2">
                        <div className="flex items-baseline justify-between text-sm">
                            <span className="font-semibold text-gray-900">{Math.round(status.percent)}% full</span>
                            {status.litres != null && (
                                <span className="text-gray-600">{litres(status.litres)} of {litres(settings.capacity)}</span>
                            )}
                        </div>
                        <LevelBar percent={status.percent} low={status.low} />
                        <p className="text-xs text-gray-500">Last reading {formatTime(status.at)}, {status.distance} mm down to the water.</p>
                    </div>
                )}

                {status && !status.configured && (
                    <div className="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                        <p className="font-medium">Set the tank's size to see its level</p>
                        <p className="mt-1">
                            The sensor measures the distance down to the water ({status.distance} mm just now). Enter the
                            distance when the tank is empty and when it's full, and its capacity, under{' '}
                            <Link href={route('manage.sensors.edit', device.id)} className="font-medium underline">Edit</Link>.
                        </p>
                    </div>
                )}

                {status?.configured && (
                    <>
                        <SensorMonthCalendar
                            device={device}
                            month={month}
                            values={days}
                            today={today}
                            firstDate={firstDate}
                            selected={selected}
                            onSelect={setSelected}
                            formatValue={(value) => `${Math.round(value)}%`}
                            summary="Level at the end of each day"
                        />

                        {selected && (
                            <div>
                                <h2 className="text-sm font-semibold text-gray-900 mb-2">{formatDayHeading(selected)}</h2>
                                {!day ? (
                                    <p className="text-sm text-gray-500">No readings this day.</p>
                                ) : (
                                    <div className="bg-white rounded-lg shadow p-4 space-y-3">
                                        <div className="grid grid-cols-3 gap-2 text-center">
                                            <div>
                                                <p className="text-xs text-gray-500">Lowest</p>
                                                <p className="text-sm text-gray-900">{Math.round(day.min)}%</p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-gray-500">Highest</p>
                                                <p className="text-sm text-gray-900">{Math.round(day.max)}%</p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-gray-500">{selected === today ? 'Now' : 'End of day'}</p>
                                                <p className="text-sm text-gray-900">
                                                    {Math.round(day.last)}%
                                                    {hasCapacity && day.last_litres != null && (
                                                        <span className="block text-xs text-gray-500">{litres(day.last_litres)}</span>
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <DayChart points={day.points} day={selected} warnBelow={settings.warn_below_percent} />
                                    </div>
                                )}
                            </div>
                        )}
                    </>
                )}

                {status?.configured && (
                    <p className="text-xs text-gray-400">
                        The level is worked out from the distance down to the water: {settings.empty_distance} mm when empty,{' '}
                        {settings.full_distance} mm when full
                        {settings.warn_below_percent > 0 && `, with a warning below ${settings.warn_below_percent}%`} - change
                        these under Edit.
                    </p>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
