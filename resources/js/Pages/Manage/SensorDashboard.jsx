import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { SensorStateIcon } from '@/Utils/sensorIcons';
import { timeAgo } from '@/Utils/sensorFormat';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import ReactGridLayout, { useContainerWidth } from 'react-grid-layout';
import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';

// The sensor dashboard: a tile per tank, gate and pump sensor (built by
// App\Services\Lorawan\SensorTiles), which each person drags and resizes
// for themselves on a wide screen. Their layout is kept for four columns;
// narrower screens flow the tiles in the same order. Tiles come in four
// sizes, each designed for: small (1x1), tall (1x2 - small with a calendar
// of the last five weeks under it), wide (2x1 - small with the calendar
// beside it) and large (2x2).

const ROW_HEIGHT = 150;
const MARGIN = [12, 12];
const REFRESH_SECONDS = 60;

function columnsFor(width) {
    if (width >= 1000) return 4;
    if (width >= 600) return 2;
    return 1;
}

// 'S', 'T', 'M' or 'L' from a layout item's size.
const sizeOf = (item) => {
    if (!item) return 'S';
    if (item.w === 1) return item.h === 2 ? 'T' : 'S';
    return item.h === 2 ? 'L' : 'M';
};

// One or two columns wide, one or two rows high.
const clamp = (n) => Math.min(2, Math.max(1, n));
const snap = (item) => ({ ...item, w: clamp(item.w), h: clamp(item.h) });

// The saved four-column layout for the tiles there are now: saved ones as
// they were, new ones added small at the end.
function fourColumnLayout(tiles, saved) {
    const ids = new Set(tiles.map((tile) => String(tile.id)));
    const kept = (saved ?? []).filter((item) => ids.has(item.i)).map(snap);
    const placed = new Set(kept.map((item) => item.i));
    let y = kept.reduce((bottom, item) => Math.max(bottom, item.y + item.h), 0);
    let x = 0;
    const added = tiles
        .filter((tile) => !placed.has(String(tile.id)))
        .map((tile) => {
            const item = { i: String(tile.id), x, y, w: 1, h: 1 };
            x += 1;
            if (x >= 4) { x = 0; y += 1; }
            return item;
        });

    return [...kept, ...added];
}

// The same tiles in the same order for fewer columns: each placed at the
// lowest free spot, no wider than the grid.
function flowLayout(layout, cols) {
    const heights = Array(cols).fill(0);
    return [...layout]
        .sort((a, b) => a.y - b.y || a.x - b.x)
        .map((item) => {
            const w = Math.min(item.w, cols);
            let best = 0;
            let bestTop = Infinity;
            for (let x = 0; x + w <= cols; x++) {
                const top = Math.max(...heights.slice(x, x + w));
                if (top < bestTop) { bestTop = top; best = x; }
            }
            for (let x = best; x < best + w; x++) heights[x] = bestTop + item.h;
            return { ...item, x: best, y: bestTop, w };
        });
}

export default function SensorDashboard({ tiles, layout: savedLayout, canManage }) {
    const { width, containerRef, mounted } = useContainerWidth();
    const cols = columnsFor(width);
    const [layout, setLayout] = useState(() => fourColumnLayout(tiles, savedLayout));
    const [editing, setEditing] = useState(false);
    const [before, setBefore] = useState(null);

    // New or removed sensors since the page loaded (on a refresh).
    useEffect(() => {
        if (!editing) setLayout((current) => fourColumnLayout(tiles, current));
    }, [tiles]); // eslint-disable-line react-hooks/exhaustive-deps

    // Fresh readings every minute, unless the layout's being changed.
    useEffect(() => {
        if (editing) return undefined;
        const timer = setInterval(() => router.reload({ only: ['tiles'] }), REFRESH_SECONDS * 1000);
        return () => clearInterval(timer);
    }, [editing]);

    const shown = useMemo(() => (cols === 4 ? layout : flowLayout(layout, cols)), [layout, cols]);
    const sizes = useMemo(() => Object.fromEntries(layout.map((item) => [item.i, sizeOf(item)])), [layout]);
    const byId = useMemo(() => Object.fromEntries(tiles.map((tile) => [String(tile.id), tile])), [tiles]);

    const startEditing = () => {
        setBefore(layout);
        setEditing(true);
    };
    const cancel = () => {
        setLayout(before ?? layout);
        setEditing(false);
    };
    const save = () => {
        router.put(route('manage.sensor-dashboard.layout'), { layout }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    };

    return (
        <AuthenticatedLayout title="Sensor dashboard">
            <Head title="Sensor dashboard" />

            <div className="max-w-6xl mx-auto mt-2 pb-24 px-1">
                <div className="flex items-center justify-between gap-2 mb-3">
                    <BackLink href={route('manage.index')}>Manage</BackLink>
                    {tiles.length > 0 && cols === 4 && (
                        editing ? (
                            <div className="flex gap-2">
                                <button onClick={cancel} className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-700">Cancel</button>
                                <button onClick={save} className="px-3 py-1.5 text-sm rounded-lg bg-green-600 text-white">Done</button>
                            </div>
                        ) : (
                            <button onClick={startEditing} className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-700">
                                Edit layout
                            </button>
                        )
                    )}
                </div>
                {editing && (
                    <p className="text-xs text-gray-500 mb-2">
                        Drag a tile to move it; drag its corner to make it small, tall (with a calendar), wide or large. Done saves the layout - it's just yours.
                    </p>
                )}
                {!editing && tiles.length > 0 && cols < 4 && (
                    <p className="text-xs text-gray-400 mb-2">The tiles can be arranged on a wider screen.</p>
                )}

                {tiles.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No tank, gate or pump sensors on this property yet.
                    </div>
                ) : (
                    <div ref={containerRef}>
                        {mounted && (
                            <ReactGridLayout
                                width={width}
                                layout={shown}
                                gridConfig={{ cols, rowHeight: ROW_HEIGHT, margin: MARGIN, containerPadding: [0, 0] }}
                                dragConfig={{ enabled: editing, threshold: 4 }}
                                resizeConfig={{ enabled: editing, handles: ['se'] }}
                                onLayoutChange={(next) => {
                                    if (editing && cols === 4) setLayout(next.map((item) => snap({ i: item.i, x: item.x, y: item.y, w: item.w, h: item.h })));
                                }}
                            >
                                {shown.map((item) => (
                                    <div key={item.i}>
                                        <Tile tile={byId[item.i]} size={sizes[item.i]} editing={editing} canManage={canManage} />
                                    </div>
                                ))}
                            </ReactGridLayout>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

// --- Tiles -----------------------------------------------------------------

function Tile({ tile, size, editing, canManage }) {
    if (!tile) return null;
    const Body = { tank: TankTile, gate: GateTile, pump: PumpTile }[tile.kind];
    const frame = (
        <div className={`h-full flex flex-col bg-white rounded-lg shadow p-3 overflow-hidden ${editing ? 'outline-dashed outline-2 outline-green-400 cursor-move select-none' : ''}`}>
            <div className="flex items-center gap-2 min-w-0">
                <SensorStateIcon kind={tile.kind} state={tile.reporting ? tile.state : null} size={22} />
                <p className="text-sm font-semibold text-gray-800 truncate flex-1">{tile.name}</p>
                {!tile.reporting && (
                    <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 flex-shrink-0">Not reporting</span>
                )}
            </div>
            <div className="flex-1 min-h-0 mt-2 flex flex-col">
                {size === 'T' ? (
                    <>
                        <div className="h-[92px] flex-none">{Body ? <Body tile={tile} size="S" /> : null}</div>
                        <div className="flex-1 min-h-0 border-t border-gray-100 pt-2">
                            <ActivityCalendar tile={tile} />
                        </div>
                    </>
                ) : size === 'M' ? (
                    <div className="h-full grid grid-cols-2 gap-3">
                        <div className="min-w-0">{Body ? <Body tile={tile} size="S" /> : null}</div>
                        <div className="min-w-0 border-l border-gray-100 pl-3">
                            <ActivityCalendar tile={tile} compact />
                        </div>
                    </div>
                ) : (
                    Body ? <Body tile={tile} size={size} /> : null
                )}
            </div>
            {size === 'L' && (
                <p className="text-[11px] text-gray-400 mt-1">
                    Last reading {timeAgo(tile.last_reading_at)}
                    {tile.battery !== null && tile.battery !== undefined && ` · Battery ${tile.battery} V`}
                </p>
            )}
        </div>
    );

    // Opens the sensor's own page - for managers, and not while arranging.
    return canManage && !editing
        ? <Link href={route('manage.sensors.show', tile.id)} className="block h-full">{frame}</Link>
        : frame;
}

function TankTile({ tile, size }) {
    const percent = tile.percent;
    const colour = tile.low ? 'bg-amber-500' : 'bg-sky-500';
    if (!tile.configured) {
        return <p className="text-sm text-gray-500">Set the tank's size on its page to show how full it is.</p>;
    }

    const level = (
        <div className="relative w-10 h-full min-h-[60px] rounded-md bg-gray-100 border border-gray-200 overflow-hidden flex-shrink-0">
            <div className={`absolute bottom-0 left-0 right-0 ${colour}`} style={{ height: `${percent ?? 0}%` }} />
        </div>
    );
    const figures = (
        <div className="min-w-0">
            <p className={`text-3xl font-bold ${tile.low ? 'text-amber-600' : 'text-gray-900'}`}>{percent !== null ? `${Math.round(percent)}%` : '—'}</p>
            {tile.litres !== null && (
                <p className="text-sm text-gray-600">
                    {tile.litres.toLocaleString()} L{size !== 'S' && tile.capacity ? ` of ${Math.round(tile.capacity).toLocaleString()} L` : ''}
                </p>
            )}
            {size !== 'S' && <p className="text-xs text-gray-500 mt-1">{describeChange(tile.change_per_hour, percent)}</p>}
            {tile.low && <p className="text-xs font-medium text-amber-600 mt-1">Low</p>}
        </div>
    );

    if (size === 'L') {
        return (
            <div className="h-full flex flex-col gap-2">
                <div className="flex gap-3 h-[45%]">{level}{figures}</div>
                <div className="flex-1 min-h-0">
                    <p className="text-[11px] text-gray-400">Last 7 days</p>
                    <LevelChart points={tile.levels} low={tile.low} />
                </div>
            </div>
        );
    }

    return <div className="flex gap-3 h-full">{level}{figures}</div>;
}

// "Falling 1.2% an hour - about 2 days left", "Rising", "Steady".
function describeChange(perHour, percent) {
    if (perHour === null || perHour === undefined) return 'Not enough recent readings to show a trend';
    if (Math.abs(perHour) < 0.1) return 'Steady';
    if (perHour > 0) return `Rising ${perHour.toFixed(1)}% an hour`;
    const hoursLeft = percent / -perHour;
    const left = hoursLeft < 48 ? `${Math.round(hoursLeft)} h` : `${Math.round(hoursLeft / 24)} days`;
    return `Falling ${(-perHour).toFixed(1)}% an hour - about ${left} left`;
}

function GateTile({ tile, size }) {
    const open = tile.open;
    const word = open === null ? '—' : open ? 'Open' : 'Closed';
    return (
        <div className="h-full flex flex-col gap-2">
            <div>
                <p className={`text-2xl font-bold ${open ? 'text-amber-600' : 'text-green-700'}`}>{word}</p>
                {tile.since && <p className="text-sm text-gray-600">for {sinceText(tile.since)}</p>}
            </div>
            {size !== 'S' && (
                <p className="text-xs text-gray-500">
                    Opened {tile.openings_today} time{tile.openings_today === 1 ? '' : 's'} today
                </p>
            )}
            {size === 'L' && (
                <div className="flex-1 min-h-0 flex flex-col gap-2">
                    <div>
                        <p className="text-[11px] text-gray-400 mb-1">Open today</p>
                        <DayTimeline periods={tile.open_periods_today} colour="bg-amber-400" />
                    </div>
                    <div className="flex-1 min-h-0">
                        <p className="text-[11px] text-gray-400">Openings, last 7 days</p>
                        <DayBars days={tile.openings_by_day} colour="bg-amber-400" format={(n) => `${n}`} />
                    </div>
                </div>
            )}
        </div>
    );
}

function PumpTile({ tile, size }) {
    const running = tile.running;
    const word = running === null ? '—' : running ? 'Running' : 'Stopped';
    return (
        <div className="h-full flex flex-col gap-2">
            <div>
                <p className={`text-2xl font-bold ${running ? 'text-blue-600' : 'text-gray-700'}`}>{word}</p>
                {tile.since && <p className="text-sm text-gray-600">for {sinceText(tile.since)}</p>}
            </div>
            {size !== 'S' && (
                <p className="text-xs text-gray-500">
                    Ran {formatDuration(tile.seconds_today)} today
                    {running && tile.current !== null && ` · drawing ${Number(tile.current).toFixed(1)} A`}
                </p>
            )}
            {size === 'L' && (
                <div className="flex-1 min-h-0 flex flex-col gap-2">
                    <div>
                        <p className="text-[11px] text-gray-400 mb-1">Running today</p>
                        <DayTimeline periods={tile.runs_today} colour="bg-blue-500" />
                    </div>
                    <div className="flex-1 min-h-0">
                        <p className="text-[11px] text-gray-400">Hours run, last 7 days</p>
                        <DayBars days={tile.seconds_by_day} colour="bg-blue-500" format={(s) => (s ? `${(s / 3600).toFixed(1)}` : '0')} />
                    </div>
                </div>
            )}
        </div>
    );
}

// --- Little charts ---------------------------------------------------------

// The tank's level over the week, 0-100%.
function LevelChart({ points, low }) {
    if (!points?.length) return <p className="text-xs text-gray-400">No readings this week.</p>;
    const start = new Date(points[0].at).getTime();
    const end = Math.max(new Date(points[points.length - 1].at).getTime(), start + 1);
    const line = points.map((p) => `${((new Date(p.at).getTime() - start) / (end - start)) * 100},${100 - p.percent}`).join(' ');
    return (
        <svg viewBox="0 0 100 100" preserveAspectRatio="none" className="w-full h-[calc(100%-1rem)]">
            {[25, 50, 75].map((y) => <line key={y} x1="0" x2="100" y1={y} y2={y} stroke="#e5e7eb" strokeWidth="1" vectorEffect="non-scaling-stroke" />)}
            <polygon points={`0,100 ${line} 100,100`} fill={low ? '#fde68a' : '#bae6fd'} opacity="0.6" />
            <polyline points={line} fill="none" stroke={low ? '#d97706' : '#0284c7'} strokeWidth="2" vectorEffect="non-scaling-stroke" />
        </svg>
    );
}

// Today from midnight to now, with each period shaded.
function DayTimeline({ periods, colour }) {
    const dayStart = new Date();
    dayStart.setHours(0, 0, 0, 0);
    const now = Date.now();
    const span = 24 * 3600 * 1000;
    const at = (iso) => Math.min(100, Math.max(0, ((new Date(iso).getTime() - dayStart.getTime()) / span) * 100));
    return (
        <div>
            <div className="relative h-4 rounded bg-gray-100 overflow-hidden">
                {(periods ?? []).map((period, i) => {
                    const from = period.from ? at(period.from) : 0;
                    const to = period.to ? at(period.to) : at(new Date(now).toISOString());
                    return <div key={i} className={`absolute top-0 bottom-0 ${colour}`} style={{ left: `${from}%`, width: `${Math.max(0.6, to - from)}%` }} />;
                })}
                <div className="absolute top-0 bottom-0 w-px bg-gray-400" style={{ left: `${at(new Date(now).toISOString())}%` }} />
            </div>
            <div className="flex justify-between text-[10px] text-gray-400 mt-0.5">
                <span>12am</span><span>6am</span><span>12pm</span><span>6pm</span><span>12am</span>
            </div>
        </div>
    );
}

// A bar per day for the last week, labelled with the day's initial.
function DayBars({ days, colour, format }) {
    const most = Math.max(1, ...(days ?? []).map((day) => day.value));
    return (
        <div className="flex items-end gap-1 h-[calc(100%-1rem)]">
            {(days ?? []).map((day) => (
                <div key={day.date} className="flex-1 h-full flex flex-col items-center justify-end" title={`${day.date}: ${format(day.value)}`}>
                    <span className="text-[10px] text-gray-500">{day.value ? format(day.value) : ''}</span>
                    <div className={`w-full rounded-t ${colour}`} style={{ height: `${(day.value / most) * 70}%`, minHeight: day.value ? 2 : 0 }} />
                    <span className="text-[10px] text-gray-400">
                        {new Date(`${day.date}T12:00:00`).toLocaleDateString([], { weekday: 'narrow' })}
                    </span>
                </div>
            ))}
        </div>
    );
}

// The last five weeks, a square per day (weeks from Monday), shaded by the
// day's activity: a gate's openings, a pump's hours running, a tank's
// lowest level (amber below its warning level).
// `compact` (beside a wide tile's figures, in one row's height) leaves out
// the day numbers, which don't fit.
function ActivityCalendar({ tile, compact = false }) {
    const days = tile.calendar ?? [];
    if (!days.length) return null;
    const today = days[days.length - 1].date;
    const most = Math.max(1, ...days.map((day) => day.value ?? 0));
    const weeks = [];
    days.forEach((day, i) => {
        if (i % 7 === 0) weeks.push([]);
        weeks[weeks.length - 1].push(day);
    });
    while (weeks[weeks.length - 1].length < 7) weeks[weeks.length - 1].push(null);

    const describe = {
        gate: (v) => `${v} opening${v === 1 ? '' : 's'}`,
        pump: (v) => `${(v / 3600).toFixed(1)} h running`,
        tank: (v) => `lowest ${Math.round(v)}%`,
    }[tile.kind] ?? ((v) => `${v}`);
    const caption = { gate: 'Openings a day', pump: 'Hours run a day', tank: 'Lowest level each day' }[tile.kind];

    const shade = (value) => {
        if (value === null || value === undefined) return { backgroundColor: '#f3f4f6' };
        if (tile.kind === 'tank') {
            const low = tile.warn_below && value < tile.warn_below;
            return { backgroundColor: low ? '#f59e0b' : `rgba(2, 132, 199, ${0.15 + 0.85 * (value / 100)})` };
        }
        if (!value) return { backgroundColor: '#e5e7eb' };
        const strength = 0.25 + 0.75 * (value / most);
        return { backgroundColor: tile.kind === 'gate' ? `rgba(245, 158, 11, ${strength})` : `rgba(37, 99, 235, ${strength})` };
    };
    const month = (date) => new Date(`${date}T12:00:00`).toLocaleDateString([], { month: 'short' });
    const months = month(days[0].date) === month(today) ? month(today) : `${month(days[0].date)} - ${month(today)}`;

    return (
        <div className="h-full flex flex-col">
            <div className="flex justify-between text-[10px] text-gray-400 mb-1">
                <span>{caption}</span>
                <span>{months}</span>
            </div>
            <div className="grid grid-cols-7 gap-[3px] text-[9px] text-gray-400 text-center mb-[3px]">
                {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((d, i) => <span key={i}>{d}</span>)}
            </div>
            <div className="flex-1 min-h-0 grid gap-[3px]" style={{ gridTemplateRows: `repeat(${weeks.length}, minmax(0, 1fr))` }}>
                {weeks.map((week, w) => (
                    <div key={w} className="grid grid-cols-7 gap-[3px]">
                        {week.map((day, d) => day ? (
                            <div
                                key={d}
                                className={`rounded-sm flex items-center justify-center text-[9px] ${day.date === today ? 'ring-2 ring-gray-700' : ''}`}
                                style={shade(day.value)}
                                title={`${new Date(`${day.date}T12:00:00`).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' })}: ${day.value === null ? 'no readings' : describe(day.value)}`}
                            >
                                {!compact && (
                                    <span className={day.value && (tile.kind !== 'tank' || day.value > 55) ? 'text-white/90' : 'text-gray-500'}>
                                        {Number(day.date.slice(8))}
                                    </span>
                                )}
                            </div>
                        ) : <div key={d} />)}
                    </div>
                ))}
            </div>
        </div>
    );
}

// --- Formatting ------------------------------------------------------------

function formatDuration(seconds) {
    const minutes = Math.round((seconds ?? 0) / 60);
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (hours < 48) return rest ? `${hours} h ${rest} min` : `${hours} h`;
    return `${Math.round(hours / 24)} days`;
}

const sinceText = (iso) => formatDuration((Date.now() - new Date(iso).getTime()) / 1000);
