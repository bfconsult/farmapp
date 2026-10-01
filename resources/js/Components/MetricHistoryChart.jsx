import { formatDate } from '@/dateInput';
import { formatNumber } from '@/numberFormat';

/**
 * A small line chart of a number metric's recorded values over time, shown
 * above its History table. Only ever reads value_number/period_start off
 * whatever measurements it's given - the caller decides which ones (e.g.
 * History.jsx passes every measurement for this metric, newest first).
 * Points are spaced by actual elapsed time, not evenly by index, so a real
 * gap (a metric paused for a while) shows as a longer stretch rather than
 * looking identical to a normal period-to-period step.
 */
export default function MetricHistoryChart({ measurements }) {
    const points = measurements
        .filter((m) => m.value_number != null)
        .map((m) => ({ date: new Date(`${m.period_start.slice(0, 10)}T00:00:00`), value: Number(m.value_number) }))
        .sort((a, b) => a.date - b.date);

    if (points.length < 2) {
        return null;
    }

    const width = 300;
    const height = 80;
    const padding = 6;

    const minDate = points[0].date.getTime();
    const maxDate = points[points.length - 1].date.getTime();
    const dateRange = maxDate - minDate || 1;

    const values = points.map((p) => p.value);
    const minValue = Math.min(...values);
    const maxValue = Math.max(...values);
    // A perfectly flat series would otherwise divide by zero - draw it as a
    // straight line through the middle instead.
    const valueRange = maxValue - minValue || 1;

    const coords = points.map((p) => ({
        ...p,
        x: padding + ((p.date.getTime() - minDate) / dateRange) * (width - padding * 2),
        y: height - padding - ((p.value - minValue) / valueRange) * (height - padding * 2),
    }));

    const linePath = coords.map((c, i) => `${i === 0 ? 'M' : 'L'} ${c.x.toFixed(1)} ${c.y.toFixed(1)}`).join(' ');

    return (
        <div className="bg-white rounded-lg shadow p-4">
            <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className="w-full h-20">
                <path d={linePath} fill="none" stroke="#16a34a" strokeWidth="2" vectorEffect="non-scaling-stroke" />
                {coords.map((c, i) => (
                    <circle key={i} cx={c.x} cy={c.y} r="2.5" fill="#16a34a" />
                ))}
            </svg>
            <div className="flex justify-between text-xs text-gray-400 mt-1">
                <span>{formatDate(points[0].date, { year: false })}</span>
                <span>{formatNumber(minValue)} – {formatNumber(maxValue)}</span>
                <span>{formatDate(points[points.length - 1].date, { year: false })}</span>
            </div>
        </div>
    );
}
