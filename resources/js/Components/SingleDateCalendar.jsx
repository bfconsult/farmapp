import { useState } from 'react';

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

function toISO(year, month, day) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${year}-${pad(month + 1)}-${pad(day)}`;
}

/**
 * A single-date picker, built the same way as DateRangeCalendar (a plain
 * grid of day buttons) rather than a native <input type="date"> - a native
 * date field's change/input/blur events all fire from just browsing the
 * calendar (e.g. paging a month back), not only from clicking a day, so
 * there's no reliable native event for "the user actually picked a date".
 * Here, onChange only ever fires from an explicit day button click.
 */
export default function SingleDateCalendar({ value, onChange, max }) {
    const [viewDate, setViewDate] = useState(() => new Date(`${value || max}T00:00:00`));

    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();
    const monthLabel = viewDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const cells = [...Array(firstWeekday).fill(null), ...Array(daysInMonth).keys()].map((d) =>
        d === null ? null : d + 1
    );

    const changeMonth = (delta) => setViewDate(new Date(year, month + delta, 1));
    const nextMonthDisabled = max ? toISO(year, month + 1, 1) > max : false;

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
                    disabled={nextMonthDisabled}
                    className="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-full disabled:opacity-30 disabled:hover:bg-transparent"
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
                    const isSelected = iso === value;
                    const isDisabled = max ? iso > max : false;
                    return (
                        <button
                            type="button"
                            key={iso}
                            onClick={() => onChange(iso)}
                            disabled={isDisabled}
                            className={`h-8 w-8 mx-auto rounded-full text-sm transition-colors ${
                                isSelected
                                    ? 'bg-green-600 text-white font-semibold'
                                    : isDisabled
                                    ? 'text-gray-300'
                                    : 'text-gray-700 hover:bg-gray-100'
                            }`}
                        >
                            {day}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
