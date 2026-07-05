import { useState } from 'react';

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

function toISO(year, month, day) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${year}-${pad(month + 1)}-${pad(day)}`;
}

export default function DateRangeCalendar({ from, to, onChange }) {
    const [viewDate, setViewDate] = useState(() => new Date(`${from}T00:00:00`));
    const [anchor, setAnchor] = useState(null);

    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();
    const monthLabel = viewDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const cells = [...Array(firstWeekday).fill(null), ...Array(daysInMonth).keys()].map((d) =>
        d === null ? null : d + 1
    );

    const changeMonth = (delta) => setViewDate(new Date(year, month + delta, 1));

    const handleDayClick = (day) => {
        const iso = toISO(year, month, day);
        if (!anchor) {
            setAnchor(iso);
            onChange(iso, iso);
        } else {
            onChange(anchor < iso ? anchor : iso, anchor < iso ? iso : anchor);
            setAnchor(null);
        }
    };

    return (
        <div className="select-none">
            <div className="flex items-center justify-between mb-2">
                <button
                    type="button"
                    onClick={() => changeMonth(-1)}
                    className="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-full"
                    aria-label="Previous month"
                >
                    ‹
                </button>
                <span className="text-sm font-semibold text-gray-900">{monthLabel}</span>
                <button
                    type="button"
                    onClick={() => changeMonth(1)}
                    className="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-full"
                    aria-label="Next month"
                >
                    ›
                </button>
            </div>

            <div className="grid grid-cols-7 gap-y-1 text-center">
                {WEEKDAYS.map((wd) => (
                    <div key={wd} className="text-xs text-gray-400 font-medium py-1">
                        {wd}
                    </div>
                ))}

                {cells.map((day, i) => {
                    if (day === null) return <div key={`empty-${i}`} />;
                    const iso = toISO(year, month, day);
                    const inRange = iso >= from && iso <= to;
                    const isEdge = iso === from || iso === to;
                    return (
                        <button
                            type="button"
                            key={iso}
                            onClick={() => handleDayClick(day)}
                            className={`h-8 w-8 mx-auto rounded-full text-sm transition-colors ${
                                isEdge
                                    ? 'bg-green-600 text-white font-semibold'
                                    : inRange
                                    ? 'bg-green-100 text-green-800'
                                    : 'text-gray-700 hover:bg-gray-100'
                            }`}
                        >
                            {day}
                        </button>
                    );
                })}
            </div>

            <p className="text-xs text-gray-400 mt-2">
                {anchor ? 'Pick an end date' : 'Pick a start date'}
            </p>
        </div>
    );
}
